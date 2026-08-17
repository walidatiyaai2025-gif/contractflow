<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Finance\FinanceOverviewService;
use SafeContracts\Finance\FinancialDirection;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Reports\ReportExportService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Translations\TranslationCatalog;
use Throwable;

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
                'collection_transactions' => '0',
                'collection_ledger_total' => '0.0000',
                'followup_events' => '0',
                'followed_up_payments' => '0',
            ];
        $customers = $read->customerOptions();
        $contracts = $read->contractOptions($filters['customer_id']);
        $finance = null;
        $financeError = '';
        if (empty($filters['date_range_error']) && self::canViewFinance()) {
            try {
                $finance = (new FinanceOverviewService())->overview([
                    'customer_id' => $filters['customer_id'],
                    'contract_id' => $filters['contract_id'],
                    'accountant_user_id' => $filters['accountant_user_id'],
                    'status' => $filters['status'],
                    'due_from' => $filters['date_from'],
                    'due_to' => $filters['date_to'],
                ]);
            } catch (Throwable $error) {
                unset($error);
                $financeError = __('Financial intelligence could not be loaded for this report scope.', 'safecontracts');
            }
        }
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading">
                <div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Server-side reporting', 'safecontracts'); ?></p><h1><?php echo esc_html__('Reports', 'safecontracts'); ?></h1></div>
                <?php if (self::canViewFinance()) : ?><a class="button" href="<?php echo esc_url(add_query_arg(['page' => FinancePage::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Open Finance workspace', 'safecontracts'); ?></a><?php endif; ?>
            </div>
            <section class="safecontracts-admin-card">
                <?php if (! empty($filters['date_range_error'])) : ?><div class="notice notice-error inline"><p><?php echo esc_html__('Invalid period. Use valid dates and make sure the end date is not earlier than the start date.', 'safecontracts'); ?></p></div><?php endif; ?>
                <form class="safecontracts-filter-bar" method="get">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                    <label><?php echo esc_html__('Customer', 'safecontracts'); ?><select name="customer_id"><option value="0"><?php echo esc_html__('All customers', 'safecontracts'); ?></option><?php foreach ($customers as $customer) : ?><option value="<?php echo esc_attr((string) $customer['id']); ?>" <?php selected($filters['customer_id'], $customer['id']); ?>><?php echo esc_html($customer['name']); ?></option><?php endforeach; ?></select></label>
                    <label><?php echo esc_html__('Contract', 'safecontracts'); ?><select name="contract_id"><option value="0"><?php echo esc_html__('All contracts', 'safecontracts'); ?></option><?php foreach ($contracts as $contract) : ?><option value="<?php echo esc_attr((string) $contract['id']); ?>" <?php selected($filters['contract_id'], $contract['id']); ?>><?php echo esc_html($contract['contract_number']); ?></option><?php endforeach; ?></select></label>
                    <?php if (current_user_can(Capabilities::VIEW_ALL)) : ?><label><?php echo esc_html__('Accountant ID', 'safecontracts'); ?><input type="number" min="0" name="accountant_user_id" value="<?php echo esc_attr((string) $filters['accountant_user_id']); ?>"></label><?php endif; ?>
                    <label><?php echo esc_html__('Status', 'safecontracts'); ?><select name="status"><option value=""><?php echo esc_html__('Any status', 'safecontracts'); ?></option><?php foreach (array_values(array_unique(array_merge(['active','draft','completed','cancelled'], PaymentStatus::all()))) as $status) : ?><option value="<?php echo esc_attr($status); ?>" <?php selected($filters['status'], $status); ?>><?php echo esc_html(self::statusLabel($status)); ?></option><?php endforeach; ?></select></label>
                    <?php AdminPeriodFilter::renderFields($filters); ?>
                    <button class="button button-primary" type="submit"><?php echo esc_html__('Run report', 'safecontracts'); ?></button>
                </form>
                <?php if (current_user_can(Capabilities::EXPORT_REPORTS) && empty($filters['date_range_error'])) : ?>
                <form class="safecontracts-export-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::EXPORT_ACTION); ?>">
                    <?php foreach (['customer_id','contract_id','accountant_user_id','status','date_from','date_to'] as $key) : ?><input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string) ($filters[$key] ?? '')); ?>"><?php endforeach; ?>
                    <?php wp_nonce_field(self::EXPORT_ACTION); ?>
                    <button class="button" type="submit"><?php echo esc_html__('Export current filters to Excel', 'safecontracts'); ?></button>
                    <span class="description"><?php echo esc_html__('XLSX includes Finance Summary, Aging, Cash Flow and Finance Obligations when your account has the corresponding AP/AR view permissions.', 'safecontracts'); ?></span>
                </form>
                <?php endif; ?>
                <p class="description"><?php echo esc_html__('Financial obligations use contractual due date. Legacy collections use collection date, follow-up metrics use event date, contracts use start date with creation-date fallback, and customers use record creation date.', 'safecontracts'); ?></p>
            </section>

            <?php if ($financeError !== '') : ?><div class="notice notice-error inline"><p><?php echo esc_html($financeError); ?></p></div><?php endif; ?>
            <?php if (is_array($finance)) : ?>
                <?php self::renderFinanceSummary((array) ($finance['summary'] ?? [])); ?>
                <?php self::renderFinanceAging((array) ($finance['aging'] ?? [])); ?>
            <?php endif; ?>

            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Receivable operations history', 'safecontracts'); ?></p><h2><?php echo esc_html__('Collections & follow-ups', 'safecontracts'); ?></h2></div></div>
            <div class="safecontracts-kpi-grid">
                <?php self::metric(__('Contracts in scope', 'safecontracts'), (string) ($summary['contract_count'] ?? 0)); ?>
                <?php self::metric(__('Collection ledger', 'safecontracts'), self::money($summary['collection_ledger_total'] ?? 0)); ?>
                <?php self::metric(__('Collection transactions', 'safecontracts'), (string) ($summary['collection_transactions'] ?? 0)); ?>
                <?php self::metric(__('Follow-up events', 'safecontracts'), (string) ($summary['followup_events'] ?? 0)); ?>
                <?php self::metric(__('Payments followed up', 'safecontracts'), (string) ($summary['followed_up_payments'] ?? 0)); ?>
            </div>
            <p class="description"><?php echo esc_html__('This legacy section represents customer receivable collection history only. Supplier payables are never folded into collection totals.', 'safecontracts'); ?></p>

            <section class="safecontracts-admin-card safecontracts-admin-card--security">
                <h2><?php echo esc_html__('Scoped report boundary', 'safecontracts'); ?></h2>
                <p><?php echo esc_html__('All totals and XLSX sheets are computed server-side from the current authorized scope. AP and AR authorization remain independent, currencies remain separate, and export completion is written through the Safe Contracts audit hook.', 'safecontracts'); ?></p>
            </section>
        </div>
        <?php
    }

    /** @param list<array<string,mixed>> $rows */
    private static function renderFinanceSummary(array $rows): void
    {
        ?>
        <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Financial intelligence', 'safecontracts'); ?></p><h2><?php echo esc_html__('AP / AR by currency', 'safecontracts'); ?></h2></div></div>
        <?php if ($rows === []) : ?><section class="safecontracts-admin-card"><p><?php echo esc_html__('No authorized financial obligations match this report scope.', 'safecontracts'); ?></p></section><?php return; endif; ?>
        <div class="safecontracts-finance-summary-grid">
            <?php foreach ($rows as $row) :
                $direction = (string) ($row['financial_direction'] ?? '');
                $currency = (string) ($row['currency_code'] ?? 'UNSET');
                ?>
                <article class="safecontracts-finance-summary safecontracts-finance-summary--<?php echo esc_attr($direction); ?>">
                    <div class="safecontracts-finance-summary__head">
                        <div><span><?php echo esc_html(self::directionLabel($direction)); ?></span><strong><?php echo esc_html($currency); ?></strong></div>
                        <span class="safecontracts-direction-pill safecontracts-direction-pill--<?php echo esc_attr($direction); ?>"><?php echo esc_html($direction === FinancialDirection::PAYABLE ? __('Cash out', 'safecontracts') : __('Cash in', 'safecontracts')); ?></span>
                    </div>
                    <div class="safecontracts-finance-metrics">
                        <?php self::miniMetric(__('Outstanding', 'safecontracts'), self::money($row['outstanding_total'] ?? 0, $currency)); ?>
                        <?php self::miniMetric($direction === FinancialDirection::PAYABLE ? __('Paid', 'safecontracts') : __('Received', 'safecontracts'), self::money($row['settled_total'] ?? 0, $currency)); ?>
                        <?php self::miniMetric(__('Overdue', 'safecontracts'), self::money($row['overdue_total'] ?? 0, $currency), (float) ($row['overdue_total'] ?? 0) > 0); ?>
                        <?php self::miniMetric(__('Due 7 days', 'safecontracts'), self::money($row['due_7_total'] ?? 0, $currency)); ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /** @param list<array<string,mixed>> $rows */
    private static function renderFinanceAging(array $rows): void
    {
        if ($rows === []) {
            return;
        }
        ?>
        <section class="safecontracts-admin-card safecontracts-table-card" aria-labelledby="safecontracts-report-aging-title">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Outstanding balance age', 'safecontracts'); ?></p><h2 id="safecontracts-report-aging-title"><?php echo esc_html__('Aging report', 'safecontracts'); ?></h2></div></div>
            <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Direction', 'safecontracts'); ?></th><th><?php echo esc_html__('Currency', 'safecontracts'); ?></th><th><?php echo esc_html__('Bucket', 'safecontracts'); ?></th><th><?php echo esc_html__('Items', 'safecontracts'); ?></th><th><?php echo esc_html__('Outstanding', 'safecontracts'); ?></th></tr></thead><tbody>
            <?php foreach ($rows as $row) : ?><tr><td><?php echo esc_html(self::directionLabel((string) ($row['financial_direction'] ?? ''))); ?></td><td><?php echo esc_html((string) ($row['currency_code'] ?? 'UNSET')); ?></td><td><?php echo esc_html(self::statusLabel((string) ($row['aging_bucket'] ?? ''))); ?></td><td><?php echo esc_html((string) (int) ($row['obligation_count'] ?? 0)); ?></td><td><?php echo esc_html(self::money($row['outstanding_total'] ?? 0, (string) ($row['currency_code'] ?? 'UNSET'))); ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </section>
        <?php
    }

    private static function metric(string $label, string $value, bool $alert = false): void
    {
        ?><article class="safecontracts-kpi<?php echo $alert ? ' safecontracts-kpi--alert' : ''; ?>"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($value); ?></strong></article><?php
    }

    private static function miniMetric(string $label, string $value, bool $alert = false): void
    {
        ?><div class="safecontracts-finance-mini<?php echo $alert ? ' safecontracts-finance-mini--alert' : ''; ?>"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($value); ?></strong></div><?php
    }

    private static function money(mixed $value, string $currency = ''): string
    {
        $amount = number_format((float) $value, 2, '.', ',');
        $currency = trim($currency);
        return $currency === '' ? $amount : $currency . ' ' . $amount;
    }

    private static function statusLabel(string $status): string
    {
        return TranslationCatalog::text(ucwords(str_replace(['_', '-'], ' ', $status)));
    }

    private static function directionLabel(string $direction): string
    {
        return $direction === FinancialDirection::PAYABLE
            ? __('Accounts Payable', 'safecontracts')
            : __('Accounts Receivable', 'safecontracts');
    }

    private static function canViewFinance(): bool
    {
        return current_user_can(Capabilities::VIEW_PAYABLES) || current_user_can(Capabilities::VIEW_RECEIVABLES);
    }
}
