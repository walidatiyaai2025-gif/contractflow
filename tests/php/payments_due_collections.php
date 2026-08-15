<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Collections\CollectionService;
use SafeContracts\Database\Migrator;
use SafeContracts\Payments\PaymentService;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;

$GLOBALS['sc_test_post_types'] = [];
if (! function_exists('get_post_type')) {
    function get_post_type(int $postId): string|false { return $GLOBALS['sc_test_post_types'][$postId] ?? false; }
}

$tests = 0;
function sc_dc_assert(bool $ok, string $message): void { global $tests; $tests++; if (! $ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
function sc_dc_expect(string $class, callable $fn, string $message): void { try { $fn(); } catch (Throwable $e) { sc_dc_assert($e instanceof $class, $message); return; } sc_dc_assert(false, $message); }
function sc_dc_payment(array $overrides = []): array { return array_merge([
    'id'=>'7001','contract_id'=>'501','sequence_no'=>'1','reference'=>'P-001','due_date'=>'2026-08-20',
    'expected_payment_date'=>null,'original_amount'=>'500.0000','paid_amount'=>'0.0000','remaining_amount'=>'500.0000',
    'status'=>'upcoming','accountant_user_id'=>'42','contract_is_archived'=>'0',
], $overrides); }

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE];
$activate();
sc_dc_assert(Migrator::LATEST_VERSION === '1.7.0', 'collection migration is current');
sc_dc_assert(count($GLOBALS['sc_test_dbdelta']) === 10, 'collection schema is added');
$schema = $GLOBALS['sc_test_dbdelta'][9];
sc_dc_assert(str_contains($schema, 'wp_safecontracts_payment_collections'), 'collection ledger uses dedicated table');
sc_dc_assert(str_contains($schema, 'payment_method_id bigint(20) unsigned NOT NULL'), 'payment method is mandatory in schema');
sc_dc_assert(str_contains($schema, 'proof_media_id bigint(20) unsigned NULL'), 'proof is optional in schema');
sc_dc_assert(str_contains($schema, 'is_reversed tinyint(1) NOT NULL DEFAULT 0'), 'ledger supports non-destructive reversal state');

$today = new DateTimeImmutable('2026-08-15');
sc_dc_assert(PaymentStatus::temporalForDueDate('2026-09-01', $today, 10) === PaymentStatus::UPCOMING, 'far payment is upcoming');
sc_dc_assert(PaymentStatus::temporalForDueDate('2026-08-25', $today, 10) === PaymentStatus::DUE_SOON, 'ten-day boundary is due soon');
sc_dc_assert(PaymentStatus::temporalForDueDate('2026-08-15', $today, 10) === PaymentStatus::DUE, 'today is due');
sc_dc_assert(PaymentStatus::temporalForDueDate('2026-08-14', $today, 10) === PaymentStatus::OVERDUE, 'past due date is overdue');
sc_dc_assert(PaymentStatus::isDueSoon('2026-08-25', $today, 10), 'due-soon helper works');
sc_dc_assert(PaymentStatus::isOverdue('2026-08-14', $today), 'overdue helper works');
sc_dc_expect(InvalidArgumentException::class, fn () => PaymentStatus::temporalForDueDate('2026-02-30', $today), 'invalid due date is rejected');

$paymentService = new PaymentService();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ALL=>true];
$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment(['due_date'=>'2026-08-14','expected_payment_date'=>'2026-08-30'])]];
sc_dc_assert($paymentService->temporalStatus(7001, $today) === PaymentStatus::OVERDUE, 'overdue uses contractual due date, not expected date');

$collections = new CollectionService();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ALL=>true, Capabilities::MANAGE_COLLECTIONS=>true];
$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment()], [['id'=>'2']]];
$GLOBALS['wpdb']->insert_id = 8101;
$id = $collections->record(['payment_id'=>7001,'amount'=>'125.5','collection_date'=>'2026-08-15','payment_method_id'=>2,'reference'=>' REF-1 ']);
sc_dc_assert($id === 8101, 'collection returns transaction ID');
$sql = (string) end($GLOBALS['sc_test_queries']);
sc_dc_assert(str_contains($sql, 'wp_safecontracts_payment_collections'), 'collection appends ledger row');
sc_dc_assert(str_contains($sql, "'125.5000'"), 'collection amount uses fixed precision');
sc_dc_assert(str_contains($sql, "'REF-1'"), 'collection reference is normalized');
sc_dc_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_collection_recorded']), 'collection emits domain event');

$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment()]];
$before = count($GLOBALS['sc_test_queries']);
sc_dc_expect(InvalidArgumentException::class, fn () => $collections->record(['payment_id'=>7001,'amount'=>'50','collection_date'=>'2026-08-15']), 'missing method is rejected');
sc_dc_assert(count($GLOBALS['sc_test_queries']) === $before, 'missing method causes no mutation');

$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment()], []];
sc_dc_expect(InvalidArgumentException::class, fn () => $collections->record(['payment_id'=>7001,'amount'=>'50','collection_date'=>'2026-08-15','payment_method_id'=>999]), 'inactive method is rejected');

$GLOBALS['sc_test_post_types'][901] = 'attachment';
$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment()], [['id'=>'1']]];
$GLOBALS['wpdb']->insert_id = 8102;
sc_dc_assert($collections->record(['payment_id'=>7001,'amount'=>'75','collection_date'=>'2026-08-15','payment_method_id'=>1,'proof_media_id'=>901]) === 8102, 'optional proof can be stored');
sc_dc_assert(str_contains((string) end($GLOBALS['sc_test_queries']), '901'), 'proof stores WordPress Media ID');

$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment()], [['id'=>'1']]];
sc_dc_expect(InvalidArgumentException::class, fn () => $collections->record(['payment_id'=>7001,'amount'=>'25','collection_date'=>'2026-08-15','payment_method_id'=>1,'proof_media_id'=>999]), 'invalid supplied proof is rejected');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ASSIGNED=>true, Capabilities::MANAGE_COLLECTIONS=>true];
$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment(['accountant_user_id'=>'99'])]];
sc_dc_expect(DomainException::class, fn () => $collections->record(['payment_id'=>7001,'amount'=>'25','collection_date'=>'2026-08-15','payment_method_id'=>1]), 'Accountant scope is enforced');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ALL=>true];
$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment()], [[
    'id'=>'8101','payment_id'=>'7001','amount'=>'125.5000','collection_date'=>'2026-08-15','payment_method_id'=>'2',
    'reference'=>'REF-1','note'=>null,'proof_media_id'=>null,'created_by'=>'42','created_at'=>'2026-08-15 10:30:00','is_reversed'=>'0',
]]];
$rows = $collections->forPayment(7001);
sc_dc_assert(count($rows) === 1 && $rows[0]['payment_method_id'] === 2, 'collection ledger is readable in scope');
sc_dc_assert($rows[0]['proof_media_id'] === null, 'missing proof remains optional');

$dbDeltaCount = count($GLOBALS['sc_test_dbdelta']);
do_action('plugins_loaded');
sc_dc_assert(count($GLOBALS['sc_test_dbdelta']) === $dbDeltaCount, 'collection migration is idempotent');

echo "SafeContracts P3 due/collection tests SC-P3-004..008 passed ({$tests} assertions).\n";
