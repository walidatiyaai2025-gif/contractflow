<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Notifications\NotificationRule;
use SafeContracts\Notifications\NotificationRuleService;
use SafeContracts\Notifications\RecipientResolver;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;

$tests = 0;
function sc_notif_assert(bool $ok, string $message): void { global $tests; $tests++; if (! $ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
function sc_notif_expect(string $class, callable $fn, string $message): void { try { $fn(); } catch (Throwable $e) { sc_notif_assert($e instanceof $class, $message); return; } sc_notif_assert(false, $message); }

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_notif_assert(is_callable($activate), 'P5 activation hook is available');
$activate();
sc_notif_assert(version_compare(Migrator::LATEST_VERSION, '1.9.0', '>='), 'SC-P5-001 notification-rule migration is registered');
sc_notif_assert(count($GLOBALS['sc_test_dbdelta']) >= 13, 'SC-P5-001 notification schema migrates after P4 tables');
$schema = $GLOBALS['sc_test_dbdelta'][12];
sc_notif_assert(str_contains($schema, 'wp_safecontracts_notification_rules'), 'SC-P5-001 rules use dedicated prefixed table');
sc_notif_assert(str_contains($schema, 'code varchar(100) NOT NULL') && str_contains($schema, 'UNIQUE KEY code (code)'), 'SC-P5-001 rule code is stable and unique');
sc_notif_assert(str_contains($schema, "trigger_type varchar(32) NOT NULL DEFAULT 'before_due'"), 'SC-P5-001 trigger type is explicit');
sc_notif_assert(str_contains($schema, 'days_before int(11) unsigned NOT NULL DEFAULT 0'), 'SC-P5-001 before-due offset is persisted');
sc_notif_assert(str_contains($schema, 'recipient_roles_json longtext NOT NULL'), 'SC-P5-003 role recipients are stored centrally');
sc_notif_assert(str_contains($schema, 'target_assigned_accountant tinyint(1) NOT NULL DEFAULT 0'), 'SC-P5-004 assigned-accountant targeting is explicit');
sc_notif_assert(str_contains($schema, 'KEY active_trigger (is_active, trigger_type, days_before)'), 'SC-P5-001 active trigger lookup is indexed');

$seedSql = implode("\n", $GLOBALS['sc_test_queries']);
sc_notif_assert(str_contains($seedSql, 'default_due_10_days'), 'SC-P5-002 default 10-day rule is seeded');
sc_notif_assert(str_contains($seedSql, "'before_due', 10"), 'SC-P5-002 default rule uses ten days before due');
sc_notif_assert(str_contains($seedSql, 'safecontracts_manager'), 'SC-P5-003 default rule includes Manager role');
sc_notif_assert(str_contains($seedSql, 'target_assigned_accountant') && str_contains($seedSql, '1, 1, NULL, NULL'), 'SC-P5-004 default rule targets the assigned Accountant explicitly');
sc_notif_assert(str_contains($seedSql, 'ON DUPLICATE KEY UPDATE code = VALUES(code)'), 'SC-P5-002 default seed is migration-idempotent without overwriting later administrator edits');

$normalized = NotificationRule::normalizeInput([
    'code' => ' FOLLOW_UP_10 ',
    'name' => '  Ten Day Reminder  ',
    'trigger_type' => 'BEFORE_DUE',
    'days_before' => '10',
    'recipient_roles' => [RoleRegistrar::MANAGER, RoleRegistrar::MANAGER, RoleRegistrar::VIEWER],
    'target_assigned_accountant' => '1',
    'is_active' => 'true',
]);
sc_notif_assert($normalized['code'] === 'follow_up_10', 'SC-P5-001 rule code is normalized');
sc_notif_assert($normalized['name'] === 'Ten Day Reminder', 'SC-P5-001 rule name is normalized');
sc_notif_assert($normalized['days_before'] === 10, 'SC-P5-002 days-before value is normalized to integer');
sc_notif_assert($normalized['recipient_roles'] === [RoleRegistrar::MANAGER, RoleRegistrar::VIEWER], 'SC-P5-003 duplicate role recipients are removed deterministically');
sc_notif_assert($normalized['target_assigned_accountant'] === true, 'SC-P5-004 assigned-accountant flag is normalized');
sc_notif_expect(InvalidArgumentException::class, fn () => NotificationRule::normalizeRecipientRoles(['administrator']), 'SC-P5-003 native/unknown roles are rejected from SafeContracts recipient policy');
sc_notif_expect(InvalidArgumentException::class, fn () => NotificationRule::normalizeInput([
    'code'=>'no-target','name'=>'No target','trigger_type'=>'before_due','days_before'=>10,'recipient_roles'=>[],'target_assigned_accountant'=>false,
]), 'SC-P5-001 rules with no recipient target are rejected');
sc_notif_expect(InvalidArgumentException::class, fn () => NotificationRule::normalizeDaysBefore(0), 'SC-P5-002 before-due rules cannot silently become due-day rules');
sc_notif_expect(InvalidArgumentException::class, fn () => NotificationRule::normalizeDaysBefore(366), 'SC-P5-002 unreasonable before-due offset is rejected');

$today = new DateTimeImmutable('2026-08-15');
$defaultRule = ['trigger_type'=>'before_due','days_before'=>10];
sc_notif_assert(NotificationRule::matchesContractualDueDate($defaultRule, '2026-08-25', $today), 'SC-P5-002 ten-day trigger matches contractual due_date');
sc_notif_assert(! NotificationRule::matchesContractualDueDate($defaultRule, '2026-08-26', $today), 'SC-P5-002 non-matching contractual due_date does not trigger');
sc_notif_expect(InvalidArgumentException::class, fn () => NotificationRule::matchesContractualDueDate($defaultRule, '2026-02-30', $today), 'SC-P5-002 invalid contractual due date is rejected');

$service = new NotificationRuleService();
$GLOBALS['sc_test_current_caps'] = [];
sc_notif_expect(DomainException::class, fn () => $service->all(), 'SC-P5-001 notification rule administration requires MANAGE_NOTIFICATIONS');

$GLOBALS['sc_test_current_caps'] = [Capabilities::MANAGE_NOTIFICATIONS=>true];
$beforeInvalid = count($GLOBALS['sc_test_queries']);
sc_notif_expect(InvalidArgumentException::class, fn () => $service->save([
    'code'=>'bad-role','name'=>'Bad role','trigger_type'=>'before_due','days_before'=>10,'recipient_roles'=>['subscriber'],'target_assigned_accountant'=>false,
]), 'SC-P5-003 invalid role is rejected before persistence');
sc_notif_assert(count($GLOBALS['sc_test_queries']) === $beforeInvalid, 'SC-P5-003 invalid role does not mutate notification rules');

$beforeSave = count($GLOBALS['sc_test_queries']);
$saved = $service->save([
    'code'=>'custom-10','name'=>'Custom ten day','trigger_type'=>'before_due','days_before'=>10,
    'recipient_roles'=>[RoleRegistrar::MANAGER], 'target_assigned_accountant'=>true, 'is_active'=>true,
]);
sc_notif_assert($saved['code'] === 'custom-10', 'SC-P5-001 valid notification rule save returns normalized model');
$saveSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeSave));
sc_notif_assert(str_contains($saveSql, 'INSERT INTO wp_safecontracts_notification_rules'), 'SC-P5-001 rule save uses dedicated table');
sc_notif_assert(str_contains($saveSql, "'safecontracts_manager'"), 'SC-P5-003 role policy is persisted as server-owned configuration');
sc_notif_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_notification_rule_saved']), 'SC-P5-001 rule mutation emits domain event');

