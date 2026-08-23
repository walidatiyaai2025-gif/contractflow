<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Payments\FinancialDirection;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Support\MoneyFormatter;
use SafeContracts\Translations\TranslationCatalog;
use Throwable;

final class DashboardV2Page
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
        if (! in_array($contractFilters['status'], ['draft', 'active', 'completed', 'cancelled'], true)) {
            $contractFilters['status'] = '';
        }
        $contracts = array_values(array_filter(
            $read->contracts($contractFilters),
            static fn (array $row): bool => empty($row['is_archived'])
        ));

        $paymentFilters = $filters;
        if (in_array($paymentFilters['status'], ['draft', 'active', 'completed', 'cancelled'], true)) {
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

        $rows = self::totals($contracts, $payments, $settlements);
        $receivableCount = count(array_filter($contracts, static fn (array $row): bool => (string) ($row['financial_direction'] ?? '') === FinancialDirection::RECEIVABLE));
        $payableCount = count(array_filter($contracts, static fn (array $row): bool => (string) ($row['financial_direction'] ?? '') === FinancialDirection::PAYABLE));
        ?>
        <section class="safecontracts-dashboard-v2">
            <div class="safecontracts-dashboard-v2__heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Operational overview', 'safecontracts'); ?></p>
                    <h2><?php echo esc_html__('Dashboard', 'safecontracts'); ?></h2>
                    <p class="description"><?php echo esc_html(self::label('Receivables and payables use actual settlement ledger movements. Select a year to scope the complete calendar year.', 'المستحقات لنا وعلينا تعتمد على حركات التحصيل والسداد الفعلية. اختر السنة لعرض السنة الميلادية كاملة.')); ?></p>
                </div>
            </div>

            <?php self::yearFilter($filters); ?>

            <div class="safecontracts-dashboard-v2__kpis safecontracts-dashboard-v2__kpis--accounting">
                <?php self::kpi(__('Contracts', 'safecontracts'), (string) count($contracts), __('All contracts', 'safecontracts'), self::contractsUrl('', $filters), 'neutral'); ?>
                <?php self::directionKpi(FinancialDirection::RECEIVABLE, $receivableCount, $rows, $filters); ?>
                <?php self::directionKpi(FinancialDirection::PAYABLE, $payableCount, $rows, $filters); ?>
                <?php self::generalAccountKpi($rows); ?>
            </div>

            <?php if (! empty($filters['date_range_error'])) : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html__('Invalid period. Use valid dates and make sure the end date is not earlier than the start date.', 'safecontracts'); ?></p></div>
            <?php endif; ?>

            <?php if (self::hasDirectionData($rows, FinancialDirection::RECEIVABLE)) : ?>
                <?php self::lane(FinancialDirection::RECEIVABLE, $rows, $filters); ?>
            <?php endif; ?>
            <?php if (self::hasDirectionData($rows, FinancialDirection::PAYABLE)) : ?>
                <?php self::lane(FinancialDirection::PAYABLE, $rows, $filters); ?>
            <?php endif; ?>

            <?php if ($rows !== []) : ?>
                <section class="safecontracts-dashboard-v2__net-section">
                    <div class="safecontracts-dashboard-v2__section-heading">
                        <div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Accounting totals', 'safecontracts'); ?></p><h3><?php echo esc_html__('Accounting totals by currency', 'safecontracts'); ?></h3></div>
                        <p class="description"><?php echo esc_html(self::label('Scheduled obligations and actual settlements are calculated independently by currency. Different currencies are never added together.', 'يتم احتساب الالتزامات المجدولة والتسويات الفعلية لكل عملة بشكل مستقل، ولا يتم جمع العملات المختلفة.')); ?></p>
                    </div>
                    <div class="safecontracts-dashboard-v2__net-grid">
                        <?php foreach ($rows as $currency => $directions) : self::netCard($currency, $directions); endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </section>
        <?php
    }

    /** @param array<string,mixed> $filters */
    private static function yearFilter(array $filters): void
    {
        $years = [];
        try {
            $years = AdminYearOptions::forCurrentUser();
        } catch (Throwable $error) {
            unset($error);
        }
        $selectedYear = (int) ($filters['year'] ?? 0);
        if ($selectedYear > 0 && ! in_array($selectedYear, $years, true)) {
            $years[] = $selectedYear;
            rsort($years, SORT_NUMERIC);
        }
        ?>
        <form class="safecontracts-filter-bar safecontracts-dashboard-v2__filters" method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr(AdminShell::SLUG); ?>">
            <label>
                <?php echo esc_html(self::label('Year', 'السنة')); ?>
                <select name="year">
                    <option value="0"><?php echo esc_html(self::label('All years', 'كل السنوات')); ?></option>
                    <?php foreach ($years as $year) : ?><option value="<?php echo esc_attr((string) $year); ?>" <?php selected($selectedYear, $year); ?>><?php echo esc_html((string) $year); ?></option><?php endforeach; ?>
                </select>
            </label>
            <button class="button button-primary" type="submit"><?php echo esc_html__('Apply filters', 'safecontracts'); ?></button>
            <a class="button" href="<?php echo esc_url(add_query_arg(['page' => AdminShell::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Clear filters', 'safecontracts'); ?></a>
        </form>
        <?php
    }

    private static function kpi(string $label, string $value, string $detail, string $url, string $class): void
    {
        ?><a class="safecontracts-dashboard-v2__kpi safecontracts-dashboard-v2__kpi--<?php echo esc_attr($class); ?>" href="<?php echo esc_url($url); ?>"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($value); ?></strong><small><?php echo esc_html($detail); ?></small></a><?php
    }

    /** @param array<string,array<string,array{contracts:int,base:string,scheduled:string,settled:string,outstanding:string}>> $rows @param array<string,mixed> $filters */
    private static function directionKpi(string $direction, int $count, array $rows, array $filters): void
    {
        $receivable = $direction === FinancialDirection::RECEIVABLE;
        $class = $receivable ? 'receivable' : 'payable';
        $label = $receivable ? self::label('Receivable contracts', 'العقود المستحقة لنا') : self::label('Payable contracts', 'العقود المستحقة علينا');
        $detail = $receivable ? self::label('Money customers owe us', 'مبالغ مستحقة لنا من العملاء') : self::label('Money we owe suppliers', 'مبالغ واجبة الدفع للموردين');
        ?>
        <a class="safecontracts-dashboard-v2__kpi safecontracts-dashboard-v2__kpi--<?php echo esc_attr($class); ?>" href="<?php echo esc_url(self::contractsUrl($direction, $filters)); ?>">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html((string) $count); ?></strong>
            <div class="safecontracts-dashboard-v2__kpi-money-list">
                <?php foreach ($rows as $currency => $directions) : $row = $directions[$direction] ?? null; if ($row === null || $row['contracts'] === 0) { continue; } ?><small><?php echo esc_html(self::directionMoney($row['base'], $currency, $direction)); ?></small><?php endforeach; ?>
            </div>
            <small><?php echo esc_html($detail); ?></small>
        </a>
        <?php
    }

    /** @param array<string,array<string,array{contracts:int,base:string,scheduled:string,settled:string,outstanding:string}>> $rows */
    private static function generalAccountKpi(array $rows): void
    {
        ?>
        <article class="safecontracts-dashboard-v2__kpi safecontracts-dashboard-v2__kpi--general-account">
            <span><?php echo esc_html(self::label('General account', 'الحساب العام')); ?></span>
            <div class="safecontracts-dashboard-v2__general-values">
                <?php if ($rows === []) : ?><strong><?php echo esc_html(MoneyFormatter::format('0', 'EGP')); ?></strong><?php endif; ?>
                <?php foreach ($rows as $currency => $directions) : ?>
                    <?php $zero = self::zeroBucket(); $r = $directions[FinancialDirection::RECEIVABLE] ?? $zero; $p = $directions[FinancialDirection::PAYABLE] ?? $zero; $net = ContractMoney::difference($r['outstanding'], $p['outstanding']); $class = str_starts_with($net, '-') ? 'payable' : ($net !== '0.0000' ? 'receivable' : 'neutral'); ?>
                    <strong class="safecontracts-dashboard-v2__net--<?php echo esc_attr($class); ?>"><?php echo esc_html(self::signedMoney($net, $currency)); ?></strong>
                <?php endforeach; ?>
            </div>
            <small><?php echo esc_html(self::label('Outstanding receivables minus outstanding payables after actual settlement-ledger movements.', 'المتبقي المستحق لنا ناقص المتبقي المستحق علينا بعد حركات التحصيل والسداد الفعلية.')); ?></small>
        </article>
        <?php
    }

    /**
     * @param list<array<string,mixed>> $contracts
     * @param list<array<string,mixed>> $payments
     * @param list<array<string,mixed>> $settlements
     * @return array<string,array<string,array{contracts:int,base:string,scheduled:string,settled:string,outstanding:string}>>
     */
    private static function totals(array $contracts, array $payments, array $settlements): array
    {
        $rows = [];
        foreach ($contracts as $row) {
            $direction = (string) ($row['financial_direction'] ?? '');
            if (! in_array($direction, [FinancialDirection::RECEIVABLE, FinancialDirection::PAYABLE], true)) { continue; }
            $currency = self::currency((string) ($row['currency_code'] ?? ''));
            self::bucket($rows, $currency, $direction);
            $rows[$currency][$direction]['contracts']++;
            $rows[$currency][$direction]['base'] = self::add($rows[$currency][$direction]['base'], (string) ($row['base_value'] ?? '0'));
        }
        foreach ($payments as $row) {
            $direction = (string) ($row['financial_direction'] ?? '');
            if (! in_array($direction, [FinancialDirection::RECEIVABLE, FinancialDirection::PAYABLE], true)) { continue; }
            $currency = self::currency((string) ($row['currency_code'] ?? ''));
            self::bucket($rows, $currency, $direction);
            $rows[$currency][$direction]['scheduled'] = self::add($rows[$currency][$direction]['scheduled'], (string) ($row['original_amount'] ?? '0'));
        }
        foreach ($settlements as $row) {
            $direction = (string) ($row['financial_direction'] ?? '');
            if (! in_array($direction, [FinancialDirection::RECEIVABLE, FinancialDirection::PAYABLE], true)) { continue; }
            $currency = self::currency((string) ($row['currency_code'] ?? ''));
            self::bucket($rows, $currency, $direction);
            $rows[$currency][$direction]['settled'] = self::add($rows[$currency][$direction]['settled'], (string) ($row['settled_total'] ?? '0'));
        }
        foreach ($rows as $currency => $directions) {
            foreach ([FinancialDirection::RECEIVABLE, FinancialDirection::PAYABLE] as $direction) {
                if (! isset($rows[$currency][$direction])) { continue; }
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
        $rows[$currency][$direction] ??= self::zeroBucket();
    }

    /** @return array{contracts:int,base:string,scheduled:string,settled:string,outstanding:string} */
    private static function zeroBucket(): array
    {
        return ['contracts' => 0, 'base' => '0.0000', 'scheduled' => '0.0000', 'settled' => '0.0000', 'outstanding' => '0.0000'];
    }

    /** @param array<string,array<string,array{contracts:int,base:string,scheduled:string,settled:string,outstanding:string}>> $rows */
    private static function hasDirectionData(array $rows, string $direction): bool
    {
        foreach ($rows as $directions) {
            $row = $directions[$direction] ?? null;
            if ($row === null) { continue; }
            if ($row['contracts'] > 0
                || ContractMoney::compare($row['base'], '0.0000') > 0
                || ContractMoney::compare($row['scheduled'], '0.0000') > 0
                || ContractMoney::compare($row['settled'], '0.0000') > 0
                || ContractMoney::compare($row['outstanding'], '0.0000') > 0) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,array<string,array{contracts:int,base:string,scheduled:string,settled:string,outstanding:string}>> $rows @param array<string,mixed> $filters */
    private static function lane(string $direction, array $rows, array $filters): void
    {
        $receivable = $direction === FinancialDirection::RECEIVABLE;
        $class = $receivable ? 'receivable' : 'payable';
        ?>
        <section class="safecontracts-dashboard-v2__lane safecontracts-dashboard-v2__lane--<?php echo esc_attr($class); ?>">
            <div class="safecontracts-dashboard-v2__lane-heading">
                <div><h3><?php echo esc_html($receivable ? self::label('Receivable contracts', 'العقود المستحقة لنا') : self::label('Payable contracts', 'العقود المستحقة علينا')); ?></h3><p><?php echo esc_html($receivable ? self::label('Money customers must pay us', 'مبالغ يجب على العملاء دفعها لنا') : self::label('Money we must pay suppliers', 'مبالغ واجبة الدفع علينا للموردين')); ?></p></div>
                <a class="button" href="<?php echo esc_url(self::contractsUrl($direction, $filters)); ?>"><?php echo esc_html(self::label('View all', 'عرض الكل')); ?></a>
            </div>
            <div class="safecontracts-dashboard-v2__lane-grid">
                <?php foreach ($rows as $currency => $directions) : $row = $directions[$direction] ?? self::zeroBucket(); if ($row['contracts'] === 0 && ContractMoney::compare($row['scheduled'], '0.0000') === 0 && ContractMoney::compare($row['settled'], '0.0000') === 0) { continue; } ?>
                    <article class="safecontracts-dashboard-v2__money-card"><h4><?php echo esc_html($currency); ?></h4><dl><div><dt><?php echo esc_html__('Contracts', 'safecontracts'); ?></dt><dd><?php echo esc_html((string) $row['contracts']); ?></dd></div><div><dt><?php echo esc_html__('Base contract total', 'safecontracts'); ?></dt><dd><?php echo esc_html(self::directionMoney($row['base'], $currency, $direction)); ?></dd></div><div><dt><?php echo esc_html__('Scheduled total', 'safecontracts'); ?></dt><dd><?php echo esc_html(self::directionMoney($row['scheduled'], $currency, $direction)); ?></dd></div><div><dt><?php echo esc_html($receivable ? __('Collected from customers', 'safecontracts') : __('Paid to suppliers', 'safecontracts')); ?></dt><dd><?php echo esc_html(self::directionMoney($row['settled'], $currency, $direction)); ?></dd></div><div><dt><?php echo esc_html__('Outstanding', 'safecontracts'); ?></dt><dd><?php echo esc_html(self::directionMoney($row['outstanding'], $currency, $direction)); ?></dd></div></dl></article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    /** @param array<string,array{contracts:int,base:string,scheduled:string,settled:string,outstanding:string}> $directions */
    private static function netCard(string $currency, array $directions): void
    {
        $zero = self::zeroBucket();
        $r = $directions[FinancialDirection::RECEIVABLE] ?? $zero;
        $p = $directions[FinancialDirection::PAYABLE] ?? $zero;
        ?><article class="safecontracts-dashboard-v2__net-card"><h4><?php echo esc_html($currency); ?></h4><?php self::netLine(__('Base contract total', 'safecontracts'), $r['base'], $p['base'], $currency); ?><?php self::netLine(__('Scheduled total', 'safecontracts'), $r['scheduled'], $p['scheduled'], $currency); ?><?php self::netLine(self::label('Settlements', 'التحصيلات والسداد'), $r['settled'], $p['settled'], $currency); ?><?php self::netLine(self::label('General account', 'الحساب العام'), $r['outstanding'], $p['outstanding'], $currency); ?></article><?php
    }

    private static function netLine(string $label, string $receivable, string $payable, string $currency): void
    {
        $net = ContractMoney::difference($receivable, $payable);
        $class = str_starts_with($net, '-') ? 'payable' : ($net !== '0.0000' ? 'receivable' : 'neutral');
        ?><div class="safecontracts-dashboard-v2__net-line"><span><?php echo esc_html($label); ?></span><small class="safecontracts-financial-amount--receivable"><?php echo esc_html(self::directionMoney($receivable, $currency, FinancialDirection::RECEIVABLE)); ?></small><small class="safecontracts-financial-amount--payable"><?php echo esc_html(self::directionMoney($payable, $currency, FinancialDirection::PAYABLE)); ?></small><strong class="safecontracts-dashboard-v2__net--<?php echo esc_attr($class); ?>"><?php echo esc_html__('Net value', 'safecontracts') . ': ' . esc_html(self::signedMoney($net, $currency)); ?></strong></div><?php
    }

    /** @param array<string,mixed> $filters */
    private static function contractsUrl(string $direction = '', array $filters = []): string
    {
        $args = ['page' => ContractsPage::SLUG];
        $year = (int) ($filters['year'] ?? 0);
        if ($year > 0) { $args['year'] = $year; }
        if (($filters['currency_code'] ?? '') !== '') { $args['currency_code'] = (string) $filters['currency_code']; }
        if (($filters['accountant_user_id'] ?? 0) > 0) { $args['accountant_user_id'] = (int) $filters['accountant_user_id']; }
        if ($direction !== '') { $args['financial_direction'] = $direction; }
        return add_query_arg($args, admin_url('admin.php'));
    }

    private static function add(string $left, string $right): string
    {
        return ContractMoney::add(ContractMoney::normalizeNonNegative($left), ContractMoney::normalizeNonNegative($right));
    }

    private static function directionMoney(string $value, string $currency, string $direction): string
    {
        $formatted = MoneyFormatter::format($value, $currency);
        return ($direction === FinancialDirection::PAYABLE ? '− ' : '+ ') . $formatted;
    }

    private static function signedMoney(string $value, string $currency): string
    {
        return MoneyFormatter::format($value, $currency);
    }

    private static function currency(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        return $currency === '' ? '—' : $currency;
    }

    private static function label(string $english, string $arabic): string
    {
        return TranslationCatalog::currentLanguage() === 'ar' ? $arabic : $english;
    }
}
