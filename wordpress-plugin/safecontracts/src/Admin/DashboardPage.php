<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Contracts\ContractArchiveService;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Payments\FinancialDirection;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Translations\TranslationCatalog;
use Throwable;

final class DashboardPage
{
    public const ARCHIVE_ACTION = 'safecontracts_archive_contract_dashboard';

    public static function handleArchive(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to delete contracts from the dashboard.', 'safecontracts'));
        }

        $contractId = max(0, (int) ($_POST['contract_id'] ?? 0));
        check_admin_referer(self::ARCHIVE_ACTION . '_' . $contractId);
        $status = 'archived';
        try {
            (new ContractArchiveService())->archive($contractId);
        } catch (Throwable $error) {
            unset($error);
            $status = 'archive_failed';
        }

        wp_safe_redirect(add_query_arg([
            'page' => AdminShell::SLUG,
            'safecontracts_status' => $status,
        ], admin_url('admin.php')));
        exit;
    }

    public static function renderContent(): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            wp_die(__('You do not have permission to access SafeContracts.', 'safecontracts'));
        }
        if (! current_user_can(Capabilities::VIEW_ALL) && ! current_user_can(Capabilities::VIEW_ASSIGNED)) {
            ?>
            <section class="safecontracts-dashboard" aria-labelledby="safecontracts-dashboard-title">
                <div class="safecontracts-section-heading">
                    <div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Operational overview', 'safecontracts'); ?></p><h2 id="safecontracts-dashboard-title"><?php echo esc_html__('Dashboard', 'safecontracts'); ?></h2></div>
                </div>
                <section class="safecontracts-admin-card safecontracts-admin-card--security">
                    <h2><?php echo esc_html__('Server-side authorization', 'safecontracts'); ?></h2>
                    <p><?php echo esc_html__('No data scope assigned. Your account can access SafeContracts, but it does not currently have permission to view all data or assigned contract data. Contact a SafeContracts administrator if operational access is required.', 'safecontracts'); ?></p>
                </section>
            </section>
            <?php
            return;
        }

        $filters = DashboardFilters::normalize($_GET);
        $read = new AdminReadRepository();
        $customers = $read->customerOptions();
        $contracts = $read->contractOptions($filters['customer_id']);
        $accountants = current_user_can(Capabilities::VIEW_ALL) ? AdminLookupOptions::accountants() : [];

        $contractFilters = $filters;
        if (! in_array($contractFilters['status'], ['draft', 'active', 'completed', 'cancelled'], true)) {
            $contractFilters['status'] = '';
        }
        $dashboardContracts = array_values(array_filter(
            $read->contracts($contractFilters),
            static fn (array $contract): bool => empty($contract['is_archived'])
        ));

        $paymentFilters = $filters;
        if (in_array($paymentFilters['status'], ['draft', 'active', 'completed', 'cancelled'], true)) {
            $paymentFilters['status'] = '';
        }
        $dashboardPayments = $read->payments($paymentFilters);

        $receivableContracts = self::contractsForDirection($dashboardContracts, FinancialDirection::RECEIVABLE);
        $payableContracts = self::contractsForDirection($dashboardContracts, FinancialDirection::PAYABLE);
        $paymentTotalsByContract = self::paymentTotalsByContract($dashboardPayments);
        $accounting = self::accountingByDirectionAndCurrency($dashboardContracts, $dashboardPayments);
        $collectorAttachments = $read->collectorAttachments($filters, 12);
        ?>
        <section class="safecontracts-dashboard" aria-labelledby="safecontracts-dashboard-title">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Operational overview', 'safecontracts'); ?></p>
                    <h2 id="safecontracts-dashboard-title"><?php echo esc_html__('Dashboard', 'safecontracts'); ?></h2>
                    <p class="description"><?php echo esc_html__('Receivables and payables are kept in separate accounting lanes. Green means money we expect to receive; red means money we must pay.', 'safecontracts'); ?></p>
                </div>
            </div>

            <?php if (! empty($filters['date_range_error'])) : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html__('Invalid period. Use valid dates and make sure the end date is not earlier than the start date.', 'safecontracts'); ?></p></div>
            <?php endif; ?>

            <form class="safecontracts-filter-bar" method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr(AdminShell::SLUG); ?>">
                <label><?php echo esc_html__('Customer', 'safecontracts'); ?>
                    <select name="customer_id">
                        <option value="0"><?php echo esc_html__('All customers', 'safecontracts'); ?></option>
                        <?php foreach ($customers as $customer) : ?><option value="<?php echo esc_attr((string) $customer['id']); ?>" <?php selected($filters['customer_id'], $customer['id']); ?>><?php echo esc_html($customer['name']); ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label><?php echo esc_html__('Contract', 'safecontracts'); ?>
                    <select name="contract_id">
                        <option value="0"><?php echo esc_html__('All contracts', 'safecontracts'); ?></option>
                        <?php foreach ($contracts as $contract) : ?><option value="<?php echo esc_attr((string) $contract['id']); ?>" <?php selected($filters['contract_id'], $contract['id']); ?>><?php echo esc_html($contract['contract_number']); ?></option><?php endforeach; ?>
                    </select>
                </label>
                <?php if (current_user_can(Capabilities::VIEW_ALL)) : ?>
                    <label><?php echo esc_html__('Responsible accountant', 'safecontracts'); ?>
                        <select name="accountant_user_id">
                            <option value="0"><?php echo esc_html__('All responsible accountants', 'safecontracts'); ?></option>
                            <?php foreach ($accountants as $accountant) : ?><option value="<?php echo esc_attr((string) $accountant['id']); ?>" <?php selected((int) $filters['accountant_user_id'], $accountant['id']); ?>><?php echo esc_html($accountant['label']); ?></option><?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
                <label><?php echo esc_html__('Status', 'safecontracts'); ?>
                    <select name="status">
                        <option value=""><?php echo esc_html__('Any status', 'safecontracts'); ?></option>
                        <?php foreach (['active','draft','completed','cancelled','upcoming','due_soon','due','overdue','partially_paid','paid'] as $status) : ?><option value="<?php echo esc_attr($status); ?>" <?php selected($filters['status'], $status); ?>><?php echo esc_html(self::statusLabel($status)); ?></option><?php endforeach; ?>
                    </select>
                </label>
                <?php AdminPeriodFilter::renderFields($filters); ?>
                <button class="button button-primary" type="submit"><?php echo esc_html__('Apply filters', 'safecontracts'); ?></button>
            </form>

            <div class="safecontracts-direction-dashboard">
                <?php self::renderContractLane(FinancialDirection::RECEIVABLE, $receivableContracts, $paymentTotalsByContract); ?>
                <?php self::renderContractLane(FinancialDirection::PAYABLE, $payableContracts, $paymentTotalsByContract); ?>
            </div>

            <section class="safecontracts-accounting-summary" aria-labelledby="safecontracts-accounting-summary-title">
                <div class="safecontracts-section-heading">
                    <div>
                        <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Accounting totals', 'safecontracts'); ?></p>
                        <h2 id="safecontracts-accounting-summary-title"><?php echo esc_html__('Accounting totals by currency', 'safecontracts'); ?></h2>
                        <p class="description"><?php echo esc_html__('Currencies are never added together. Each currency is calculated independently from active contracts and non-archived scheduled payments.', 'safecontracts'); ?></p>
                    </div>
                </div>
                <div class="safecontracts-accounting-direction-grid">
                    <?php self::renderAccountingLane(FinancialDirection::RECEIVABLE, $accounting[FinancialDirection::RECEIVABLE]); ?>
                    <?php self::renderAccountingLane(FinancialDirection::PAYABLE, $accounting[FinancialDirection::PAYABLE]); ?>
                </div>
            </section>

            <section class="safecontracts-admin-card safecontracts-table-card" aria-labelledby="safecontracts-dashboard-collector-attachments-title">
                <div class="safecontracts-section-heading">
                    <div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Collection evidence', 'safecontracts'); ?></p><h2 id="safecontracts-dashboard-collector-attachments-title"><?php echo esc_html__('Collector attachments', 'safecontracts'); ?></h2></div>
                </div>
                <?php if ($collectorAttachments === []) : ?>
                    <p><?php echo esc_html__('No collector attachments match the current dashboard scope and period.', 'safecontracts'); ?></p>
                <?php else : ?>
                    <div class="safecontracts-collector-proof-grid">
                        <?php foreach ($collectorAttachments as $collection) : ?><article class="safecontracts-admin-card"><?php CollectorAttachmentView::render($collection); ?></article><?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <p class="description"><?php echo esc_html__('Attachments are resolved through WordPress Media and inherit the same customer/contract/accountant scope as the collection ledger. Raw filesystem paths are never exposed.', 'safecontracts'); ?></p>
            </section>
        </section>
        <?php
    }

    /** @param list<array<string,mixed>> $contracts @return list<array<string,mixed>> */
    private static function contractsForDirection(array $contracts, string $direction): array
    {
        return array_values(array_filter(
            $contracts,
            static fn (array $contract): bool => (string) ($contract['financial_direction'] ?? '') === $direction
        ));
    }

    /** @param list<array<string,mixed>> $payments @return array<int,array{scheduled:string,settled:string,outstanding:string}> */
    private static function paymentTotalsByContract(array $payments): array
    {
        $totals = [];
        foreach ($payments as $payment) {
            $contractId = (int) ($payment['contract_id'] ?? 0);
            if ($contractId <= 0) {
                continue;
            }
            $totals[$contractId] ??= ['scheduled' => '0.0000', 'settled' => '0.0000', 'outstanding' => '0.0000'];
            $totals[$contractId]['scheduled'] = self::addMoney($totals[$contractId]['scheduled'], (string) ($payment['original_amount'] ?? '0'));
            $totals[$contractId]['settled'] = self::addMoney($totals[$contractId]['settled'], (string) ($payment['paid_amount'] ?? '0'));
            $totals[$contractId]['outstanding'] = self::addMoney($totals[$contractId]['outstanding'], (string) ($payment['remaining_amount'] ?? '0'));
        }
        return $totals;
    }

    /**
     * @param list<array<string,mixed>> $contracts
     * @param list<array<string,mixed>> $payments
     * @return array<string,array<string,array{contract_count:int,contract_total:string,payment_count:int,scheduled:string,settlement_count:int,settled:string,outstanding:string}>>
     */
    private static function accountingByDirectionAndCurrency(array $contracts, array $payments): array
    {
        $result = [FinancialDirection::RECEIVABLE => [], FinancialDirection::PAYABLE => []];
        foreach ($contracts as $contract) {
            $direction = (string) ($contract['financial_direction'] ?? '');
            if (! isset($result[$direction])) {
                continue;
            }
            $currency = strtoupper(trim((string) ($contract['currency_code'] ?? '')));
            $currency = $currency === '' ? '—' : $currency;
            self::ensureAccountingBucket($result[$direction], $currency);
            $result[$direction][$currency]['contract_count']++;
            $result[$direction][$currency]['contract_total'] = self::addMoney($result[$direction][$currency]['contract_total'], (string) ($contract['base_value'] ?? '0'));
        }
        foreach ($payments as $payment) {
            $direction = (string) ($payment['financial_direction'] ?? '');
            if (! isset($result[$direction])) {
                continue;
            }
            $currency = strtoupper(trim((string) ($payment['currency_code'] ?? '')));
            $currency = $currency === '' ? '—' : $currency;
            self::ensureAccountingBucket($result[$direction], $currency);
            $result[$direction][$currency]['payment_count']++;
            $result[$direction][$currency]['scheduled'] = self::addMoney($result[$direction][$currency]['scheduled'], (string) ($payment['original_amount'] ?? '0'));
            $paid = ContractMoney::normalizeNonNegative((string) ($payment['paid_amount'] ?? '0'));
            if (ContractMoney::compare($paid, '0.0000') > 0) {
                $result[$direction][$currency]['settlement_count']++;
            }
            $result[$direction][$currency]['settled'] = ContractMoney::add($result[$direction][$currency]['settled'], $paid);
            $result[$direction][$currency]['outstanding'] = self::addMoney($result[$direction][$currency]['outstanding'], (string) ($payment['remaining_amount'] ?? '0'));
        }
        foreach ($result as &$directionRows) {
            ksort($directionRows);
        }
        unset($directionRows);
        return $result;
    }

    /** @param array<string,array{contract_count:int,contract_total:string,payment_count:int,scheduled:string,settlement_count:int,settled:string,outstanding:string}> $rows */
    private static function ensureAccountingBucket(array &$rows, string $currency): void
    {
        $rows[$currency] ??= [
            'contract_count' => 0,
            'contract_total' => '0.0000',
            'payment_count' => 0,
            'scheduled' => '0.0000',
            'settlement_count' => 0,
            'settled' => '0.0000',
            'outstanding' => '0.0000',
        ];
    }

    /** @param list<array<string,mixed>> $contracts @param array<int,array{scheduled:string,settled:string,outstanding:string}> $paymentTotals */
    private static function renderContractLane(string $direction, array $contracts, array $paymentTotals): void
    {
        $receivable = $direction === FinancialDirection::RECEIVABLE;
        $type = $receivable ? 'customer' : 'supplier';
        $title = $receivable ? __('Receivable contracts', 'safecontracts') : __('Payable contracts', 'safecontracts');
        $description = $receivable ? __('Money customers will pay us', 'safecontracts') : __('Money we will pay suppliers', 'safecontracts');
        $class = $receivable ? 'receivable' : 'payable';
        ?>
        <section class="safecontracts-admin-card safecontracts-direction-column safecontracts-direction-column--<?php echo esc_attr($class); ?>">
            <div class="safecontracts-direction-column__heading">
                <div><h2><?php echo esc_html($title); ?></h2><p><?php echo esc_html($description); ?></p></div>
                <a class="button safecontracts-payment-action--<?php echo esc_attr($class); ?>" href="<?php echo esc_url(self::contractsTypeUrl($type)); ?>"><?php echo esc_html__('View all', 'safecontracts'); ?></a>
            </div>
            <?php if ($contracts === []) : ?>
                <p><?php echo esc_html__('No contracts in this direction match the current filters.', 'safecontracts'); ?></p>
            <?php else : ?>
                <div class="safecontracts-contract-card-list">
                    <?php foreach (array_slice($contracts, 0, 25) as $contract) : ?>
                        <?php $contractId = (int) ($contract['id'] ?? 0); $totals = $paymentTotals[$contractId] ?? ['scheduled' => '0.0000', 'settled' => '0.0000', 'outstanding' => '0.0000']; ?>
                        <a class="safecontracts-contract-card safecontracts-contract-card--<?php echo esc_attr($class); ?>" href="<?php echo esc_url(self::contractUrl($contract)); ?>">
                            <span class="safecontracts-contract-card__top"><strong><?php echo esc_html((string) ($contract['contract_number'] ?? '')); ?></strong><span><?php echo esc_html(self::statusLabel((string) ($contract['status'] ?? ''))); ?></span></span>
                            <span class="safecontracts-contract-card__party"><?php echo esc_html((string) ($contract['counterparty_name'] ?? '')); ?></span>
                            <span class="safecontracts-contract-card__metrics">
                                <span><small><?php echo esc_html__('Base value', 'safecontracts'); ?></small><strong><?php echo esc_html(self::money((string) ($contract['base_value'] ?? '0'), (string) ($contract['currency_code'] ?? ''))); ?></strong></span>
                                <span><small><?php echo esc_html__('Scheduled total', 'safecontracts'); ?></small><strong><?php echo esc_html(self::money($totals['scheduled'], (string) ($contract['currency_code'] ?? ''))); ?></strong></span>
                                <span><small><?php echo esc_html($receivable ? __('Collected', 'safecontracts') : __('Paid', 'safecontracts')); ?></small><strong><?php echo esc_html(self::money($totals['settled'], (string) ($contract['currency_code'] ?? ''))); ?></strong></span>
                                <span><small><?php echo esc_html__('Outstanding', 'safecontracts'); ?></small><strong><?php echo esc_html(self::money($totals['outstanding'], (string) ($contract['currency_code'] ?? ''))); ?></strong></span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    /** @param array<string,array{contract_count:int,contract_total:string,payment_count:int,scheduled:string,settlement_count:int,settled:string,outstanding:string}> $rows */
    private static function renderAccountingLane(string $direction, array $rows): void
    {
        $receivable = $direction === FinancialDirection::RECEIVABLE;
        $class = $receivable ? 'receivable' : 'payable';
        $title = $receivable ? __('Receivable totals', 'safecontracts') : __('Payable totals', 'safecontracts');
        ?>
        <section class="safecontracts-admin-card safecontracts-accounting-lane safecontracts-accounting-lane--<?php echo esc_attr($class); ?>">
            <h3><?php echo esc_html($title); ?></h3>
            <?php if ($rows === []) : ?><p><?php echo esc_html__('No accounting totals are available for this direction.', 'safecontracts'); ?></p><?php endif; ?>
            <?php foreach ($rows as $currency => $row) : ?>
                <div class="safecontracts-accounting-currency">
                    <h4><?php echo esc_html($currency); ?></h4>
                    <div class="safecontracts-accounting-card-grid">
                        <?php self::summaryMetric(__('Contracts count', 'safecontracts'), (string) $row['contract_count']); ?>
                        <?php self::summaryMetric(__('Base contract total', 'safecontracts'), self::money($row['contract_total'], $currency)); ?>
                        <?php self::summaryMetric(__('Scheduled payments count', 'safecontracts'), (string) $row['payment_count']); ?>
                        <?php self::summaryMetric(__('Scheduled total', 'safecontracts'), self::money($row['scheduled'], $currency)); ?>
                        <?php self::summaryMetric($receivable ? __('Collections / settlements count', 'safecontracts') : __('Payments / settlements count', 'safecontracts'), (string) $row['settlement_count']); ?>
                        <?php self::summaryMetric($receivable ? __('Collected from customers', 'safecontracts') : __('Paid to suppliers', 'safecontracts'), self::money($row['settled'], $currency)); ?>
                        <?php self::summaryMetric(__('Outstanding', 'safecontracts'), self::money($row['outstanding'], $currency)); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
        <?php
    }

    private static function summaryMetric(string $label, string $value): void
    {
        ?><article class="safecontracts-accounting-metric"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($value); ?></strong></article><?php
    }

    /** @param array<string,mixed> $contract */
    private static function contractUrl(array $contract): string
    {
        $type = (string) ($contract['counterparty_type'] ?? '') === 'supplier' ? 'supplier' : 'customer';
        return add_query_arg([
            'page' => ContractsPage::SLUG,
            'counterparty_type' => $type,
            'contract_id' => (int) ($contract['id'] ?? 0),
        ], admin_url('admin.php'));
    }

    private static function contractsTypeUrl(string $type): string
    {
        return add_query_arg([
            'page' => ContractsPage::SLUG,
            'counterparty_type' => $type === 'supplier' ? 'supplier' : 'customer',
        ], admin_url('admin.php'));
    }

    private static function addMoney(string $left, string $right): string
    {
        return ContractMoney::add(
            ContractMoney::normalizeNonNegative($left),
            ContractMoney::normalizeNonNegative($right)
        );
    }

    private static function money(string $value, string $currency = ''): string
    {
        $normalized = ContractMoney::normalizeNonNegative($value);
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0000');
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole) ?? $whole;
        $formatted = $whole . '.' . substr(str_pad($fraction, 2, '0'), 0, 2);
        $currency = trim($currency);
        return $currency === '' || $currency === '—' ? $formatted : $currency . ' ' . $formatted;
    }

    private static function statusLabel(string $status): string
    {
        return TranslationCatalog::text(ucwords(str_replace('_', ' ', $status)));
    }
}
