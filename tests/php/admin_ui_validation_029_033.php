<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\AdminReadRepository;
use SafeContracts\Admin\AdminShell;
use SafeContracts\Admin\CollectionsPage;
use SafeContracts\Admin\FollowUpsPage;
use SafeContracts\Admin\NotificationsPage;
use SafeContracts\Admin\ReportsPage;
use SafeContracts\Admin\UsersRolesPage;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p6v5_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

SafeContracts\Plugin::instance()->boot();
$read = new AdminReadRepository();

// SC-P6-029 — Collections screen: server-side scope + domain mutation boundary.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [[]];
$before = count($GLOBALS['sc_test_read_queries']);
$read->collections(['customer_id' => 7, 'contract_id' => 9, 'accountant_user_id' => 999]);
$collectionSql = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p6v5_assert(str_contains($collectionSql, "c.counterparty_type = 'customer'") && str_contains($collectionSql, 'c.counterparty_id = 7') && str_contains($collectionSql, 'c.id = 9'), 'SC-P6-029 collection filters are enforced server-side with receivable customer semantics');
sc_p6v5_assert(str_contains($collectionSql, 'c.accountant_user_id = 42') && ! str_contains($collectionSql, 'accountant_user_id = 999'), 'SC-P6-029 assigned collection scope cannot be widened');
$collectionSource = file_get_contents((string) (new ReflectionClass(CollectionsPage::class))->getFileName()) ?: '';
sc_p6v5_assert(str_contains($collectionSource, 'Capabilities::MANAGE_COLLECTIONS') && str_contains($collectionSource, 'check_admin_referer'), 'SC-P6-029 collection writes require capability and nonce');
sc_p6v5_assert(str_contains($collectionSource, 'CollectionService') && str_contains($collectionSource, 'PaymentMethodRepository'), 'SC-P6-029 collections delegate writes and payment-method authority to domain boundaries');
sc_p6v5_assert(str_contains($collectionSource, 'Proof media ID (optional)') && str_contains($collectionSource, 'payment_method_id'), 'SC-P6-029 proof remains optional while payment method remains explicit');
sc_p6v5_assert(! str_contains($collectionSource, '$wpdb'), 'SC-P6-029 collection page contains no presentation-layer SQL');

// SC-P6-030 — Follow-up screen: operational actions stay in FollowUpService and cannot rewrite due date.
$followupSource = file_get_contents((string) (new ReflectionClass(FollowUpsPage::class))->getFileName()) ?: '';
sc_p6v5_assert(str_contains($followupSource, 'Capabilities::MANAGE_FOLLOWUPS') && str_contains($followupSource, 'check_admin_referer'), 'SC-P6-030 follow-up writes require capability and nonce');
foreach (['addNote', 'promiseToPay', 'markIssue', 'defer', 'escalate'] as $method) {
    sc_p6v5_assert(str_contains($followupSource, $method), 'SC-P6-030 delegates ' . $method . ' to FollowUpService');
}
sc_p6v5_assert(str_contains($followupSource, 'Contractual due date remains') && str_contains($followupSource, 'Append-only history'), 'SC-P6-030 UI preserves contractual due-date and append-only history semantics');
sc_p6v5_assert(! str_contains($followupSource, 'updateDates(') && ! str_contains($followupSource, '$wpdb'), 'SC-P6-030 follow-up presentation cannot rewrite payment due dates or run SQL directly');

// SC-P6-031 — Notifications screen: capability-gated bounded operational metadata only.
$notificationSource = file_get_contents((string) (new ReflectionClass(NotificationsPage::class))->getFileName()) ?: '';
sc_p6v5_assert(substr_count($notificationSource, 'Capabilities::MANAGE_NOTIFICATIONS') >= 2, 'SC-P6-031 notification registration and render are capability-gated');
sc_p6v5_assert(str_contains($notificationSource, 'NotificationRuleService') && str_contains($notificationSource, 'DeliveryLogRepository') && str_contains($notificationSource, 'recent(100,'), 'SC-P6-031 notification reads use bounded domain/repository boundaries');
sc_p6v5_assert(str_contains($notificationSource, "\$filters['date_from']") && str_contains($notificationSource, "\$filters['date_to']"), 'SC-P6-031 notification delivery reads carry the normalized display period server-side');
sc_p6v5_assert(! str_contains($notificationSource, 'private_key') && ! str_contains($notificationSource, 'access_token') && ! str_contains($notificationSource, "['device_token']"), 'SC-P6-031 notification operations do not expose credential/token material');
sc_p6v5_assert(str_contains($notificationSource, 'esc_html') && ! str_contains($notificationSource, '$wpdb'), 'SC-P6-031 notification output is escaped and presentation contains no SQL');

