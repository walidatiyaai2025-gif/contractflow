<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Contracts\ContractArchiveService;
use SafeContracts\Contracts\ContractService;
use SafeContracts\Contracts\ContractStatus;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;
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
        try {
            if ($contractId === 0) {
                $contractId = $service->create([
                    'contract_number' => sanitize_text_field((string) ($_POST['contract_number'] ?? '')),
                    'customer_id' => (int) ($_POST['customer_id'] ?? 0),
                    'accountant_user_id' => ($_POST['accountant_user_id'] ?? '') === '' ? null : (int) $_POST['accountant_user_id'],
                    'notes' => sanitize_textarea_field((string) ($_POST['notes'] ?? '')),
                ]);
            } else {
                if (current_user_can(Capabilities::EDIT_CONTRACTS)) {
                    $service->edit($contractId, [
                        'contract_number' => sanitize_text_field((string) ($_POST['contract_number'] ?? '')),
                        'notes' => sanitize_textarea_field((string) ($_POST['notes'] ?? '')),
                    ]);
                    $service->updateDates($contractId, $_POST['start_date'] ?? null, $_POST['end_date'] ?? null);
                    $targetStatus = sanitize_key((string) ($_POST['status'] ?? ''));
                    if ($targetStatus !== '') {
                        $service->changeStatus($contractId, $targetStatus);
                    }
                }
                if (current_user_can(Capabilities::ASSIGN_CONTRACTS)) {
                    $service->assignCustomer($contractId, (int) ($_POST['customer_id'] ?? 0));
                    $accountant = ($_POST['accountant_user_id'] ?? '') === '' ? null : (int) $_POST['accountant_user_id'];
                    $service->assignAccountant($contractId, $accountant);
                }
            }
        } catch (Throwable $error) {
            unset($error);
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
        $customers = $read->customerOptions();
        $accountants = self::accountantOptions();
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
        $selectedId = max(0, (int) ($_GET['contract_id'] ?? 0));
        if ($selectedId > 0) {
            $rows = $read->contracts(['contract_id' => $selectedId]);
            $selected = $rows[0] ?? null;
            if ($selected !== null) {
                try {
                    $reconciliation = (new ContractService())->reconcile($selectedId);
                } catch (Throwable $error) {
                    unset($error);
                }
            }
        }
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Contract operations', 'safecontracts'); ?></p><h1><?php echo esc_html__('Contracts', 'safecontracts'); ?></h1></div></div>
            <?php AdminPeriodFilter::render(self::SLUG, $filters, $selectedId > 0 ? ['contract_id' => $selectedId] : []); ?>
            <p class="description"><?php echo esc_html__('Contract period filtering uses the contract start date, falling back to the record creation date when no start date exists.', 'safecontracts'); ?></p>
            <?php if ($status === 'bulk_assigned') : ?>
                <div class="notice notice-success inline"><p><?php echo esc_html(sprintf(__('Responsible accountant assigned to %1$d contract(s). %2$d contract(s) were skipped because they were no longer eligible.', 'safecontracts'), $bulkAssigned, $bulkSkipped)); ?></p></div>
            <?php elseif ($status === 'bulk_invalid') : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html__('Bulk assignment was not applied. Select an eligible SafeContracts Accountant and at least one unassigned contract.', 'safecontracts'); ?></p></div>
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
                    <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Contract', 'safecontracts'); ?></th><th><?php echo esc_html__('Customer', 'safecontracts'); ?></th><th><?php echo esc_html__('Responsible accountant', 'safecontracts'); ?></th><th><?php echo esc_html__('Status', 'safecontracts'); ?></th><th><?php echo esc_html__('Base value', 'safecontracts'); ?></th><th><?php echo esc_html__('Actions', 'safecontracts'); ?></th></tr></thead><tbody>
                    <?php foreach ($contracts as $contract) : ?>
                        <?php $accountantId = (int) ($contract['accountant_user_id'] ?? 0); ?>
                        <tr>
                            <td><a href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'contract_id' => (int) $contract['id']], admin_url('admin.php'))); ?>"><?php echo esc_html((string) $contract['contract_number']); ?></a><?php echo ! empty($contract['is_archived']) ? ' · ' . esc_html__('Archived', 'safecontracts') : ''; ?></td>
                            <td><?php echo esc_html((string) $contract['customer_name']); ?></td>
                            <td><?php echo $accountantId > 0 ? esc_html($accountantLabels[$accountantId] ?? ('#' . $accountantId)) : '<strong>' . esc_html__('Unassigned', 'safecontracts') . '</strong>'; ?></td>
                            <td><?php echo esc_html(self::statusLabel((string) $contract['status'])); ?></td>
                            <td><?php echo esc_html(number_format((float) $contract['base_value'], 2)); ?></td>
                            <td>
                                <div class="safecontracts-dashboard-table-actions">
                                    <a class="button button-small" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'contract_id' => (int) $contract['id']], admin_url('admin.php'))); ?>"><?php echo esc_html__('Open', 'safecontracts'); ?></a>
                                    <?php if (empty($contract['is_archived']) && current_user_can(Capabilities::MANAGE_SYSTEM)) : ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-safecontracts-delete-form data-delete-message="<?php echo esc_attr__('Delete this contract from active operations? Payments, collections, history and audit evidence will be preserved.', 'safecontracts'); ?>">
                                            <input type="hidden" name="action" value="<?php echo esc_attr(self::DELETE_ACTION); ?>">
                                            <input type="hidden" name="contract_id" value="<?php echo esc_attr((string) $contract['id']); ?>">
                                            <?php wp_nonce_field(self::DELETE_ACTION . '_' . (int) $contract['id']); ?>
                                            <button type="submit" class="button button-small safecontracts-delete-button"><?php echo esc_html__('Delete', 'safecontracts'); ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </section>
                <?php if ((! $selected && current_user_can(Capabilities::CREATE_CONTRACTS)) || ($selected && (current_user_can(Capabilities::EDIT_CONTRACTS) || $canAssignContracts))) : ?>
                <section class="safecontracts-admin-card">
                    <h2><?php echo $selected ? esc_html__('Contract details', 'safecontracts') : esc_html__('Create contract', 'safecontracts'); ?></h2>
                    <?php if ($selected && ! empty($selected['is_archived'])) : ?><p class="notice notice-warning inline"><?php echo esc_html__('Archived contracts are read-only.', 'safecontracts'); ?></p><?php endif; ?>
                    <?php if ($reconciliation) : ?><p><strong><?php echo esc_html__('Net value:', 'safecontracts'); ?></strong> <?php echo esc_html(number_format((float) $reconciliation['net_value'], 2)); ?></p><?php endif; ?>
                    <?php if (! $selected || empty($selected['is_archived'])) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><input type="hidden" name="contract_id" value="<?php echo esc_attr((string) ($selected['id'] ?? 0)); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>
                        <p><label><?php echo esc_html__('Contract number', 'safecontracts'); ?><input class="widefat" name="contract_number" required value="<?php echo esc_attr((string) ($selected['contract_number'] ?? '')); ?>"></label></p>
                        <p><label><?php echo esc_html__('Customer', 'safecontracts'); ?><select class="widefat" name="customer_id" required><?php foreach ($customers as $customer) : ?><option value="<?php echo esc_attr((string) $customer['id']); ?>" <?php selected((int) ($selected['customer_id'] ?? 0), $customer['id']); ?>><?php echo esc_html($customer['name']); ?></option><?php endforeach; ?></select></label></p>
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
                            <p class="safecontracts-field-row"><label><?php echo esc_html__('Start date', 'safecontracts'); ?><input type="date" name="start_date" value="<?php echo esc_attr((string) ($selected['start_date'] ?? '')); ?>"></label><label><?php echo esc_html__('End date', 'safecontracts'); ?><input type="date" name="end_date" value="<?php echo esc_attr((string) ($selected['end_date'] ?? '')); ?>"></label></p>
                            <?php if (current_user_can(Capabilities::EDIT_CONTRACTS)) : ?>
                                <?php $statusOptions = array_values(array_unique(array_merge([(string) $selected['status']], ContractStatus::allowedTargets((string) $selected['status'])))); ?>
                                <p><label><?php echo esc_html__('Contract status', 'safecontracts'); ?><select class="widefat" name="status"><?php foreach ($statusOptions as $statusOption) : ?><option value="<?php echo esc_attr($statusOption); ?>" <?php selected((string) $selected['status'], $statusOption); ?>><?php echo esc_html(self::statusLabel($statusOption)); ?></option><?php endforeach; ?></select></label></p>
                                <?php if (count($statusOptions) === 1) : ?><p class="description"><?php echo esc_html__('This contract is in a terminal lifecycle state and cannot transition to another status.', 'safecontracts'); ?></p><?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                        <p><label><?php echo esc_html__('Notes', 'safecontracts'); ?><textarea class="widefat" rows="4" name="notes"><?php echo esc_textarea((string) ($selected['notes'] ?? '')); ?></textarea></label></p>
                        <?php submit_button($selected ? __('Save contract', 'safecontracts') : __('Create contract', 'safecontracts')); ?>
                    </form>
                    <?php endif; ?>
                </section>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /** @return list<array{id:int,label:string}> */
    private static function accountantOptions(): array
    {
        $users = get_users([
            'role' => RoleRegistrar::ACCOUNTANT,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);
        if (! is_array($users)) {
            return [];
        }

        $options = [];
        foreach ($users as $user) {
            if (! is_object($user) || ! isset($user->ID)) {
                continue;
            }
            $id = (int) $user->ID;
            if ($id <= 0) {
                continue;
            }
            $name = trim((string) ($user->display_name ?? ''));
            if ($name === '') {
                $name = trim((string) ($user->user_login ?? ''));
            }
            $email = trim((string) ($user->user_email ?? ''));
            $label = $name !== '' ? $name : ('#' . $id);
            if ($email !== '') {
                $label .= ' <' . $email . '>';
            }
            $options[] = ['id' => $id, 'label' => $label];
        }
        return $options;
    }

    private static function statusLabel(string $status): string
    {
        return TranslationCatalog::text(ucwords(str_replace('_', ' ', $status)));
    }
}
