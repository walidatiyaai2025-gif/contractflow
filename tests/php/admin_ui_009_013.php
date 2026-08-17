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
use SafeContracts\Notifications\DeliveryLogRepository;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p6ops_assert(bool $ok, string $message): void
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

// SC-P6-009 — collection ledger read model is scoped and joins authoritative entities.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true];
$GLOBALS['sc_test_result_queue'] = [[['id' => '1', 'payment_id' => '20', 'amount' => '125.0000', 'collection_date' => '2026-08-15', 'payment_method_name' => 'Bank Transfer', 'customer_name' => 'Acme', 'contract_number' => 'SC-20']]];
$before = count($GLOBALS['sc_test_read_queries']);
$collections = $read->collections(['customer_id' => 4, 'contract_id' => 8, 'accountant_user_id' => 17]);
$collectionQuery = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p6ops_assert(count($collections) === 1, 'SC-P6-009 collection ledger returns scoped rows');
sc_p6ops_assert(str_contains($collectionQuery, 'safecontracts_payment_collections') && str_contains($collectionQuery, 'safecontracts_payment_methods'), 'SC-P6-009 ledger joins collection and payment-method sources');
sc_p6ops_assert(str_contains($collectionQuery, "c.counterparty_type = 'customer'") && str_contains($collectionQuery, 'c.counterparty_id = 4') && str_contains($collectionQuery, 'c.id = 8') && str_contains($collectionQuery, 'c.accountant_user_id = 17'), 'SC-P6-009 manager collection filters apply server-side with receivable customer semantics');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [[]];
$before = count($GLOBALS['sc_test_read_queries']);
$read->collections(['accountant_user_id' => 999]);
$assignedCollectionQuery = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p6ops_assert(str_contains($assignedCollectionQuery, 'c.accountant_user_id = 42') && ! str_contains($assignedCollectionQuery, 'accountant_user_id = 999'), 'SC-P6-009 assigned collection scope cannot be widened');

$collectionSource = file_get_contents((string) (new ReflectionClass(CollectionsPage::class))->getFileName()) ?: '';
sc_p6ops_assert(str_contains($collectionSource, 'CollectionService'), 'SC-P6-009 collection mutation delegates to CollectionService');
sc_p6ops_assert(str_contains($collectionSource, 'PaymentMethodRepository') && str_contains($collectionSource, 'Proof media ID (optional)'), 'SC-P6-009 collection screen uses active method master data and optional proof');
sc_p6ops_assert(! str_contains($collectionSource, '$wpdb'), 'SC-P6-009 collection page contains no presentation-layer SQL');

// SC-P6-010 — follow-up screen delegates all state transitions to FollowUpService.
$followupSource = file_get_contents((string) (new ReflectionClass(FollowUpsPage::class))->getFileName()) ?: '';
sc_p6ops_assert(str_contains($followupSource, 'FollowUpService'), 'SC-P6-010 follow-up screen delegates queue/history/mutations to FollowUpService');
sc_p6ops_assert(str_contains($followupSource, 'promiseToPay') && str_contains($followupSource, 'markIssue') && str_contains($followupSource, 'defer') && str_contains($followupSource, 'escalate'), 'SC-P6-010 operational follow-up states are wired through domain methods');
sc_p6ops_assert(str_contains($followupSource, 'Contractual due date remains') && str_contains($followupSource, 'Append-only history'), 'SC-P6-010 UI preserves due-date semantics and append-only history contract');
sc_p6ops_assert(! str_contains($followupSource, '$wpdb'), 'SC-P6-010 follow-up page contains no presentation-layer SQL');

// SC-P6-011 — notification operations expose rules/log metadata without secrets.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::MANAGE_NOTIFICATIONS => true];
$GLOBALS['sc_test_result_queue'] = [[['id' => '9', 'rule_id' => '1', 'payment_id' => '20', 'user_id' => '42', 'device_token_id' => '5', 'template_code' => 'due_default', 'scheduled_for' => '2026-08-15 09:00:00', 'attempt_no' => '1', 'status' => 'sent', 'response_code' => '200', 'error_code' => '', 'created_at' => '2026-08-15 09:00:01']]];
$before = count($GLOBALS['sc_test_read_queries']);
$deliveryRows = (new DeliveryLogRepository())->recent(25);
$deliveryQuery = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p6ops_assert(count($deliveryRows) === 1 && $deliveryRows[0]['status'] === 'sent', 'SC-P6-011 recent delivery log returns operational metadata');
sc_p6ops_assert(str_contains($deliveryQuery, 'safecontracts_notification_deliveries') && str_contains($deliveryQuery, 'LIMIT 25'), 'SC-P6-011 delivery read is bounded and server-side');
$notificationSource = file_get_contents((string) (new ReflectionClass(NotificationsPage::class))->getFileName()) ?: '';
sc_p6ops_assert(str_contains($notificationSource, 'NotificationRuleService') && str_contains($notificationSource, 'DeliveryLogRepository'), 'SC-P6-011 notification screen uses notification domain/repository boundaries');
sc_p6ops_assert(! str_contains($notificationSource, 'FirebaseSettings') && ! str_contains($notificationSource, 'private_key') && ! str_contains($notificationSource, 'service_account'), 'SC-P6-011 notification screen does not expose Firebase credentials');
sc_p6ops_assert(! str_contains($notificationSource, '$wpdb'), 'SC-P6-011 notification page contains no presentation-layer SQL');

