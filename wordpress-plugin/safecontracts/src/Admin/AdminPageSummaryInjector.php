<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Notifications\DeliveryLogRepository;
use SafeContracts\Notifications\NotificationRuleService;
use SafeContracts\Notifications\NotificationScheduleRepository;
use SafeContracts\Roles\Capabilities;
use Throwable;

final class AdminPageSummaryInjector
{
    public static function register(): void
    {
        add_action('admin_notices', [self::class, 'render'], 4);
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            return;
        }
        $page = isset($_GET['page']) && is_scalar($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
        if ($page === '' || in_array($page, [ArchivePage::SLUG, ActiveUsersPage::SLUG, NotificationCenterPage::SLUG], true)) {
            return;
        }
        try {
            $cards = self::cards($page);
        } catch (Throwable) {
            return;
        }
        if ($cards === []) {
            return;
        }
        echo '<div class="safecontracts-summary-injector" dir="auto">';
        AdminSummaryCards::render($cards);
        echo '</div>';
    }

    /** @return list<array{label:string,value:string|int,detail?:string}> */
    private static function cards(string $page): array
    {
        $filters = DashboardFilters::normalize($_GET);
        $repository = new AdminReadRepository();
        if ($page === AdminShell::SLUG) {
            $kpis = $repository->kpis($filters);
            return [
                ['label' => __('Contracts', 'safecontracts'), 'value' => (string) ($kpis['contract_count'] ?? 0)],
                ['label' => __('Scheduled', 'safecontracts'), 'value' => (string) ($kpis['scheduled_total'] ?? '0.0000')],
                ['label' => __('Remaining', 'safecontracts'), 'value' => (string) ($kpis['remaining_total'] ?? '0.0000')],
                ['label' => __('Overdue', 'safecontracts'), 'value' => (string) ($kpis['overdue_exposure'] ?? '0.0000')],
            ];
        }
        if ($page === CustomersPage::SLUG) {
            $rows = $repository->customers($filters);
            return [
                ['label' => __('Customers shown', 'safecontracts'), 'value' => count($rows)],
                ['label' => __('With email', 'safecontracts'), 'value' => count(array_filter($rows, static fn (array $r): bool => trim((string) ($r['email'] ?? '')) !== ''))],
                ['label' => __('With phone', 'safecontracts'), 'value' => count(array_filter($rows, static fn (array $r): bool => trim((string) ($r['phone'] ?? '')) !== ''))],
            ];
        }
        if ($page === ContractsPage::SLUG) {
            $rows = $repository->contracts($filters);
            return [
                ['label' => __('Contracts shown', 'safecontracts'), 'value' => count($rows)],
                ['label' => __('Active', 'safecontracts'), 'value' => self::countState($rows, 'status', 'active')],
                ['label' => __('Completed', 'safecontracts'), 'value' => self::countState($rows, 'status', 'completed')],
                ['label' => __('Draft', 'safecontracts'), 'value' => self::countState($rows, 'status', 'draft')],
            ];
        }
        if ($page === PaymentsPage::SLUG) {
            $rows = $repository->payments($filters);
            $remaining = self::sum($rows, 'remaining_amount');
            return [
                ['label' => __('Payments shown', 'safecontracts'), 'value' => count($rows)],
                ['label' => __('Overdue payments', 'safecontracts'), 'value' => self::countState($rows, 'status', 'overdue')],
                ['label' => __('Paid payments', 'safecontracts'), 'value' => self::countState($rows, 'status', 'paid')],
                ['label' => __('Remaining amount', 'safecontracts'), 'value' => number_format($remaining, 4, '.', '')],
            ];
        }
        if ($page === CollectionsPage::SLUG) {
            $rows = $repository->collections($filters);
            return [
                ['label' => __('Collections shown', 'safecontracts'), 'value' => count($rows)],
                ['label' => __('Collected amount', 'safecontracts'), 'value' => number_format(self::sum($rows, 'amount'), 4, '.', '')],
                ['label' => __('With attachments', 'safecontracts'), 'value' => count(array_filter($rows, static fn (array $r): bool => (int) ($r['proof_media_id'] ?? 0) > 0))],
            ];
        }
        if ($page === ReportsPage::SLUG) {
            $summary = $repository->reportSummary($filters);
            return [
                ['label' => __('Contracts', 'safecontracts'), 'value' => (string) ($summary['contract_count'] ?? 0)],
                ['label' => __('Collection transactions', 'safecontracts'), 'value' => (string) ($summary['collection_transactions'] ?? 0)],
                ['label' => __('Follow-up events', 'safecontracts'), 'value' => (string) ($summary['followup_events'] ?? 0)],
                ['label' => __('Overdue exposure', 'safecontracts'), 'value' => (string) ($summary['overdue_exposure'] ?? '0.0000')],
            ];
        }
        if ($page === NotificationsPage::SLUG) {
            $rules = current_user_can(Capabilities::MANAGE_NOTIFICATIONS) ? (new NotificationRuleService())->all() : [];
            $deliveries = current_user_can(Capabilities::MANAGE_NOTIFICATIONS) ? (new DeliveryLogRepository())->recent(100, $filters['date_from'], $filters['date_to']) : [];
            return [
                ['label' => __('Active rules', 'safecontracts'), 'value' => count(array_filter($rules, static fn (array $r): bool => ! empty($r['is_active'])))],
                ['label' => __('Delivery attempts', 'safecontracts'), 'value' => count($deliveries)],
                ['label' => __('Sent', 'safecontracts'), 'value' => self::countState($deliveries, 'status', 'sent')],
                ['label' => __('Failed', 'safecontracts'), 'value' => self::countState($deliveries, 'status', 'failed')],
            ];
        }
        if ($page === NotificationSchedulePage::SLUG && current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            $rows = (new NotificationScheduleRepository())->recent($filters['date_from'], $filters['date_to'], '', 500);
            return [
                ['label' => __('Scheduled rows', 'safecontracts'), 'value' => count($rows)],
                ['label' => __('Pending', 'safecontracts'), 'value' => self::countState($rows, 'status', 'pending')],
                ['label' => __('Sent', 'safecontracts'), 'value' => self::countState($rows, 'status', 'sent')],
                ['label' => __('Failed / partial', 'safecontracts'), 'value' => self::countState($rows, 'status', 'failed') + self::countState($rows, 'status', 'partial')],
            ];
        }
        if ($page === UsersRolesPage::SLUG && current_user_can(Capabilities::MANAGE_USERS)) {
            $users = get_users(['fields' => 'ID']);
            return [
                ['label' => __('WordPress users', 'safecontracts'), 'value' => is_array($users) ? count($users) : 0],
                ['label' => __('Safe Contracts users', 'safecontracts'), 'value' => is_array($users) ? count(array_filter($users, static fn ($id): bool => user_can((int) $id, Capabilities::ACCESS))) : 0],
            ];
        }
        if ($page === FollowUpsPage::SLUG) {
            global $wpdb;
            $table = $wpdb->prefix . 'safecontracts_payment_followups';
            $rows = $wpdb->get_results("SELECT COUNT(*) AS total FROM {$table}", ARRAY_A);
            $count = is_array($rows) && isset($rows[0]['total']) ? (int) $rows[0]['total'] : 0;
            return [['label' => __('Follow-up events', 'safecontracts'), 'value' => $count]];
        }
        if ($page === ImportsPage::SLUG && current_user_can(Capabilities::RUN_IMPORTS)) {
            global $wpdb;
            $table = $wpdb->prefix . 'safecontracts_import_runs';
            $rows = $wpdb->get_results("SELECT COUNT(*) AS total FROM {$table}", ARRAY_A);
            $count = is_array($rows) && isset($rows[0]['total']) ? (int) $rows[0]['total'] : 0;
            return [['label' => __('Import runs', 'safecontracts'), 'value' => $count]];
        }
        return [];
    }

    /** @param list<array<string,mixed>> $rows */
    private static function countState(array $rows, string $field, string $state): int
    {
        return count(array_filter($rows, static fn (array $row): bool => strtolower((string) ($row[$field] ?? '')) === $state));
    }

    /** @param list<array<string,mixed>> $rows */
    private static function sum(array $rows, string $field): float
    {
        $sum = 0.0;
        foreach ($rows as $row) {
            $sum += (float) ($row[$field] ?? 0);
        }
        return $sum;
    }
}
