<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use InvalidArgumentException;
use SafeContracts\Attachments\EntityAttachmentService;
use SafeContracts\Contracts\ContractArchiveService;
use SafeContracts\Contracts\ContractService;
use SafeContracts\Contracts\ContractStatus;
use SafeContracts\Counterparties\CounterpartyType;
use SafeContracts\Payments\FinancialDirection;
use SafeContracts\Payments\PaymentRepository;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Settings\GeneralSettings;
use SafeContracts\Suppliers\SupplierService;
use SafeContracts\Suppliers\SupplierStatus;
use SafeContracts\Support\MoneyFormatter;
use SafeContracts\Translations\TranslationCatalog;
use Throwable;

final class ContractsPage
{
    public const SLUG = 'safecontracts-contracts';
    public const SAVE_ACTION = 'safecontracts_save_contract_admin';
    public const DELETE_ACTION = 'safecontracts_delete_contract_admin';
    public const BULK_ASSIGN_ACTION = 'safecontracts_bulk_assign_accountant_admin';
    private const PAGE_SIZE = 20;

    public static function register(): void
    {
        Worker1Assets::register();
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
        $search = self::queryText('contract_search');
        if ($search !== '') {
            $contracts = array_values(array_filter($contracts, static function (array $contract) use ($search): bool {
                $haystack = implode(' ', [
                    (string) ($contract['contract_number'] ?? ''),
                    (string) ($contract['counterparty_name'] ?? ''),
                    (string) ($contract['currency_code'] ?? ''),
                    (string) ($contract['status'] ?? ''),
                ]);
                return stripos($haystack, $search) !== false;
            }));
        }

        $customers = $read->customerOptions();
        $suppliers = self::supplierOptions();
        $accountants = AdminLookupOptions::accountants();
        $accountantLabels = [];
        foreach ($accountants as $accountant) {
            $accountantLabels[$accountant['id']] = $accountant['label'];
        }

        $scheduledTotals = [];
        try {
            $scheduledTotals = (new PaymentRepository())->scheduledTotalsForContracts(array_values(array_map(
                static fn (array $contract): int => (int) ($contract['id'] ?? 0),
                $contracts
            )));
        } catch (Throwable $error) {
            unset($error);
        }

        $canAssignContracts = current_user_can(Capabilities::ASSIGN_CONTRACTS);
        $canEditContracts = current_user_can(Capabilities::EDIT_CONTRACTS);
        $canCreateContracts = current_user_can(Capabilities::CREATE_CONTRACTS);
        $unassignedContracts = array_values(array_filter(
            $contracts,
            static fn (array $contract): bool => empty($contract['accountant_user_id']) && empty($contract['is_archived'])
        ));
        $unassignedCount = count($unassignedContracts);
        $status = self::queryKey('safecontracts_status');
        $bulkAssigned = max(0, (int) ($_GET['safecontracts_assigned'] ?? 0));
        $bulkSkipped = max(0, (int) ($_GET['safecontracts_skipped'] ?? 0));

        $selected = null;
        $reconciliation = null;
        $selectedAttachments = [];
        $selectedPayments = [];
        $selectedPreview = null;
        $selectedId = max(0, (int) ($_GET['contract_id'] ?? 0));
        if ($selectedId > 0) {
            $rows = $read->contracts(['contract_id' => $selectedId]);
            $selected = $rows[0] ?? null;
            if ($selected !== null) {
                try {
                    $reconciliation = (new ContractService())->reconcile($selectedId);
                    $selectedAttachments = (new EntityAttachmentService())->all(EntityAttachmentService::CONTRACT, $selectedId);
                    $selectedPayments = $read->payments(['contract_id' => $selectedId]);
                    foreach ($selectedAttachments as $attachmentRow) {
                        $resolved = CollectorAttachmentView::resolve($attachmentRow['media_id'] ?? 0);
                        if ($resolved !== null && $resolved['preview_url'] !== '') {
                            $selectedPreview = $resolved;
                            break;
                        }
                    }
                } catch (Throwable $error) {
                    unset($error);
                }
            }
        }

        $tab = self::queryKey('contract_tab');
        if (! in_array($tab, ['overview', 'payments', 'attachments'], true)) {
            $tab = 'overview';
        }
        if ($selected === null) {
            $tab = 'overview';
        }

        $defaultCurrency = self::defaultCurrency();
        $selectedCurrency = (string) ($selected['currency_code'] ?? $defaultCurrency);
        $currencyChoices = AdminLookupOptions::currencyChoices($read, $selectedCurrency);
        $canManageAttachments = $selected !== null
            && empty($selected['is_archived'])
            && ($canEditContracts || $canCreateContracts);

        $receivableCount = count(array_filter($contracts, static fn (array $contract): bool => (string) ($contract['financial_direction'] ?? '') === FinancialDirection::RECEIVABLE));
        $payableCount = count(array_filter($contracts, static fn (array $contract): bool => (string) ($contract['financial_direction'] ?? '') === FinancialDirection::PAYABLE));
        $totalRows = count($contracts);
        $totalPages = max(1, (int) ceil($totalRows / self::PAGE_SIZE));
        $currentPage = min($totalPages, max(1, (int) ($_GET['contract_page'] ?? 1)));
        $pageRows = array_slice($contracts, ($currentPage - 1) * self::PAGE_SIZE, self::PAGE_SIZE);
        $showDetailPanel = $selected !== null || $canCreateContracts;
        ?>
        <div class="wrap safecontracts-settings safecontracts-contracts safecontracts-worker1" dir="auto">
            <header class="safecontracts-worker1__header">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html(self::text('Contract operations · AR / AP')); ?></p>
                    <h1><?php echo esc_html__('Contracts', 'safecontracts'); ?></h1>
                    <p class="description"><?php echo esc_html(self::text('Every contract has an explicit counterparty. Customer contracts are Accounts Receivable; Supplier contracts are Accounts Payable. Financial direction remains derived server-side from the counterparty type.')); ?></p>
                </div>
                <div class="safecontracts-worker1__header-actions">
                    <a class="button" href="<?php echo esc_url(add_query_arg(['page' => CustomersPage::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Customers', 'safecontracts'); ?></a>
                    <?php if (self::canViewSuppliers()) : ?><a class="button" href="<?php echo esc_url(add_query_arg(['page' => SuppliersPage::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Suppliers', 'safecontracts'); ?></a><?php endif; ?>
                    <?php if (current_user_can(Capabilities::VIEW_PAYABLES) || current_user_can(Capabilities::VIEW_RECEIVABLES)) : ?><a class="button" href="<?php echo esc_url(add_query_arg(['page' => FinancePage::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Finance', 'safecontracts'); ?></a><?php endif; ?>
                    <?php if ($canCreateContracts && $selected) : ?><a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Create contract', 'safecontracts'); ?></a><?php endif; ?>
                </div>
            </header>

            <div class="safecontracts-worker1__notice-stack">
                <?php if ($status === 'saved') : ?><div class="notice notice-success inline"><p><?php echo esc_html(self::text('Contract saved successfully.')); ?></p></div><?php endif; ?>
                <?php if ($status === 'deleted') : ?><div class="notice notice-success inline"><p><?php echo esc_html(self::text('Contract archived from active operations. Payments, collections, history and audit evidence were preserved.')); ?></p></div><?php endif; ?>
                <?php if ($status === 'delete_failed') : ?><div class="notice notice-error inline"><p><?php echo esc_html(self::text('Contract could not be archived.')); ?></p></div><?php endif; ?>
                <?php if ($status === 'bulk_assigned') : ?><div class="notice notice-success inline"><p><?php echo esc_html(sprintf(__('Responsible accountant assigned to %1$d contract(s). %2$d contract(s) were skipped because they were no longer eligible.', 'safecontracts'), $bulkAssigned, $bulkSkipped)); ?></p></div><?php endif; ?>
                <?php if ($status === 'bulk_invalid') : ?><div class="notice notice-error inline"><p><?php echo esc_html__('Bulk assignment was not applied. Select an eligible SafeContracts Accountant and at least one unassigned contract.', 'safecontracts'); ?></p></div><?php endif; ?>
                <?php if ($status === 'invalid' || $status === 'attachment_failed') : ?><div class="notice notice-error inline"><p><?php echo esc_html__('Contract or attachment was not saved. Check the values, file types, counterparty, currency, lifecycle transition and permissions.', 'safecontracts'); ?></p></div><?php endif; ?>
                <?php if ($status === 'attachments_added' || $status === 'attachment_removed') : ?><div class="notice notice-success inline"><p><?php echo esc_html__('Contract attachments were updated.', 'safecontracts'); ?></p></div><?php endif; ?>
                <?php if ($selected && ! empty($selected['is_archived'])) : ?><div class="notice notice-warning inline"><p><?php echo esc_html__('Archived contracts are read-only.', 'safecontracts'); ?></p></div><?php endif; ?>
                <?php if ($canAssignContracts && $unassignedCount > 0) : ?><div class="notice notice-warning inline"><p><?php echo esc_html(sprintf(_n('%d active contract in the current result has no responsible accountant. Assigned-scope users will not see it on mobile until it is assigned.', '%d active contracts in the current result have no responsible accountant. Assigned-scope users will not see them on mobile until they are assigned.', $unassignedCount, 'safecontracts'), $unassignedCount)); ?></p></div><?php endif; ?>
            </div>

            <section class="safecontracts-worker1__metrics" aria-label="<?php echo esc_attr__('Contract summary', 'safecontracts'); ?>">
                <article class="safecontracts-worker1__metric"><span><?php echo esc_html(self::text('Visible contracts')); ?></span><strong><?php echo esc_html((string) $totalRows); ?></strong><small><?php echo esc_html(self::text('Current filters and data scope')); ?></small></article>
                <article class="safecontracts-worker1__metric safecontracts-worker1__metric--receivable"><span><?php echo esc_html__('Accounts Receivable', 'safecontracts'); ?></span><strong><?php echo esc_html((string) $receivableCount); ?></strong><small><?php echo esc_html(self::text('Customer contracts · owed to us')); ?></small></article>
                <article class="safecontracts-worker1__metric safecontracts-worker1__metric--payable"><span><?php echo esc_html__('Accounts Payable', 'safecontracts'); ?></span><strong><?php echo esc_html((string) $payableCount); ?></strong><small><?php echo esc_html(self::text('Supplier contracts · owed by us')); ?></small></article>
                <article class="safecontracts-worker1__metric<?php echo $unassignedCount > 0 ? ' safecontracts-worker1__metric--warning' : ''; ?>"><span><?php echo esc_html__('Unassigned', 'safecontracts'); ?></span><strong><?php echo esc_html((string) $unassignedCount); ?></strong><small><?php echo esc_html(self::text('Needs responsible accountant')); ?></small></article>
            </section>

            <section class="safecontracts-worker1__toolbar">
                <?php self::renderFilters($filters, $selectedId); ?>
                <p class="description"><?php echo esc_html(self::text('The year filter uses the complete calendar year and contract start date, falling back to record creation date when no start date exists. Monetary totals are never combined across currencies.', 'فلتر السنة يستخدم السنة الميلادية كاملة وتاريخ بداية العقد، أو تاريخ إنشاء السجل إذا لم يوجد تاريخ بداية. ولا يتم جمع العملات المختلفة في إجمالي واحد.')); ?></p>
            </section>

            <?php if ($canAssignContracts && $unassignedCount > 0 && $accountants !== []) : ?>
                <section class="safecontracts-worker1__panel" style="margin-bottom:16px;">
                    <div class="safecontracts-worker1__panel-head"><div><h2><?php echo esc_html(self::text('Assign existing unassigned contracts')); ?></h2><p><?php echo esc_html(self::text('Assignment never overwrites contracts that became assigned before submission.')); ?></p></div><span class="safecontracts-worker1__count"><?php echo esc_html((string) $unassignedCount); ?></span></div>
                    <div class="safecontracts-worker1__panel-body">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="safecontracts-worker1__filter-grid">
                            <input type="hidden" name="action" value="<?php echo esc_attr(self::BULK_ASSIGN_ACTION); ?>">
                            <?php foreach ($unassignedContracts as $contract) : ?><input type="hidden" name="contract_ids[]" value="<?php echo esc_attr((string) $contract['id']); ?>"><?php endforeach; ?>
                            <?php wp_nonce_field(self::BULK_ASSIGN_ACTION); ?>
                            <label><?php echo esc_html__('Responsible accountant', 'safecontracts'); ?><select name="accountant_user_id" required><option value=""><?php echo esc_html__('Select responsible accountant', 'safecontracts'); ?></option><?php foreach ($accountants as $accountant) : ?><option value="<?php echo esc_attr((string) $accountant['id']); ?>"><?php echo esc_html($accountant['label']); ?></option><?php endforeach; ?></select></label>
                            <div class="safecontracts-worker1__filter-actions"><button class="button button-primary" type="submit"><?php echo esc_html(sprintf(_n('Assign %d contract', 'Assign %d contracts', $unassignedCount, 'safecontracts'), $unassignedCount)); ?></button></div>
                        </form>
                    </div>
                </section>
            <?php endif; ?>

            <div class="safecontracts-worker1__layout<?php echo $showDetailPanel ? '' : ' safecontracts-worker1__layout--single'; ?>">
                <section class="safecontracts-worker1__panel">
                    <div class="safecontracts-worker1__panel-head"><div><h2><?php echo esc_html(self::text('Contract register')); ?></h2><p><?php echo esc_html(self::text('Customer AR and supplier AP contracts stay explicitly separated')); ?></p></div><span class="safecontracts-worker1__count"><?php echo esc_html((string) $totalRows); ?></span></div>
                    <div class="safecontracts-worker1__panel-body--flush">
                        <?php if ($pageRows === []) : ?>
                            <div class="safecontracts-worker1__empty"><span class="safecontracts-worker1__empty-mark" aria-hidden="true">+</span><h3><?php echo esc_html(self::text('No contracts match the selected filters', 'لا توجد عقود مطابقة للفلاتر المحددة')); ?></h3><p><?php echo esc_html(self::text('Clear one or more filters, or create a contract if you have permission.')); ?></p></div>
                        <?php else : ?>
                            <div class="safecontracts-worker1__table-scroll">
                                <table class="widefat striped">
                                    <thead><tr><th><?php echo esc_html__('Contract', 'safecontracts'); ?></th><th><?php echo esc_html__('Counterparty', 'safecontracts'); ?></th><th><?php echo esc_html__('Direction', 'safecontracts'); ?></th><th><?php echo esc_html__('Currency', 'safecontracts'); ?></th><th><?php echo esc_html__('Responsible accountant', 'safecontracts'); ?></th><th><?php echo esc_html__('Status', 'safecontracts'); ?></th><th><?php echo esc_html__('Base value', 'safecontracts'); ?></th><th><?php echo esc_html__('Scheduled total', 'safecontracts'); ?></th><th><?php echo esc_html__('Actions', 'safecontracts'); ?></th></tr></thead>
                                    <tbody>
                                    <?php foreach ($pageRows as $contract) : ?>
                                        <?php
                                        $contractId = (int) ($contract['id'] ?? 0);
                                        $accountantId = (int) ($contract['accountant_user_id'] ?? 0);
                                        $direction = (string) ($contract['financial_direction'] ?? FinancialDirection::RECEIVABLE);
                                        $directionClass = $direction === FinancialDirection::PAYABLE ? 'payable' : 'receivable';
                                        $scheduledTotal = $scheduledTotals[$contractId] ?? '0.0000';
                                        $addPaymentUrl = add_query_arg(['page' => PaymentsPage::SLUG, 'contract_id' => $contractId], admin_url('admin.php'));
                                        ?>
                                        <tr>
                                            <td><div class="safecontracts-worker1__primary-cell"><a href="<?php echo esc_url(self::contractUrl($contractId, $filters, $search, $currentPage, 'overview')); ?>"><?php echo esc_html((string) $contract['contract_number']); ?></a><span class="safecontracts-worker1__secondary"><?php echo esc_html((string) ($contract['start_date'] ?: self::text('No start date'))); ?></span></div></td>
                                            <td><div class="safecontracts-worker1__primary-cell"><strong><?php echo esc_html((string) ($contract['counterparty_name'] ?? '')); ?></strong><span class="safecontracts-worker1__secondary"><?php echo esc_html(self::counterpartyTypeLabel((string) ($contract['counterparty_type'] ?? ''))); ?></span></div></td>
                                            <td><span class="safecontracts-worker1__status safecontracts-worker1__status--<?php echo esc_attr($directionClass); ?>"><?php echo esc_html(self::directionLabel($direction)); ?></span></td>
                                            <td><?php echo esc_html((string) ($contract['currency_code'] ?: '—')); ?></td>
                                            <td><?php echo $accountantId > 0 ? esc_html($accountantLabels[$accountantId] ?? __('Assigned user unavailable', 'safecontracts')) : '<span class="safecontracts-worker1__status safecontracts-worker1__status--warning">' . esc_html__('Unassigned', 'safecontracts') . '</span>'; ?></td>
                                            <td><span class="safecontracts-worker1__status"><?php echo esc_html(self::statusLabel((string) $contract['status'])); ?></span></td>
                                            <td><?php echo esc_html(self::money($contract['base_value'], (string) ($contract['currency_code'] ?? ''))); ?></td>
                                            <td><strong><?php echo esc_html(self::money($scheduledTotal, (string) ($contract['currency_code'] ?? ''))); ?></strong></td>
                                            <td><div class="safecontracts-dashboard-table-actions"><a class="button button-small" href="<?php echo esc_url(self::contractUrl($contractId, $filters, $search, $currentPage, 'overview')); ?>"><?php echo esc_html__('Open', 'safecontracts'); ?></a><?php if (empty($contract['is_archived']) && current_user_can(Capabilities::MANAGE_PAYMENTS)) : ?><a class="button button-small safecontracts-payment-action safecontracts-payment-action--<?php echo esc_attr($directionClass); ?>" href="<?php echo esc_url($addPaymentUrl); ?>"><?php echo esc_html__('Add payment', 'safecontracts'); ?></a><?php endif; ?><?php if (empty($contract['is_archived']) && current_user_can(Capabilities::MANAGE_SYSTEM)) : ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-safecontracts-delete-form data-delete-message="<?php echo esc_attr(self::text('Archive this contract from active operations? Payments, collections, history and audit evidence will be preserved.')); ?>"><input type="hidden" name="action" value="<?php echo esc_attr(self::DELETE_ACTION); ?>"><input type="hidden" name="contract_id" value="<?php echo esc_attr((string) $contractId); ?>"><?php wp_nonce_field(self::DELETE_ACTION . '_' . $contractId); ?><button type="submit" class="button button-small safecontracts-delete-button"><?php echo esc_html__('Delete', 'safecontracts'); ?></button></form><?php endif; ?></div></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php self::renderPagination($currentPage, $totalPages, $totalRows, $filters, $search, $selectedId, $tab); ?>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if ($showDetailPanel) : ?>
                    <aside class="safecontracts-worker1__panel safecontracts-worker1__editor">
                        <div class="safecontracts-worker1__panel-head">
                            <div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html($selected ? self::text('Contract workspace') : self::text('New contract')); ?></p><h2><?php echo $selected ? esc_html((string) $selected['contract_number']) : esc_html__('Create contract', 'safecontracts'); ?></h2></div>
                            <?php if ($selected) : ?><span class="safecontracts-worker1__status safecontracts-worker1__status--<?php echo esc_attr((string) ($selected['financial_direction'] ?? '') === FinancialDirection::PAYABLE ? 'payable' : 'receivable'); ?>"><?php echo esc_html(self::directionLabel((string) ($selected['financial_direction'] ?? ''))); ?></span><?php endif; ?>
                        </div>

                        <?php if ($selected) : ?>
                            <nav class="safecontracts-worker1__tabs" aria-label="<?php echo esc_attr(self::text('Contract detail tabs')); ?>">
                                <a class="safecontracts-worker1__tab<?php echo $tab === 'overview' ? ' is-active' : ''; ?>" href="<?php echo esc_url(self::contractUrl($selectedId, $filters, $search, $currentPage, 'overview')); ?>"><?php echo esc_html(self::text('Overview')); ?></a>
                                <a class="safecontracts-worker1__tab<?php echo $tab === 'payments' ? ' is-active' : ''; ?>" href="<?php echo esc_url(self::contractUrl($selectedId, $filters, $search, $currentPage, 'payments')); ?>"><?php echo esc_html(sprintf(self::text('Payments (%d)'), count($selectedPayments))); ?></a>
                                <a class="safecontracts-worker1__tab<?php echo $tab === 'attachments' ? ' is-active' : ''; ?>" href="<?php echo esc_url(self::contractUrl($selectedId, $filters, $search, $currentPage, 'attachments')); ?>"><?php echo esc_html(sprintf(self::text('Attachments (%d)'), count($selectedAttachments))); ?></a>
                            </nav>
                        <?php endif; ?>

                        <div class="safecontracts-worker1__panel-body">
                            <?php if ($selected && $tab === 'overview') : ?>
                                <?php if ($selectedPreview !== null) : ?><div class="safecontracts-worker1__context"><a href="<?php echo esc_url($selectedPreview['url']); ?>" target="_blank" rel="noopener noreferrer"><img src="<?php echo esc_url($selectedPreview['preview_url']); ?>" alt="" style="display:block;width:100%;max-height:180px;object-fit:cover;border-radius:10px;"></a><span class="safecontracts-worker1__secondary"><?php echo esc_html(self::text('Contract media preview from the existing WordPress attachment set')); ?></span></div><?php endif; ?>
                                <div class="safecontracts-worker1__summary-list">
                                    <div class="safecontracts-worker1__summary-item"><span><?php echo esc_html__('Counterparty', 'safecontracts'); ?></span><strong><?php echo esc_html((string) ($selected['counterparty_name'] ?? '—')); ?></strong></div>
                                    <div class="safecontracts-worker1__summary-item"><span><?php echo esc_html__('Currency', 'safecontracts'); ?></span><strong><?php echo esc_html((string) ($selected['currency_code'] ?: '—')); ?></strong></div>
                                    <div class="safecontracts-worker1__summary-item"><span><?php echo esc_html__('Base value', 'safecontracts'); ?></span><strong><?php echo esc_html(self::money($selected['base_value'] ?? '0', (string) ($selected['currency_code'] ?? ''))); ?></strong></div>
                                    <div class="safecontracts-worker1__summary-item"><span><?php echo esc_html__('Scheduled total', 'safecontracts'); ?></span><strong><?php echo esc_html(self::money($scheduledTotals[$selectedId] ?? '0', (string) ($selected['currency_code'] ?? ''))); ?></strong></div>
                                    <?php if ($reconciliation) : ?><div class="safecontracts-worker1__summary-item"><span><?php echo esc_html(self::text('Financial items')); ?></span><strong><?php echo esc_html(self::money($reconciliation['financial_items'], (string) ($selected['currency_code'] ?? ''))); ?></strong></div><div class="safecontracts-worker1__summary-item"><span><?php echo esc_html(self::text('Additions')); ?></span><strong><?php echo esc_html(self::money($reconciliation['additions'], (string) ($selected['currency_code'] ?? ''))); ?></strong></div><div class="safecontracts-worker1__summary-item"><span><?php echo esc_html(self::text('Discounts')); ?></span><strong><?php echo esc_html(self::money($reconciliation['discounts'], (string) ($selected['currency_code'] ?? ''))); ?></strong></div><div class="safecontracts-worker1__summary-item"><span><?php echo esc_html__('Net value', 'safecontracts'); ?></span><strong><?php echo esc_html(self::money($reconciliation['net_value'], (string) ($selected['currency_code'] ?? ''))); ?></strong></div><?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($selected && $tab === 'payments') : ?>
                                <?php if ($selectedPayments === []) : ?><div class="safecontracts-worker1__empty"><span class="safecontracts-worker1__empty-mark" aria-hidden="true">$</span><h3><?php echo esc_html(self::text('No scheduled payments')); ?></h3><p><?php echo esc_html(self::text('This contract has no visible scheduled-payment records in the current authorized scope.')); ?></p><?php if (current_user_can(Capabilities::MANAGE_PAYMENTS)) : ?><a class="button button-primary" href="<?php echo esc_url(add_query_arg(['page' => PaymentsPage::SLUG, 'contract_id' => $selectedId], admin_url('admin.php'))); ?>"><?php echo esc_html__('Add payment', 'safecontracts'); ?></a><?php endif; ?></div><?php else : ?><div class="safecontracts-worker1__table-scroll"><table class="widefat striped"><thead><tr><th><?php echo esc_html__('Due date', 'safecontracts'); ?></th><th><?php echo esc_html__('Status', 'safecontracts'); ?></th><th><?php echo esc_html__('Original', 'safecontracts'); ?></th><th><?php echo esc_html__('Paid', 'safecontracts'); ?></th><th><?php echo esc_html__('Remaining', 'safecontracts'); ?></th></tr></thead><tbody><?php foreach ($selectedPayments as $payment) : ?><tr><td><?php echo esc_html((string) ($payment['due_date'] ?? '—')); ?></td><td><span class="safecontracts-worker1__status"><?php echo esc_html(self::statusLabel((string) ($payment['status'] ?? ''))); ?></span></td><td><?php echo esc_html(self::money($payment['original_amount'] ?? '0', (string) ($payment['currency_code'] ?? $selected['currency_code'] ?? ''))); ?></td><td><?php echo esc_html(self::money($payment['paid_amount'] ?? '0', (string) ($payment['currency_code'] ?? $selected['currency_code'] ?? ''))); ?></td><td><strong><?php echo esc_html(self::money($payment['remaining_amount'] ?? '0', (string) ($payment['currency_code'] ?? $selected['currency_code'] ?? ''))); ?></strong></td></tr><?php endforeach; ?></tbody></table></div><?php if (current_user_can(Capabilities::MANAGE_PAYMENTS)) : ?><p><a class="button button-primary" href="<?php echo esc_url(add_query_arg(['page' => PaymentsPage::SLUG, 'contract_id' => $selectedId], admin_url('admin.php'))); ?>"><?php echo esc_html(self::text('Manage payments')); ?></a></p><?php endif; ?><?php endif; ?>
                            <?php endif; ?>

                            <?php if ($selected && $tab === 'attachments') : ?>
                                <div class="safecontracts-worker1__form-section"><h3><?php echo esc_html__('Contract attachments', 'safecontracts'); ?></h3><?php EntityAttachmentView::render(EntityAttachmentService::CONTRACT, $selectedId, $selectedAttachments, $canManageAttachments); ?></div>
                                <?php if ($canManageAttachments) : ?><div class="safecontracts-worker1__form-section"><h3><?php echo esc_html__('Add files', 'safecontracts'); ?></h3><?php EntityAttachmentView::renderUploadForm(EntityAttachmentService::CONTRACT, $selectedId, __('Add contract files', 'safecontracts')); ?></div><?php endif; ?>
                            <?php endif; ?>

                            <?php if ($tab === 'overview' && (! $selected || ($canEditContracts || $canAssignContracts))) : ?>
                                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><input type="hidden" name="contract_id" value="<?php echo esc_attr((string) ($selected['id'] ?? 0)); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>
                                    <?php $selectedCounterparty = self::counterpartyRef((string) ($selected['counterparty_type'] ?? CounterpartyType::CUSTOMER), (int) ($selected['counterparty_id'] ?? 0)); ?>
                                    <div class="safecontracts-worker1__form-section"><h3><?php echo esc_html(self::text('Contract identity')); ?></h3><div class="safecontracts-worker1__field-grid">
                                        <?php if (! $selected || $canEditContracts) : ?><label class="safecontracts-worker1__field-full"><?php echo esc_html__('Contract number', 'safecontracts'); ?><input name="contract_number" required maxlength="100" value="<?php echo esc_attr((string) ($selected['contract_number'] ?? '')); ?>"></label><?php else : ?><input type="hidden" name="contract_number" value="<?php echo esc_attr((string) $selected['contract_number']); ?>"><?php endif; ?>
                                        <?php if (! $selected || $canAssignContracts) : ?><label class="safecontracts-worker1__field-full"><?php echo esc_html__('Counterparty', 'safecontracts'); ?><select name="counterparty_ref" required><option value=""><?php echo esc_html__('Select customer or supplier', 'safecontracts'); ?></option><optgroup label="<?php echo esc_attr__('Customers · Accounts Receivable', 'safecontracts'); ?>"><?php foreach ($customers as $customer) : $ref = self::counterpartyRef(CounterpartyType::CUSTOMER, (int) $customer['id']); ?><option value="<?php echo esc_attr($ref); ?>" <?php selected($selectedCounterparty, $ref); ?>><?php echo esc_html((string) $customer['name']); ?></option><?php endforeach; ?></optgroup><?php if ($suppliers !== []) : ?><optgroup label="<?php echo esc_attr__('Suppliers · Accounts Payable', 'safecontracts'); ?>"><?php foreach ($suppliers as $supplier) : $ref = self::counterpartyRef(CounterpartyType::SUPPLIER, (int) $supplier['id']); ?><option value="<?php echo esc_attr($ref); ?>" <?php selected($selectedCounterparty, $ref); ?>><?php echo esc_html((string) $supplier['label']); ?></option><?php endforeach; ?></optgroup><?php endif; ?></select></label><?php else : ?><input type="hidden" name="counterparty_ref" value="<?php echo esc_attr($selectedCounterparty); ?>"><?php endif; ?>
                                    </div><p class="description"><?php echo esc_html(self::text('Customer means Accounts Receivable; Supplier means Accounts Payable. Direction is derived by the backend and cannot be overridden here.')); ?></p></div>

                                    <div class="safecontracts-worker1__form-section"><h3><?php echo esc_html(self::text('Financial & responsibility')); ?></h3><div class="safecontracts-worker1__field-grid">
                                        <?php if (! $selected || $canEditContracts) : ?><label><?php echo esc_html__('Contract currency', 'safecontracts'); ?><select name="currency_code" required><option value=""><?php echo esc_html__('Select currency', 'safecontracts'); ?></option><?php foreach ($currencyChoices as $currencyChoice) : ?><option value="<?php echo esc_attr($currencyChoice); ?>" <?php selected(strtoupper($selectedCurrency), $currencyChoice); ?>><?php echo esc_html($currencyChoice); ?></option><?php endforeach; ?></select></label><label><?php echo esc_html__('Base contract value', 'safecontracts'); ?><input type="number" min="0" step="0.01" inputmode="decimal" name="base_value" required value="<?php echo esc_attr(self::moneyInput($selected['base_value'] ?? '0')); ?>"></label><?php else : ?><input type="hidden" name="currency_code" value="<?php echo esc_attr((string) ($selected['currency_code'] ?? '')); ?>"><input type="hidden" name="base_value" value="<?php echo esc_attr((string) ($selected['base_value'] ?? '0')); ?>"><?php endif; ?>
                                        <?php if ($canAssignContracts) : ?><label class="safecontracts-worker1__field-full"><?php echo esc_html__('Responsible accountant', 'safecontracts'); ?><select name="accountant_user_id" required><option value=""><?php echo esc_html__('Select responsible accountant', 'safecontracts'); ?></option><?php foreach ($accountants as $accountant) : ?><option value="<?php echo esc_attr((string) $accountant['id']); ?>" <?php selected((int) ($selected['accountant_user_id'] ?? 0), $accountant['id']); ?>><?php echo esc_html($accountant['label']); ?></option><?php endforeach; ?></select></label><?php else : ?><?php $currentUser = wp_get_current_user(); ?><input type="hidden" name="accountant_user_id" value="<?php echo esc_attr((string) get_current_user_id()); ?>"><div class="safecontracts-worker1__field-full safecontracts-worker1__context"><div class="safecontracts-worker1__context-row"><span><?php echo esc_html__('Responsible accountant', 'safecontracts'); ?></span><strong><?php echo esc_html((string) ($currentUser->display_name ?: $currentUser->user_login)); ?></strong></div></div><?php endif; ?>
                                    </div><?php if ($canAssignContracts && $accountants === []) : ?><div class="notice notice-warning inline"><p><?php echo esc_html__('No SafeContracts Accountant users are available. Assign the Accountant role to a user before saving this contract.', 'safecontracts'); ?></p></div><?php endif; ?></div>

                                    <?php if ($selected && $canEditContracts) : ?><div class="safecontracts-worker1__form-section"><h3><?php echo esc_html(self::text('Lifecycle')); ?></h3><div class="safecontracts-worker1__field-grid"><label><?php echo esc_html__('Start date', 'safecontracts'); ?><input type="date" name="start_date" value="<?php echo esc_attr((string) ($selected['start_date'] ?? '')); ?>"></label><label><?php echo esc_html__('End date', 'safecontracts'); ?><input type="date" name="end_date" value="<?php echo esc_attr((string) ($selected['end_date'] ?? '')); ?>"></label><?php $statusOptions = array_values(array_unique(array_merge([(string) $selected['status']], ContractStatus::allowedTargets((string) $selected['status'])))); ?><label class="safecontracts-worker1__field-full"><?php echo esc_html__('Contract status', 'safecontracts'); ?><select name="status"><?php foreach ($statusOptions as $statusOption) : ?><option value="<?php echo esc_attr($statusOption); ?>" <?php selected((string) $selected['status'], $statusOption); ?>><?php echo esc_html(self::statusLabel($statusOption)); ?></option><?php endforeach; ?></select></label></div><?php if (count($statusOptions) === 1) : ?><p class="description"><?php echo esc_html__('This contract is in a terminal lifecycle state and cannot transition to another status.', 'safecontracts'); ?></p><?php endif; ?></div><?php elseif ($selected) : ?><input type="hidden" name="start_date" value="<?php echo esc_attr((string) ($selected['start_date'] ?? '')); ?>"><input type="hidden" name="end_date" value="<?php echo esc_attr((string) ($selected['end_date'] ?? '')); ?>"><?php endif; ?>

                                    <?php if (! $selected || $canEditContracts) : ?><div class="safecontracts-worker1__form-section"><h3><?php echo esc_html(self::text('Notes & files')); ?></h3><label class="safecontracts-worker1__field-full"><?php echo esc_html__('Notes', 'safecontracts'); ?><textarea rows="4" name="notes"><?php echo esc_textarea((string) ($selected['notes'] ?? '')); ?></textarea></label><?php EntityAttachmentView::renderUploadField(__('Contract files', 'safecontracts')); ?></div><?php else : ?><input type="hidden" name="notes" value="<?php echo esc_attr((string) ($selected['notes'] ?? '')); ?>"><?php endif; ?>

                                    <?php submit_button($selected ? __('Save contract', 'safecontracts') : __('Create contract', 'safecontracts'), 'primary', 'submit', false); ?>
                                    <?php if ($selected) : ?> <a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Close', 'safecontracts'); ?></a><?php endif; ?>
                                </form>
                            <?php elseif ($selected && $tab === 'overview') : ?>
                                <div class="safecontracts-worker1__context"><div class="safecontracts-worker1__context-row"><span><?php echo esc_html__('Status', 'safecontracts'); ?></span><strong><?php echo esc_html(self::statusLabel((string) ($selected['status'] ?? ''))); ?></strong></div><div class="safecontracts-worker1__context-row"><span><?php echo esc_html__('Responsible accountant', 'safecontracts'); ?></span><strong><?php echo esc_html((string) (($accountantLabels[(int) ($selected['accountant_user_id'] ?? 0)] ?? __('Unassigned', 'safecontracts')))); ?></strong></div><div class="safecontracts-worker1__context-row"><span><?php echo esc_html(self::text('Dates')); ?></span><strong><?php echo esc_html((string) (($selected['start_date'] ?: '—') . ' → ' . ($selected['end_date'] ?: '—'))); ?></strong></div></div><p><?php echo esc_html((string) ($selected['notes'] ?: self::text('No contract notes.'))); ?></p>
                            <?php endif; ?>
                        </div>
                    </aside>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /** @param array<string,mixed> $filters */
    private static function renderFilters(array $filters, int $selectedId): void
    {
        $search = self::queryText('contract_search');
        $tab = self::queryKey('contract_tab');
        if (! in_array($tab, ['overview', 'payments', 'attachments'], true)) {
            $tab = 'overview';
        }

        $read = new AdminReadRepository();
        $filterCurrency = self::defaultCurrency();
        if ($selectedId > 0) {
            $selectedRows = $read->contracts(['contract_id' => $selectedId]);
            if (($selectedRows[0]['currency_code'] ?? '') !== '') {
                $filterCurrency = (string) $selectedRows[0]['currency_code'];
            }
        }
        $currencyChoices = AdminLookupOptions::currencyChoices($read, $filterCurrency);
        $years = [];
        try { $years = AdminYearOptions::forCurrentUser(); } catch (Throwable $error) { unset($error); }
        $selectedYear = (int) ($filters['year'] ?? 0);
        if ($selectedYear > 0 && ! in_array($selectedYear, $years, true)) { $years[] = $selectedYear; rsort($years, SORT_NUMERIC); }
        $direction = (string) ($filters['financial_direction'] ?? '');
        $status = (string) ($filters['status'] ?? '');
        $currency = (string) ($filters['currency_code'] ?? '');
        ?>
        <form class="safecontracts-worker1__filter-grid" method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
            <?php if ($selectedId > 0) : ?><input type="hidden" name="contract_id" value="<?php echo esc_attr((string) $selectedId); ?>"><input type="hidden" name="contract_tab" value="<?php echo esc_attr($tab); ?>"><?php endif; ?>
            <label><?php echo esc_html(self::text('Search')); ?><input type="search" name="contract_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr(self::text('Contract number or counterparty')); ?>"></label>
            <label><?php echo esc_html(self::text('Contract Type', 'نوع العقد')); ?><select name="financial_direction"><option value=""><?php echo esc_html(self::text('All contracts', 'كل العقود')); ?></option><option value="<?php echo esc_attr(FinancialDirection::RECEIVABLE); ?>" <?php selected($direction, FinancialDirection::RECEIVABLE); ?>><?php echo esc_html(self::text('Receivable · owed to us', 'مستحقة لنا')); ?></option><option value="<?php echo esc_attr(FinancialDirection::PAYABLE); ?>" <?php selected($direction, FinancialDirection::PAYABLE); ?>><?php echo esc_html(self::text('Payable · owed by us', 'مستحقة علينا')); ?></option></select></label>
            <label><?php echo esc_html__('Status', 'safecontracts'); ?><select name="status"><option value=""><?php echo esc_html__('All statuses', 'safecontracts'); ?></option><?php foreach ([ContractStatus::DRAFT, ContractStatus::ACTIVE, ContractStatus::COMPLETED, ContractStatus::CANCELLED] as $statusOption) : ?><option value="<?php echo esc_attr($statusOption); ?>" <?php selected($status, $statusOption); ?>><?php echo esc_html(self::statusLabel($statusOption)); ?></option><?php endforeach; ?></select></label>
            <label><?php echo esc_html(self::text('Year', 'السنة')); ?><select name="year"><option value="0"><?php echo esc_html(self::text('All years', 'كل السنوات')); ?></option><?php foreach ($years as $year) : ?><option value="<?php echo esc_attr((string) $year); ?>" <?php selected($selectedYear, $year); ?>><?php echo esc_html((string) $year); ?></option><?php endforeach; ?></select></label>
            <label><?php echo esc_html__('Currency', 'safecontracts'); ?><select name="currency_code"><option value=""><?php echo esc_html__('All currencies', 'safecontracts'); ?></option><?php foreach ($currencyChoices as $currencyChoice) : ?><option value="<?php echo esc_attr($currencyChoice); ?>" <?php selected($currency, $currencyChoice); ?>><?php echo esc_html($currencyChoice); ?></option><?php endforeach; ?></select></label>
            <div class="safecontracts-worker1__filter-actions"><button class="button button-primary" type="submit"><?php echo esc_html(self::text('Apply filters', 'تطبيق الفلاتر')); ?></button><a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html(self::text('Clear filters', 'مسح الفلاتر')); ?></a></div>
        </form>
        <?php
    }

    /** @param array<string,mixed> $filters */
    private static function contractUrl(int $contractId, array $filters, string $search = '', int $page = 1, string $tab = 'overview'): string
    {
        $args = ['page' => self::SLUG, 'contract_id' => $contractId, 'contract_tab' => $tab];
        if (($filters['financial_direction'] ?? '') !== '') { $args['financial_direction'] = (string) $filters['financial_direction']; }
        if (($filters['year'] ?? 0) > 0) { $args['year'] = (int) $filters['year']; }
        if (($filters['status'] ?? '') !== '') { $args['status'] = (string) $filters['status']; }
        if (($filters['currency_code'] ?? '') !== '') { $args['currency_code'] = (string) $filters['currency_code']; }
        if ($search !== '') { $args['contract_search'] = $search; }
        if ($page > 1) { $args['contract_page'] = $page; }
        return add_query_arg($args, admin_url('admin.php'));
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
        return $direction === FinancialDirection::PAYABLE ? __('Accounts Payable', 'safecontracts') : __('Accounts Receivable', 'safecontracts');
    }

    private static function money(mixed $value, string $currency): string
    {
        return MoneyFormatter::format($value, $currency);
    }

    private static function moneyInput(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '' || ! preg_match('/^\d+(?:\.\d+)?$/', $raw)) { return '0'; }
        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        $fraction = rtrim($fraction, '0');
        return $fraction === '' ? $whole : $whole . '.' . $fraction;
    }

