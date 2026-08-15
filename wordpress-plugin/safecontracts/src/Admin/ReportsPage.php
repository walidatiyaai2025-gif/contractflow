<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Reports\ReportExportService;
use SafeContracts\Roles\Capabilities;

final class ReportsPage
{
    public const SLUG = 'safecontracts-reports';
    public const EXPORT_ACTION = 'safecontracts_export_report_xlsx';

    public static function register(): void
    {
        add_submenu_page(AdminShell::SLUG, __('Reports', 'safecontracts'), __('Reports', 'safecontracts'), Capabilities::VIEW_REPORTS, self::SLUG, [self::class, 'render']);
    }

    public static function handleExport(): void
    {
        if (! current_user_can(Capabilities::VIEW_REPORTS)) {
            wp_die(__('You do not have permission to export reports.', 'safecontracts'));
        }
        check_admin_referer(self::EXPORT_ACTION);

        $export = (new ReportExportService())->generate($_POST);
        header('Content-Type: ' . $export['content_type']);
        header('Content-Disposition: attachment; filename="' . $export['filename'] . '"');
        header('Content-Length: ' . strlen($export['content']));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo $export['content'];
        exit;
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::VIEW_REPORTS)) {
            wp_die(__('You do not have permission to view reports.', 'safecontracts'));
        }
        $filters = DashboardFilters::normalize($_GET);
        $read = new AdminReadRepository();
        $summary = $read->reportSummary($filters);
        $customers = $read->customerOptions();
        $contracts = $read->contractOptions($filters['customer_id']);
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Server-side reporting', 'safecontracts'); ?></p><h1><?php echo esc_html__('Reports', 'safecontracts'); ?></h1></div></div>
            <section class="safecontracts-admin-card">
                <form class="safecontracts-filter-bar" method="get">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                    <label><?php echo esc_html__('Customer', 'safecontracts'); ?><select name="customer_id"><option value="0"><?php echo esc_html__('All customers', 'safecontracts'); ?></option><?php foreach ($customers as $customer) : ?><option value="<?php echo esc_attr((string) $customer['id']); ?>" <?php selected($filters['customer_id'], $customer['id']); ?>><?php echo esc_html($customer['name']); ?></option><?php endforeach; ?></select></label>
                    <label><?php echo esc_html__('Contract', 'safecontracts'); ?><select name="contract_id"><option value="0"><?php echo esc_html__('All contracts', 'safecontracts'); ?></option><?php foreach ($contracts as $contract) : ?><option value="<?php echo esc_attr((string) $contract['id']); ?>" <?php selected($filters['contract_id'], $contract['id']); ?>><?php echo esc_html($contract['contract_number']); ?></option><?php endforeach; ?></select></label>
                    <?php if (current_user_can(Capabilities::VIEW_ALL)) : ?><label><?php echo esc_html__('Accountant ID', 'safecontracts'); ?><input type="number" min="0" name="accountant_user_id" value="<?php echo esc_attr((string) $filters['accountant_user_id']); ?>"></label><?php endif; ?>
                    <label><?php echo esc_html__('Status', 'safecontracts'); ?><select name="status"><option value=""><?php echo esc_html__('Any status', 'safecontracts'); ?></option><?php foreach (['active','draft','completed','cancelled','upcoming','due_soon','due','overdue','partially_paid','paid'] as $status) : ?><option value="<?php echo esc_attr($status); ?>" <?php selected($filters['status'], $status); ?>><?php echo esc_html(ucwords(str_replace('_', ' ', $status))); ?></option><?php endforeach; ?></select></label>
                    <label><?php echo esc_html__('Due from', 'safecontracts'); ?><input type="date" name="due_from" value="<?php echo esc_attr((string) ($filters['due_from'] ?? '')); ?>"></label>
                    <label><?php echo esc_html__('Due to', 'safecontracts'); ?><input type="date" name="due_to" value="<?php echo esc_attr((string) ($filters['due_to'] ?? '')); ?>"></label>
                    <button class="button button-primary" type="submit"><?php echo esc_html__('Run report', 'safecontracts'); ?></button>
                </form>
                <form class="safecontracts-export-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::EXPORT_ACTION); ?>">
                    <?php foreach ($filters as $key => $value) : ?><input type="hidden" name="<?php echo esc_attr((string) $key); ?>" value="<?php echo esc_attr((string) ($value ?? '')); ?>"><?php endforeach; ?>
                    <?php wp_nonce_field(self::EXPORT_ACTION); ?>
                    <button class="button" type="submit"><?php echo esc_html__('Export current filters to Excel', 'safecontracts'); ?></button>
                    <span class="description"><?php echo esc_html__('XLSX is generated server-side from your authorized report scope.', 'safecontracts'); ?></span>
                </form>
            </section>
            <div class="safecontracts-kpi-grid">
                <?php self::metric(__('Contracts', 'safecontracts'), (string) $summary['contract_count']); ?>
                <?php self::metric(__('Scheduled receivables', 'safecontracts'), self::money($summary['scheduled_total'])); ?>
                <?php self::metric(__('Remaining receivables', 'safecontracts'), self::money($summary['remaining_total'])); ?>
                <?php self::metric(__('Overdue exposure', 'safecontracts'), self::money($summary['overdue_exposure']), true); ?>
                <?php self::metric(__('Collection ledger', 'safecontracts'), self::money($summary['collection_ledger_total'])); ?>
                <?php self::metric(__('Collection transactions', 'safecontracts'), (string) $summary['collection_transactions']); ?>
                <?php self::metric(__('Follow-up events', 'safecontracts'), (string) $summary['followup_events']); ?>
                <?php self::metric(__('Payments followed up', 'safecontracts'), (string) $summary['followed_up_payments']); ?>
            </div>
            <section class="safecontracts-admin-card safecontracts-admin-card--security">
                <h2><?php echo esc_html__('Scoped report boundary', 'safecontracts'); ?></h2>
                <p><?php echo esc_html__('All totals and XLSX sheets are computed server-side using the same authorized customer, contract, accountant, payment-status and contractual due-date filters. Export completion is written through the SafeContracts audit hook.', 'safecontracts'); ?></p>
            </section>
        </div>
        <?php
    }

    private static function metric(string $label, string $value, bool $alert = false): void
    {
        ?><article class="safecontracts-kpi<?php echo $alert ? ' safecontracts-kpi--alert' : ''; ?>"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($value); ?></strong></article><?php
    }

    private static function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', ',');
    }
}
