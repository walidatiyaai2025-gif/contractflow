<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Payments\FinancialDirection;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Translations\TranslationCatalog;

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
        $contracts = array_values(array_filter($read->contracts($contractFilters), static fn (array $row): bool => empty($row['is_archived'])));
        $paymentFilters = $filters;
        if (in_array($paymentFilters['status'], ['draft', 'active', 'completed', 'cancelled'], true)) {
            $paymentFilters['status'] = '';
        }
        $payments = array_values(array_filter($read->payments($paymentFilters), static fn (array $row): bool => empty($row['is_archived'])));
        $rows = self::totals($contracts, $payments);
        $receivableCount = count(array_filter($contracts, static fn (array $row): bool => (string) ($row['financial_direction'] ?? '') === FinancialDirection::RECEIVABLE));
        $payableCount = count(array_filter($contracts, static fn (array $row): bool => (string) ($row['financial_direction'] ?? '') === FinancialDirection::PAYABLE));
        ?>
        <section class="safecontracts-dashboard-v2">
            <div class="safecontracts-dashboard-v2__heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Operational overview', 'safecontracts'); ?></p><h2><?php echo esc_html__('Dashboard', 'safecontracts'); ?></h2><p class="description"><?php echo esc_html__('Receivables and payables are kept in separate accounting lanes. Green means money we expect to receive; red means money we must pay.', 'safecontracts'); ?></p></div></div>

            <div class="safecontracts-dashboard-v2__kpis safecontracts-dashboard-v2__kpis--accounting">
                <?php self::kpi(__('Contracts', 'safecontracts'), (string) count($contracts), __('All contracts', 'safecontracts'), self::contractsUrl(), 'neutral'); ?>
                <?php self::directionKpi(FinancialDirection::RECEIVABLE, $receivableCount, $rows); ?>
                <?php self::directionKpi(FinancialDirection::PAYABLE, $payableCount, $rows); ?>
                <?php self::generalAccountKpi($rows); ?>
            </div>

            <?php if (! empty($filters['date_range_error'])) : ?><div class="notice notice-error inline"><p><?php echo esc_html__('Invalid period. Use valid dates and make sure the end date is not earlier than the start date.', 'safecontracts'); ?></p></div><?php endif; ?>
            <form class="safecontracts-filter-bar safecontracts-dashboard-v2__filters" method="get"><input type="hidden" name="page" value="<?php echo esc_attr(AdminShell::SLUG); ?>"><?php AdminPeriodFilter::renderFields($filters); ?><button class="button button-primary" type="submit"><?php echo esc_html__('Apply filters', 'safecontracts'); ?></button><a class="button" href="<?php echo esc_url(add_query_arg(['page' => AdminShell::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Clear filters', 'safecontracts'); ?></a></form>

            <div class="safecontracts-dashboard-v2__lanes">
                <?php self::lane(FinancialDirection::RECEIVABLE, $rows); ?>
                <?php self::lane(FinancialDirection::PAYABLE, $rows); ?>
            </div>

            <section class="safecontracts-dashboard-v2__net-section"><div class="safecontracts-dashboard-v2__section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Accounting totals', 'safecontracts'); ?></p><h3><?php echo esc_html__('Accounting totals by currency', 'safecontracts'); ?></h3></div><p class="description"><?php echo esc_html__('Currencies are never added together. Each currency is calculated independently from active contracts and non-archived scheduled payments.', 'safecontracts'); ?></p></div>
                <div class="safecontracts-dashboard-v2__net-grid">
                    <?php foreach ($rows as $currency => $directions) : self::netCard($currency, $directions); endforeach; ?>
                </div>
            </section>
        </section>
        <?php
    }

    private static function kpi(string $label, string $value, string $detail, string $url, string $class): void
    {
        ?><a class="safecontracts-dashboard-v2__kpi safecontracts-dashboard-v2__kpi--<?php echo esc_attr($class); ?>" href="<?php echo esc_url($url); ?>"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($value); ?></strong><small><?php echo esc_html($detail); ?></small></a><?php
    }

    /** @param array<string,array<string,array{contracts:int,base:string,scheduled:string,settled:string,outstanding:string}>> $rows */
    private static function directionKpi(string $direction, int $count, array $rows): void
    {
        $receivable = $direction === FinancialDirection::RECEIVABLE;
        $class = $receivable ? 'receivable' : 'payable';
        $label = $receivable ? __('Receivable contracts', 'safecontracts') : __('Payable contracts', 'safecontracts');
        $detail = $receivable ? __('Money customers will pay us', 'safecontracts') : __('Money we will pay suppliers', 'safecontracts');
        $type = $receivable ? 'customer' : 'supplier';
        ?>
        <a class="safecontracts-dashboard-v2__kpi safecontracts-dashboard-v2__kpi--<?php echo esc_attr($class); ?>" href="<?php echo esc_url(self::contractsUrl($type)); ?>">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html($receivable ? (string) $count : '− ' . $count); ?></strong>
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
                <?php if ($rows === []) : ?><strong>0.00</strong><?php endif; ?>
                <?php foreach ($rows as $currency => $directions) : ?>
                    <?php $zero = ['contracts' => 0, 'base' => '0.0000', 'scheduled' => '0.0000', 'settled' => '0.0000', 'outstanding' => '0.0000']; $r = $directions[FinancialDirection::RECEIVABLE] ?? $zero; $p = $directions[FinancialDirection::PAYABLE] ?? $zero; $net = ContractMoney::difference($r['outstanding'], $p['outstanding']); $class = str_starts_with($net, '-') ? 'payable' : ($net !== '0.0000' ? 'receivable' : 'neutral'); ?>
                    <strong class="safecontracts-dashboard-v2__net--<?php echo esc_attr($class); ?>"><?php echo esc_html(self::signedMoney($net, $currency)); ?></strong>
                <?php endforeach; ?>
            </div>
            <small><?php echo esc_html(self::label('Receivables still due to us minus payables still due from us. Settlements update this balance automatically.', 'المستحق لنا المتبقي ناقص المستحق علينا المتبقي، ويتغير تلقائياً مع كل تحصيل أو سداد.')); ?></small>
        </article>
        <?php
    }

    /** @param list<array<string,mixed>> $contracts @param list<array<string,mixed>> $payments @return array<string,array<string,array{contracts:int,base:string,scheduled:string,settled:string,outstanding:string}>> */
    private static function totals(array $contracts, array $payments): array
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
            $rows[$currency][$direction]['settled'] = self::add($rows[$currency][$direction]['settled'], (string) ($row['paid_amount'] ?? '0'));
            $rows[$currency][$direction]['outstanding'] = self::add($rows[$currency][$direction]['outstanding'], (string) ($row['remaining_amount'] ?? '0'));
        }
        ksort($rows);
        return $rows;
    }

    /** @param array<string,array<string,array{contracts:int,base:string,scheduled:string,settled:string,outstanding:string}>> $rows */
    private static function bucket(array &$rows, string $currency, string $direction): void
    {
        $rows[$currency] ??= [];
        $rows[$currency][$direction] ??= ['contracts' => 0, 'base' => '0.0000', 'scheduled' => '0.0000', 'settled' => '0.0000', 'outstanding' => '0.0000'];
    }

    /** @param array<string,array<string,array{contracts:int,base:string,scheduled:string,settled:string,outstanding:string}>> $rows */
    private static function lane(string $direction, array $rows): void
    {
        $receivable = $direction === FinancialDirection::RECEIVABLE;
        $class = $receivable ? 'receivable' : 'payable';
        ?><section class="safecontracts-dashboard-v2__lane safecontracts-dashboard-v2__lane--<?php echo esc_attr($class); ?>"><div class="safecontracts-dashboard-v2__lane-heading"><div><h3><?php echo esc_html($receivable ? __('Receivable contracts', 'safecontracts') : __('Payable contracts', 'safecontracts')); ?></h3><p><?php echo esc_html($receivable ? __('Money customers will pay us', 'safecontracts') : __('Money we will pay suppliers', 'safecontracts')); ?></p></div><a class="button" href="<?php echo esc_url(self::contractsUrl($receivable ? 'customer' : 'supplier')); ?>"><?php echo esc_html__('View all', 'safecontracts'); ?></a></div><div class="safecontracts-dashboard-v2__lane-grid">
        <?php foreach ($rows as $currency => $directions) : $row = $directions[$direction] ?? ['contracts' => 0, 'base' => '0.0000', 'scheduled' => '0.0000', 'settled' => '0.0000', 'outstanding' => '0.0000']; ?>
            <article class="safecontracts-dashboard-v2__money-card"><h4><?php echo esc_html($currency); ?></h4><dl><div><dt><?php echo esc_html__('Contracts', 'safecontracts'); ?></dt><dd><?php echo esc_html((string) $row['contracts']); ?></dd></div><div><dt><?php echo esc_html__('Base contract total', 'safecontracts'); ?></dt><dd><?php echo esc_html(self::directionMoney($row['base'], $currency, $direction)); ?></dd></div><div><dt><?php echo esc_html__('Scheduled total', 'safecontracts'); ?></dt><dd><?php echo esc_html(self::directionMoney($row['scheduled'], $currency, $direction)); ?></dd></div><div><dt><?php echo esc_html($receivable ? __('Collected from customers', 'safecontracts') : __('Paid to suppliers', 'safecontracts')); ?></dt><dd><?php echo esc_html(self::directionMoney($row['settled'], $currency, $direction)); ?></dd></div><div><dt><?php echo esc_html__('Outstanding', 'safecontracts'); ?></dt><dd><?php echo esc_html(self::directionMoney($row['outstanding'], $currency, $direction)); ?></dd></div></dl></article>
        <?php endforeach; ?>
        </div></section><?php
    }

    /** @param array<string,array{contracts:int,base:string,scheduled:string,settled:string,outstanding:string}> $directions */
    private static function netCard(string $currency, array $directions): void
    {
        $zero = ['contracts' => 0, 'base' => '0.0000', 'scheduled' => '0.0000', 'settled' => '0.0000', 'outstanding' => '0.0000'];
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

    private static function contractsUrl(string $type = ''): string
    {
        $args = ['page' => ContractsPage::SLUG];
        if ($type !== '') { $args['counterparty_type'] = $type === 'supplier' ? 'supplier' : 'customer'; }
        return add_query_arg($args, admin_url('admin.php'));
    }

    private static function add(string $left, string $right): string
    {
        return ContractMoney::add(ContractMoney::normalizeNonNegative($left), ContractMoney::normalizeNonNegative($right));
    }

    private static function directionMoney(string $value, string $currency, string $direction): string
    {
        return ($direction === FinancialDirection::PAYABLE ? '− ' : '+ ') . self::money($value, $currency);
    }

    private static function signedMoney(string $value, string $currency): string
    {
        $negative = str_starts_with($value, '-');
        $absolute = $negative ? substr($value, 1) : $value;
        if (ContractMoney::compare($absolute, '0.0000') === 0) { return self::money($absolute, $currency); }
        return ($negative ? '− ' : '+ ') . self::money($absolute, $currency);
    }

    private static function money(string $value, string $currency): string
    {
        $normalized = ContractMoney::normalizeNonNegative($value);
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0000');
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole) ?? $whole;
        return ($currency === '—' ? '' : $currency . ' ') . $whole . '.' . substr(str_pad($fraction, 2, '0'), 0, 2);
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