    private static function text(string $english, ?string $arabic = null): string
    {
        if (TranslationCatalog::currentLanguage() !== 'ar') {
            return __($english, 'safecontracts');
        }
        if ($arabic !== null) {
            return $arabic;
        }

        return match ($english) {
            'Contract operations · AR / AP' => 'عمليات العقود · قبض / دفع',
            'Every contract has an explicit counterparty. Customer contracts are Accounts Receivable; Supplier contracts are Accounts Payable. Financial direction remains derived server-side from the counterparty type.' => 'لكل عقد طرف مقابل محدد. عقود العملاء تمثل حسابات القبض، وعقود الموردين تمثل حسابات الدفع، ويظل الاتجاه المالي مشتقاً من نوع الطرف المقابل في الخادم.',
            'Contract saved successfully.' => 'تم حفظ العقد بنجاح.',
            'Contract archived from active operations. Payments, collections, history and audit evidence were preserved.' => 'تمت أرشفة العقد من العمليات النشطة مع الاحتفاظ بالدفعات والتحصيلات والسجل وأدلة التدقيق.',
            'Contract could not be archived.' => 'تعذر أرشفة العقد.',
            'Visible contracts' => 'العقود الظاهرة',
            'Current filters and data scope' => 'الفلاتر الحالية ونطاق البيانات',
            'Customer contracts · owed to us' => 'عقود العملاء · مستحقة لنا',
            'Supplier contracts · owed by us' => 'عقود الموردين · مستحقة علينا',
            'Needs responsible accountant' => 'تحتاج إلى محاسب مسؤول',
            'Assign existing unassigned contracts' => 'تعيين العقود الحالية غير المسندة',
            'Assignment never overwrites contracts that became assigned before submission.' => 'لا يستبدل التعيين العقود التي تم إسنادها قبل إرسال الطلب.',
            'Contract register' => 'سجل العقود',
            'Customer AR and supplier AP contracts stay explicitly separated' => 'تظل عقود العملاء المستحقة لنا منفصلة بوضوح عن عقود الموردين المستحقة علينا',
            'Clear one or more filters, or create a contract if you have permission.' => 'امسح فلترًا واحدًا أو أكثر، أو أنشئ عقدًا إذا كانت لديك الصلاحية.',
            'No start date' => 'لا يوجد تاريخ بداية',
            'Archive this contract from active operations? Payments, collections, history and audit evidence will be preserved.' => 'هل تريد أرشفة هذا العقد من العمليات النشطة؟ سيتم الاحتفاظ بالدفعات والتحصيلات والسجل وأدلة التدقيق.',
            'Contract workspace' => 'مساحة عمل العقد',
            'New contract' => 'عقد جديد',
            'Contract detail tabs' => 'تبويبات تفاصيل العقد',
            'Overview' => 'نظرة عامة',
            'Payments (%d)' => 'الدفعات (%d)',
            'Attachments (%d)' => 'المرفقات (%d)',
            'Contract media preview from the existing WordPress attachment set' => 'معاينة وسائط العقد من مرفقات WordPress الحالية',
            'Financial items' => 'البنود المالية',
            'Additions' => 'الإضافات',
            'Discounts' => 'الخصومات',
            'No scheduled payments' => 'لا توجد دفعات مجدولة',
            'This contract has no visible scheduled-payment records in the current authorized scope.' => 'لا يحتوي هذا العقد على دفعات مجدولة ظاهرة ضمن نطاق الصلاحيات الحالي.',
            'Manage payments' => 'إدارة الدفعات',
            'Contract identity' => 'بيانات العقد التعريفية',
            'Customer means Accounts Receivable; Supplier means Accounts Payable. Direction is derived by the backend and cannot be overridden here.' => 'العميل يعني حسابات القبض، والمورد يعني حسابات الدفع. يحدد الخادم الاتجاه المالي ولا يمكن تجاوزه من هذه الصفحة.',
            'Financial & responsibility' => 'البيانات المالية والمسؤولية',
            'Lifecycle' => 'دورة حياة العقد',
            'Notes & files' => 'الملاحظات والملفات',
            'Dates' => 'التواريخ',
            'No contract notes.' => 'لا توجد ملاحظات على العقد.',
            'Search' => 'بحث',
            'Contract number or counterparty' => 'رقم العقد أو الطرف المقابل',
            'Contract pagination' => 'ترقيم صفحات العقود',
            '%1$d contracts · page %2$d of %3$d' => '%1$d عقد · الصفحة %2$d من %3$d',
            default => $english,
        };
    }

