<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Payments\FinancialDirection;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Support\MoneyFormatter;
use Throwable;

/**
 * Compact premium dashboard requested for Alkenzy ADV 0.3.2.
 *
 * This is additive: DashboardV2Page remains available in the codebase. All
 * accounting values are sourced from server-side read models and currencies
 * are never summed together.
 */
final class DashboardV3Page
{
    public static function renderContent(): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            wp_die(__('You do not have permission to access SafeContracts.', 'safecontracts'));
        }
        if (! current_user_can(Capabilities::VIEW_ALL) && ! current_user_can(Capabilities::VIEW_ASSIGNED)) {
            echo '<section class="safecontracts-admin-card safecontracts-admin-card--security"><h2>' . esc_html__('Server-side authorization', 'safecontracts') . '</h2></section>';
            return;
        }

        $filters = DashboardFilters::normalize($_GET);
        $read = new AdminReadRepository();
        $contractFilters = $filters;
        if (! in_array((string) $contractFilters['status'], ['draft', 'active', 'completed', 'cancelled'], true)) {
            $contractFilters['status'] = '';
        }
        $contracts = array_values(array_filter(
            $read->contracts($contractFilters),
            static fn (array $row): bool => empty($row['is_archived'])
        ));

        $paymentFilters = $filters;
        if (in_array((string) $paymentFilters['status'], ['draft', 'active', 'completed', 'cancelled'], true)) {
            $paymentFilters['status'] = '';
        }
        $payments = array_values(array_filter(
            $read->payments($paymentFilters),
            static fn (array $row): bool => empty($row['is_archived'])
        ));
        $settlements = [];
        try {
            $settlements = (new AdminFinancialSettlementSummary())->totals($filters);
        } catch (Throwable $error) {
            unset($error);
        }

        $totals = self::totals($contracts, $payments, $settlements);
        $statusCounts = self::statusCounts($contracts);
        $receivable = array_values(array_filter($contracts, static fn (array $row): bool => (string) ($row['financial_direction'] ?? '') === FinancialDirection::RECEIVABLE));
        $payable = array_values(array_filter($contracts, static fn (array $row): bool => (string) ($row['financial_direction'] ?? '') === FinancialDirection::PAYABLE));
        ?>
        <section class="safecontracts-dashboard-v3" dir="auto">
            <div class="safecontracts-dashboard-v3__topline">
                <div>
                    <span class="safecontracts-dashboard-v3__eyebrow"><?php echo esc_html(self::t('Executive dashboard', 'لوحة المتابعة التنفيذية')); ?></span>
                    <h1><?php echo esc_html(self::t('Dashboard', 'لوحة التحكم')); ?></h1>
                </div>
                <div class="safecontracts-dashboard-v3__total">
                    <span><?php echo esc_html(self::t('All contracts', 'إجمالي العقود')); ?></span>
                    <strong><?php echo esc_html((string) count($contracts)); ?></strong>
                </div>
            </div>

            <?php self::periodFilter($filters); ?>

            <div class="safecontracts-dashboard-v3__three">
                <?php self::directionPanel(FinancialDirection::RECEIVABLE, $receivable, $totals); ?>
                <?php self::directionPanel(FinancialDirection::PAYABLE, $payable, $totals); ?>
                <?php self::accountingPanel($totals); ?>
            </div>

            <section class="safecontracts-dashboard-v3__chart" aria-labelledby="safecontracts-contract-status-chart-title">
                <div class="safecontracts-dashboard-v3__chart-heading">
                    <div>
                        <span class="safecontracts-dashboard-v3__eyebrow"><?php echo esc_html(self::t('Real data', 'بيانات فعلية')); ?></span>
                        <h2 id="safecontracts-contract-status-chart-title"><?php echo esc_html(self::t('Contracts by status', 'العقود حسب الحالة')); ?></h2>
                    </div>
                    <small><?php echo esc_html(self::t('Counts follow the selected year/month scope.', 'الأعداد تتبع فلتر السنة والشهر المحدد.')); ?></small>
                </div>
                <?php self::statusChart($statusCounts); ?>
            </section>

            <?php self::quickActions(); ?>
        </section>
        <?php
    }

    /** @param array<string,mixed> $filters */
    private static function periodFilter(array $filters): void
    {
        $years = [];
        try {
            $years = AdminYearOptions::forCurrentUser();
        } catch (Throwable $error) {
            unset($error);
        }
        $selectedYear = (int) ($filters['year'] ?? 0);
        $selectedMonth = (int) ($filters['month'] ?? 0);
        if ($selectedYear > 0 && ! in_array($selectedYear, $years, true)) {
            $years[] = $selectedYear;
            rsort($years, SORT_NUMERIC);
        }
        $months = [
            1 => self::t('January', 'يناير'), 2 => self::t('February', 'فبراير'),
            3 => self::t('March', 'مارس'), 4 => self::t('April', 'أبريل'),
            5 => self::t('May', 'مايو'), 6 => self::t('June', 'يونيو'),
            7 => self::t('July', 'يوليو'), 8 => self::t('August', 'أغسطس'),
            9 => self::t('September', 'سبتمبر'), 10 => self::t('October', 'أكتوبر'),
            11 => self::t('November', 'نوفمبر'), 12 => self::t('December', 'ديسمبر'),
        ];
        ?>
        <form class="safecontracts-dashboard-v3__filters" method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr(AdminShell::SLUG); ?>">
            <label><?php echo esc_html(self::t('Year', 'السنة')); ?>
                <select name="year">
                    <option value="0"><?php echo esc_html(self::t('All years', 'كل السنوات')); ?></option>
                    <?php foreach ($years as $year) : ?><option value="<?php echo esc_attr((string) $year); ?>" <?php selected($selectedYear, $year); ?>><?php echo esc_html((string) $year); ?></option><?php endforeach; ?>
                </select>
            </label>
            <label><?php echo esc_html(self::t('Month', 'الشهر')); ?>
                <select name="month">
                    <option value="0"><?php echo esc_html(self::t('All months', 'كل الشهور')); ?></option>
                    <?php foreach ($months as $month => $label) : ?><option value="<?php echo esc_attr((string) $month); ?>" <?php selected($selectedMonth, $month); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                </select>
            </label>
            <button class="button button-primary" type="submit"><?php echo esc_html(self::t('Apply', 'تطبيق')); ?></button>
            <a class="button" href="<?php echo esc_url(add_query_arg(['page' => AdminShell::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html(self::t('Clear', 'مسح')); ?></a>
            <?php if ($selectedMonth > 0 && $selectedYear === 0) : ?><small><?php echo esc_html(self::t('Month-only mode uses the current calendar year.', 'عند اختيار الشهر فقط يتم استخدام السنة الميلادية الحالية.')); ?></small><?php endif; ?>
        </form>
        <?php
    }

    /** @param list<array<string,mixed>> $contracts @param array<string,array<string,array{contracts:int,base:string,scheduled:string,settled:string,outstanding:string}>> $totals */
    private static function directionPanel(string $direction, array $contracts, array $totals): void
    {
        $receivable = $direction === FinancialDirection::RECEIVABLE;
        $title = $receivable ? self::t('Receivable contracts', 'العقود المستحقة لنا') : self::t('Payable contracts', 'العقود المستحقة علينا');
        $icon = $receivable ? '↙' : '↗';
        $class = $receivable ? 'receivable' : 'payable';
        ?>
        <article class="safecontracts-dashboard-v3__panel safecontracts-dashboard-v3__panel--<?php echo esc_attr($class); ?>">
            <div class="safecontracts-dashboard-v3__panel-title"><span><?php echo esc_html($icon); ?></span><h2><?php echo esc_html($title); ?></h2></div>
            <strong class="safecontracts-dashboard-v3__count"><?php echo esc_html((string) count($contracts)); ?></strong>
            <div class="safecontracts-dashboard-v3__money-lines">
                <?php foreach ($totals as $currency => $directions) : $row = $directions[$direction] ?? null; if ($row === null) { continue; } ?>
                    <div><span><?php echo esc_html($currency); ?></span><b><?php echo esc_html(MoneyFormatter::format($row['outstanding'], $currency)); ?></b><small><?php echo esc_html(self::t('outstanding', 'متبقي')); ?></small></div>
                <?php endforeach; ?>
                <?php if ($totals === []) : ?><div><b>0</b><small><?php echo esc_html(self::t('No matching obligations', 'لا توجد التزامات مطابقة')); ?></small></div><?php endif; ?>
            </div>
        </article>
        <?php
    }

    /** @param array<string,array<string,array{contracts:int,base:string,scheduled:string,settled:string,outstanding:string}>> $totals */
    private static function accountingPanel(array $totals): void
    {
        ?>
        <article class="safecontracts-dashboard-v3__panel safecontracts-dashboard-v3__panel--accounting">
            <div class="safecontracts-dashboard-v3__panel-title"><span>≋</span><h2><?php echo esc_html(self::t('Accounting totals by currency', 'الإجماليات المحاسبية حسب العملة')); ?></h2></div>
            <div class="safecontracts-dashboard-v3__accounting-list">
                <?php if ($totals === []) : ?><p><?php echo esc_html(self::t('No accounting rows match the selected period.', 'لا توجد إجماليات محاسبية للفترة المحددة.')); ?></p><?php endif; ?>
                <?php foreach ($totals as $currency => $directions) : $r = $directions[FinancialDirection::RECEIVABLE] ?? self::zero(); $p = $directions[FinancialDirection::PAYABLE] ?? self::zero(); ?>
                    <div class="safecontracts-dashboard-v3__currency-row">
                        <strong><?php echo esc_html($currency); ?></strong>
                        <span class="is-receivable"><?php echo esc_html(self::t('Us', 'لنا') . ' ' . MoneyFormatter::format($r['outstanding'], $currency)); ?></span>
                        <span class="is-payable"><?php echo esc_html(self::t('Due', 'علينا') . ' ' . MoneyFormatter::format($p['outstanding'], $currency)); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
        <?php
    }

    /** @param array<string,int> $counts */
    private static function statusChart(array $counts): void
    {
        $max = max(1, ...array_values($counts));
        $labels = [
            'draft' => self::t('Draft', 'مسودة'),
            'active' => self::t('Active', 'نشط'),
            'completed' => self::t('Completed', 'مكتمل'),
            'cancelled' => self::t('Cancelled', 'ملغي'),
        ];
        echo '<div class="safecontracts-dashboard-v3__bars">';
        foreach ($labels as $status => $label) {
            $count = (int) ($counts[$status] ?? 0);
            $height = max(8, (int) round(($count / $max) * 100));
            echo '<div class="safecontracts-dashboard-v3__bar-item"><b>' . esc_html((string) $count) . '</b><div class="safecontracts-dashboard-v3__bar-track"><span class="is-' . esc_attr($status) . '" style="height:' . esc_attr((string) $height) . '%"></span></div><small>' . esc_html($label) . '</small></div>';
        }
        echo '</div>';
    }

    private static function quickActions(): void
    {
        $actions = [];
        if (current_user_can(Capabilities::CREATE_CONTRACTS)) $actions[] = [self::t('Add contract', 'إضافة عقد'), 'dashicons-media-document', 'safecontracts-contracts'];
        if (current_user_can(Capabilities::CREATE_CUSTOMERS)) $actions[] = [self::t('Add customer', 'إضافة عميل'), 'dashicons-businessperson', 'safecontracts-customers'];
        if (current_user_can(Capabilities::CREATE_SUPPLIERS)) $actions[] = [self::t('Add supplier', 'إضافة مورد'), 'dashicons-store', 'safecontracts-suppliers'];
        if (current_user_can(Capabilities::CREATE_PAYMENTS)) $actions[] = [self::t('Add payment', 'إضافة دفعة'), 'dashicons-money-alt', 'safecontracts-payments'];
        if ($actions === []) return;
        ?>
        <details class="safecontracts-dashboard-v3__quick">
            <summary aria-label="<?php echo esc_attr(self::t('Quick add', 'إضافات سريعة')); ?>"><span class="dashicons dashicons-plus-alt2"></span></summary>
            <div>
                <?php foreach ($actions as [$label, $icon, $page]) : ?><a href="<?php echo esc_url(add_query_arg(['page' => $page, 'mode' => 'add'], admin_url('admin.php'))); ?>"><span class="dashicons <?php echo esc_attr($icon); ?>"></span><?php echo esc_html($label); ?></a><?php endforeach; ?>
            </div>
        </details>
        <?php
    }

    /** @param list<array<string,mixed>> $contracts */
    private static function statusCounts(array $contracts): array
    {
        $counts = ['draft' => 0, 'active' => 0, 'completed' => 0, 'cancelled' => 0];
        foreach ($contracts as $contract) {
            $status = strtolower((string) ($contract['status'] ?? ''));
            if (isset($counts[$status])) $counts[$status]++;
        }
        return $counts;
    }

    /** @param list<array<string,mixed>> $contracts @param list<array<string,mixed>> $payments @param list<array<string,mixed>> $settlements @return array<string,array<string,array{contracts:int,base:string,scheduled:string,settled:string,outstanding:string}>> */
    private static function totals(array $contracts, array $payments, array $settlements): array
    {
        $rows = [];
        foreach ($contracts as $row) {
            $direction = (string) ($row['financial_direction'] ?? '');
            if (! in_array($direction, [FinancialDirection::RECEIVABLE, FinancialDirection::PAYABLE], true)) continue;
            $currency = self::currency((string) ($row['currency_code'] ?? ''));
            self::bucket($rows, $currency, $direction);
            $rows[$currency][$direction]['contracts']++;
            $rows[$currency][$direction]['base'] = self::add($rows[$currency][$direction]['base'], (string) ($row['base_value'] ?? '0'));
        }
        foreach ($payments as $row) {
            $direction = (string) ($row['financial_direction'] ?? '');
            if (! in_array($direction, [FinancialDirection::RECEIVABLE, FinancialDirection::PAYABLE], true)) continue;
            $currency = self::currency((string) ($row['currency_code'] ?? ''));
            self::bucket($rows, $currency, $direction);
            $rows[$currency][$direction]['scheduled'] = self::add($rows[$currency][$direction]['scheduled'], (string) ($row['original_amount'] ?? '0'));
        }
        foreach ($settlements as $row) {
            $direction = (string) ($row['financial_direction'] ?? '');
            if (! in_array($direction, [FinancialDirection::RECEIVABLE, FinancialDirection::PAYABLE], true)) continue;
            $currency = self::currency((string) ($row['currency_code'] ?? ''));
            self::bucket($rows, $currency, $direction);
            $rows[$currency][$direction]['settled'] = self::add($rows[$currency][$direction]['settled'], (string) ($row['settled_total'] ?? '0'));
        }
        foreach ($rows as $currency => $directions) {
            foreach ([FinancialDirection::RECEIVABLE, FinancialDirection::PAYABLE] as $direction) {
                if (! isset($rows[$currency][$direction])) continue;
                $scheduled = $rows[$currency][$direction]['scheduled'];
                $settled = $rows[$currency][$direction]['settled'];
                $rows[$currency][$direction]['outstanding'] = ContractMoney::compare($scheduled, $settled) >= 0
                    ? ContractMoney::subtract($scheduled, $settled)
                    : '0.0000';
            }
        }
        ksort($rows);
        return $rows;
    }

    /** @param array<string,array<string,array{contracts:int,base:string,scheduled:string,settled:string,outstanding:string}>> $rows */
    private static function bucket(array &$rows, string $currency, string $direction): void
    {
        $rows[$currency] ??= [];
        $rows[$currency][$direction] ??= self::zero();
    }

    /** @return array{contracts:int,base:string,scheduled:string,settled:string,outstanding:string} */
    private static function zero(): array
    {
        return ['contracts' => 0, 'base' => '0.0000', 'scheduled' => '0.0000', 'settled' => '0.0000', 'outstanding' => '0.0000'];
    }

    private static function add(string $left, string $right): string
    {
        return ContractMoney::add(ContractMoney::normalizeNonNegative($left), ContractMoney::normalizeNonNegative($right));
    }

    private static function currency(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'UNSET';
    }

    private static function t(string $en, string $ar): string
    {
        return function_exists('is_rtl') && is_rtl() ? $ar : $en;
    }
}
