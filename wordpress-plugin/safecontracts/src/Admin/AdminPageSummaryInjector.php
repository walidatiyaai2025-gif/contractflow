<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Notifications\DeliveryLogRepository;
use SafeContracts\Notifications\FirebaseSettings;
use SafeContracts\Notifications\NotificationRuleService;
use SafeContracts\Notifications\NotificationScheduleRepository;
use SafeContracts\ReferenceData\PaymentMethodRepository;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Settings\GeneralSettings;
use SafeContracts\Settings\MobileConfiguration;
use SafeContracts\Support\MoneyFormatter;
use SafeContracts\Translations\TranslationCatalog;
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
        if ($page === '' || in_array($page, [
            AdminShell::SLUG,
            ArchivePage::SLUG,
            ActiveUsersPage::SLUG,
            ContractsPage::SLUG,
            NotificationCenterPage::SLUG,
            UsersRolesPage::SLUG,
        ], true)) {
            return;
        }
        try {
            $cards = self::cards($page);
        } catch (Throwable $error) {
            unset($error);
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
        $currency = (string) ($filters['currency_code'] ?? '');
        if ($currency === '') {
            try {
                $settings = (new GeneralSettings())->read();
                $currency = strtoupper(trim((string) ($settings['currency_code'] ?? '')));
            } catch (Throwable $error) {
                unset($error);
            }
        }
        if ($page === CustomersPage::SLUG) {
            $rows = $repository->customers($filters);
            return [
                ['label' => __('Customers shown', 'safecontracts'), 'value' => count($rows)],
                ['label' => __('With email', 'safecontracts'), 'value' => count(array_filter($rows, static fn (array $r): bool => trim((string) ($r['email'] ?? '')) !== ''))],
                ['label' => __('With phone', 'safecontracts'), 'value' => count(array_filter($rows, static fn (array $r): bool => trim((string) ($r['phone'] ?? '')) !== ''))],
            ];
        }
        if ($page === PaymentsPage::SLUG) {
            $rows = $repository->payments($filters);
            return [
                ['label' => __('Payments shown', 'safecontracts'), 'value' => count($rows)],
                ['label' => __('Overdue payments', 'safecontracts'), 'value' => self::countState($rows, 'status', 'overdue')],
                ['label' => __('Paid payments', 'safecontracts'), 'value' => self::countState($rows, 'status', 'paid')],
                ['label' => __('Remaining amount', 'safecontracts'), 'value' => MoneyFormatter::format((string) self::sum($rows, 'remaining_amount'), $currency)],
            ];
        }
        if ($page === CollectionsPage::SLUG) {
            $rows = $repository->collections($filters);
            return [
                ['label' => __('Collections shown', 'safecontracts'), 'value' => count($rows)],
                ['label' => __('Collected amount', 'safecontracts'), 'value' => MoneyFormatter::format((string) self::sum($rows, 'amount'), $currency)],
                ['label' => __('With attachments', 'safecontracts'), 'value' => count(array_filter($rows, static fn (array $r): bool => (int) ($r['proof_media_id'] ?? 0) > 0))],
            ];
        }
        if ($page === ReportsPage::SLUG) {
            $summary = $repository->reportSummary($filters);
            return [
                ['label' => __('Contracts', 'safecontracts'), 'value' => (string) ($summary['contract_count'] ?? 0)],
                ['label' => __('Collection transactions', 'safecontracts'), 'value' => (string) ($summary['collection_transactions'] ?? 0)],
                ['label' => __('Follow-up events', 'safecontracts'), 'value' => (string) ($summary['followup_events'] ?? 0)],
                ['label' => __('Overdue exposure', 'safecontracts'), 'value' => MoneyFormatter::format((string) ($summary['overdue_exposure'] ?? '0'), $currency)],
            ];
        }
        if ($page === NotificationsPage::SLUG || $page === NotificationSettingsPage::SLUG) {
            if (! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
                return [];
            }
            $rules = (new NotificationRuleService())->all();
            $deliveries = (new DeliveryLogRepository())->recent(100, $filters['date_from'], $filters['date_to']);
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
        if ($page === PaymentMethodsPage::SLUG && current_user_can(Capabilities::MANAGE_REFERENCE_DATA)) {
            $methods = (new PaymentMethodRepository())->all(true);
            return [
                ['label' => __('Active payment methods', 'safecontracts'), 'value' => count($methods)],
                ['label' => __('Reference-data state', 'safecontracts'), 'value' => __('Active only', 'safecontracts'), 'detail' => __('Deleted methods are in Archive', 'safecontracts')],
            ];
        }
        if ($page === GeneralSettingsPage::SLUG && current_user_can(Capabilities::MANAGE_SYSTEM)) {
            $settings = (new GeneralSettings())->read();
            return [
                ['label' => __('Organization', 'safecontracts'), 'value' => (string) ($settings['organization_name'] ?? '')],
                ['label' => __('Currency', 'safecontracts'), 'value' => trim((string) ($settings['currency_code'] ?? '') . ' ' . (string) ($settings['currency_symbol'] ?? ''))],
                ['label' => __('Admin page size', 'safecontracts'), 'value' => (string) ($settings['admin_page_size'] ?? '')],
            ];
        }
        if ($page === MobileConfigurationPage::SLUG && current_user_can(Capabilities::MANAGE_SYSTEM)) {
            $config = (new MobileConfiguration())->read();
            $enabledFeatures = count(array_filter([
                ! empty($config['excel_export_enabled']),
                ! empty($config['push_notifications_enabled']),
                ! empty($config['collection_entry_enabled']),
            ]));
            return [
                ['label' => __('Enabled mobile features', 'safecontracts'), 'value' => $enabledFeatures . '/3'],
                ['label' => __('Mobile page size', 'safecontracts'), 'value' => (string) ($config['default_page_size'] ?? '')],
                ['label' => __('Push notifications', 'safecontracts'), 'value' => ! empty($config['push_notifications_enabled']) ? __('Enabled', 'safecontracts') : __('Disabled', 'safecontracts')],
            ];
        }
        if ($page === FirebaseSettingsPage::SLUG && current_user_can(Capabilities::MANAGE_SYSTEM)) {
            $config = (new FirebaseSettings())->publicConfig();
            global $wpdb;
            $tokens = $wpdb->prefix . 'safecontracts_device_tokens';
            $tokenRows = $wpdb->get_results("SELECT COUNT(*) AS total FROM {$tokens} WHERE is_active = 1", ARRAY_A);
            $activeTokens = is_array($tokenRows) && isset($tokenRows[0]['total']) ? (int) $tokenRows[0]['total'] : 0;
            return [
                ['label' => __('Firebase project', 'safecontracts'), 'value' => trim((string) ($config['project_id'] ?? '')) !== '' ? __('Configured', 'safecontracts') : __('Not configured', 'safecontracts')],
                ['label' => __('Sender ID', 'safecontracts'), 'value' => trim((string) ($config['messaging_sender_id'] ?? '')) !== '' ? __('Configured', 'safecontracts') : __('Not configured', 'safecontracts')],
                ['label' => __('Active device registrations', 'safecontracts'), 'value' => $activeTokens],
            ];
        }
        if ($page === TranslationsPage::SLUG && current_user_can(Capabilities::MANAGE_SYSTEM)) {
            $overrides = TranslationCatalog::overrides();
            $ar = is_array($overrides['ar'] ?? null) ? count(array_filter($overrides['ar'], static fn ($value): bool => trim((string) $value) !== '')) : 0;
            $en = is_array($overrides['en'] ?? null) ? count(array_filter($overrides['en'], static fn ($value): bool => trim((string) $value) !== '')) : 0;
            return [
                ['label' => __('Arabic overrides', 'safecontracts'), 'value' => $ar],
                ['label' => __('English overrides', 'safecontracts'), 'value' => $en],
                ['label' => __('Current language', 'safecontracts'), 'value' => strtoupper(TranslationCatalog::currentLanguage())],
            ];
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