    private static function queryText(string $key): string
    {
        return isset($_GET[$key]) && is_scalar($_GET[$key]) ? sanitize_text_field((string) $_GET[$key]) : '';
    }

    private static function queryKey(string $key): string
    {
        return isset($_GET[$key]) && is_scalar($_GET[$key]) ? sanitize_key((string) $_GET[$key]) : '';
    }

    /** @param array<string,mixed> $filters */
    private static function renderPagination(int $currentPage, int $totalPages, int $totalRows, array $filters, string $search, int $selectedId, string $tab): void
    {
        if ($totalPages <= 1) {
            return;
        }
        $base = ['page' => self::SLUG];
        if (($filters['financial_direction'] ?? '') !== '') { $base['financial_direction'] = (string) $filters['financial_direction']; }
        if (($filters['year'] ?? 0) > 0) { $base['year'] = (int) $filters['year']; }
        if (($filters['status'] ?? '') !== '') { $base['status'] = (string) $filters['status']; }
        if (($filters['currency_code'] ?? '') !== '') { $base['currency_code'] = (string) $filters['currency_code']; }
        if ($search !== '') { $base['contract_search'] = $search; }
        if ($selectedId > 0) { $base['contract_id'] = $selectedId; $base['contract_tab'] = $tab; }
        ?>
        <nav class="safecontracts-worker1__pagination" aria-label="<?php echo esc_attr(self::text('Contract pagination')); ?>">
            <span><?php echo esc_html(sprintf(self::text('%1$d contracts · page %2$d of %3$d'), $totalRows, $currentPage, $totalPages)); ?></span>
            <span class="safecontracts-worker1__pagination-links"><?php for ($page = 1; $page <= $totalPages; $page++) : ?><a class="button button-small" <?php if ($page === $currentPage) : ?>aria-current="page"<?php endif; ?> href="<?php echo esc_url(add_query_arg(array_merge($base, ['contract_page' => $page]), admin_url('admin.php'))); ?>"><?php echo esc_html((string) $page); ?></a><?php endfor; ?></span>
        </nav>
        <?php
    }
}