$GLOBALS['sc_test_result_queue'] = [[[
    'id'=>'7','code'=>'default_due_10_days','name'=>'Default 10-day due reminder','trigger_type'=>'before_due','days_before'=>'10',
    'recipient_roles_json'=>'["safecontracts_manager"]','target_assigned_accountant'=>'1','is_active'=>'1','created_by'=>null,'updated_by'=>null,
    'created_at'=>'2026-08-15 11:00:00','updated_at'=>'2026-08-15 11:00:00',
]]];
$rows = $service->all();
sc_notif_assert(count($rows) === 1 && $rows[0]['days_before'] === 10, 'SC-P5-001 rule reads normalize persisted fields');
sc_notif_assert($rows[0]['recipient_roles'] === [RoleRegistrar::MANAGER], 'SC-P5-003 role JSON is normalized on read');
sc_notif_assert($rows[0]['target_assigned_accountant'] === true, 'SC-P5-004 assigned target is normalized on read');

$GLOBALS['sc_test_result_queue'] = [[[
    'id'=>'7','code'=>'default_due_10_days','name'=>'Default','trigger_type'=>'before_due','days_before'=>'10',
    'recipient_roles_json'=>'["safecontracts_manager"]','target_assigned_accountant'=>'1','is_active'=>'1',
]]];
$active = $service->activeBeforeDue(10);
sc_notif_assert(count($active) === 1, 'SC-P5-002 active ten-day rules can be selected server-side');
$activeSql = (string) end($GLOBALS['sc_test_read_queries']);
sc_notif_assert(str_contains($activeSql, 'is_active = 1') && str_contains($activeSql, "trigger_type = 'before_due'") && str_contains($activeSql, 'days_before = 10'), 'SC-P5-002 active trigger query is bounded by trigger and offset');
sc_notif_assert($service->activeBeforeDue(0) === [], 'SC-P5-002 invalid trigger offset returns no rules');

