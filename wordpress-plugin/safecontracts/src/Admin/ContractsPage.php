<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use InvalidArgumentException;
use SafeContracts\Attachments\EntityAttachmentService;
use SafeContracts\Contracts\ContractArchiveService;
use SafeContracts\Contracts\ContractService;
use SafeContracts\Contracts\ContractStatus;
use SafeContracts\Counterparties\CounterpartyType;
use SafeContracts\Payments\PaymentRepository;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Settings\GeneralSettings;
use SafeContracts\Suppliers\SupplierService;
use SafeContracts\Suppliers\SupplierStatus;
use SafeContracts\Translations\TranslationCatalog;
use Throwable;

final class ContractsPage
{
    public const SLUG = 'safecontracts-contracts';
    public const SAVE_ACTION = 'safecontracts_save_contract_admin';
    public const DELETE_ACTION = 'safecontracts_delete_contract_admin';
    public const BULK_ASSIGN_ACTION = 'safecontracts_bulk_assign_accountant_admin';

    public static function register(): void
    {
        add_submenu_page(AdminShell::SLUG, __('Contracts', 'safecontracts'), __('Contracts', 'safecontracts'), Capabilities::ACCESS, self::SLUG, [self::class, 'render']);
    }

    public static function handleSave(): void
    {
        $contractId = max(0, (int) ($_POST['contract_id'] ?? 0));
        if ($contractId === 0) {
            if (! current_user_can(Capabilities::CREATE_CONTRACTS)) {
                wp_die(__('You do not have permission to create contracts.', 'safecontracts'));
            }
        } elseif (! current_user_can(Capabilities::EDIT_CONTRACTS) && ! current_user_can(Capabilities::ASSIGN_CONTRACTS)) {
            wp_die(__('You do not have permission to edit or assign contracts.', 'safecontracts'));
        }

        check_admin_referer(self::SAVE_ACTION);
        $service = new ContractService();
        $status = 'saved';
        $uploadedMediaIds = [];
        $linkingAttachments = false;
        try {
            $uploadedMediaIds = MultipleAttachmentUploader::upload();
            [$counterpartyType, $counterpartyId] = self::parseCounterpartyRef($_POST['counterparty_ref'] ?? '');
            $currency = strtoupper(sanitize_text_field((string) ($_POST['currency_code'] ?? '')));
            if ($contractId === 0) {
                $contractId = $service->create([
                    'contract_number' => sanitize_text_field((string) ($_POST['contract_number'] ?? '')),
                    'counterparty_type' => $counterpartyType,
                    'counterparty_id' => $counterpartyId,
                    'currency_code' => $currency,
                    'base_value' => sanitize_text_field((string) ($_POST['base_value'] ?? '0')),
                    'accountant_user_id' => ($_POST['accountant_user_id'] ?? '') === '' ? null : (int) $_POST['accountant_user_id'],
                    'notes' => sanitize_textarea_field((string) ($_POST['notes'] ?? '')),
                ]);
            } else {
                if (current_user_can(Capabilities::EDIT_CONTRACTS)) {
                    $service->edit($contractId, [
                        'contract_number' => sanitize_text_field((string) ($_POST['contract_number'] ?? '')),
                        'notes' => sanitize_textarea_field((string) ($_POST['notes'] ?? '')),
                    ]);
                    $service->updateBaseValue($contractId, sanitize_text_field((string) ($_POST['base_value'] ?? '0')));
                    $service->updateDates($contractId, $_POST['start_date'] ?? null, $_POST['end_date'] ?? null);
                    if ($currency !== '') {
                        $service->updateCurrency($contractId, $currency);
                    }
                    $targetStatus = sanitize_key((string) ($_POST['status'] ?? ''));
                    if ($targetStatus !== '') {
                        $service->changeStatus($contractId, $targetStatus);
                    }
                }
                if (current_user_can(Capabilities::ASSIGN_CONTRACTS)) {
                    $rows = (new AdminReadRepository())->contracts(['contract_id' => $contractId]);
                    $current = $rows[0] ?? null;
                    if ($current === null) {
                        throw new InvalidArgumentException('Contract was not found in the current data scope.');
                    }
                    if ((string) ($current['counterparty_type'] ?? '') !== $counterpartyType
                        || (int) ($current['counterparty_id'] ?? 0) !== $counterpartyId) {
                        $service->assignCounterparty($contractId, $counterpartyType, $counterpartyId);
                    }
                    $accountant = ($_POST['accountant_user_id'] ?? '') === '' ? null : (int) $_POST['accountant_user_id'];
                    $service->assignAccountant($contractId, $accountant);
                }
            }

            if ($uploadedMediaIds !== []) {
                $attachments = new EntityAttachmentService();
                $attachments->assertCanManage(EntityAttachmentService::CONTRACT, $contractId);
                $linkingAttachments = true;
                $attachments->attachMany(EntityAttachmentService::CONTRACT, $contractId, $uploadedMediaIds);
            }
        } catch (Throwable $error) {
            unset($error);
            if (! $linkingAttachments && $uploadedMediaIds !== []) {
                MultipleAttachmentUploader::cleanup($uploadedMediaIds);
            }
            $status = 'invalid';
        }
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'contract_id' => $contractId, 'safecontracts_status' => $status], admin_url('admin.php')));
        exit;
    }

    public static function handleBulkAssign(): void
    {
        if (! current_user_can(Capabilities::ASSIGN_CONTRACTS)) {
            wp_die(__('You do not have permission to assign contracts.', 'safecontracts'));
        }

        check_admin_referer(self::BULK_ASSIGN_ACTION);
        $accountantId = max(0, (int) ($_POST['accountant_user_id'] ?? 0));
        $rawContractIds = $_POST['contract_ids'] ?? [];
        $contractIds = is_array($rawContractIds)
            ? array_values(array_unique(array_filter(array_map('absint', $rawContractIds), static fn (int $id): bool => $id > 0)))
            : [];

        $status = 'bulk_assigned';
        $assigned = 0;
        $skipped = 0;
        if ($accountantId <= 0 || $contractIds === [] || ! user_can($accountantId, Capabilities::ACCESS) || ! user_can($accountantId, Capabilities::CREATE_CONTRACTS) || ! user_can($accountantId, Capabilities::VIEW_ASSIGNED)) {
            $status = 'bulk_invalid';
        } else {
            $read = new AdminReadRepository();
            $service = new ContractService();
            foreach ($contractIds as $contractId) {
                try {
                    $rows = $read->contracts(['contract_id' => $contractId]);
                    $contract = $rows[0] ?? null;
                    if ($contract === null || ! empty($contract['is_archived']) || ! empty($contract['accountant_user_id'])) {
                        $skipped++;
                        continue;
                    }
                    $service->assignAccountant($contractId, $accountantId);
                    $assigned++;
                } catch (Throwable $error) {
                    unset($error);
                    $skipped++;
                }
            }
        }

        wp_safe_redirect(add_query_arg([
            'page' => self::SLUG,
            'safecontracts_status' => $status,
            'safecontracts_assigned' => $assigned,
            'safecontracts_skipped' => $skipped,
        ], admin_url('admin.php')));
        exit;
    }

    public static function handleDelete(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to delete contracts.', 'safecontracts'));
        }
        $contractId = max(0, (int) ($_POST['contract_id'] ?? 0));
        check_admin_referer(self::DELETE_ACTION . '_' . $contractId);
        $status = 'deleted';
        try {
            (new ContractArchiveService())->archive($contractId);
        } catch (Throwable $error) {
            unset($error);
            $status = 'delete_failed';
        }
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'safecontracts_status' => $status], admin_url('admin.php')));
        exit;
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            wp_die(__('You do not have permission to access contracts.', 'safecontracts'));
        }
        $read = new AdminReadRepository();
        $filters = DashboardFilters::normalize($_GET);
        $contracts = $read->contracts($filters);
        $scheduledTotals = [];
        try {
            $scheduledTotals = (new PaymentRepository())->scheduledTotalsForContracts(array_values(array_map(
                static fn (array $contract): int => (int) ($contract['id'] ?? 0),
                $contracts
            )));
        } catch (Throwable $error) {
            unset($error);
        }
        $customers = $read->customerOptions();
        $suppliers = self::supplierOptions();
        $accountants = AdminLookupOptions::accountants();
        $accountantLabels = [];
        foreach ($accountants as $accountant) {
            $accountantLabels[$accountant['id']] = $accountant['label'];
        }
        $canAssignContracts = current_user_can(Capabilities::ASSIGN_CONTRACTS);
        $unassignedContracts = array_values(array_filter(
            $contracts,
            static fn (array $contract): bool => empty($contract['accountant_user_id']) && empty($contract['is_archived'])
        ));
        $unassignedCount = count($unassignedContracts);
        $status = isset($_GET['safecontracts_status']) && is_scalar($_GET['safecontracts_status']) ? sanitize_key((string) $_GET['safecontracts_status']) : '';
        $bulkAssigned = max(0, (int) ($_GET['safecontracts_assigned'] ?? 0));
        $bulkSkipped = max(0, (int) ($_GET['safecontracts_skipped'] ?? 0));
        $selected = null;
        $reconciliation = null;
        $selectedAttachments = [];
        $selectedId = max(0, (int) ($_GET['contract_id'] ?? 0));
        if ($selectedId > 0) {
            $rows = $read->contracts(['contract_id' => $selectedId]);
            $selected = $rows[0] ?? null;
            if ($selected !== null) {
                try {
                    $reconciliation = (new ContractService())->reconcile($selectedId);
                    $selectedAttachments = (new EntityAttachmentService())->all(EntityAttachmentService::CONTRACT, $selectedId);
                } catch (Throwable $error) {
                    unset($error);
                }
            }
        }
        $defaultCurrency = self::defaultCurrency();
        $selectedCurrency = (string) ($selected['currency_code'] ?? $defaultCurrency);
        $currencyChoices = AdminLookupOptions::currencyChoices($read, $selectedCurrency);
        $canManageAttachments = $selected !== null
            && empty($selected['is_archived'])
            && (current_user_can(Capabilities::EDIT_CONTRACTS) || current_user_can(Capabilities::CREATE_CONTRACTS));
        ?>
        <div class="wrap safecontracts-settings safecontracts-contracts" dir="auto">
            <div class="safecontracts-section-heading">
                <div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Contract operations', 'safecontracts'); ?></p><h1><?php echo esc_html__('Contracts', 'safecontracts'); ?></h1><p class="description"><?php echo esc_html__('Every contract has an explicit counterparty. Customer contracts are Accounts Receivable; Supplier contracts are Accounts Payable. Direction is derived server-side from the counterparty type.', 'safecontracts'); ?></p></div>
                <div class="safecontracts-heading-actions">
                    <?php if (self::canViewSuppliers()) : ?><a class="button" href="<?php echo esc_url(add_query_arg(['page' => SuppliersPage::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Supplier master', 'safecontracts'); ?></a><?php endif; ?>
                    <?php if (current_user_can(Capabilities::VIEW_PAYABLES) || current_user_can(Capabilities::VIEW_RECEIVABLES)) : ?><a class="button" href="<?php echo esc_url(add_query_arg(['page' => FinancePage::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Finance', 'safecontracts'); ?></a><?php endif; ?>
                </div>
            </div>
            <?php AdminPeriodFilter::render(self::SLUG, $filters, $selectedId > 0 ? ['contract_id' => $selectedId] : []); ?>
            <p class="description"><?php echo esc_html__('Contract period filtering uses the contract start date, falling back to the record creation date when no start date exists.', 'safecontracts'); ?></p>
            <?php if ($status === 'bulk_assigned') : ?>
                <div class="notice notice-success inline"><p><?php echo esc_html(sprintf(__('Responsible accountant assigned to %1$d contract(s). %2$d contract(s) were skipped because they were no longer eligible.', 'safecontracts'), $bulkAssigned, $bulkSkipped)); ?></p></div>
            <?php elseif ($status === 'bulk_invalid') : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html__('Bulk assignment was not applied. Select an eligible SafeContracts Accountant and at least one unassigned contract.', 'safecontracts'); ?></p></div>
            <?php elseif ($status === 'invalid' || $status === 'attachment_failed') : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html__('Contract or attachment was not saved. Check the values, file types, counterparty, currency, lifecycle transition and permissions.', 'safecontracts'); ?></p></div>
            <?php elseif ($status === 'attachments_added' || $status === 'attachment_removed') : ?>
                <div class="notice notice-success inline"><p><?php echo esc_html__('Contract attachments were updated.', 'safecontracts'); ?></p></div>
            <?php endif; ?>
            <?php if ($canAssignContracts && $unassignedCount > 0) : ?>
                <div class="notice notice-warning inline"><p><?php echo esc_html(sprintf(_n('%d active contract has no responsible accountant. Assigned-scope users will not see it on mobile until it is assigned.', '%d active contracts have no responsible accountant. Assigned-scope users will not see them on mobile until they are assigned.', $unassignedCount, 'safecontracts'), $unassignedCount)); ?></p></div>
                <?php if ($accountants !== []) : ?>
                    <section class="safecontracts-admin-card safecontracts-settings-card">
                        <h2><?php echo esc_html__('Assign existing unassigned contracts', 'safecontracts'); ?></h2>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="<?php echo esc_attr(self::BULK_ASSIGN_ACTION); ?>">
                            <?php foreach ($unassignedContracts as $contract) : ?><input type="hidden" name="contract_ids[]" value="<?php echo esc_attr((string) $contract['id']); ?>"><?php endforeach; ?>
                            <?php wp_nonce_field(self::BULK_ASSIGN_ACTION); ?>
                            <p><label><?php echo esc_html__('Responsible accountant', 'safecontracts'); ?><select class="widefat" name="accountant_user_id" required><option value=""><?php echo esc_html__('Select responsible accountant', 'safecontracts'); ?></option><?php foreach ($accountants as $accountant) : ?><option value="<?php echo esc_attr((string) $accountant['id']); ?>"><?php echo esc_html($accountant['label']); ?></option><?php endforeach; ?></select></label></p>
                            <p class="description"><?php echo esc_html__('This assigns only the currently visible active contracts that are still unassigned. Contracts assigned by another user before submission are skipped and never overwritten.', 'safecontracts'); ?></p>
                            <?php submit_button(sprintf(_n('Assign %d visible contract', 'Assign %d visible contracts', $unassignedCount, 'safecontracts'), $unassignedCount), 'secondary'); ?>
                        </form>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
            <div class="safecontracts-split-layout">
                <section class="safecontracts-admin-card safecontracts-table-card">
                    <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Contract', 'safecontracts'); ?></th><th><?php echo esc_html__('Counterparty', 'safecontracts'); ?></th><th><?php echo esc_html__('Direction', 'safecontracts'); ?></th><th><?php echo esc_html__('Currency', 'safecontracts'); ?></th><th><?php echo esc_html__('Responsible accountant', 'safecontracts'); ?></th><th><?php echo esc_html__('Status', 'safecontracts'); ?></th><th><?php echo esc_html__('Base value', 'safecontracts'); ?></th><th><?php echo esc_html__('Scheduled total', 'safecontracts'); ?></th><th><?php echo esc_html__('Actions', 'safecontracts'); ?></th></tr></thead><tbody>
                    <?php foreach ($contracts as $contract) : ?>
                        <?php
                        $contractId = (int) ($contract['id'] ?? 0);
                        $accountantId = (int) ($contract['accountant_user_id'] ?? 0);
                        $direction = (string) ($contract['financial_direction'] ?? 'receivable');
                        $directionClass = $direction === 'payable' ? 'payable' : 'receivable';
                        $scheduledTotal = $scheduledTotals[$contractId] ?? '0.0000';
                        $addPaymentUrl = add_query_arg(['page' => PaymentsPage::SLUG, 'contract_id' => $contractId], admin_url('admin.php'));
                        ?>
                        <tr>
                            <td><a href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'contract_id' => $contractId], admin_url('admin.php'))); ?>"><?php echo esc_html((string) $contract['contract_number']); ?></a><?php echo ! empty($contract['is_archived']) ? ' · ' . esc_html__('Archived', 'safecontracts') : ''; ?></td>
                            <td><strong><?php echo esc_html((string) ($contract['counterparty_name'] ?? '')); ?></strong><br><small><?php echo esc_html(self::counterpartyTypeLabel((string) ($contract['counterparty_type'] ?? ''))); ?></small></td>
                            <td><span class="safecontracts-direction-pill safecontracts-direction-pill--<?php echo esc_attr($directionClass); ?>"><?php echo esc_html(self::directionLabel($direction)); ?></span></td>
                            <td><?php echo esc_html((string) ($contract['currency_code'] ?: '—')); ?></td>
                            <td><?php echo $accountantId > 0 ? esc_html($accountantLabels[$accountantId] ?? __('Assigned user unavailable', 'safecontracts')) : '<strong>' . esc_html__('Unassigned', 'safecontracts') . '</strong>'; ?></td>
                            <td><?php echo esc_html(self::statusLabel((string) $contract['status'])); ?></td>
                            <td><?php echo esc_html(self::money($contract['base_value'], (string) ($contract['currency_code'] ?? ''))); ?></td>
                            <td><strong><?php echo esc_html(self::money($scheduledTotal, (string) ($contract['currency_code'] ?? ''))); ?></strong></td>
                            <td><div class="safecontracts-dashboard-table-actions"><a class="button button-small" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'contract_id' => $contractId], admin_url('admin.php'))); ?>"><?php echo esc_html__('Open', 'safecontracts'); ?></a><?php if (empty($contract['is_archived']) && current_user_can(Capabilities::MANAGE_PAYMENTS)) : ?><a class="button button-small safecontracts-payment-action safecontracts-payment-action--<?php echo esc_attr($directionClass); ?>" href="<?php echo esc_url($addPaymentUrl); ?>"><?php echo esc_html__('Add payment', 'safecontracts'); ?></a><?php endif; ?><?php if (empty($contract['is_archived']) && current_user_can(Capabilities::MANAGE_SYSTEM)) : ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-safecontracts-delete-form data-delete-message="<?php echo esc_attr__('Delete this contract from active operations? Payments, collections, history and audit evidence will be preserved.', 'safecontracts'); ?>"><input type="hidden" name="action" value="<?php echo esc_attr(self::DELETE_ACTION); ?>"><input type="hidden" name="contract_id" value="<?php echo esc_attr((string) $contractId); ?>"><?php wp_nonce_field(self::DELETE_ACTION . '_' . $contractId); ?><button type="submit" class="button button-small safecontracts-delete-button"><?php echo esc_html__('Delete', 'safecontracts'); ?></button></form><?php endif; ?></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </section>
                <?php if ((! $selected && current_user_can(Capabilities::CREATE_CONTRACTS)) || ($selected && (current_user_can(Capabilities::EDIT_CONTRACTS) || $canAssignContracts))) : ?>
                <section class="safecontracts-admin-card safecontracts-contract-editor">
                    <h2><?php echo $selected ? esc_html__('Contract details', 'safecontracts') : esc_html__('Create contract', 'safecontracts'); ?></h2>
                    <?php if ($selected && ! empty($selected['is_archived'])) : ?><p class="notice notice-warning inline"><?php echo esc_html__('Archived contracts are read-only.', 'safecontracts'); ?></p><?php endif; ?>
                    <?php if ($reconciliation) : ?><p><strong><?php echo esc_html__('Net value:', 'safecontracts'); ?></strong> <?php echo esc_html(self::money($reconciliation['net_value'], (string) ($selected['currency_code'] ?? ''))); ?></p><?php endif; ?>
                    <?php if (! $selected || empty($selected['is_archived'])) : ?>
                    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><input type="hidden" name="contract_id" value="<?php echo esc_attr((string) ($selected['id'] ?? 0)); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>
                        <p><label><?php echo esc_html__('Contract number', 'safecontracts'); ?><input class="widefat" name="contract_number" required value="<?php echo esc_attr((string) ($selected['contract_number'] ?? '')); ?>"></label></p>
                        <?php $selectedCounterparty = self::counterpartyRef((string) ($selected['counterparty_type'] ?? CounterpartyType::CUSTOMER), (int) ($selected['counterparty_id'] ?? 0)); ?>
                        <?php if (! $selected || $canAssignContracts) : ?>
                            <p><label><?php echo esc_html__('Counterparty', 'safecontracts'); ?><select class="widefat" name="counterparty_ref" required>
                                <option value=""><?php echo esc_html__('Select customer or supplier', 'safecontracts'); ?></option>
                                <optgroup label="<?php echo esc_attr__('Customers · Accounts Receivable', 'safecontracts'); ?>"><?php foreach ($customers as $customer) : $ref = self::counterpartyRef(CounterpartyType::CUSTOMER, (int) $customer['id']); ?><option value="<?php echo esc_attr($ref); ?>" <?php selected($selectedCounterparty, $ref); ?>><?php echo esc_html((string) $customer['name']); ?></option><?php endforeach; ?></optgroup>
                                <?php if ($suppliers !== []) : ?><optgroup label="<?php echo esc_attr__('Suppliers · Accounts Payable', 'safecontracts'); ?>"><?php foreach ($suppliers as $supplier) : $ref = self::counterpartyRef(CounterpartyType::SUPPLIER, (int) $supplier['id']); ?><option value="<?php echo esc_attr($ref); ?>" <?php selected($selectedCounterparty, $ref); ?>><?php echo esc_html((string) $supplier['label']); ?></option><?php endforeach; ?></optgroup><?php endif; ?>
                            </select></label></p>
                        <?php else : ?>
                            <input type="hidden" name="counterparty_ref" value="<?php echo esc_attr($selectedCounterparty); ?>">
                            <p><strong><?php echo esc_html__('Counterparty:', 'safecontracts'); ?></strong> <?php echo esc_html((string) ($selected['counterparty_name'] ?? '')); ?> · <?php echo esc_html(self::counterpartyTypeLabel((string) ($selected['counterparty_type'] ?? ''))); ?></p>
                        <?php endif; ?>
                        <p class="description"><?php echo esc_html__('Customer automatically means Accounts Receivable. Supplier automatically means Accounts Payable. This direction is determined by the backend and cannot be overridden by the form.', 'safecontracts'); ?></p>
                        <?php if (current_user_can(Capabilities::EDIT_CONTRACTS) || ! $selected) : ?>
                            <p><label><?php echo esc_html__('Contract currency', 'safecontracts'); ?><select class="widefat" name="currency_code" required><option value=""><?php echo esc_html__('Select currency', 'safecontracts'); ?></option><?php foreach ($currencyChoices as $currencyChoice) : ?><option value="<?php echo esc_attr($currencyChoice); ?>" <?php selected(strtoupper($selectedCurrency), $currencyChoice); ?>><?php echo esc_html($currencyChoice); ?></option><?php endforeach; ?></select></label></p>
                            <p><label><?php echo esc_html__('Base contract value', 'safecontracts'); ?><input class="widefat" type="number" min="0" step="0.01" inputmode="decimal" name="base_value" required value="<?php echo esc_attr((string) ($selected['base_value'] ?? '0.00')); ?>"></label></p>
                            <p class="description"><?php echo esc_html__('The base value is the original contractual amount before additions, discounts or other financial adjustments.', 'safecontracts'); ?></p>
                            <p class="description"><?php echo esc_html__('Currency belongs to this contract and its financial obligations. Different currencies remain separate in finance totals and reports.', 'safecontracts'); ?></p>
                        <?php else : ?><input type="hidden" name="currency_code" value="<?php echo esc_attr((string) ($selected['currency_code'] ?? '')); ?>"><input type="hidden" name="base_value" value="<?php echo esc_attr((string) ($selected['base_value'] ?? '0')); ?>"><?php endif; ?>
                        <?php if ($canAssignContracts) : ?>
                            <p><label><?php echo esc_html__('Responsible accountant', 'safecontracts'); ?><select class="widefat" name="accountant_user_id" required><option value=""><?php echo esc_html__('Select responsible accountant', 'safecontracts'); ?></option><?php foreach ($accountants as $accountant) : ?><option value="<?php echo esc_attr((string) $accountant['id']); ?>" <?php selected((int) ($selected['accountant_user_id'] ?? 0), $accountant['id']); ?>><?php echo esc_html($accountant['label']); ?></option><?php endforeach; ?></select></label></p>
                            <?php if ($accountants === []) : ?><p class="notice notice-warning inline"><?php echo esc_html__('No SafeContracts Accountant users are available. Assign the Accountant role to a user before saving this contract.', 'safecontracts'); ?></p><?php endif; ?>
                        <?php else : ?>
                            <?php $currentUser = wp_get_current_user(); ?>
                            <input type="hidden" name="accountant_user_id" value="<?php echo esc_attr((string) get_current_user_id()); ?>">
                            <p><strong><?php echo esc_html__('Responsible accountant:', 'safecontracts'); ?></strong> <?php echo esc_html((string) ($currentUser->display_name ?: $currentUser->user_login)); ?></p>
                        <?php endif; ?>
                        <p class="description"><?php echo esc_html__('Assigned-scope mobile data and assigned-accountant notification rules use this responsible accountant.', 'safecontracts'); ?></p>
                        <?php if ($selected) : ?>
                            <dl class="safecontracts-detail-list"><div><dt><?php echo esc_html__('Direction', 'safecontracts'); ?></dt><dd><?php echo esc_html(self::directionLabel((string) ($selected['financial_direction'] ?? ''))); ?></dd></div><div><dt><?php echo esc_html__('Currency', 'safecontracts'); ?></dt><dd><?php echo esc_html((string) ($selected['currency_code'] ?: '—')); ?></dd></div><div><dt><?php echo esc_html__('Counterparty type', 'safecontracts'); ?></dt><dd><?php echo esc_html(self::counterpartyTypeLabel((string) ($selected['counterparty_type'] ?? ''))); ?></dd></div></dl>
                            <p class="safecontracts-field-row"><label><?php echo esc_html__('Start date', 'safecontracts'); ?><input type="date" name="start_date" value="<?php echo esc_attr((string) ($selected['start_date'] ?? '')); ?>"></label><label><?php echo esc_html__('End date', 'safecontracts'); ?><input type="date" name="end_date" value="<?php echo esc_attr((string) ($selected['end_date'] ?? '')); ?>"></label></p>
                            <?php if (current_user_can(Capabilities::EDIT_CONTRACTS)) : ?><?php $statusOptions = array_values(array_unique(array_merge([(string) $selected['status']], ContractStatus::allowedTargets((string) $selected['status'])))); ?><p><label><?php echo esc_html__('Contract status', 'safecontracts'); ?><select class="widefat" name="status"><?php foreach ($statusOptions as $statusOption) : ?><option value="<?php echo esc_attr($statusOption); ?>" <?php selected((string) $selected['status'], $statusOption); ?>><?php echo esc_html(self::statusLabel($statusOption)); ?></option><?php endforeach; ?></select></label></p><?php if (count($statusOptions) === 1) : ?><p class="description"><?php echo esc_html__('This contract is in a terminal lifecycle state and cannot transition to another status.', 'safecontracts'); ?></p><?php endif; ?><?php endif; ?>
                        <?php endif; ?>
                        <p><label><?php echo esc_html__('Notes', 'safecontracts'); ?><textarea class="widefat" rows="4" name="notes"><?php echo esc_textarea((string) ($selected['notes'] ?? '')); ?></textarea></label></p>
                        <?php if (! $selected || current_user_can(Capabilities::EDIT_CONTRACTS) || current_user_can(Capabilities::CREATE_CONTRACTS)) : ?><?php EntityAttachmentView::renderUploadField(__('Contract files', 'safecontracts')); ?><?php endif; ?>
                        <?php submit_button($selected ? __('Save contract', 'safecontracts') : __('Create contract', 'safecontracts')); ?>
                    </form>
                    <?php endif; ?>
                    <?php if ($selected) : ?>
                        <hr>
                        <h3><?php echo esc_html__('Contract attachments', 'safecontracts'); ?></h3>
                        <?php EntityAttachmentView::render(EntityAttachmentService::CONTRACT, $selectedId, $selectedAttachments, $canManageAttachments); ?>
                        <?php if ($canManageAttachments) : ?><?php EntityAttachmentView::renderUploadForm(EntityAttachmentService::CONTRACT, $selectedId, __('Add contract files', 'safecontracts')); ?><?php endif; ?>
                    <?php endif; ?>
                </section>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /** @return list<array{id:int,label:string,default_currency:string}> */
    private static function supplierOptions(): array
    {
        if (! self::canViewSuppliers()) {
            return [];
        }
        try {
            $rows = (new SupplierService())->search('', 200, false);
        } catch (Throwable $error) {
            unset($error);
            return [];
        }
        $options = [];
        foreach ($rows as $supplier) {
            if (! empty($supplier['is_archived']) || (string) ($supplier['status'] ?? '') !== SupplierStatus::ACTIVE) {
                continue;
            }
            $id = (int) ($supplier['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $label = trim((string) ($supplier['legal_name'] ?? ''));
            $code = trim((string) ($supplier['internal_code'] ?? ''));
            if ($code !== '') {
                $label .= ' · ' . $code;
            }
            $options[] = ['id' => $id, 'label' => $label, 'default_currency' => (string) ($supplier['default_currency'] ?? '')];
        }
        return $options;
    }

    private static function canViewSuppliers(): bool
    {
        return current_user_can(Capabilities::VIEW_SUPPLIERS)
            || current_user_can(Capabilities::VIEW_ALL)
            || current_user_can(Capabilities::MANAGE_REFERENCE_DATA);
    }

    /** @return array{0:string,1:int} */
    private static function parseCounterpartyRef(mixed $value): array
    {
        $value = trim((string) $value);
        if (! preg_match('/^(customer|supplier):(\d+)$/', $value, $matches)) {
            throw new InvalidArgumentException('Contract counterparty selection is invalid.');
        }
        $type = CounterpartyType::normalize($matches[1]);
        $id = (int) $matches[2];
        if ($id <= 0) {
            throw new InvalidArgumentException('Contract counterparty ID must be positive.');
        }
        return [$type, $id];
    }

    private static function counterpartyRef(string $type, int $id): string
    {
        return $id > 0 ? CounterpartyType::normalize($type) . ':' . $id : '';
    }

    private static function defaultCurrency(): string
    {
        try {
            $settings = (new GeneralSettings())->read();
            return strtoupper(trim((string) ($settings['currency_code'] ?? '')));
        } catch (Throwable $error) {
            unset($error);
            return '';
        }
    }

    private static function statusLabel(string $status): string
    {
        return TranslationCatalog::text(ucwords(str_replace('_', ' ', $status)));
    }

    private static function counterpartyTypeLabel(string $type): string
    {
        return $type === CounterpartyType::SUPPLIER ? __('Supplier', 'safecontracts') : __('Customer', 'safecontracts');
    }

    private static function directionLabel(string $direction): string
    {
        return $direction === 'payable' ? __('Accounts Payable', 'safecontracts') : __('Accounts Receivable', 'safecontracts');
    }

    private static function money(mixed $value, string $currency): string
    {
        $amount = number_format((float) $value, 2, '.', ',');
        $currency = trim($currency);
        return $currency === '' ? $amount : $currency . ' ' . $amount;
    }
}
