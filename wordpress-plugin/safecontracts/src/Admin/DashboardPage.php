<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Contracts\ContractArchiveService;
use SafeContracts\Finance\FinanceOverviewService;
use SafeContracts\Finance\FinancialDirection;
use SafeContracts\Payments\PaymentStatus;
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
                    <div>
                        <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Operational overview', 'safecontracts'); ?></p>
                        <h2 id="safecontracts-dashboard-title"><?php echo esc_html__('Dashboard', 'safecontracts'); ?></h2>
                    </div>
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
        $kpis = $read->kpis($filters);
        $customers = $read->customerOptions();
        $contracts = $read->contractOptions($filters['customer_id']);
        $tableFilters = $filters;
        if (! in_array($tableFilters['status'], ['draft', 'active', 'completed', 'cancelled'], true)) {
            $tableFilters['status'] = '';
        }
        $dashboardContracts = array_values(array_filter(
            $read->contracts($tableFilters),
            static fn (array $contract): bool => empty($contract['is_archived'])
        ));
        $dashboardContracts = array_slice($dashboardContracts, 0, 25);
        $collectorAttachments = $read->collectorAttachments($filters, 12);

        $finance = null;
        if (self::canViewFinance() && empty($filters['date_range_error'])) {
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
                $finance = null;
            }
        }
        ?>
        <section class="safecontracts-dashboard" aria-labelledby="safecontracts-dashboard-title">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Operational overview', 'safecontracts'); ?></p>
                    <h2 id="safecontracts-dashboard-title"><?php echo esc_html__('Dashboard', 'safecontracts'); ?></h2>
                </div>
                <?php if (self::canViewFinance()) : ?>
                    <a class="button" href="<?php echo esc_url(add_query_arg(['page' => FinancePage::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Open Finance workspace', 'safecontracts'); ?></a>
                <?php endif; ?>
            </div>

            <?php if (! empty($filters['date_range_error'])) : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html__('Invalid period. Use valid dates and make sure the end date is not earlier than the start date.', 'safecontracts'); ?></p></div>
            <?php endif; ?>
            <form class="safecontracts-filter-bar" method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr(AdminShell::SLUG); ?>">
                <label><?php echo esc_html__('Customer', 'safecontracts'); ?>
                    <select name="customer_id">
                        <option value="0"><?php echo esc_html__('All customers / counterparties', 'safecontracts'); ?></option>
                        <?php foreach ($customers as $customer) : ?>
                            <option value="<?php echo esc_attr((string) $customer['id']); ?>" <?php selected($filters['customer_id'], $customer['id']); ?>><?php echo esc_html($customer['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><?php echo esc_html__('Contract', 'safecontracts'); ?>
                    <select name="contract_id">
                        <option value="0"><?php echo esc_html__('All contracts', 'safecontracts'); ?></option>
                        <?php foreach ($contracts as $contract) : ?>
                            <option value="<?php echo esc_attr((string) $contract['id']); ?>" <?php selected($filters['contract_id'], $contract['id']); ?>><?php echo esc_html($contract['contract_number']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if (current_user_can(Capabilities::VIEW_ALL)) : ?>
                    <label><?php echo esc_html__('Accountant ID', 'safecontracts'); ?><input type="number" min="0" name="accountant_user_id" value="<?php echo esc_attr((string) $filters['accountant_user_id']); ?>"></label>
                <?php endif; ?>
                <label><?php echo esc_html__('Status', 'safecontracts'); ?>
                    <select name="status">
                        <option value=""><?php echo esc_html__('Any status', 'safecontracts'); ?></option>
                        <?php foreach (array_values(array_unique(array_merge(['active','draft','completed','cancelled'], PaymentStatus::all()))) as $status) : ?>
                            <option value="<?php echo esc_attr($status); ?>" <?php selected($filters['status'], $status); ?>><?php echo esc_html(self::statusLabel($status)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php AdminPeriodFilter::renderFields($filters); ?>
                <button class="button button-primary" type="submit"><?php echo esc_html__('Apply filters', 'safecontracts'); ?></button>
            </form>

            <div class="safecontracts-kpi-grid">
                <?php self::kpi(__('Contracts in scope', 'safecontracts'), (string) $kpis['contract_count']); ?>
            </div>

            <?php if (is_array($finance)) : ?>
                <?php self::renderFinanceSummary((array) ($finance['summary'] ?? [])); ?>
                <?php self::renderActionCenter((array) ($finance['action_center'] ?? [])); ?>
            <?php elseif (self::canViewFinance()) : ?>
                <section class="safecontracts-admin-card"><p><?php echo esc_html__('Financial intelligence is temporarily unavailable for this dashboard scope. Contract operations remain available below.', 'safecontracts'); ?></p></section>
            <?php endif; ?>
            <p class="description"><?php echo esc_html__('Financial cards are server-authorized and stay separated by Accounts Payable / Accounts Receivable and currency. Contract lists use start date (or creation date when start date is empty). Collector attachments are receivable/customer history and use collection date.', 'safecontracts'); ?></p>

            <section class="safecontracts-admin-card safecontracts-table-card safecontracts-dashboard-contracts" aria-labelledby="safecontracts-dashboard-contracts-title">
                <div class="safecontracts-section-heading">
                    <div>
                        <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Quick management', 'safecontracts'); ?></p>
                        <h2 id="safecontracts-dashboard-contracts-title"><?php echo esc_html__('Active contracts', 'safecontracts'); ?></h2>
                    </div>
                </div>
                <?php if ($dashboardContracts === []) : ?>
                    <p><?php echo esc_html__('No active contracts match the current dashboard filters.', 'safecontracts'); ?></p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead><tr>
                            <th><?php echo esc_html__('Contract', 'safecontracts'); ?></th>
                            <th><?php echo esc_html__('Counterparty', 'safecontracts'); ?></th>
                            <th><?php echo esc_html__('Direction', 'safecontracts'); ?></th>
                            <th><?php echo esc_html__('Status', 'safecontracts'); ?></th>
                            <th><?php echo esc_html__('Base value', 'safecontracts'); ?></th>
                            <th><?php echo esc_html__('Actions', 'safecontracts'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($dashboardContracts as $contract) :
                            $direction = (string) ($contract['financial_direction'] ?? '');
                            $currency = (string) ($contract['currency_code'] ?? '');
                            ?>
                            <tr>
                                <td><?php echo esc_html((string) $contract['contract_number']); ?></td>
                                <td><strong><?php echo esc_html((string) ($contract['counterparty_name'] ?? '')); ?></strong><br><small><?php echo esc_html(self::counterpartyTypeLabel((string) ($contract['counterparty_type'] ?? ''))); ?></small></td>
                                <td><?php echo esc_html(self::directionLabel($direction)); ?></td>
                                <td><?php echo esc_html(self::statusLabel((string) $contract['status'])); ?></td>
                                <td><?php echo esc_html(self::money($contract['base_value'], $currency)); ?></td>
                                <td>
                                    <div class="safecontracts-dashboard-table-actions">
                                        <a class="button button-small" href="<?php echo esc_url(add_query_arg(['page' => ContractsPage::SLUG, 'contract_id' => (int) $contract['id']], admin_url('admin.php'))); ?>"><?php echo esc_html__('Open', 'safecontracts'); ?></a>
                                        <?php if (current_user_can(Capabilities::MANAGE_SYSTEM)) : ?>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-safecontracts-delete-form data-delete-message="<?php echo esc_attr__('Delete this contract from active operations? Its financial, collection, history and audit records will be preserved.', 'safecontracts'); ?>">
                                                <input type="hidden" name="action" value="<?php echo esc_attr(self::ARCHIVE_ACTION); ?>">
                                                <input type="hidden" name="contract_id" value="<?php echo esc_attr((string) $contract['id']); ?>">
                                                <?php wp_nonce_field(self::ARCHIVE_ACTION . '_' . (int) $contract['id']); ?>
                                                <button type="submit" class="button button-small safecontracts-delete-button"><?php echo esc_html__('Delete', 'safecontracts'); ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="description"><?php echo esc_html__('Customer and Supplier contracts share this operational list without fabricating a Customer relationship. Delete is a safe archive action; financial, collection, history and audit records are preserved.', 'safecontracts'); ?></p>
                <?php endif; ?>
            </section>

            <section class="safecontracts-admin-card safecontracts-table-card" aria-labelledby="safecontracts-dashboard-collector-attachments-title">
                <div class="safecontracts-section-heading">
                    <div>
                        <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Receivable evidence', 'safecontracts'); ?></p>
                        <h2 id="safecontracts-dashboard-collector-attachments-title"><?php echo esc_html__('Collector attachments', 'safecontracts'); ?></h2>
                    </div>
                </div>
                <?php if ($collectorAttachments === []) : ?>
                    <p><?php echo esc_html__('No collector attachments match the current dashboard scope and period.', 'safecontracts'); ?></p>
                <?php else : ?>
                    <div class="safecontracts-collector-proof-grid">
                        <?php foreach ($collectorAttachments as $collection) : ?>
                            <article class="safecontracts-admin-card"><?php CollectorAttachmentView::render($collection); ?></article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <p class="description"><?php echo esc_html__('Collector attachments belong to Customer receivable history only. They inherit the same customer/contract/accountant scope, resolve through WordPress Media, and never expose raw filesystem paths.', 'safecontracts'); ?></p>
            </section>
        </section>
        <?php
    }

    /** @param list<array<string,mixed>> $rows */
    private static function renderFinanceSummary(array $rows): void
    {
        if ($rows === []) {
            return;
        }
        ?>
        <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Financial position', 'safecontracts'); ?></p><h2><?php echo esc_html__('AP / AR by currency', 'safecontracts'); ?></h2></div></div>
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

    /** @param list<array<string,mixed>> $items */
    private static function renderActionCenter(array $items): void
    {
        if ($items === []) {
            return;
        }
        ?>
        <section class="safecontracts-admin-card safecontracts-finance-panel" aria-labelledby="safecontracts-dashboard-action-center-title">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Needs attention', 'safecontracts'); ?></p><h2 id="safecontracts-dashboard-action-center-title"><?php echo esc_html__('Finance Action Center', 'safecontracts'); ?></h2></div></div>
            <div class="safecontracts-action-list">
                <?php foreach (array_slice($items, 0, 6) as $item) :
                    $direction = (string) ($item['direction'] ?? '');
                    $currency = (string) ($item['currency_code'] ?? 'UNSET');
                    $query = ['page' => FinancePage::SLUG, 'direction' => $direction, 'currency_code' => $currency];
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
        </section>
        <?php
    }

    private static function kpi(string $label, string $value, bool $alert = false): void
    {
        ?><article class="safecontracts-kpi<?php echo $alert ? ' safecontracts-kpi--alert' : ''; ?>"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($value); ?></strong></article><?php
    }

    private static function miniMetric(string $label, string $value, bool $alert = false): void
    {
        ?><div class="safecontracts-finance-mini<?php echo $alert ? ' safecontracts-finance-mini--alert' : ''; ?>"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($value); ?></strong></div><?php
    }

    private static function money(mixed $value, string $currencyToken = ''): string
    {
        $amount = number_format((float) $value, 2, '.', ',');
        $currencyToken = trim($currencyToken);
        if ($currencyToken === '') {
            return $amount;
        }
        $locale = function_exists('get_user_locale') ? strtolower((string) get_user_locale()) : 'en_us';
        return str_starts_with($locale, 'ar') ? $amount . ' ' . $currencyToken : $currencyToken . ' ' . $amount;
    }

    private static function statusLabel(string $status): string
    {
        return TranslationCatalog::text(ucwords(str_replace('_', ' ', $status)));
    }

    private static function directionLabel(string $direction): string
    {
        return $direction === FinancialDirection::PAYABLE
            ? __('Accounts Payable', 'safecontracts')
            : __('Accounts Receivable', 'safecontracts');
    }

    private static function counterpartyTypeLabel(string $type): string
    {
        return $type === 'supplier' ? __('Supplier', 'safecontracts') : __('Customer', 'safecontracts');
    }

    private static function actionLabel(string $kind, string $direction): string
    {
        $subject = $direction === FinancialDirection::PAYABLE ? __('Payables', 'safecontracts') : __('Receivables', 'safecontracts');
        return match ($kind) {
            'overdue' => sprintf(__('%s overdue', 'safecontracts'), $subject),
            'due_today' => sprintf(__('%s due today', 'safecontracts'), $subject),
            'due_7_days' => sprintf(__('%s due in 7 days', 'safecontracts'), $subject),
            default => $subject,
        };
    }

    private static function canViewFinance(): bool
    {
        return current_user_can(Capabilities::VIEW_PAYABLES) || current_user_can(Capabilities::VIEW_RECEIVABLES);
    }
}
