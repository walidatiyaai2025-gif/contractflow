<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Reports\ReportExportService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Translations\TranslationCatalog;

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
        if (! current_user_can(Capabilities::EXPORT_REPORTS)) {
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
        $summary = empty($filters['date_range_error'])
            ? $read->reportSummary($filters)
            : [
                'contract_count' => '0',
                'scheduled_total' => '0.0000',
                'remaining_total' => '0.0000',
                'overdue_exposure' => '0.0000',
                'collected_total' => '0.0000',
                'collection_transactions' => '0',
                'collection_ledger_total' => '0.0000',
                'followup_events' => '0',
                'followed_up_payments' => '0',
            ];
        $customers = $read->customerOptions();
        $contracts = $read->contractOptions($filters['customer_id']);
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Server-side reporting', 'safecontracts'); ?></p><h1><?php echo esc_html__('Reports', 'safecontracts'); ?></h1></div></div>
            <section class="safecontracts-admin-card">
                <?php if (! empty($filters['date_range_error'])) : ?><div class="notice notice-error inline"><p><?php echo esc_html__('Invalid period. Use valid dates and make sure the end date is not earlier than the start date.', 'safecontracts'); ?></p></div><?php endif; ?>
                <form class="safecontracts-filter-bar" method="get">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                    <label><?php echo esc_html__('Customer', 'safecontracts'); ?><select name="customer_id"><option value="0"><?php echo esc_html__('All customers', 'safecontracts'); ?></option><?php foreach ($customers as $customer) : ?><option value="<?php echo esc_attr((string) $customer['id']); ?>" <?php selected($filters['customer_id'], $customer['id']); ?>><?php echo esc_html($customer['name']); ?></option><?php endforeach; ?></select></label>
                    <label><?php echo esc_html__('Contract', 'safecontracts'); ?><select name="contract_id"><option value="0"><?php echo esc_html__('All contracts', 'safecontracts'); ?></option><?php foreach ($contracts as $contract) : ?><option value="<?php echo esc_attr((string) $contract['id']); ?>" <?php selected($filters['contract_id'], $contract['id']); ?>><?php echo esc_html($contract['contract_number']); ?></option><?php endforeach; ?></select></label>
                    <?php if (current_user_can(Capabilities::VIEW_ALL)) : ?><label><?php echo esc_html__('Accountant ID', 'safecontracts'); ?><input type="number" min="0" name="accountant_user_id" value="<?php echo esc_attr((string) $filters['accountant_user_id']); ?>"></label><?php endif; ?>
                    <label><?php echo esc_html__('Status', 'safecontracts'); ?><select name="status"><option value=""><?php echo esc_html__('Any status', 'safecontracts'); ?></option><?php foreach (['active','draft','completed','cancelled','upcoming','due_soon','due','overdue','partially_paid','paid'] as $status) : ?><option value="<?php echo esc_attr($status); ?>" <?php selected($filters['status'], $status); ?>><?php echo esc_html(self::statusLabel($status)); ?></option><?php endforeach; ?></select></label>
                    <?php AdminPeriodFilter::renderFields($filters); ?>
                    <button class="button button-primary" type="submit"><?php echo esc_html__('Run report', 'safecontracts'); ?></button>
                </form>
                <?php if (current_user_can(Capabilities::EXPORT_REPORTS) && empty($filters['date_range_error'])) : ?>
                <form class="safecontracts-export-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::EXPORT_ACTION); ?>">
                    <?php foreach (['customer_id','contract_id','accountant_user_id','status','date_from','date_to'] as $key) : ?><input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string) ($filters[$key] ?? '')); ?>"><?php endforeach; ?>
                    <?php wp_nonce_field(self::EXPORT_ACTION); ?>
                    <button class="button" type="submit"><?php echo esc_html__('Export current filters to Excel', 'safecontracts'); ?></button>
                    <span class="description"><?php echo esc_html__('XLSX is generated server-side from your authorized report scope and selected period.', 'safecontracts'); ?></span>
                </form>
                <?php endif; ?>
                <p class="description"><?php echo esc_html__('Period semantics are server-side and explicit: receivable KPIs/payments use contractual due date; collections use collection date; follow-up metrics use follow-up event date; contracts use start date with creation-date fallback; customers use record creation date.', 'safecontracts'); ?></p>
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
                <p><?php echo esc_html__('All totals and XLSX sheets are computed server-side using the same authorized customer, contract, accountant, status and period filters. Export completion is written through the Safe Contracts audit hook.', 'safecontracts'); ?></p>
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

    private static function statusLabel(string $status): string
    {
        return TranslationCatalog::text(ucwords(str_replace('_', ' ', $status)));
    }
}
