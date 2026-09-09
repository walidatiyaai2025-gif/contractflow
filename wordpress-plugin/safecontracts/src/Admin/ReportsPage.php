<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Finance\FinanceOverviewService;
use SafeContracts\Payments\CurrencyCode;
use SafeContracts\Payments\FinancialDirection;
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

        $reportInput = $_GET;
        $counterpartyRef = isset($_GET['counterparty_ref']) && is_scalar($_GET['counterparty_ref'])
            ? sanitize_text_field((string) $_GET['counterparty_ref'])
            : '';
        if ($counterpartyRef !== '') {
            $parsed = AdminLookupOptions::parseCounterpartyRef($counterpartyRef);
            if ($parsed !== null) {
                $reportInput['counterparty_type'] = $parsed['type'];
                $reportInput['counterparty_id'] = $parsed['id'];
            } else {
                $reportInput['counterparty_type'] = '';
                $reportInput['counterparty_id'] = 0;
            }
        } else {
            $reportInput['counterparty_type'] = '';
            $reportInput['counterparty_id'] = 0;
        }

        $filters = DashboardFilters::normalize($reportInput);
        $read = new AdminReadRepository();
        $summary = empty($filters['date_range_error'])
            ? $read->reportSummary($filters)
            : [
                'contract_count' => '0', 'currency_group_count' => '0', 'currency_code' => '',
                'scheduled_total' => '0.0000', 'remaining_total' => '0.0000',
                'overdue_exposure' => '0.0000', 'collected_total' => '0.0000', 'collection_transactions' => '0',
                'collection_ledger_total' => '0.0000', 'followup_events' => '0', 'followed_up_payments' => '0',
            ];
        $customers = $read->customerOptions();
        $contracts = $read->contractOptions($filters['customer_id']);
        $counterparties = AdminLookupOptions::counterparties($read);
        $accountants = AdminLookupOptions::accountants();
        $currencies = AdminLookupOptions::currencies($read, (string) ($filters['currency_code'] ?? ''));
        $canViewFinance = current_user_can(Capabilities::VIEW_FINANCE) || current_user_can(Capabilities::MANAGE_FINANCE);
        $finance = ['summary' => [], 'aging' => []];
        if ($canViewFinance && empty($filters['date_range_error'])) {
            $financeInput = $reportInput;
            $financeInput['due_from'] = $financeInput['due_from'] ?? ($filters['date_from'] ?? null);
            $financeInput['due_to'] = $financeInput['due_to'] ?? ($filters['date_to'] ?? null);
            $finance = (new FinanceOverviewService())->overview($financeInput);
        }
        $legacyCurrency = (string) ($summary['currency_code'] ?? '');
        $legacyMultiCurrency = (int) ($summary['currency_group_count'] ?? 0) > 1;
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Server-side reporting', 'safecontracts'); ?></p><h1><?php echo esc_html__('Reports', 'safecontracts'); ?></h1></div></div>
            <section class="safecontracts-admin-card">
                <?php if (! empty($filters['date_range_error'])) : ?><div class="notice notice-error inline"><p><?php echo esc_html__('Invalid period. Use valid dates and make sure the end date is not earlier than the start date.', 'safecontracts'); ?></p></div><?php endif; ?>
                <form class="safecontracts-filter-bar" method="get">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                    <label><?php echo esc_html__('Customer', 'safecontracts'); ?><select name="customer_id"><option value="0"><?php echo esc_html__('All customers', 'safecontracts'); ?></option><?php foreach ($customers as $customer) : ?><option value="<?php echo esc_attr((string) $customer['id']); ?>" <?php selected($filters['customer_id'], $customer['id']); ?>><?php echo esc_html($customer['name']); ?></option><?php endforeach; ?></select></label>
                    <label><?php echo esc_html__('Contract', 'safecontracts'); ?><select name="contract_id"><option value="0"><?php echo esc_html__('All contracts', 'safecontracts'); ?></option><?php foreach ($contracts as $contract) : ?><option value="<?php echo esc_attr((string) $contract['id']); ?>" <?php selected($filters['contract_id'], $contract['id']); ?>><?php echo esc_html($contract['contract_number']); ?></option><?php endforeach; ?></select></label>
                    <?php if ($canViewFinance) : ?>
                        <label><?php echo esc_html__('Direction', 'safecontracts'); ?><select name="financial_direction"><option value=""><?php echo esc_html__('All AP / AR', 'safecontracts'); ?></option><option value="payable" <?php selected($filters['financial_direction'] ?? '', 'payable'); ?>><?php echo esc_html__('Accounts Payable', 'safecontracts'); ?></option><option value="receivable" <?php selected($filters['financial_direction'] ?? '', 'receivable'); ?>><?php echo esc_html__('Accounts Receivable', 'safecontracts'); ?></option></select></label>
                        <label><?php echo esc_html__('Currency', 'safecontracts'); ?><select name="currency_code"><option value=""><?php echo esc_html__('All currencies', 'safecontracts'); ?></option><?php foreach ($currencies as $currency) : ?><option value="<?php echo esc_attr($currency); ?>" <?php selected((string) ($filters['currency_code'] ?? ''), $currency); ?>><?php echo esc_html($currency); ?></option><?php endforeach; ?></select></label>
                        <label><?php echo esc_html__('Counterparty', 'safecontracts'); ?><select name="counterparty_ref"><option value=""><?php echo esc_html__('All counterparties', 'safecontracts'); ?></option><?php foreach ($counterparties as $counterparty) : ?><option value="<?php echo esc_attr($counterparty['ref']); ?>" <?php selected($counterpartyRef, $counterparty['ref']); ?>><?php echo esc_html($counterparty['label']); ?></option><?php endforeach; ?></select></label>
                    <?php endif; ?>
                    <?php if (current_user_can(Capabilities::VIEW_ALL)) : ?><label><?php echo esc_html__('Responsible accountant', 'safecontracts'); ?><select name="accountant_user_id"><option value="0"><?php echo esc_html__('All responsible accountants', 'safecontracts'); ?></option><?php foreach ($accountants as $accountant) : ?><option value="<?php echo esc_attr((string) $accountant['id']); ?>" <?php selected((int) $filters['accountant_user_id'], $accountant['id']); ?>><?php echo esc_html($accountant['label']); ?></option><?php endforeach; ?></select></label><?php endif; ?>
                    <label><?php echo esc_html__('Status', 'safecontracts'); ?><select name="status"><option value=""><?php echo esc_html__('Any status', 'safecontracts'); ?></option><?php foreach (['active','draft','completed','cancelled','upcoming','due_soon','due','overdue','partially_paid','paid'] as $status) : ?><option value="<?php echo esc_attr($status); ?>" <?php selected($filters['status'], $status); ?>><?php echo esc_html(self::statusLabel($status)); ?></option><?php endforeach; ?></select></label>
                    <?php AdminPeriodFilter::renderFields($filters); ?>
                    <button class="button button-primary" type="submit"><?php echo esc_html__('Run report', 'safecontracts'); ?></button>
                </form>
                <?php if (current_user_can(Capabilities::EXPORT_REPORTS) && empty($filters['date_range_error'])) : ?>
                <form class="safecontracts-export-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::EXPORT_ACTION); ?>">
                    <?php foreach (['customer_id','counterparty_type','counterparty_id','financial_direction','currency_code','contract_id','accountant_user_id','status','date_from','date_to'] as $key) : ?><input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string) ($filters[$key] ?? '')); ?>"><?php endforeach; ?>
                    <?php wp_nonce_field(self::EXPORT_ACTION); ?>
                    <button class="button" type="submit"><?php echo esc_html__('Export current filters to Excel', 'safecontracts'); ?></button>
                    <span class="description"><?php echo esc_html__('XLSX is generated server-side and includes currency-safe finance summary, aging, cash flow and obligation sheets when authorized.', 'safecontracts'); ?></span>
                </form>
                <?php endif; ?>
            </section>

            <?php if ($canViewFinance) : ?>
                <section class="safecontracts-admin-card safecontracts-table-card">
                    <h2><?php echo esc_html__('AP / AR by currency', 'safecontracts'); ?></h2>
                    <p class="description"><?php echo esc_html__('Supplier payables are never folded into collection totals. Every finance total remains grouped by direction and currency.', 'safecontracts'); ?></p>
                    <div class="safecontracts-w2-table-scroll"><table class="widefat striped"><thead><tr><th><?php echo esc_html__('Direction', 'safecontracts'); ?></th><th><?php echo esc_html__('Currency', 'safecontracts'); ?></th><th><?php echo esc_html__('Outstanding', 'safecontracts'); ?></th><th><?php echo esc_html__('Overdue', 'safecontracts'); ?></th><th><?php echo esc_html__('Due today', 'safecontracts'); ?></th><th><?php echo esc_html__('Due 30 days', 'safecontracts'); ?></th></tr></thead><tbody>
                    <?php foreach ((array) ($finance['summary'] ?? []) as $row) : $currency = (string) ($row['currency_code'] ?? CurrencyCode::UNKNOWN); ?><tr><td><?php echo esc_html(self::directionLabel((string) ($row['financial_direction'] ?? ''))); ?></td><td><?php echo esc_html($currency); ?></td><td><?php echo esc_html(self::money($row['outstanding_total'] ?? 0, $currency)); ?></td><td><?php echo esc_html(self::money($row['overdue_total'] ?? 0, $currency)); ?></td><td><?php echo esc_html(self::money($row['due_today_total'] ?? 0, $currency)); ?></td><td><?php echo esc_html(self::money($row['due_30_total'] ?? 0, $currency)); ?></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                </section>
                <section class="safecontracts-admin-card safecontracts-table-card"><h2><?php echo esc_html__('Aging report', 'safecontracts'); ?></h2><div class="safecontracts-w2-table-scroll"><table class="widefat striped"><thead><tr><th><?php echo esc_html__('Direction', 'safecontracts'); ?></th><th><?php echo esc_html__('Currency', 'safecontracts'); ?></th><th><?php echo esc_html__('Bucket', 'safecontracts'); ?></th><th><?php echo esc_html__('Outstanding', 'safecontracts'); ?></th></tr></thead><tbody><?php foreach ((array) ($finance['aging'] ?? []) as $row) : $currency = (string) ($row['currency_code'] ?? CurrencyCode::UNKNOWN); ?><tr><td><?php echo esc_html(self::directionLabel((string) ($row['financial_direction'] ?? ''))); ?></td><td><?php echo esc_html($currency); ?></td><td><?php echo esc_html((string) ($row['aging_bucket'] ?? '')); ?></td><td><?php echo esc_html(self::money($row['outstanding_total'] ?? 0, $currency)); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
            <?php endif; ?>

            <div class="safecontracts-kpi-grid">
                <?php self::metric(__('Contracts', 'safecontracts'), (string) $summary['contract_count']); ?>
                <?php self::metric(__('Scheduled receivables', 'safecontracts'), self::legacyMoney($summary['scheduled_total'] ?? null, $legacyCurrency, $legacyMultiCurrency)); ?>
                <?php self::metric(__('Remaining receivables', 'safecontracts'), self::legacyMoney($summary['remaining_total'] ?? null, $legacyCurrency, $legacyMultiCurrency)); ?>
                <?php self::metric(__('Overdue exposure', 'safecontracts'), self::legacyMoney($summary['overdue_exposure'] ?? null, $legacyCurrency, $legacyMultiCurrency), true); ?>
                <?php self::metric(__('Collection ledger', 'safecontracts'), self::money($summary['collection_ledger_total'])); ?>
                <?php self::metric(__('Collection transactions', 'safecontracts'), (string) $summary['collection_transactions']); ?>
                <?php self::metric(__('Follow-up events', 'safecontracts'), (string) $summary['followup_events']); ?>
                <?php self::metric(__('Payments followed up', 'safecontracts'), (string) $summary['followed_up_payments']); ?>
            </div>
            <?php if ($legacyMultiCurrency) : ?><p class="description"><?php echo esc_html__('Legacy receivable money cards are hidden because more than one currency is in scope. Use AP / AR by currency above.', 'safecontracts'); ?></p><?php endif; ?>
            <section class="safecontracts-admin-card safecontracts-admin-card--security">
                <h2><?php echo esc_html__('Receivable operations history', 'safecontracts'); ?></h2>
                <p><?php echo esc_html__('Legacy collection and follow-up metrics remain customer/receivable operational history. Canonical AP/AR reporting is shown above and exported in dedicated Finance sheets.', 'safecontracts'); ?></p>
            </section>
        </div>
        <?php
    }

    private static function metric(string $label, string $value, bool $alert = false): void
    {
        ?><article class="safecontracts-kpi<?php echo $alert ? ' safecontracts-kpi--alert' : ''; ?>"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($value); ?></strong></article><?php
    }

    private static function legacyMoney(mixed $value, string $currency, bool $multiCurrency): string
    {
        if ($multiCurrency || $value === null || $value === '') {
            return '—';
        }
        return self::money($value, $currency);
    }

    private static function money(mixed $value, string $currency = ''): string
    {
        $amount = number_format((float) $value, 2, '.', ',');
        return $currency === '' ? $amount : $currency . ' ' . $amount;
    }

    private static function directionLabel(string $direction): string
    {
        return $direction === FinancialDirection::PAYABLE ? __('Accounts Payable', 'safecontracts') : __('Accounts Receivable', 'safecontracts');
    }

    private static function statusLabel(string $status): string
    {
        return TranslationCatalog::text(ucwords(str_replace('_', ' ', $status)));
    }
}
