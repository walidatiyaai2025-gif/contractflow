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

        [$year, $month, $periodStart, $periodEnd] = self::selectedMonth();
        $filters = DashboardFilters::normalize(array_merge($_GET, [
            'date_from' => $periodStart,
            'date_to' => $periodEnd,
            'due_from' => '',
            'due_to' => '',
        ]));

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

        $rows = self::totals($contracts, $payments);
        $customerContracts = count(array_filter(
            $contracts,
            static fn (array $row): bool => (string) ($row['counterparty_type'] ?? '') === 'customer'
        ));
        $supplierContracts = count(array_filter(
            $contracts,
            static fn (array $row): bool => (string) ($row['counterparty_type'] ?? '') === 'supplier'
        ));
        ?>
        <section class="safecontracts-dashboard-v2 safecontracts-monthly-dashboard" aria-label="<?php echo esc_attr(self::label('Monthly dashboard', 'لوحة التحكم الشهرية')); ?>">
            <?php self::renderMonthFilter($year, $month); ?>

            <div class="safecontracts-monthly-dashboard__cards">
                <?php self::renderContractsCard($customerContracts, $supplierContracts); ?>
                <?php self::renderReceivableCard($rows); ?>
                <?php self::renderPayableCard($rows); ?>
                <?php self::renderGeneralAccountCard($rows); ?>
            </div>

            <div class="safecontracts-dashboard-v2__lanes safecontracts-monthly-dashboard__lanes">
                <?php self::lane(FinancialDirection::RECEIVABLE, $rows); ?>
                <?php self::lane(FinancialDirection::PAYABLE, $rows); ?>
            </div>

            <section class="safecontracts-dashboard-v2__net-section safecontracts-monthly-dashboard__totals">
                <div class="safecontracts-dashboard-v2__section-heading">
                    <div>
                        <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html(self::label('Accounting totals', 'الإجماليات المحاسبية')); ?></p>
                        <h3><?php echo esc_html(self::label('Monthly totals by currency', 'إجماليات الشهر حسب العملة')); ?></h3>
                    </div>
                    <p class="description"><?php echo esc_html(self::label('Currencies are calculated independently and are never added together.', 'يتم احتساب كل عملة بشكل مستقل ولا يتم جمع العملات المختلفة معاً.')); ?></p>
                </div>
                <div class="safecontracts-dashboard-v2__net-grid">
                    <?php foreach ($rows as $currency => $directions) : self::netCard($currency, $directions); endforeach; ?>
                </div>
            </section>

            <?php self::renderQuickAdd(); ?>
        </section>
        <?php
    }

    /** @return array{0:int,1:int,2:string,3:string} */
    private static function selectedMonth(): array
    {
        $currentYear = (int) (function_exists('wp_date') ? wp_date('Y') : gmdate('Y'));
        $currentMonth = (int) (function_exists('wp_date') ? wp_date('n') : gmdate('n'));
        $year = max(2000, min(2100, (int) ($_GET['dashboard_year'] ?? $currentYear)));
        $month = max(1, min(12, (int) ($_GET['dashboard_month'] ?? $currentMonth)));
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = (new \DateTimeImmutable($start))->format('Y-m-t');
        return [$year, $month, $start, $end];
    }

    private static function renderMonthFilter(int $year, int $month): void
    {
        $currentYear = (int) (function_exists('wp_date') ? wp_date('Y') : gmdate('Y'));
        ?>
        <form class="safecontracts-monthly-dashboard__period" method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr(AdminShell::SLUG); ?>">
            <label>
                <span><?php echo esc_html(self::label('Year', 'السنة')); ?></span>
                <select name="dashboard_year" onchange="this.form.submit()">
                    <?php for ($candidate = $currentYear - 5; $candidate <= $currentYear + 2; $candidate++) : ?>
                        <option value="<?php echo esc_attr((string) $candidate); ?>" <?php selected($year, $candidate); ?>><?php echo esc_html((string) $candidate); ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <label>
                <span><?php echo esc_html(self::label('Month', 'الشهر')); ?></span>
                <select name="dashboard_month" onchange="this.form.submit()">
                    <?php foreach (self::monthLabels() as $number => $label) : ?>
                        <option value="<?php echo esc_attr((string) $number); ?>" <?php selected($month, $number); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
        <?php
    }

    /** @return array<int,string> */
    private static function monthLabels(): array
    {
        if (TranslationCatalog::currentLanguage() === 'ar') {
            return [
                1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
                5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
                9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
            ];
        }
        return [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];
    }

    private static function renderContractsCard(int $customers, int $suppliers): void
    {
        ?>
        <article class="safecontracts-monthly-card safecontracts-monthly-card--contracts">
            <div class="safecontracts-monthly-card__title"><?php echo esc_html(self::label('Contracts', 'العقود')); ?></div>
            <div class="safecontracts-monthly-card__split">
                <a class="safecontracts-monthly-card__half safecontracts-monthly-card__half--customer" href="<?php echo esc_url(self::contractsUrl('customer')); ?>">
                    <span><?php echo esc_html(self::label('Customers', 'عملاء')); ?></span>
                    <strong><?php echo esc_html((string) $customers); ?></strong>
                    <small><?php echo esc_html(self::label('Customer contracts', 'عقود العملاء')); ?></small>
                </a>
                <a class="safecontracts-monthly-card__half safecontracts-monthly-card__half--supplier" href="<?php echo esc_url(self::contractsUrl('supplier')); ?>">
                    <span><?php echo esc_html(self::label('Suppliers', 'موردين')); ?></span>
                    <strong><?php echo esc_html((string) $suppliers); ?></strong>
                    <small><?php echo esc_html(self::label('Supplier contracts', 'عقود الموردين')); ?></small>
                </a>
            </div>
        </article>
        <?php
    }

    /** @param array<string,array<string,array{contracts:int,base:string,payments:int,due_count:int,scheduled:string,settled:string,outstanding:string}>> $rows */
    private static function renderReceivableCard(array $rows): void
    {
        ?>
        <article class="safecontracts-monthly-card safecontracts-monthly-card--receivable">
            <div class="safecontracts-monthly-card__title"><?php echo esc_html(self::label('Receivable this month', 'المستحق لنا هذا الشهر')); ?></div>
            <div class="safecontracts-monthly-card__split">
                <div class="safecontracts-monthly-card__half">
                    <span><?php echo esc_html(self::label('Due payments', 'دفعات مستحقة')); ?></span>
                    <strong><?php echo esc_html((string) self::directionPaymentCount($rows, FinancialDirection::RECEIVABLE)); ?></strong>
                    <small><?php echo esc_html(self::label('payment(s)', 'عدد الدفعات')); ?></small>
                </div>
                <div class="safecontracts-monthly-card__half">
                    <span><?php echo esc_html(self::label('Expected payment', 'متوقع الدفع')); ?></span>
                    <?php self::moneyLines($rows, FinancialDirection::RECEIVABLE, 'outstanding'); ?>
                    <small><?php echo esc_html(self::label('Expected receivable balance', 'إجمالي المتوقع تحصيله')); ?></small>
                </div>
            </div>
        </article>
        <?php
    }

    /** @param array<string,array<string,array{contracts:int,base:string,payments:int,due_count:int,scheduled:string,settled:string,outstanding:string}>> $rows */
    private static function renderPayableCard(array $rows): void
    {
        ?>
        <article class="safecontracts-monthly-card safecontracts-monthly-card--payable">
            <div class="safecontracts-monthly-card__title"><?php echo esc_html(self::label('Payable this month', 'المستحق علينا هذا الشهر')); ?></div>
            <div class="safecontracts-monthly-card__split">
                <div class="safecontracts-monthly-card__half">
                    <span><?php echo esc_html(self::label('Amounts paid', 'مبالغ مسددة')); ?></span>
                    <?php self::moneyLines($rows, FinancialDirection::PAYABLE, 'settled'); ?>
                    <small><?php echo esc_html(self::label('Total payments already paid', 'مجموع الدفعات التي تم سدادها')); ?></small>
                </div>
                <div class="safecontracts-monthly-card__half">
                    <span><?php echo esc_html(self::label('Amounts still due', 'دفعات مستحقة')); ?></span>
                    <?php self::moneyLines($rows, FinancialDirection::PAYABLE, 'outstanding'); ?>
                    <small><?php echo esc_html(self::label('Total outstanding', 'إجمالي المستحق')); ?></small>
                </div>
            </div>
        </article>
        <?php
    }

    /** @param array<string,array<string,array{contracts:int,base:string,payments:int,due_count:int,scheduled:string,settled:string,outstanding:string}>> $rows */
    private static function renderGeneralAccountCard(array $rows): void
    {
        ?>
        <article class="safecontracts-monthly-card safecontracts-monthly-card--general">
            <div class="safecontracts-monthly-card__title"><?php echo esc_html(self::label('General account', 'الحساب العام')); ?></div>
            <div class="safecontracts-monthly-card__general-values">
                <?php if ($rows === []) : ?><strong>0.00</strong><?php endif; ?>
                <?php foreach ($rows as $currency => $directions) : ?>
                    <?php
                    $base = '0.0000';
                    $settled = '0.0000';
                    foreach ([FinancialDirection::RECEIVABLE, FinancialDirection::PAYABLE] as $direction) {
                        $row = $directions[$direction] ?? self::zeroBucket();
                        $base = self::add($base, $row['base']);
                        $settled = self::add($settled, $row['settled']);
                    }
                    $balance = ContractMoney::difference($base, $settled);
                    ?>
                    <strong class="<?php echo esc_attr(str_starts_with($balance, '-') ? 'safecontracts-dashboard-v2__net--payable' : 'safecontracts-dashboard-v2__net--receivable'); ?>"><?php echo esc_html(self::signedMoney($balance, $currency)); ?></strong>
                <?php endforeach; ?>
            </div>
            <small><?php echo esc_html(self::label('Total contract value in the selected month minus amounts already settled.', 'إجمالي قيمة العقود في الشهر المحدد ناقص إجمالي المبالغ التي تم سدادها.')); ?></small>
        </article>
        <?php
    }

    private static function renderQuickAdd(): void
    {
        $items = [];
        if (current_user_can(Capabilities::CREATE_CUSTOMERS)) {
            $items[] = [self::label('Add customer', 'إضافة عميل'), self::pageUrl(CustomersPage::SLUG), 'dashicons-businessperson'];
        }
        if (current_user_can(Capabilities::CREATE_CONTRACTS)) {
            $items[] = [self::label('Add contract', 'إضافة عقد'), self::pageUrl(ContractsPage::SLUG), 'dashicons-media-document'];
        }
        if (current_user_can(Capabilities::CREATE_SUPPLIERS)) {
            $items[] = [self::label('Add supplier', 'إضافة مورد'), self::pageUrl(SuppliersPage::SLUG), 'dashicons-store'];
        }
        if ($items === []) {
            return;
        }
        ?>
        <details class="safecontracts-monthly-dashboard__quick-add">
            <summary aria-label="<?php echo esc_attr(self::label('Add new', 'إضافة جديدة')); ?>">+</summary>
            <div class="safecontracts-monthly-dashboard__quick-menu">
                <?php foreach ($items as [$label, $url, $icon]) : ?>
                    <a href="<?php echo esc_url($url); ?>"><span class="dashicons <?php echo esc_attr($icon); ?>" aria-hidden="true"></span><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </div>
        </details>
        <?php
    }

    /** @param list<array<string,mixed>> $contracts @param list<array<string,mixed>> $payments @return array<string,array<string,array{contracts:int,base:string,payments:int,due_count:int,scheduled:string,settled:string,outstanding:string}>> */
    private static function totals(array $contracts, array $payments): array
    {
        $rows = [];
        foreach ($contracts as $row) {
            $direction = (string) ($row['financial_direction'] ?? '');
            if (! in_array($direction, [FinancialDirection::RECEIVABLE, FinancialDirection::PAYABLE], true)) {
                continue;
            }
            $currency = self::currency((string) ($row['currency_code'] ?? ''));
            self::bucket($rows, $currency, $direction);
            $rows[$currency][$direction]['contracts']++;
            $rows[$currency][$direction]['base'] = self::add($rows[$currency][$direction]['base'], (string) ($row['base_value'] ?? '0'));
        }
        foreach ($payments as $row) {
            $direction = (string) ($row['financial_direction'] ?? '');
            if (! in_array($direction, [FinancialDirection::RECEIVABLE, FinancialDirection::PAYABLE], true)) {
                continue;
            }
            $currency = self::currency((string) ($row['currency_code'] ?? ''));
            self::bucket($rows, $currency, $direction);
            $rows[$currency][$direction]['payments']++;
            $rows[$currency][$direction]['scheduled'] = self::add($rows[$currency][$direction]['scheduled'], (string) ($row['original_amount'] ?? '0'));
            $rows[$currency][$direction]['settled'] = self::add($rows[$currency][$direction]['settled'], (string) ($row['paid_amount'] ?? '0'));
            $remaining = ContractMoney::normalizeNonNegative((string) ($row['remaining_amount'] ?? '0'));
            $rows[$currency][$direction]['outstanding'] = ContractMoney::add($rows[$currency][$direction]['outstanding'], $remaining);
            if (ContractMoney::compare($remaining, '0.0000') > 0) {
                $rows[$currency][$direction]['due_count']++;
            }
        }
        ksort($rows);
        return $rows;
    }

    /** @param array<string,array<string,array{contracts:int,base:string,payments:int,due_count:int,scheduled:string,settled:string,outstanding:string}>> $rows */
    private static function directionPaymentCount(array $rows, string $direction): int
    {
        $count = 0;
        foreach ($rows as $directions) {
            $count += (int) (($directions[$direction] ?? self::zeroBucket())['due_count']);
        }
        return $count;
    }

    /** @param array<string,array<string,array{contracts:int,base:string,payments:int,due_count:int,scheduled:string,settled:string,outstanding:string}>> $rows */
    private static function moneyLines(array $rows, string $direction, string $field): void
    {
        $printed = false;
        foreach ($rows as $currency => $directions) {
            $row = $directions[$direction] ?? self::zeroBucket();
            $value = (string) ($row[$field] ?? '0.0000');
            if (ContractMoney::compare(ContractMoney::normalizeNonNegative($value), '0.0000') === 0 && count($rows) > 1) {
                continue;
            }
            $printed = true;
            ?><strong><?php echo esc_html(self::money($value, $currency)); ?></strong><?php
        }
        if (! $printed) {
            ?><strong>0.00</strong><?php
        }
    }

    /** @param array<string,array<string,array{contracts:int,base:string,payments:int,due_count:int,scheduled:string,settled:string,outstanding:string}>> $rows */
    private static function lane(string $direction, array $rows): void
    {
        $receivable = $direction === FinancialDirection::RECEIVABLE;
        $class = $receivable ? 'receivable' : 'payable';
        ?>
        <section class="safecontracts-dashboard-v2__lane safecontracts-dashboard-v2__lane--<?php echo esc_attr($class); ?>">
            <div class="safecontracts-dashboard-v2__lane-heading">
                <div>
                    <h3><?php echo esc_html($receivable ? self::label('Receivable contracts', 'العقود المستحقة لنا') : self::label('Payable contracts', 'العقود المستحقة علينا')); ?></h3>
                    <p><?php echo esc_html($receivable ? self::label('Money customers will pay us', 'مبالغ نتوقع تحصيلها من العملاء') : self::label('Money we will pay suppliers', 'مبالغ سنقوم بسدادها للموردين')); ?></p>
                </div>
                <a class="button" href="<?php echo esc_url(self::contractsUrl($receivable ? 'customer' : 'supplier')); ?>"><?php echo esc_html(self::label('View all', 'عرض الكل')); ?></a>
            </div>
            <div class="safecontracts-dashboard-v2__lane-grid">
                <?php if ($rows === []) : ?><p><?php echo esc_html(self::label('No records in the selected month.', 'لا توجد سجلات في الشهر المحدد.')); ?></p><?php endif; ?>
                <?php foreach ($rows as $currency => $directions) : $row = $directions[$direction] ?? self::zeroBucket(); ?>
                    <article class="safecontracts-dashboard-v2__money-card">
                        <h4><?php echo esc_html($currency); ?></h4>
                        <dl>
                            <div><dt><?php echo esc_html(self::label('Contracts', 'العقود')); ?></dt><dd><?php echo esc_html((string) $row['contracts']); ?></dd></div>
                            <div><dt><?php echo esc_html(self::label('Base contract total', 'إجمالي قيمة العقود')); ?></dt><dd><?php echo esc_html(self::directionMoney($row['base'], $currency, $direction)); ?></dd></div>
                            <div><dt><?php echo esc_html(self::label('Scheduled total', 'إجمالي الدفعات المجدولة')); ?></dt><dd><?php echo esc_html(self::directionMoney($row['scheduled'], $currency, $direction)); ?></dd></div>
                            <div><dt><?php echo esc_html($receivable ? self::label('Collected', 'تم تحصيله') : self::label('Paid', 'تم سداده')); ?></dt><dd><?php echo esc_html(self::directionMoney($row['settled'], $currency, $direction)); ?></dd></div>
                            <div><dt><?php echo esc_html(self::label('Outstanding', 'المتبقي')); ?></dt><dd><?php echo esc_html(self::directionMoney($row['outstanding'], $currency, $direction)); ?></dd></div>
                        </dl>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    /** @param array<string,array{contracts:int,base:string,payments:int,due_count:int,scheduled:string,settled:string,outstanding:string}> $directions */
    private static function netCard(string $currency, array $directions): void
    {
        $r = $directions[FinancialDirection::RECEIVABLE] ?? self::zeroBucket();
        $p = $directions[FinancialDirection::PAYABLE] ?? self::zeroBucket();
        ?>
        <article class="safecontracts-dashboard-v2__net-card">
            <h4><?php echo esc_html($currency); ?></h4>
            <?php self::netLine(self::label('Contract value', 'قيمة العقود'), $r['base'], $p['base'], $currency); ?>
            <?php self::netLine(self::label('Scheduled', 'المجدول'), $r['scheduled'], $p['scheduled'], $currency); ?>
            <?php self::netLine(self::label('Settled', 'المسدد'), $r['settled'], $p['settled'], $currency); ?>
            <?php self::netLine(self::label('Outstanding', 'المتبقي'), $r['outstanding'], $p['outstanding'], $currency); ?>
        </article>
        <?php
    }

    private static function netLine(string $label, string $receivable, string $payable, string $currency): void
    {
        $net = ContractMoney::difference($receivable, $payable);
        $class = str_starts_with($net, '-') ? 'payable' : ($net !== '0.0000' ? 'receivable' : 'neutral');
        ?>
        <div class="safecontracts-dashboard-v2__net-line">
            <span><?php echo esc_html($label); ?></span>
            <small class="safecontracts-financial-amount--receivable"><?php echo esc_html(self::directionMoney($receivable, $currency, FinancialDirection::RECEIVABLE)); ?></small>
            <small class="safecontracts-financial-amount--payable"><?php echo esc_html(self::directionMoney($payable, $currency, FinancialDirection::PAYABLE)); ?></small>
            <strong class="safecontracts-dashboard-v2__net--<?php echo esc_attr($class); ?>"><?php echo esc_html(self::label('Net value', 'الصافي')) . ': ' . esc_html(self::signedMoney($net, $currency)); ?></strong>
        </div>
        <?php
    }

    /** @param array<string,array<string,array{contracts:int,base:string,payments:int,due_count:int,scheduled:string,settled:string,outstanding:string}>> $rows */
    private static function bucket(array &$rows, string $currency, string $direction): void
    {
        $rows[$currency] ??= [];
        $rows[$currency][$direction] ??= self::zeroBucket();
    }

    /** @return array{contracts:int,base:string,payments:int,due_count:int,scheduled:string,settled:string,outstanding:string} */
    private static function zeroBucket(): array
    {
        return [
            'contracts' => 0,
            'base' => '0.0000',
            'payments' => 0,
            'due_count' => 0,
            'scheduled' => '0.0000',
            'settled' => '0.0000',
            'outstanding' => '0.0000',
        ];
    }

    private static function contractsUrl(?string $type = null): string
    {
        $args = ['page' => ContractsPage::SLUG];
        if ($type !== null) {
            $args['counterparty_type'] = $type;
        }
        return add_query_arg($args, admin_url('admin.php'));
    }

    private static function pageUrl(string $slug): string
    {
        return add_query_arg(['page' => $slug], admin_url('admin.php'));
    }

    private static function add(string $left, string $right): string
    {
        return ContractMoney::add(
            ContractMoney::normalizeNonNegative($left),
            ContractMoney::normalizeNonNegative($right)
        );
    }

    private static function directionMoney(string $value, string $currency, string $direction): string
    {
        return ($direction === FinancialDirection::PAYABLE ? '− ' : '+ ') . self::money($value, $currency);
    }

    private static function signedMoney(string $value, string $currency): string
    {
        $negative = str_starts_with($value, '-');
        $absolute = $negative ? substr($value, 1) : $value;
        if (ContractMoney::compare($absolute, '0.0000') === 0) {
            return self::money($absolute, $currency);
        }
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