// SC-P6-012 — report summary reuses scoped read semantics and due-date authority.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true, Capabilities::VIEW_REPORTS => true];
$GLOBALS['sc_test_result_queue'] = [
    [['contract_count' => '2', 'scheduled_total' => '500.0000', 'remaining_total' => '225.0000', 'overdue_exposure' => '75.0000', 'collected_total' => '275.0000']],
    [['collection_transactions' => '3', 'collection_ledger_total' => '275.0000']],
    [['followup_events' => '4', 'followed_up_payments' => '2']],
];
$before = count($GLOBALS['sc_test_read_queries']);
$summary = $read->reportSummary(['customer_id' => 3, 'status' => 'overdue']);
$reportQueries = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p6ops_assert($summary['contract_count'] === '2' && $summary['collection_transactions'] === '3' && $summary['followup_events'] === '4', 'SC-P6-012 report read model combines contract/payment/collection/follow-up summaries');
sc_p6ops_assert(str_contains($reportQueries, 'c.accountant_user_id = 42') && str_contains($reportQueries, "c.counterparty_type = 'customer'") && str_contains($reportQueries, 'c.counterparty_id = 3'), 'SC-P6-012 assigned report scope is enforced across receivable customer summary queries');
sc_p6ops_assert(str_contains($reportQueries, 'p.due_date <') && str_contains($reportQueries, "p.status = 'overdue'"), 'SC-P6-012 reporting preserves contractual due-date and status filtering');
$reportsSource = file_get_contents((string) (new ReflectionClass(ReportsPage::class))->getFileName()) ?: '';
sc_p6ops_assert(str_contains($reportsSource, 'AdminReadRepository') && str_contains($reportsSource, 'server-side'), 'SC-P6-012 report screen keeps the scoped read-model/server-side reporting boundary as later export features are added');
sc_p6ops_assert(! str_contains($reportsSource, '$wpdb'), 'SC-P6-012 report page contains no presentation-layer SQL');

// SC-P6-013 — users/roles remains WordPress-native but now supports controlled Safe Contracts role/capability administration.
$usersSource = file_get_contents((string) (new ReflectionClass(UsersRolesPage::class))->getFileName()) ?: '';
sc_p6ops_assert(str_contains($usersSource, 'get_role') && str_contains($usersSource, 'get_users') && str_contains($usersSource, 'Capabilities::all'), 'SC-P6-013 users/roles screen reads effective WordPress role grants');
sc_p6ops_assert(str_contains($usersSource, 'SAVE_CAPABILITIES_ACTION') && str_contains($usersSource, 'ASSIGN_ROLE_ACTION') && str_contains($usersSource, 'check_admin_referer'), 'SC-P6-013 role and membership changes are explicit nonce-protected admin actions');
sc_p6ops_assert(str_contains($usersSource, 'Capabilities::MANAGE_USERS') && str_contains($usersSource, 'add_cap') && str_contains($usersSource, 'remove_cap'), 'SC-P6-013 only manage-users actors can edit Safe Contracts capability grants');
sc_p6ops_assert(str_contains($usersSource, 'add_role') && str_contains($usersSource, 'remove_role') && ! str_contains($usersSource, 'user_pass'), 'SC-P6-013 Safe Contracts role membership is editable without exposing password fields');
sc_p6ops_assert(! str_contains($usersSource, '$wpdb'), 'SC-P6-013 users/roles page contains no direct SQL');

// Registration capabilities match each screen's security boundary.
CollectionsPage::register();
FollowUpsPage::register();
NotificationsPage::register();
ReportsPage::register();
UsersRolesPage::register();
$expectedCaps = [
    CollectionsPage::SLUG => Capabilities::ACCESS,
    FollowUpsPage::SLUG => Capabilities::ACCESS,
    NotificationsPage::SLUG => Capabilities::MANAGE_NOTIFICATIONS,
    ReportsPage::SLUG => Capabilities::VIEW_REPORTS,
    UsersRolesPage::SLUG => Capabilities::MANAGE_USERS,
];
foreach ($expectedCaps as $slug => $capability) {
    sc_p6ops_assert(($GLOBALS['sc_test_admin_pages'][$slug]['parent'] ?? '') === AdminShell::SLUG, $slug . ' is registered under SafeContracts shell');
    sc_p6ops_assert(($GLOBALS['sc_test_admin_pages'][$slug]['capability'] ?? '') === $capability, $slug . ' uses its intended capability boundary');
}

// Responsive/RTL operations asset layers after existing core styles.
$_GET['page'] = CollectionsPage::SLUG;
AdminShell::enqueueAssets();
sc_p6ops_assert(isset($GLOBALS['sc_test_enqueued_styles'][AdminShell::OPS_STYLE_HANDLE]), 'SC-P6-009..013 operations screens load dedicated stylesheet');
sc_p6ops_assert(($GLOBALS['sc_test_enqueued_styles'][AdminShell::OPS_STYLE_HANDLE]['deps'] ?? []) === [AdminShell::CORE_STYLE_HANDLE], 'SC-P6 operations styles layer after core identity styles');
$opsCss = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/assets/admin/safecontracts-admin-ops.css') ?: '';
sc_p6ops_assert(str_contains($opsCss, '[dir="rtl"]') && str_contains($opsCss, '@media (max-width: 782px)'), 'SC-P6 operations styles include RTL and mobile behavior');

printf("SafeContracts P6 operations/reports/users SC-P6-009..013 passed (%d assertions).\n", $tests);
