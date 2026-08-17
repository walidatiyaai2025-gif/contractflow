<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use DomainException;
use SafeContracts\Finance\AgingBucket;
use SafeContracts\Finance\FinanceOverviewService;
use SafeContracts\Finance\FinanceReadFilters;
use SafeContracts\Finance\FinancialDirection;
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
            [self::class, 'render']
        );
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::ACCESS) || ! self::canViewFinance()) {
            wp_die(__('You do not have permission to view SafeContracts finance.', 'safecontracts'));
        }

        $filters = FinanceReadFilters::normalize($_GET);
        $overview = [
            'directions' => [],
            'summary' => [],
            'aging' => [],
            'cash_flow' => [],
            'action_center' => [],
            'work_queue_preview' => [],
        ];
        $error = '';
        try {
            $overview = (new FinanceOverviewService())->overview($filters);
        } catch (DomainException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            unset($exception);
            $error = __('SafeContracts could not load the finance workspace. Try again or contact an administrator.', 'safecontracts');
        }

        $authorizedDirections = array_values(array_filter([
            current_user_can(Capabilities::VIEW_PAYABLES) ? FinancialDirection::PAYABLE : null,
            current_user_can(Capabilities::VIEW_RECEIVABLES) ? FinancialDirection::RECEIVABLE : null,
        ]));
        ?>
        <div class="wrap safecontracts-settings safecontracts-finance" dir="auto">
            <header class="safecontracts-finance-hero">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Contracts & financial operations', 'safecontracts'); ?></p>
                    <h1><?php echo esc_html__('Finance', 'safecontracts'); ?></h1>
                    <p><?php echo esc_html__('Accounts Payable and Accounts Receivable stay separated by direction and currency, with server-authorized aging, due dates and accountant work queues.', 'safecontracts'); ?></p>
                </div>
                <div class="safecontracts-finance-legend" aria-label="<?php echo esc_attr__('Financial direction legend', 'safecontracts'); ?>">
                    <?php if (in_array(FinancialDirection::PAYABLE, $authorizedDirections, true)) : ?>
                        <span class="safecontracts-direction-pill safecontracts-direction-pill--payable"><?php echo esc_html__('Payable · Company → Supplier', 'safecontracts'); ?></span>
                    <?php endif; ?>
                    <?php if (in_array(FinancialDirection::RECEIVABLE, $authorizedDirections, true)) : ?>
                        <span class="safecontracts-direction-pill safecontracts-direction-pill--receivable"><?php echo esc_html__('Receivable · Customer → Company', 'safecontracts'); ?></span>
                    <?php endif; ?>
                </div>
            </header>

            <?php if ($error !== '') : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <?php self::renderFilters($filters, $authorizedDirections); ?>

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

    /** @param array<string,mixed> $filters @param list<string> $authorizedDirections */
    private static function renderFilters(array $filters, array $authorizedDirections): void
    {
        ?>
        <section class="safecontracts-admin-card safecontracts-finance-filter-card" aria-labelledby="safecontracts-finance-filters-title">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Work queue scope', 'safecontracts'); ?></p>
                    <h2 id="safecontracts-finance-filters-title"><?php echo esc_html__('Finance filters', 'safecontracts'); ?></h2>
                </div>
                <a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Clear filters', 'safecontracts'); ?></a>
            </div>
            <form class="safecontracts-filter-bar safecontracts-finance-filter-bar" method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                <label><?php echo esc_html__('Direction', 'safecontracts'); ?>
                    <select name="direction">
                        <option value=""><?php echo esc_html__('All permitted directions', 'safecontracts'); ?></option>
                        <?php if (in_array(FinancialDirection::PAYABLE, $authorizedDirections, true)) : ?>
                            <option value="payable" <?php selected($filters['direction'], 'payable'); ?>><?php echo esc_html__('Accounts Payable', 'safecontracts'); ?></option>
                        <?php endif; ?>
                        <?php if (in_array(FinancialDirection::RECEIVABLE, $authorizedDirections, true)) : ?>
                            <option value="receivable" <?php selected($filters['direction'], 'receivable'); ?>><?php echo esc_html__('Accounts Receivable', 'safecontracts'); ?></option>
                        <?php endif; ?>
                    </select>
                </label>
                <label><?php echo esc_html__('Currency', 'safecontracts'); ?>
                    <input name="currency_code" maxlength="5" value="<?php echo esc_attr((string) $filters['currency_code']); ?>" placeholder="KWD / USD / UNSET">
                </label>
                <label><?php echo esc_html__('Counterparty type', 'safecontracts'); ?>
                    <select name="counterparty_type">
                        <option value=""><?php echo esc_html__('Any type', 'safecontracts'); ?></option>
                        <option value="supplier" <?php selected($filters['counterparty_type'], 'supplier'); ?>><?php echo esc_html__('Supplier', 'safecontracts'); ?></option>
                        <option value="customer" <?php selected($filters['counterparty_type'], 'customer'); ?>><?php echo esc_html__('Customer', 'safecontracts'); ?></option>
                    </select>
                </label>
                <label><?php echo esc_html__('Counterparty ID', 'safecontracts'); ?>
                    <input type="number" min="1" name="counterparty_id" value="<?php echo esc_attr($filters['counterparty_id'] > 0 ? (string) $filters['counterparty_id'] : ''); ?>">
                </label>
                <?php if (current_user_can(Capabilities::VIEW_ALL)) : ?>
                    <label><?php echo esc_html__('Responsible accountant ID', 'safecontracts'); ?>
                        <input type="number" min="1" name="accountant_user_id" value="<?php echo esc_attr($filters['accountant_user_id'] > 0 ? (string) $filters['accountant_user_id'] : ''); ?>">
                    </label>
                <?php endif; ?>
                <label><?php echo esc_html__('Financial status', 'safecontracts'); ?>
                    <select name="status">
                        <option value=""><?php echo esc_html__('Any status', 'safecontracts'); ?></option>
                        <?php foreach (PaymentStatus::all() as $status) : ?>
                            <option value="<?php echo esc_attr($status); ?>" <?php selected($filters['status'], $status); ?>><?php echo esc_html(self::statusLabel($status)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><?php echo esc_html__('Aging', 'safecontracts'); ?>
                    <select name="aging_bucket">
                        <option value=""><?php echo esc_html__('Any aging bucket', 'safecontracts'); ?></option>
                        <?php foreach (AgingBucket::all() as $bucket) : ?>
                            <option value="<?php echo esc_attr($bucket); ?>" <?php selected($filters['aging_bucket'], $bucket); ?>><?php echo esc_html(self::agingLabel($bucket)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><?php echo esc_html__('Due from', 'safecontracts'); ?><input type="date" name="due_from" value="<?php echo esc_attr((string) ($filters['due_from'] ?? '')); ?>"></label>
                <label><?php echo esc_html__('Due to', 'safecontracts'); ?><input type="date" name="due_to" value="<?php echo esc_attr((string) ($filters['due_to'] ?? '')); ?>"></label>
                <button class="button button-primary" type="submit"><?php echo esc_html__('Apply finance filters', 'safecontracts'); ?></button>
            </form>
        </section>
        <?php
    }

    /** @param list<array<string,mixed>> $rows */
    private static function renderSummary(array $rows): void
    {
        ?>
        <section aria-labelledby="safecontracts-finance-summary-title">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Financial position', 'safecontracts'); ?></p><h2 id="safecontracts-finance-summary-title"><?php echo esc_html__('AP / AR summary', 'safecontracts'); ?></h2></div></div>
            <?php if ($rows === []) : ?>
                <div class="safecontracts-admin-card safecontracts-empty-state"><strong><?php echo esc_html__('No financial obligations match this scope.', 'safecontracts'); ?></strong><p><?php echo esc_html__('Adjust the filters or create a payment schedule on an authorized contract.', 'safecontracts'); ?></p></div>
            <?php else : ?>
                <div class="safecontracts-finance-summary-grid">
                    <?php foreach ($rows as $row) :
                        $direction = (string) ($row['financial_direction'] ?? '');
                        $currency = (string) ($row['currency_code'] ?? 'UNSET');
                        ?>
                        <article class="safecontracts-finance-summary safecontracts-finance-summary--<?php echo esc_attr($direction); ?>">
                            <div class="safecontracts-finance-summary__head">
                                <div><span><?php echo esc_html($direction === FinancialDirection::PAYABLE ? __('Accounts Payable', 'safecontracts') : __('Accounts Receivable', 'safecontracts')); ?></span><strong><?php echo esc_html($currency); ?></strong></div>
                                <span class="safecontracts-direction-pill safecontracts-direction-pill--<?php echo esc_attr($direction); ?>"><?php echo esc_html($direction === FinancialDirection::PAYABLE ? __('Cash out', 'safecontracts') : __('Cash in', 'safecontracts')); ?></span>
                            </div>
                            <div class="safecontracts-finance-metrics">
                                <?php self::miniMetric(__('Outstanding', 'safecontracts'), self::money($row['outstanding_total'] ?? 0, $currency), true); ?>
                                <?php self::miniMetric($direction === FinancialDirection::PAYABLE ? __('Paid', 'safecontracts') : __('Received', 'safecontracts'), self::money($row['settled_total'] ?? 0, $currency)); ?>
                                <?php self::miniMetric(__('Overdue', 'safecontracts'), self::money($row['overdue_total'] ?? 0, $currency), (float) ($row['overdue_total'] ?? 0) > 0); ?>
                                <?php self::miniMetric(__('Due today', 'safecontracts'), self::money($row['due_today_total'] ?? 0, $currency)); ?>
                                <?php self::miniMetric(__('Due 7 days', 'safecontracts'), self::money($row['due_7_total'] ?? 0, $currency)); ?>
                                <?php self::miniMetric(__('Due 30 days', 'safecontracts'), self::money($row['due_30_total'] ?? 0, $currency)); ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    /** @param list<array<string,mixed>> $items */
    private static function renderActionCenter(array $items): void
    {
        ?>
        <section class="safecontracts-admin-card safecontracts-finance-panel" aria-labelledby="safecontracts-action-center-title">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Needs attention', 'safecontracts'); ?></p><h2 id="safecontracts-action-center-title"><?php echo esc_html__('Action Center', 'safecontracts'); ?></h2></div></div>
            <?php if ($items === []) : ?><p class="safecontracts-empty-copy"><?php echo esc_html__('No overdue or near-term financial actions in the current scope.', 'safecontracts'); ?></p><?php else : ?>
                <div class="safecontracts-action-list">
                    <?php foreach ($items as $item) :
                        $direction = (string) ($item['direction'] ?? '');
                        $currency = (string) ($item['currency_code'] ?? 'UNSET');
                        $query = ['page' => self::SLUG, 'direction' => $direction, 'currency_code' => $currency];
                        if (($item['kind'] ?? '') === 'overdue') {
                            $query['status'] = 'overdue';
                        }
                        ?>
                        <a class="safecontracts-action-item safecontracts-action-item--<?php echo esc_attr((string) ($item['priority'] ?? 'normal')); ?>" href="<?php echo esc_url(add_query_arg($query, admin_url('admin.php'))); ?>">
                            <span><strong><?php echo esc_html(self::actionLabel((string) ($item['kind'] ?? ''), $direction)); ?></strong><small><?php echo esc_html($currency . ' · ' . (int) ($item['count'] ?? 0) . ' ' . __('items', 'safecontracts')); ?></small></span>
                            <b><?php echo esc_html(self::money($item['amount'] ?? 0, $currency)); ?></b>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    /** @param list<array<string,mixed>> $rows */
    private static function renderAging(array $rows): void
    {
        ?>
        <section class="safecontracts-admin-card safecontracts-finance-panel safecontracts-table-card" aria-labelledby="safecontracts-aging-title">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Outstanding balance age', 'safecontracts'); ?></p><h2 id="safecontracts-aging-title"><?php echo esc_html__('Aging', 'safecontracts'); ?></h2></div></div>
            <?php if ($rows === []) : ?><p class="safecontracts-empty-copy"><?php echo esc_html__('No outstanding balances to age in this scope.', 'safecontracts'); ?></p><?php else : ?>
                <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Direction', 'safecontracts'); ?></th><th><?php echo esc_html__('Currency', 'safecontracts'); ?></th><th><?php echo esc_html__('Bucket', 'safecontracts'); ?></th><th><?php echo esc_html__('Items', 'safecontracts'); ?></th><th><?php echo esc_html__('Outstanding', 'safecontracts'); ?></th></tr></thead><tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr><td><?php echo esc_html(self::directionLabel((string) ($row['financial_direction'] ?? ''))); ?></td><td><?php echo esc_html((string) ($row['currency_code'] ?? 'UNSET')); ?></td><td><?php echo esc_html(self::agingLabel((string) ($row['aging_bucket'] ?? ''))); ?></td><td><?php echo esc_html((string) (int) ($row['obligation_count'] ?? 0)); ?></td><td><?php echo esc_html(self::money($row['outstanding_total'] ?? 0, (string) ($row['currency_code'] ?? 'UNSET'))); ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        </section>
        <?php
    }

    /** @param list<array<string,mixed>> $rows */
    private static function renderCashFlow(array $rows): void
    {
        ?>
        <section class="safecontracts-admin-card safecontracts-table-card" aria-labelledby="safecontracts-cashflow-title">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Next 90 days', 'safecontracts'); ?></p><h2 id="safecontracts-cashflow-title"><?php echo esc_html__('Expected cash flow', 'safecontracts'); ?></h2></div></div>
            <?php if ($rows === []) : ?><p class="safecontracts-empty-copy"><?php echo esc_html__('No future obligations are scheduled in the next 90 days for this scope.', 'safecontracts'); ?></p><?php else : ?>
                <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Due date', 'safecontracts'); ?></th><th><?php echo esc_html__('Flow', 'safecontracts'); ?></th><th><?php echo esc_html__('Currency', 'safecontracts'); ?></th><th><?php echo esc_html__('Items', 'safecontracts'); ?></th><th><?php echo esc_html__('Expected', 'safecontracts'); ?></th></tr></thead><tbody>
                <?php foreach (array_slice($rows, 0, 30) as $row) : ?>
                    <tr><td><?php echo esc_html((string) ($row['due_date'] ?? '')); ?></td><td><?php echo esc_html(($row['cash_flow_kind'] ?? '') === 'outflow' ? __('Cash outflow', 'safecontracts') : __('Cash inflow', 'safecontracts')); ?></td><td><?php echo esc_html((string) ($row['currency_code'] ?? 'UNSET')); ?></td><td><?php echo esc_html((string) (int) ($row['obligation_count'] ?? 0)); ?></td><td><?php echo esc_html(self::money($row['expected_amount'] ?? 0, (string) ($row['currency_code'] ?? 'UNSET'))); ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
                <?php if (count($rows) > 30) : ?><p class="description"><?php echo esc_html__('Showing the first 30 date/direction/currency forecast rows. Narrow the filters for a focused view.', 'safecontracts'); ?></p><?php endif; ?>
            <?php endif; ?>
        </section>
        <?php
    }

    /** @param list<array<string,mixed>> $rows */
    private static function renderWorkQueue(array $rows): void
    {
        ?>
        <section class="safecontracts-admin-card safecontracts-table-card" aria-labelledby="safecontracts-finance-queue-title">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Operational queue', 'safecontracts'); ?></p><h2 id="safecontracts-finance-queue-title"><?php echo esc_html__('Financial obligations', 'safecontracts'); ?></h2></div></div>
            <?php if ($rows === []) : ?><p class="safecontracts-empty-copy"><?php echo esc_html__('No financial obligations match the current filters.', 'safecontracts'); ?></p><?php else : ?>
                <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Due', 'safecontracts'); ?></th><th><?php echo esc_html__('Direction', 'safecontracts'); ?></th><th><?php echo esc_html__('Counterparty', 'safecontracts'); ?></th><th><?php echo esc_html__('Contract', 'safecontracts'); ?></th><th><?php echo esc_html__('Status', 'safecontracts'); ?></th><th><?php echo esc_html__('Original', 'safecontracts'); ?></th><th><?php echo esc_html__('Settled', 'safecontracts'); ?></th><th><?php echo esc_html__('Remaining', 'safecontracts'); ?></th></tr></thead><tbody>
                <?php foreach ($rows as $row) :
                    $currency = (string) ($row['currency_code'] ?? 'UNSET');
                    $direction = (string) ($row['financial_direction'] ?? '');
                    ?>
                    <tr>
                        <td><?php echo esc_html((string) ($row['due_date'] ?? '')); ?><br><small><?php echo esc_html(self::agingLabel((string) ($row['aging_bucket'] ?? ''))); ?></small></td>
                        <td><span class="safecontracts-direction-pill safecontracts-direction-pill--<?php echo esc_attr($direction); ?>"><?php echo esc_html(self::directionLabel($direction)); ?></span></td>
                        <td><strong><?php echo esc_html((string) ($row['counterparty_name'] ?? '—')); ?></strong><br><small><?php echo esc_html(ucfirst((string) ($row['counterparty_type'] ?? '')) . ' #' . (int) ($row['counterparty_id'] ?? 0)); ?></small></td>
                        <td><?php echo esc_html((string) ($row['contract_number'] ?? '')); ?></td>
                        <td><?php echo esc_html(self::statusLabel((string) ($row['status'] ?? ''))); ?></td>
                        <td><?php echo esc_html(self::money($row['original_amount'] ?? 0, $currency)); ?></td>
                        <td><?php echo esc_html(self::money($row['settled_amount'] ?? 0, $currency)); ?></td>
                        <td><strong><?php echo esc_html(self::money($row['remaining_amount'] ?? 0, $currency)); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        </section>
        <?php
    }

    private static function miniMetric(string $label, string $value, bool $important = false): void
    {
        ?><div class="safecontracts-finance-mini-metric<?php echo $important ? ' safecontracts-finance-mini-metric--important' : ''; ?>"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($value); ?></strong></div><?php
    }

    private static function money(mixed $value, string $currency): string
    {
        $amount = number_format((float) $value, 2, '.', ',');
        $currency = trim($currency);
        return $currency === '' || $currency === 'UNSET' ? $amount . ' ' . __('(currency unset)', 'safecontracts') : $amount . ' ' . $currency;
    }

    private static function directionLabel(string $direction): string
    {
        return $direction === FinancialDirection::PAYABLE ? __('Payable', 'safecontracts') : __('Receivable', 'safecontracts');
    }

    private static function agingLabel(string $bucket): string
    {
        return match ($bucket) {
            AgingBucket::CURRENT => __('Current', 'safecontracts'),
            AgingBucket::DAYS_1_30 => __('1–30 days', 'safecontracts'),
            AgingBucket::DAYS_31_60 => __('31–60 days', 'safecontracts'),
            AgingBucket::DAYS_61_90 => __('61–90 days', 'safecontracts'),
            AgingBucket::DAYS_90_PLUS => __('90+ days', 'safecontracts'),
            default => __('Not aged', 'safecontracts'),
        };
    }

    private static function actionLabel(string $kind, string $direction): string
    {
        $noun = $direction === FinancialDirection::PAYABLE ? __('payables', 'safecontracts') : __('receivables', 'safecontracts');
        return match ($kind) {
            'overdue' => sprintf(__('Overdue %s', 'safecontracts'), $noun),
            'due_today' => sprintf(__('%s due today', 'safecontracts'), ucfirst($noun)),
            'due_7_days' => sprintf(__('%s due within 7 days', 'safecontracts'), ucfirst($noun)),
            default => __('Financial action', 'safecontracts'),
        };
    }

    private static function statusLabel(string $status): string
    {
        return TranslationCatalog::text(ucwords(str_replace('_', ' ', $status)));
    }

    private static function canViewFinance(): bool
    {
        return current_user_can(Capabilities::VIEW_PAYABLES) || current_user_can(Capabilities::VIEW_RECEIVABLES);
    }
}
