<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Contracts\ContractArchiveService;
use SafeContracts\Contracts\ContractService;
use SafeContracts\Roles\Capabilities;
use Throwable;

final class ContractsPage
{
    public const SLUG = 'safecontracts-contracts';
    public const SAVE_ACTION = 'safecontracts_save_contract_admin';
    public const DELETE_ACTION = 'safecontracts_delete_contract_admin';

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
            <div class="safecontracts-split-layout">
                <section class="safecontracts-admin-card safecontracts-table-card">
                    <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Contract', 'safecontracts'); ?></th><th><?php echo esc_html__('Customer', 'safecontracts'); ?></th><th><?php echo esc_html__('Status', 'safecontracts'); ?></th><th><?php echo esc_html__('Base value', 'safecontracts'); ?></th><th><?php echo esc_html__('Actions', 'safecontracts'); ?></th></tr></thead><tbody>
                    <?php foreach ($contracts as $contract) : ?>
                        <tr>
                            <td><a href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'contract_id' => (int) $contract['id']], admin_url('admin.php'))); ?>"><?php echo esc_html((string) $contract['contract_number']); ?></a><?php echo ! empty($contract['is_archived']) ? ' · ' . esc_html__('Archived', 'safecontracts') : ''; ?></td>
                            <td><?php echo esc_html((string) $contract['customer_name']); ?></td>
                            <td><?php echo esc_html((string) $contract['status']); ?></td>
                            <td><?php echo esc_html(number_format((float) $contract['base_value'], 2)); ?></td>
                            <td>
                                <div class="safecontracts-dashboard-table-actions">
                                    <a class="button button-small" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'contract_id' => (int) $contract['id']], admin_url('admin.php'))); ?>"><?php echo esc_html__('Open', 'safecontracts'); ?></a>
                                    <?php if (empty($contract['is_archived']) && current_user_can(Capabilities::MANAGE_SYSTEM)) : ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-safecontracts-delete-form data-delete-message="<?php echo esc_attr__('Delete this contract from active operations? Payments, collections, history and audit evidence will be preserved.', 'safecontracts'); ?>">
                                            <input type="hidden" name="action" value="<?php echo esc_attr(self::DELETE_ACTION); ?>">
                                            <input type="hidden" name="contract_id" value="<?php echo esc_attr((string) $contract['id']); ?>">
                                            <?php wp_nonce_field(self::DELETE_ACTION . '_' . (int) $contract['id']); ?>
                                            <button type="submit" class="button button-small safecontracts-delete-button"><?php echo esc_html__('Delete', 'safecontracts'); ?> / حذف</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </section>
                <?php if (current_user_can(Capabilities::CREATE_CONTRACTS) || ($selected && (current_user_can(Capabilities::EDIT_CONTRACTS) || current_user_can(Capabilities::ASSIGN_CONTRACTS)))) : ?>
                <section class="safecontracts-admin-card">
                    <h2><?php echo $selected ? esc_html__('Contract details', 'safecontracts') : esc_html__('Create contract', 'safecontracts'); ?></h2>
                    <?php if ($selected && ! empty($selected['is_archived'])) : ?><p class="notice notice-warning inline"><?php echo esc_html__('Archived contracts are read-only.', 'safecontracts'); ?></p><?php endif; ?>
                    <?php if ($reconciliation) : ?><p><strong><?php echo esc_html__('Net value:', 'safecontracts'); ?></strong> <?php echo esc_html(number_format((float) $reconciliation['net_value'], 2)); ?></p><?php endif; ?>
                    <?php if (! $selected || empty($selected['is_archived'])) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><input type="hidden" name="contract_id" value="<?php echo esc_attr((string) ($selected['id'] ?? 0)); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>
                        <p><label><?php echo esc_html__('Contract number', 'safecontracts'); ?><input class="widefat" name="contract_number" required value="<?php echo esc_attr((string) ($selected['contract_number'] ?? '')); ?>"></label></p>
                        <p><label><?php echo esc_html__('Customer', 'safecontracts'); ?><select class="widefat" name="customer_id" required><?php foreach ($customers as $customer) : ?><option value="<?php echo esc_attr((string) $customer['id']); ?>" <?php selected((int) ($selected['customer_id'] ?? 0), $customer['id']); ?>><?php echo esc_html($customer['name']); ?></option><?php endforeach; ?></select></label></p>
                        <p><label><?php echo esc_html__('Accountant user ID', 'safecontracts'); ?><input class="widefat" type="number" min="1" name="accountant_user_id" value="<?php echo esc_attr((string) ($selected['accountant_user_id'] ?? '')); ?>"></label></p>
                        <?php if ($selected) : ?><p class="safecontracts-field-row"><label><?php echo esc_html__('Start date', 'safecontracts'); ?><input type="date" name="start_date" value="<?php echo esc_attr((string) ($selected['start_date'] ?? '')); ?>"></label><label><?php echo esc_html__('End date', 'safecontracts'); ?><input type="date" name="end_date" value="<?php echo esc_attr((string) ($selected['end_date'] ?? '')); ?>"></label></p><?php endif; ?>
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
}
