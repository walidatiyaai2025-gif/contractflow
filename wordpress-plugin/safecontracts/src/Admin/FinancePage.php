<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use DomainException;
use SafeContracts\Finance\AgingBucket;
use SafeContracts\Finance\FinanceOverviewService;
use SafeContracts\Finance\FinanceReadAccess;
use SafeContracts\Finance\FinanceReadFilters;
use SafeContracts\Payments\CurrencyCode;
use SafeContracts\Payments\FinancialDirection;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Translations\TranslationCatalog;
use Throwable;

final class FinancePage
{
    public const SLUG = 'safecontracts-finance';

    public static function register(): void
    {
        if (! self::canViewFinance()) {
            return;
        }
        add_submenu_page(
            AdminShell::SLUG,
            __('Finance', 'safecontracts'),
            __('Finance', 'safecontracts'),
            Capabilities::ACCESS,
            self::SLUG,
            [self::class, 'render'],
            18
        );
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::ACCESS) || ! self::canViewFinance()) {
            wp_die(__('You do not have permission to view SafeContracts finance.', 'safecontracts'));
        }

        $financeInput = $_GET;
        $counterpartyRef = isset($_GET['counterparty_ref']) && is_scalar($_GET['counterparty_ref'])
            ? sanitize_text_field((string) $_GET['counterparty_ref'])
            : '';
        if ($counterpartyRef !== '') {
            $parsed = AdminLookupOptions::parseCounterpartyRef($counterpartyRef);
            if ($parsed !== null) {
                $financeInput['counterparty_type'] = $parsed['type'];
                $financeInput['counterparty_id'] = $parsed['id'];
            } else {
                $financeInput['counterparty_type'] = '';
                $financeInput['counterparty_id'] = 0;
            }
        } else {
            $financeInput['counterparty_type'] = '';
            $financeInput['counterparty_id'] = 0;
        }

        $filters = FinanceReadFilters::normalize($financeInput);
        $overview = [
            'directions' => [], 'summary' => [], 'aging' => [], 'cash_flow' => [],
            'action_center' => [], 'work_queue_preview' => [],
        ];
        $error = '';
        try {
            $overview = (new FinanceOverviewService())->overview($filters);
        } catch (DomainException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            unset($exception);
            $error = __('SafeContracts could not load the finance workspace.', 'safecontracts');
        }

        $read = new AdminReadRepository();
        $directions = FinanceReadAccess::authorizedDirections();
        $counterparties = AdminLookupOptions::counterparties($read);
        $accountants = AdminLookupOptions::accountants();
        $currencies = AdminLookupOptions::currencies($read, (string) ($filters['currency_code'] ?? ''));
        ?>
        <div class="wrap safecontracts-settings safecontracts-finance" dir="auto">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Financial operations', 'safecontracts'); ?></p>
                    <h1><?php echo esc_html__('Finance', 'safecontracts'); ?></h1>
                    <p><?php echo esc_html__('Accounts Payable and Accounts Receivable stay separated by direction and currency. No cross-currency grand total is produced.', 'safecontracts'); ?></p>
                </div>
            </div>

            <?php if ($error !== '') : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <?php self::renderFilters($filters, $directions, $counterparties, $accountants, $currencies, $counterpartyRef); ?>
            <?php if ($error === '') : ?>
                <?php self::renderSummary((array) ($overview['summary'] ?? [])); ?>
                <div class="safecontracts-finance-layout">
                    <?php self::renderActionCenter((array) ($overview['action_center'] ?? [])); ?>
                    <?php self::renderAging((array) ($overview['aging'] ?? [])); ?>
                </div>
                <?php self::renderCashFlow((array) ($overview['cash_flow'] ?? [])); ?>
                <?php self::renderWorkQueue((array) ($overview['work_queue_preview'] ?? [])); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * @param array<string,mixed> $filters
     * @param list<string> $directions
     * @param list<array{ref:string,type:string,id:int,label:string}> $counterparties
     * @param list<array{id:int,label:string}> $accountants
     * @param list<string> $currencies
     */
    private static function renderFilters(
        array $filters,
        array $directions,
        array $counterparties,
        array $accountants,
        array $currencies,
        string $counterpartyRef
    ): void {
        ?>
        <section class="safecontracts-admin-card" aria-labelledby="safecontracts-finance-filters-title">
            <div class="safecontracts-section-heading">
                <div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Work queue scope', 'safecontracts'); ?></p><h2 id="safecontracts-finance-filters-title"><?php echo esc_html__('Finance filters', 'safecontracts'); ?></h2></div>
                <a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Clear filters', 'safecontracts'); ?></a>
            </div>
            <form class="safecontracts-filter-bar" method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                <label><?php echo esc_html__('Direction', 'safecontracts'); ?>
                    <select name="direction">
                        <option value=""><?php echo esc_html__('All AP / AR', 'safecontracts'); ?></option>
                        <?php if (in_array(FinancialDirection::PAYABLE, $directions, true)) : ?><option value="payable" <?php selected($filters['direction'], 'payable'); ?>><?php echo esc_html__('Accounts Payable', 'safecontracts'); ?></option><?php endif; ?>
                        <?php if (in_array(FinancialDirection::RECEIVABLE, $directions, true)) : ?><option value="receivable" <?php selected($filters['direction'], 'receivable'); ?>><?php echo esc_html__('Accounts Receivable', 'safecontracts'); ?></option><?php endif; ?>
                    </select>
                </label>
                <label><?php echo esc_html__('Currency', 'safecontracts'); ?>
                    <select name="currency_code"><option value=""><?php echo esc_html__('All currencies', 'safecontracts'); ?></option><?php foreach ($currencies as $currency) : ?><option value="<?php echo esc_attr($currency); ?>" <?php selected((string) $filters['currency_code'], $currency); ?>><?php echo esc_html($currency); ?></option><?php endforeach; ?></select>
                </label>
                <label><?php echo esc_html__('Counterparty', 'safecontracts'); ?>
                    <select name="counterparty_ref"><option value=""><?php echo esc_html__('All counterparties', 'safecontracts'); ?></option><?php foreach ($counterparties as $counterparty) : ?><option value="<?php echo esc_attr($counterparty['ref']); ?>" <?php selected($counterpartyRef, $counterparty['ref']); ?>><?php echo esc_html($counterparty['label']); ?></option><?php endforeach; ?></select>
                </label>
                <?php if (current_user_can(Capabilities::VIEW_ALL)) : ?>
                    <label><?php echo esc_html__('Responsible accountant', 'safecontracts'); ?>
                        <select name="accountant_user_id"><option value="0"><?php echo esc_html__('All responsible accountants', 'safecontracts'); ?></option><?php foreach ($accountants as $accountant) : ?><option value="<?php echo esc_attr((string) $accountant['id']); ?>" <?php selected((int) $filters['accountant_user_id'], $accountant['id']); ?>><?php echo esc_html($accountant['label']); ?></option><?php endforeach; ?></select>
                    </label>
                <?php endif; ?>
                <label><?php echo esc_html__('Status', 'safecontracts'); ?>
                    <select name="status"><option value=""><?php echo esc_html__('Any status', 'safecontracts'); ?></option><?php foreach (PaymentStatus::all() as $status) : ?><option value="<?php echo esc_attr($status); ?>" <?php selected($filters['status'], $status); ?>><?php echo esc_html(self::statusLabel($status)); ?></option><?php endforeach; ?></select>
                </label>
                <label><?php echo esc_html__('Aging', 'safecontracts'); ?>
                    <select name="aging_bucket"><option value=""><?php echo esc_html__('Any aging bucket', 'safecontracts'); ?></option><?php foreach (AgingBucket::all() as $bucket) : ?><option value="<?php echo esc_attr($bucket); ?>" <?php selected($filters['aging_bucket'], $bucket); ?>><?php echo esc_html(self::agingLabel($bucket)); ?></option><?php endforeach; ?></select>
                </label>
                <label><?php echo esc_html__('Due from', 'safecontracts'); ?><input type="date" name="due_from" value="<?php echo esc_attr((string) ($filters['due_from'] ?? '')); ?>"></label>
                <label><?php echo esc_html__('Due to', 'safecontracts'); ?><input type="date" name="due_to" value="<?php echo esc_attr((string) ($filters['due_to'] ?? '')); ?>"></label>
                <button class="button button-primary" type="submit"><?php echo esc_html__('Apply', 'safecontracts'); ?></button>
            </form>
        </section>
        <?php
    }

    /** @param list<array<string,mixed>> $rows */
    private static function renderSummary(array $rows): void
    {
        ?>
        <section aria-labelledby="safecontracts-finance-summary-title">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Financial position', 'safecontracts'); ?></p><h2 id="safecontracts-finance-summary-title"><?php echo esc_html__('AP / AR by currency', 'safecontracts'); ?></h2></div></div>
            <div class="safecontracts-kpi-grid">
            <?php if ($rows === []) : ?><article class="safecontracts-admin-card"><strong><?php echo esc_html__('No obligations match this scope.', 'safecontracts'); ?></strong></article><?php endif; ?>
            <?php foreach ($rows as $row) : $direction = (string) ($row['financial_direction'] ?? ''); $currency = (string) ($row['currency_code'] ?? CurrencyCode::UNKNOWN); ?>
                <article class="safecontracts-kpi safecontracts-finance-summary--<?php echo esc_attr($direction); ?>">
                    <span><?php echo esc_html(self::directionLabel($direction) . ' · ' . $currency); ?></span>
                    <strong><?php echo esc_html(self::money($row['outstanding_total'] ?? 0, $currency)); ?></strong>
                    <small><?php echo esc_html(sprintf(__('Overdue %1$s · Due today %2$s · 7d %3$s · 30d %4$s', 'safecontracts'), self::money($row['overdue_total'] ?? 0, $currency), self::money($row['due_today_total'] ?? 0, $currency), self::money($row['due_7_total'] ?? 0, $currency), self::money($row['due_30_total'] ?? 0, $currency))); ?></small>
                </article>
            <?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    /** @param list<array<string,mixed>> $items */
    private static function renderActionCenter(array $items): void
    {
        ?><section class="safecontracts-admin-card safecontracts-finance-panel"><h2><?php echo esc_html__('Action Center', 'safecontracts'); ?></h2><?php if ($items === []) : ?><p><?php echo esc_html__('No overdue or near-term finance actions.', 'safecontracts'); ?></p><?php else : ?><div class="safecontracts-action-list"><?php foreach ($items as $item) : ?><div class="safecontracts-action-item"><span><strong><?php echo esc_html(self::actionLabel((string) ($item['kind'] ?? ''))); ?></strong><small><?php echo esc_html(self::directionLabel((string) ($item['direction'] ?? '')) . ' · ' . (string) ($item['currency_code'] ?? CurrencyCode::UNKNOWN)); ?></small></span><b><?php echo esc_html(self::money($item['amount'] ?? 0, (string) ($item['currency_code'] ?? CurrencyCode::UNKNOWN))); ?></b></div><?php endforeach; ?></div><?php endif; ?></section><?php
    }

    /** @param list<array<string,mixed>> $rows */
    private static function renderAging(array $rows): void
    {
        ?><section class="safecontracts-admin-card safecontracts-table-card"><h2><?php echo esc_html__('Aging', 'safecontracts'); ?></h2><div class="safecontracts-w2-table-scroll"><table class="widefat striped"><thead><tr><th><?php echo esc_html__('Direction', 'safecontracts'); ?></th><th><?php echo esc_html__('Currency', 'safecontracts'); ?></th><th><?php echo esc_html__('Bucket', 'safecontracts'); ?></th><th><?php echo esc_html__('Items', 'safecontracts'); ?></th><th><?php echo esc_html__('Outstanding', 'safecontracts'); ?></th></tr></thead><tbody><?php foreach ($rows as $row) : $currency = (string) ($row['currency_code'] ?? CurrencyCode::UNKNOWN); ?><tr><td><?php echo esc_html(self::directionLabel((string) ($row['financial_direction'] ?? ''))); ?></td><td><?php echo esc_html($currency); ?></td><td><?php echo esc_html(self::agingLabel((string) ($row['aging_bucket'] ?? ''))); ?></td><td><?php echo esc_html((string) (int) ($row['obligation_count'] ?? 0)); ?></td><td><?php echo esc_html(self::money($row['outstanding_total'] ?? 0, $currency)); ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php
    }

    /** @param list<array<string,mixed>> $rows */
    private static function renderCashFlow(array $rows): void
    {
        ?><section class="safecontracts-admin-card safecontracts-table-card"><h2><?php echo esc_html__('Expected cash flow · 90 days', 'safecontracts'); ?></h2><p class="description"><?php echo esc_html__('Receivables are inflow; payables are outflow. Values remain separated by currency.', 'safecontracts'); ?></p><div class="safecontracts-w2-table-scroll"><table class="widefat striped"><thead><tr><th><?php echo esc_html__('Due date', 'safecontracts'); ?></th><th><?php echo esc_html__('Flow', 'safecontracts'); ?></th><th><?php echo esc_html__('Currency', 'safecontracts'); ?></th><th><?php echo esc_html__('Amount', 'safecontracts'); ?></th></tr></thead><tbody><?php foreach ($rows as $row) : $currency = (string) ($row['currency_code'] ?? CurrencyCode::UNKNOWN); ?><tr><td><?php echo esc_html((string) ($row['due_date'] ?? '')); ?></td><td><?php echo esc_html((string) ($row['cash_flow_kind'] ?? '')); ?></td><td><?php echo esc_html($currency); ?></td><td><?php echo esc_html(self::money($row['expected_amount'] ?? 0, $currency)); ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php
    }

    /** @param list<array<string,mixed>> $rows */
    private static function renderWorkQueue(array $rows): void
    {
        ?><section class="safecontracts-admin-card safecontracts-table-card"><h2><?php echo esc_html__('Finance work queue', 'safecontracts'); ?></h2><div class="safecontracts-w2-table-scroll"><table class="widefat striped"><thead><tr><th><?php echo esc_html__('Due', 'safecontracts'); ?></th><th><?php echo esc_html__('Counterparty', 'safecontracts'); ?></th><th><?php echo esc_html__('Contract', 'safecontracts'); ?></th><th><?php echo esc_html__('Direction', 'safecontracts'); ?></th><th><?php echo esc_html__('Currency', 'safecontracts'); ?></th><th><?php echo esc_html__('Remaining', 'safecontracts'); ?></th><th><?php echo esc_html__('Aging', 'safecontracts'); ?></th></tr></thead><tbody><?php foreach ($rows as $row) : $currency = (string) ($row['currency_code'] ?? CurrencyCode::UNKNOWN); ?><tr><td><?php echo esc_html((string) ($row['due_date'] ?? '')); ?></td><td><strong><?php echo esc_html((string) ($row['counterparty_name'] ?? '')); ?></strong><br><small><?php echo esc_html(self::counterpartyTypeLabel((string) ($row['counterparty_type'] ?? ''))); ?></small></td><td><?php echo esc_html((string) ($row['contract_number'] ?? '')); ?></td><td><?php echo esc_html(self::directionLabel((string) ($row['financial_direction'] ?? ''))); ?></td><td><?php echo esc_html($currency); ?></td><td><?php echo esc_html(self::money($row['remaining_amount'] ?? 0, $currency)); ?></td><td><?php echo esc_html(self::agingLabel((string) ($row['aging_bucket'] ?? ''))); ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php
    }

    private static function canViewFinance(): bool
    {
        return current_user_can(Capabilities::VIEW_FINANCE) || current_user_can(Capabilities::MANAGE_FINANCE);
    }

    private static function directionLabel(string $direction): string
    {
        return $direction === FinancialDirection::PAYABLE ? __('Accounts Payable', 'safecontracts') : __('Accounts Receivable', 'safecontracts');
    }

    private static function counterpartyTypeLabel(string $type): string
    {
        return $type === 'supplier' ? __('Supplier', 'safecontracts') : __('Customer', 'safecontracts');
    }

    private static function agingLabel(string $bucket): string
    {
        return match ($bucket) {
            AgingBucket::CURRENT => __('Current', 'safecontracts'),
            AgingBucket::DAYS_1_30 => __('1–30 days', 'safecontracts'),
            AgingBucket::DAYS_31_60 => __('31–60 days', 'safecontracts'),
            AgingBucket::DAYS_61_90 => __('61–90 days', 'safecontracts'),
            AgingBucket::DAYS_90_PLUS => __('90+ days', 'safecontracts'),
            default => $bucket,
        };
    }

    private static function actionLabel(string $kind): string
    {
        return match ($kind) {
            'overdue' => __('Overdue', 'safecontracts'),
            'due_today' => __('Due today', 'safecontracts'),
            'due_7_days' => __('Due in 7 days', 'safecontracts'),
            'due_30_days' => __('Due in 30 days', 'safecontracts'),
            default => $kind,
        };
    }

    private static function statusLabel(string $status): string
    {
        return TranslationCatalog::text(ucwords(str_replace('_', ' ', $status)));
    }

    private static function money(mixed $value, string $currency): string
    {
        return $currency . ' ' . number_format((float) $value, 2, '.', ',');
    }
}