$GLOBALS['sc_test_users_by_role'] = [
    RoleRegistrar::MANAGER => [100, 101],
    RoleRegistrar::VIEWER => [101, 102],
    RoleRegistrar::ACCOUNTANT => [42, 77],
];
$resolver = new RecipientResolver();
$recipientIds = $resolver->resolve([
    'recipient_roles'=>[RoleRegistrar::MANAGER, RoleRegistrar::VIEWER],
    'target_assigned_accountant'=>true,
], 42);
sc_notif_assert($recipientIds === [42, 100, 101, 102], 'SC-P5-003/004 role and assigned recipients merge uniquely and deterministically');
$defaultRecipients = $resolver->resolve([
    'recipient_roles'=>[RoleRegistrar::MANAGER],
    'target_assigned_accountant'=>true,
], 42);
sc_notif_assert($defaultRecipients === [42, 100, 101], 'SC-P5-003/004 default policy resolves Manager plus assigned Accountant');
$missingAssigned = $resolver->resolve([
    'recipient_roles'=>[RoleRegistrar::MANAGER],
    'target_assigned_accountant'=>true,
], null);
sc_notif_assert($missingAssigned === [100, 101], 'SC-P5-004 missing assignment never broadens to every Accountant');
sc_notif_assert(! in_array(77, $missingAssigned, true), 'SC-P5-004 unassigned payment cannot leak to unrelated Accountants');

$dbDeltaCount = count($GLOBALS['sc_test_dbdelta']);
$queryCount = count($GLOBALS['sc_test_queries']);
do_action('plugins_loaded');
sc_notif_assert(count($GLOBALS['sc_test_dbdelta']) === $dbDeltaCount, 'SC-P5-001 migration is idempotent after stored version is current');
sc_notif_assert(count($GLOBALS['sc_test_queries']) === $queryCount, 'SC-P5-002 default seed is not replayed at runtime after migration');

echo "SafeContracts P5 notification rules SC-P5-001..004 passed ({$tests} assertions).\n";
