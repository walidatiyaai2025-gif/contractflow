<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;

final class DashboardPage
{
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
        $customers = $read->customerOptions();
        $contracts = $read->contractOptions($filters['customer_id']);
        ?>
        <section class="safecontracts-dashboard" aria-labelledby="safecontracts-dashboard-title">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Operational overview', 'safecontracts'); ?></p>
                    <h2 id="safecontracts-dashboard-title"><?php echo esc_html__('Dashboard', 'safecontracts'); ?></h2>
                </div>
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
                <?php self::kpi(__('Scheduled', 'safecontracts'), self::money($kpis['scheduled_total'])); ?>
                <?php self::kpi(__('Remaining', 'safecontracts'), self::money($kpis['remaining_total'])); ?>
                <?php self::kpi(__('Overdue exposure', 'safecontracts'), self::money($kpis['overdue_exposure']), true); ?>
                <?php self::kpi(__('Collected', 'safecontracts'), self::money($kpis['collected_total'])); ?>
            </div>
            <p class="description"><?php echo esc_html__('Dashboard values are calculated from server-side scoped contract/payment data. Contractual due dates remain authoritative for overdue exposure. Server-side authorization and assignment scope remain authoritative for every metric and filter.', 'safecontracts'); ?></p>
        </section>
        <?php
    }

    private static function kpi(string $label, string $value, bool $alert = false): void
    {
        ?><article class="safecontracts-kpi<?php echo $alert ? ' safecontracts-kpi--alert' : ''; ?>"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($value); ?></strong></article><?php
    }

    private static function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', ',');
    }
}
