<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Notifications\NotificationRule;
use SafeContracts\Notifications\NotificationRuleService;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;

$tests = 0;
function sc_p5v_assert(bool $ok, string $message): void { global $tests; $tests++; if (! $ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
function sc_p5v_expect(string $class, callable $fn, string $message): void { try { $fn(); } catch (Throwable $e) { sc_p5v_assert($e instanceof $class, $message); return; } sc_p5v_assert(false, $message); }

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_p5v_assert(is_callable($activate), 'P5 rule validation activation hook exists');
$activate();
sc_p5v_assert(version_compare(Migrator::LATEST_VERSION, '1.10.0', '>='), 'SC-P5-014 validates expanded rule schema version');
sc_p5v_assert(NotificationRule::allowedTriggers() === ['before_due','due_day','overdue'], 'SC-P5-014 trigger allow-list is explicit and closed');

$legacy = NotificationRule::normalizeInput([
    'code'=>'legacy-10','name'=>'Legacy ten day','trigger_type'=>'before_due','days_before'=>10,
    'recipient_roles'=>[RoleRegistrar::MANAGER],'target_assigned_accountant'=>true,
]);
sc_p5v_assert($legacy['days_before'] === 10 && $legacy['days_after'] === 0, 'SC-P5-014 legacy before-due offset remains compatible');
sc_p5v_assert($legacy['repeat_interval_days'] === 0 && $legacy['max_repeats'] === 0 && $legacy['escalation_roles'] === [], 'SC-P5-014 legacy rules default to no repeat/escalation');
sc_p5v_assert($legacy['template_code'] === 'payment_due_soon', 'SC-P5-014 legacy rule receives backward-compatible default template');
sc_p5v_assert(NotificationRule::matchesContractualDueDate($legacy, '2026-08-25', new DateTimeImmutable('2026-08-15')), 'SC-P5-014 legacy 10-day contractual matcher remains compatible');

$dueDay = NotificationRule::normalizeInput([
    'code'=>'due-day','name'=>'Due day','trigger_type'=>'due_day','days_before'=>99,'days_after'=>99,
    'recipient_roles'=>[RoleRegistrar::MANAGER],
]);
sc_p5v_assert($dueDay['days_before'] === 0 && $dueDay['days_after'] === 0, 'SC-P5-014 due-day model normalizes irrelevant offsets to zero');
sc_p5v_assert(NotificationRule::targetDate($dueDay, '2026-08-15')->format('Y-m-d') === '2026-08-15', 'SC-P5-014 due-day target equals contractual due date');

$overdue = NotificationRule::normalizeInput([
    'code'=>'overdue','name'=>'Overdue','trigger_type'=>'overdue','days_after'=>3,
    'repeat_interval_days'=>7,'max_repeats'=>4,
    'recipient_roles'=>[RoleRegistrar::MANAGER,RoleRegistrar::MANAGER],
    'escalation_roles'=>[RoleRegistrar::SYSTEM_ADMIN,RoleRegistrar::SYSTEM_ADMIN],
]);
sc_p5v_assert($overdue['recipient_roles'] === [RoleRegistrar::MANAGER] && $overdue['escalation_roles'] === [RoleRegistrar::SYSTEM_ADMIN], 'SC-P5-014 normalizes duplicate normal/escalation roles deterministically');
sc_p5v_assert(NotificationRule::targetDate($overdue, '2026-08-10', 0)->format('Y-m-d') === '2026-08-13', 'SC-P5-014 overdue base target uses configured days-after');
sc_p5v_assert(NotificationRule::targetDate($overdue, '2026-08-10', 2)->format('Y-m-d') === '2026-08-27', 'SC-P5-014 repeat target uses exact bounded cadence');
sc_p5v_expect(InvalidArgumentException::class, fn () => NotificationRule::targetDate($overdue, '2026-08-10', 5), 'SC-P5-014 target date rejects repeat number beyond max repeats');

sc_p5v_expect(InvalidArgumentException::class, fn () => NotificationRule::normalizeInput([
    'code'=>'bad-trigger','name'=>'Bad','trigger_type'=>'tomorrow','recipient_roles'=>[RoleRegistrar::MANAGER],
]), 'SC-P5-014 unknown trigger type is rejected');
sc_p5v_expect(InvalidArgumentException::class, fn () => NotificationRule::normalizeInput([
    'code'=>'bad-overdue','name'=>'Bad','trigger_type'=>'overdue','days_after'=>0,'recipient_roles'=>[RoleRegistrar::MANAGER],
]), 'SC-P5-014 overdue rules require a positive bounded days-after value');
sc_p5v_expect(InvalidArgumentException::class, fn () => NotificationRule::normalizeInput([
    'code'=>'bad-repeat','name'=>'Bad','trigger_type'=>'due_day','repeat_interval_days'=>1,'max_repeats'=>51,'recipient_roles'=>[RoleRegistrar::MANAGER],
]), 'SC-P5-014 max repeats are hard-bounded');
sc_p5v_expect(InvalidArgumentException::class, fn () => NotificationRule::normalizeInput([
    'code'=>'bad-interval','name'=>'Bad','trigger_type'=>'due_day','repeat_interval_days'=>366,'max_repeats'=>1,'recipient_roles'=>[RoleRegistrar::MANAGER],
]), 'SC-P5-014 repeat interval is hard-bounded');
sc_p5v_expect(InvalidArgumentException::class, fn () => NotificationRule::normalizeInput([
    'code'=>'bad-escalation','name'=>'Bad','trigger_type'=>'due_day','recipient_roles'=>[RoleRegistrar::MANAGER],'escalation_roles'=>['administrator'],
]), 'SC-P5-014 escalation roles are limited to SafeContracts roles');
sc_p5v_expect(InvalidArgumentException::class, fn () => NotificationRule::normalizeInput([
    'code'=>'no-target','name'=>'Bad','trigger_type'=>'due_day','recipient_roles'=>[],'target_assigned_accountant'=>false,
]), 'SC-P5-014 notification rules cannot have an empty target policy');

$legacyRow = NotificationRule::fromRow([
    'id'=>'1','code'=>'default_due_10_days','name'=>'Default','trigger_type'=>'before_due','days_before'=>'10',
    'recipient_roles_json'=>'["safecontracts_manager"]','target_assigned_accountant'=>'1','is_active'=>'1',
]);
sc_p5v_assert($legacyRow['days_after'] === 0 && $legacyRow['repeat_interval_days'] === 0 && $legacyRow['max_repeats'] === 0, 'SC-P5-014 legacy database rows normalize missing expanded fields safely');
sc_p5v_assert($legacyRow['template_code'] === 'payment_due_soon' && $legacyRow['escalation_roles'] === [], 'SC-P5-014 legacy database rows receive safe template/escalation defaults');

$payment = ['id'=>8,'due_date'=>'2026-08-15','remaining_amount'=>'100.0000','status'=>PaymentStatus::DUE,'accountant_user_id'=>42];
$dueDay['is_active'] = false;
sc_p5v_assert(! NotificationRule::matchesPayment($dueDay, $payment, new DateTimeImmutable('2026-08-15')), 'SC-P5-014 inactive rules never match');
$dueDay['is_active'] = true;
$payment['remaining_amount'] = '0.0000';
sc_p5v_assert(! NotificationRule::matchesPayment($dueDay, $payment, new DateTimeImmutable('2026-08-15')), 'SC-P5-014 zero remaining balance suppresses even inconsistent non-paid status');
$payment['remaining_amount'] = '100.0000';
$payment['status'] = PaymentStatus::PAID;
sc_p5v_assert(! NotificationRule::matchesPayment($dueDay, $payment, new DateTimeImmutable('2026-08-15')), 'SC-P5-014 paid status suppresses even inconsistent positive balance');
sc_p5v_expect(InvalidArgumentException::class, fn () => NotificationRule::targetDate($dueDay, '2026-02-30'), 'SC-P5-014 invalid contractual due dates are rejected');

$service = new NotificationRuleService();
$GLOBALS['sc_test_current_caps'] = [Capabilities::MANAGE_NOTIFICATIONS=>true];
$beforeSave = count($GLOBALS['sc_test_queries']);
$saved = $service->save([
    'code'=>'validated-overdue','name'=>'Validated overdue','trigger_type'=>'overdue','days_after'=>2,
    'repeat_interval_days'=>5,'max_repeats'=>3,
    'recipient_roles'=>[RoleRegistrar::MANAGER],'escalation_roles'=>[RoleRegistrar::SYSTEM_ADMIN],
    'target_assigned_accountant'=>true,'template_code'=>'payment_overdue','is_active'=>true,
]);
$saveSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeSave));
sc_p5v_assert($saved['days_after'] === 2 && $saved['repeat_interval_days'] === 5 && $saved['max_repeats'] === 3, 'SC-P5-014 expanded rule service returns normalized cadence');
sc_p5v_assert(str_contains($saveSql, 'days_after') && str_contains($saveSql, 'repeat_interval_days') && str_contains($saveSql, 'escalation_roles_json') && str_contains($saveSql, 'template_code'), 'SC-P5-014 expanded fields persist through repository contract');
sc_p5v_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_notification_rule_saved']), 'SC-P5-014 validated expanded rule still emits domain mutation event');

$dbDeltaCount = count($GLOBALS['sc_test_dbdelta']);
$queryCount = count($GLOBALS['sc_test_queries']);
do_action('plugins_loaded');
sc_p5v_assert(count($GLOBALS['sc_test_dbdelta']) === $dbDeltaCount && count($GLOBALS['sc_test_queries']) === $queryCount, 'SC-P5-014 expanded notification migration remains idempotent after activation');

echo "SafeContracts P5 notification rule validation SC-P5-014 passed ({$tests} assertions).\n";