// SC-P6-032 — Reports screen: normalized filters, assigned scope, due-date authority and server-side export.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true, Capabilities::VIEW_REPORTS => true];
$GLOBALS['sc_test_result_queue'] = [
    [['contract_count' => '1', 'scheduled_total' => '100.0000', 'remaining_total' => '75.0000', 'overdue_exposure' => '75.0000', 'collected_total' => '25.0000']],
    [['collection_transactions' => '1', 'collection_ledger_total' => '25.0000']],
    [['followup_events' => '2', 'followed_up_payments' => '1']],
];
$before = count($GLOBALS['sc_test_read_queries']);
$summary = $read->reportSummary(['customer_id' => 7, 'contract_id' => 9, 'accountant_user_id' => 999, 'status' => 'overdue']);
$reportSql = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p6v5_assert($summary['overdue_exposure'] === '75.0000', 'SC-P6-032 reports consume canonical server-side financial aggregates');
sc_p6v5_assert(str_contains($reportSql, 'c.accountant_user_id = 42') && ! str_contains($reportSql, 'accountant_user_id = 999'), 'SC-P6-032 report scope cannot be widened by accountant filter');
sc_p6v5_assert(str_contains($reportSql, 'p.due_date <') && str_contains($reportSql, "p.status = 'overdue'"), 'SC-P6-032 reporting retains contractual due-date authority');
$reportsSource = file_get_contents((string) (new ReflectionClass(ReportsPage::class))->getFileName()) ?: '';
sc_p6v5_assert(str_contains($reportsSource, 'DashboardFilters::normalize') && str_contains($reportsSource, 'ReportExportService'), 'SC-P6-032 reports normalize filters and delegate XLSX generation server-side');
sc_p6v5_assert(str_contains($reportsSource, 'Capabilities::VIEW_REPORTS') && str_contains($reportsSource, 'check_admin_referer') && ! str_contains($reportsSource, '$wpdb'), 'SC-P6-032 report view/export retains capability, nonce and repository boundaries');

// SC-P6-033 — Users/roles screen: WordPress-native, capability-gated and explicit writes limited to Safe Contracts roles/capabilities.
$usersSource = file_get_contents((string) (new ReflectionClass(UsersRolesPage::class))->getFileName()) ?: '';
sc_p6v5_assert(substr_count($usersSource, 'Capabilities::MANAGE_USERS') >= 2, 'SC-P6-033 users/roles registration and mutation handlers require manage-users capability');
sc_p6v5_assert(str_contains($usersSource, 'get_role') && str_contains($usersSource, 'get_users') && str_contains($usersSource, 'Capabilities::all'), 'SC-P6-033 displayed permissions come from WordPress grants and Safe Contracts capability registry');
sc_p6v5_assert(str_contains($usersSource, 'SAVE_CAPABILITIES_ACTION') && str_contains($usersSource, 'ASSIGN_ROLE_ACTION') && substr_count($usersSource, 'check_admin_referer') >= 2, 'SC-P6-033 explicit role writes are nonce-protected');
sc_p6v5_assert(str_contains($usersSource, 'add_cap') && str_contains($usersSource, 'remove_cap') && str_contains($usersSource, 'add_role') && str_contains($usersSource, 'remove_role'), 'SC-P6-033 Safe Contracts grants and role membership are editable through WordPress-native APIs');
sc_p6v5_assert(! str_contains($usersSource, 'user_pass') && str_contains($usersSource, 'esc_html') && ! str_contains($usersSource, '$wpdb'), 'SC-P6-033 role administration avoids password data, escapes output and uses no direct SQL');

// Registration stays under the SafeContracts shell with intended capability boundaries.
CollectionsPage::register();
FollowUpsPage::register();
NotificationsPage::register();
ReportsPage::register();
UsersRolesPage::register();
$expected = [
    CollectionsPage::SLUG => Capabilities::ACCESS,
    FollowUpsPage::SLUG => Capabilities::ACCESS,
    NotificationsPage::SLUG => Capabilities::MANAGE_NOTIFICATIONS,
    ReportsPage::SLUG => Capabilities::VIEW_REPORTS,
    UsersRolesPage::SLUG => Capabilities::MANAGE_USERS,
];
foreach ($expected as $slug => $capability) {
    sc_p6v5_assert(($GLOBALS['sc_test_admin_pages'][$slug]['parent'] ?? '') === AdminShell::SLUG, $slug . ' remains under SafeContracts shell');
    sc_p6v5_assert(($GLOBALS['sc_test_admin_pages'][$slug]['capability'] ?? '') === $capability, $slug . ' retains intended capability boundary');
}

printf("SafeContracts P6 validation SC-P6-029..033 passed (%d assertions).\n", $tests);
