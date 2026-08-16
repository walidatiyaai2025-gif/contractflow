<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Contracts\ContractArchiveService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Settings\GeneralSettings;
use Throwable;

final class DashboardPage
{
    public const ARCHIVE_ACTION = 'safecontracts_archive_contract_dashboard';

    public static function handleArchive(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to delete contracts from the dashboard.', 'safecontracts'));
        }

        $contractId = max(0, (int) ($_POST['contract_id'] ?? 0));
        check_admin_referer(self::ARCHIVE_ACTION . '_' . $contractId);
        $status = 'archived';
        try {
            (new ContractArchiveService())->archive($contractId);
        } catch (Throwable $error) {
            unset($error);
            $status = 'archive_failed';
        }

        wp_safe_redirect(add_query_arg([
            'page' => AdminShell::SLUG,
            'safecontracts_status' => $status,
        ], admin_url('admin.php')));
        exit;
    }

    public static function renderContent(): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            wp_die(__('You do not have permission to access SafeContracts.', 'safecontracts'));
        }
        if (! current_user_can(Capabilities::VIEW_ALL) && ! current_user_can(Capabilities::VIEW_ASSIGNED)) {
            ?>
            <section class="safecontracts-dashboard" aria-labelledby="safecontracts-dashboard-title">
                <div class="safecontracts-section-heading">
                    <div>
                        <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Operational overview', 'safecontracts'); ?></p>
                        <h2 id="safecontracts-dashboard-title"><?php echo esc_html__('Dashboard', 'safecontracts'); ?></h2>
                    </div>
                </div>
                <section class="safecontracts-admin-card safecontracts-admin-card--security">
                    <h2><?php echo esc_html__('Server-side authorization', 'safecontracts'); ?></h2>
                    <p><?php echo esc_html__('No data scope assigned. Your account can access SafeContracts, but it does not currently have permission to view all data or assigned contract data. Contact a SafeContracts administrator if operational access is required.', 'safecontracts'); ?></p>
                </section>
            </section>
            <?php
            return;
        }
        $filters = DashboardFilters::normalize($_GET);
        $read = new AdminReadRepository();
        $kpis = $read->kpis($filters);
        $settings = (new GeneralSettings())->read();
        $currencyToken = trim((string) ($settings['currency_symbol'] ?? ''));
        if ($currencyToken === '') {
            $currencyToken = trim((string) ($settings['currency_code'] ?? ''));
        }
        $currencyLabel = trim(implode(' ', array_filter([
            (string) ($settings['currency_symbol'] ?? ''),
            (string) ($settings['currency_code'] ?? ''),
        ], static fn (string $value): bool => trim($value) !== '')));
        $customers = $read->customerOptions();
        $contracts = $read->contractOptions($filters['customer_id']);
        $tableFilters = $filters;
        if (! in_array($tableFilters['status'], ['draft', 'active', 'completed', 'cancelled'], true)) {
            $tableFilters['status'] = '';
        }
        $dashboardContracts = array_values(array_filter(
            $read->contracts($tableFilters),
            static fn (array $contract): bool => empty($contract['is_archived'])
        ));
        $dashboardContracts = array_slice($dashboardContracts, 0, 25);
        ?>
        <section class="safecontracts-dashboard" aria-labelledby="safecontracts-dashboard-title">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Operational overview', 'safecontracts'); ?></p>
                    <h2 id="safecontracts-dashboard-title"><?php echo esc_html__('Dashboard', 'safecontracts'); ?></h2>
                </div>
                <?php if ($currencyLabel !== '') : ?>
                    <span class="safecontracts-currency-badge"><?php echo esc_html__('Currency', 'safecontracts'); ?>: <?php echo esc_html($currencyLabel); ?></span>
                <?php endif; ?>
            </div>

            <form class="safecontracts-filter-bar" method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr(AdminShell::SLUG); ?>">
                <label><?php echo esc_html__('Customer', 'safecontracts'); ?>
                    <select name="customer_id">
                        <option value="0"><?php echo esc_html__('All customers', 'safecontracts'); ?></option>
                        <?php foreach ($customers as $customer) : ?>
                            <option value="<?php echo esc_attr((string) $customer['id']); ?>" <?php selected($filters['customer_id'], $customer['id']); ?>><?php echo esc_html($customer['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><?php echo esc_html__('Contract', 'safecontracts'); ?>
                    <select name="contract_id">
                        <option value="0"><?php echo esc_html__('All contracts', 'safecontracts'); ?></option>
                        <?php foreach ($contracts as $contract) : ?>
                            <option value="<?php echo esc_attr((string) $contract['id']); ?>" <?php selected($filters['contract_id'], $contract['id']); ?>><?php echo esc_html($contract['contract_number']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if (current_user_can(Capabilities::VIEW_ALL)) : ?>
                    <label><?php echo esc_html__('Accountant ID', 'safecontracts'); ?><input type="number" min="0" name="accountant_user_id" value="<?php echo esc_attr((string) $filters['accountant_user_id']); ?>"></label>
                <?php endif; ?>
                <label><?php echo esc_html__('Status', 'safecontracts'); ?>
                    <select name="status">
                        <option value=""><?php echo esc_html__('Any status', 'safecontracts'); ?></option>
                        <?php foreach (['active','draft','completed','cancelled','upcoming','due_soon','due','overdue','partially_paid','paid'] as $status) : ?>
                            <option value="<?php echo esc_attr($status); ?>" <?php selected($filters['status'], $status); ?>><?php echo esc_html(ucwords(str_replace('_', ' ', $status))); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><?php echo esc_html__('Due from', 'safecontracts'); ?><input type="date" name="due_from" value="<?php echo esc_attr((string) ($filters['due_from'] ?? '')); ?>"></label>
                <label><?php echo esc_html__('Due to', 'safecontracts'); ?><input type="date" name="due_to" value="<?php echo esc_attr((string) ($filters['due_to'] ?? '')); ?>"></label>
                <button class="button button-primary" type="submit"><?php echo esc_html__('Apply filters', 'safecontracts'); ?></button>
            </form>

            <div class="safecontracts-kpi-grid">
                <?php self::kpi(__('Contracts', 'safecontracts'), (string) $kpis['contract_count']); ?>
                <?php self::kpi(__('Scheduled', 'safecontracts'), self::money($kpis['scheduled_total'], $currencyToken)); ?>
                <?php self::kpi(__('Remaining', 'safecontracts'), self::money($kpis['remaining_total'], $currencyToken)); ?>
                <?php self::kpi(__('Overdue exposure', 'safecontracts'), self::money($kpis['overdue_exposure'], $currencyToken), true); ?>
                <?php self::kpi(__('Collected', 'safecontracts'), self::money($kpis['collected_total'], $currencyToken)); ?>
            </div>
            <p class="description"><?php echo esc_html__('Dashboard values use the configured SafeContracts currency and are calculated from server-side scoped contract/payment data. Contractual due dates remain authoritative for overdue exposure.', 'safecontracts'); ?></p>

            <section class="safecontracts-admin-card safecontracts-table-card safecontracts-dashboard-contracts" aria-labelledby="safecontracts-dashboard-contracts-title">
                <div class="safecontracts-section-heading">
                    <div>
                        <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Quick management', 'safecontracts'); ?></p>
                        <h2 id="safecontracts-dashboard-contracts-title"><?php echo esc_html__('Active contracts', 'safecontracts'); ?> / العقود النشطة</h2>
                    </div>
                </div>
                <?php if ($dashboardContracts === []) : ?>
                    <p><?php echo esc_html__('No active contracts match the current dashboard filters.', 'safecontracts'); ?></p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead><tr>
                            <th><?php echo esc_html__('Contract', 'safecontracts'); ?></th>
                            <th><?php echo esc_html__('Customer', 'safecontracts'); ?></th>
                            <th><?php echo esc_html__('Status', 'safecontracts'); ?></th>
                            <th><?php echo esc_html__('Base value', 'safecontracts'); ?></th>
                            <th><?php echo esc_html__('Actions', 'safecontracts'); ?> / الإجراءات</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($dashboardContracts as $contract) : ?>
                            <tr>
                                <td><?php echo esc_html((string) $contract['contract_number']); ?></td>
                                <td><?php echo esc_html((string) $contract['customer_name']); ?></td>
                                <td><?php echo esc_html((string) $contract['status']); ?></td>
                                <td><?php echo esc_html(self::money($contract['base_value'], $currencyToken)); ?></td>
                                <td>
                                    <div class="safecontracts-dashboard-table-actions">
                                        <a class="button button-small" href="<?php echo esc_url(add_query_arg(['page' => ContractsPage::SLUG, 'contract_id' => (int) $contract['id']], admin_url('admin.php'))); ?>"><?php echo esc_html__('Open', 'safecontracts'); ?> / فتح</a>
                                        <?php if (current_user_can(Capabilities::MANAGE_SYSTEM)) : ?>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-safecontracts-delete-form data-delete-message="<?php echo esc_attr__('Delete this contract from active operations? Its financial, collection, history and audit records will be preserved.', 'safecontracts'); ?>">
                                                <input type="hidden" name="action" value="<?php echo esc_attr(self::ARCHIVE_ACTION); ?>">
                                                <input type="hidden" name="contract_id" value="<?php echo esc_attr((string) $contract['id']); ?>">
                                                <?php wp_nonce_field(self::ARCHIVE_ACTION . '_' . (int) $contract['id']); ?>
                                                <button type="submit" class="button button-small safecontracts-delete-button"><?php echo esc_html__('Delete', 'safecontracts'); ?> / حذف</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="description"><?php echo esc_html__('Delete is a safe archive action: the contract disappears from this dashboard list, while financial, collection, history and audit records are preserved.', 'safecontracts'); ?> / الحذف هنا أرشفة آمنة تحفظ السجل المالي والتاريخي.</p>
                <?php endif; ?>
            </section>
        </section>
        <?php
    }

    private static function kpi(string $label, string $value, bool $alert = false): void
    {
        ?><article class="safecontracts-kpi<?php echo $alert ? ' safecontracts-kpi--alert' : ''; ?>"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($value); ?></strong></article><?php
    }

    private static function money(mixed $value, string $currencyToken = ''): string
    {
        $amount = number_format((float) $value, 2, '.', ',');
        $currencyToken = trim($currencyToken);
        if ($currencyToken === '') {
            return $amount;
        }
        $locale = function_exists('get_user_locale') ? strtolower((string) get_user_locale()) : 'en_us';
        return str_starts_with($locale, 'ar') ? $amount . ' ' . $currencyToken : $currencyToken . ' ' . $amount;
    }
}
