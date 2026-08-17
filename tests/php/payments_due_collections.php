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
    'id'=>'7001','contract_id'=>'501','financial_direction'=>'receivable','currency_code'=>'XXX',
    'sequence_no'=>'1','reference'=>'P-001','due_date'=>'2026-08-20',
    'expected_payment_date'=>null,'original_amount'=>'500.0000','paid_amount'=>'0.0000','remaining_amount'=>'500.0000',
    'status'=>'upcoming','accountant_user_id'=>'42','contract_is_archived'=>'0',
    'counterparty_type'=>'customer','counterparty_id'=>'7',
], $overrides); }
/** @return list<string> */
function sc_dc_mutations_since(int $offset): array { return array_slice($GLOBALS['sc_test_queries'], $offset); }

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE];
$activate();
sc_dc_assert(version_compare(Migrator::LATEST_VERSION, '1.7.0', '>='), 'SC-P3-006 collection migration remains available after later schema versions');
sc_dc_assert(count($GLOBALS['sc_test_dbdelta']) >= 10, 'SC-P3-006 collection schema remains present after later migrations');
$schema = $GLOBALS['sc_test_dbdelta'][9];
sc_dc_assert(str_contains($schema, 'wp_safecontracts_payment_collections'), 'SC-P3-006 collection ledger uses dedicated table');
sc_dc_assert(str_contains($schema, 'amount decimal(20,4) NOT NULL'), 'SC-P3-006 collection amount uses fixed precision');
sc_dc_assert(str_contains($schema, 'payment_method_id bigint(20) unsigned NOT NULL'), 'SC-P3-007 payment method is mandatory in schema');
sc_dc_assert(str_contains($schema, 'proof_media_id bigint(20) unsigned NULL'), 'SC-P3-008 proof is optional in schema');
sc_dc_assert(str_contains($schema, 'details text NULL'), 'SC-P3-006 collection details are supported');
sc_dc_assert(str_contains($schema, 'updated_by bigint(20) unsigned NULL'), 'SC-P3-006 collection updater is traceable');
sc_dc_assert(str_contains($schema, 'updated_at datetime NOT NULL'), 'SC-P3-006 collection updated timestamp is stored');
sc_dc_assert(! str_contains($schema, 'is_reversed'), 'reversal workflow is not pre-implemented outside this batch');

$today = new DateTimeImmutable('2026-08-15');
sc_dc_assert(PaymentStatus::temporalForDueDate('2026-09-01', $today, 10) === PaymentStatus::UPCOMING, 'SC-P3-004 far payment is upcoming');
sc_dc_assert(PaymentStatus::temporalForDueDate('2026-08-25', $today, 10) === PaymentStatus::DUE_SOON, 'SC-P3-004 ten-day boundary is due soon');
sc_dc_assert(PaymentStatus::temporalForDueDate('2026-08-15', $today, 10) === PaymentStatus::DUE, 'SC-P3-004 today is due');
sc_dc_assert(PaymentStatus::temporalForDueDate('2026-08-14', $today, 10) === PaymentStatus::OVERDUE, 'SC-P3-005 past contractual due date is overdue');
sc_dc_assert(PaymentStatus::isDueSoon('2026-08-25', $today, 10), 'SC-P3-004 due-soon helper works');
sc_dc_assert(PaymentStatus::isOverdue('2026-08-14', $today), 'SC-P3-005 overdue helper works');
sc_dc_expect(InvalidArgumentException::class, fn () => PaymentStatus::temporalForDueDate('2026-02-30', $today), 'SC-P3-004 invalid date is rejected');
sc_dc_expect(InvalidArgumentException::class, fn () => PaymentStatus::temporalForDueDate('2026-08-20', $today, -1), 'SC-P3-004 negative due-soon window is rejected');

$paymentService = new PaymentService();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ALL=>true];
$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment(['due_date'=>'2026-08-14','expected_payment_date'=>'2026-08-30'])]];
sc_dc_assert($paymentService->temporalStatus(7001, $today) === PaymentStatus::OVERDUE, 'SC-P3-005 later expected date does not erase contractual overdue state');
$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment(['due_date'=>'2026-08-25','expected_payment_date'=>'2026-09-15'])]];
sc_dc_assert($paymentService->isDueSoon(7001, $today, 10), 'SC-P3-004 due-soon uses contractual due date even with later expected date');
$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment(['due_date'=>'2026-09-01','expected_payment_date'=>'2026-08-14'])]];
sc_dc_assert($paymentService->temporalStatus(7001, $today) === PaymentStatus::UPCOMING, 'SC-P3-005 earlier expected date does not make future contractual due date overdue');
$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment(['due_date'=>'2026-08-14'])]];
sc_dc_assert($paymentService->isOverdue(7001, $today), 'SC-P3-005 service identifies overdue contractual due date');
$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment(['due_date'=>'2026-08-01','status'=>PaymentStatus::PARTIALLY_PAID])]];
sc_dc_assert($paymentService->temporalStatus(7001, $today) === PaymentStatus::PARTIALLY_PAID, 'financial partially-paid state takes precedence in combined status helper');
$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment(['due_date'=>'2026-08-01','status'=>PaymentStatus::PAID])]];
sc_dc_assert($paymentService->temporalStatus(7001, $today) === PaymentStatus::PAID, 'financial paid state takes precedence in combined status helper');

$collections = new CollectionService();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ALL=>true, Capabilities::MANAGE_COLLECTIONS=>true];
$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment()], [['id'=>'2']], [['total'=>'0.0000']]];
$GLOBALS['wpdb']->insert_id = 8101;
$beforeRecord = count($GLOBALS['sc_test_queries']);
$id = $collections->record(['payment_id'=>7001,'amount'=>'125.5','collection_date'=>'2026-08-15','payment_method_id'=>2,'reference'=>' REF-1 ','details'=>' First collection ']);
sc_dc_assert($id === 8101, 'SC-P3-006 collection returns transaction ID');
$recordMutations = sc_dc_mutations_since($beforeRecord);
sc_dc_assert($recordMutations[0] === 'START TRANSACTION' && end($recordMutations) === 'COMMIT', 'SC-P3-006 collection write remains transactionally bounded');
$recordSql = implode("\n", $recordMutations);
sc_dc_assert(str_contains($recordSql, 'INSERT INTO wp_safecontracts_payment_collections'), 'SC-P3-006 collection appends ledger row');
sc_dc_assert(str_contains($recordSql, "'125.5000'"), 'SC-P3-006 collection amount uses fixed precision');
sc_dc_assert(str_contains($recordSql, "'REF-1'") && str_contains($recordSql, "'First collection'"), 'SC-P3-006 collection text is normalized');
sc_dc_assert(str_contains($recordSql, ', 2,'), 'SC-P3-007 active payment method ID is persisted');
sc_dc_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_collection_recorded']), 'SC-P3-006 collection emits domain event');

$beforeMissing = count($GLOBALS['sc_test_queries']);
sc_dc_expect(InvalidArgumentException::class, fn () => $collections->record(['payment_id'=>7001,'amount'=>'50','collection_date'=>'2026-08-15']), 'SC-P3-007 missing method is rejected');
sc_dc_assert(count($GLOBALS['sc_test_queries']) === $beforeMissing, 'SC-P3-007 missing method is rejected before transaction');

$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment()], []];
$beforeInactive = count($GLOBALS['sc_test_queries']);
sc_dc_expect(InvalidArgumentException::class, fn () => $collections->record(['payment_id'=>7001,'amount'=>'50','collection_date'=>'2026-08-15','payment_method_id'=>999]), 'SC-P3-007 inactive method is rejected');
$inactiveMutations = sc_dc_mutations_since($beforeInactive);
sc_dc_assert($inactiveMutations === ['START TRANSACTION', 'ROLLBACK'], 'SC-P3-007 inactive method rolls back transaction without collection write');

$GLOBALS['sc_test_post_types'][901] = 'attachment';
$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment()], [['id'=>'1']], [['total'=>'0.0000']]];
$GLOBALS['wpdb']->insert_id = 8102;
$beforeProofRecord = count($GLOBALS['sc_test_queries']);
sc_dc_assert($collections->record(['payment_id'=>7001,'amount'=>'75','collection_date'=>'2026-08-15','payment_method_id'=>1,'proof_media_id'=>901]) === 8102, 'SC-P3-008 optional proof can be stored');
sc_dc_assert(str_contains(implode("\n", sc_dc_mutations_since($beforeProofRecord)), '901'), 'SC-P3-008 proof stores WordPress Media ID');

$beforeBadProof = count($GLOBALS['sc_test_queries']);
sc_dc_expect(InvalidArgumentException::class, fn () => $collections->record(['payment_id'=>7001,'amount'=>'25','collection_date'=>'2026-08-15','payment_method_id'=>1,'proof_media_id'=>999]), 'SC-P3-008 invalid supplied proof is rejected');
sc_dc_assert(count($GLOBALS['sc_test_queries']) === $beforeBadProof, 'SC-P3-008 invalid proof is rejected before transaction');

$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment()], [['id'=>'1']], [['total'=>'0.0000']]];
$GLOBALS['wpdb']->insert_id = 8103;
$beforeNoProof = count($GLOBALS['sc_test_queries']);
$collections->record(['payment_id'=>7001,'amount'=>'25','collection_date'=>'2026-08-15','payment_method_id'=>1]);
sc_dc_assert(str_contains(implode("\n", sc_dc_mutations_since($beforeNoProof)), 'NULL'), 'SC-P3-008 omitted proof remains nullable');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ASSIGNED=>true, Capabilities::MANAGE_COLLECTIONS=>true];
$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment(['accountant_user_id'=>'99'])]];
$beforeScope = count($GLOBALS['sc_test_queries']);
sc_dc_expect(DomainException::class, fn () => $collections->record(['payment_id'=>7001,'amount'=>'25','collection_date'=>'2026-08-15','payment_method_id'=>1]), 'SC-P3-006 Accountant scope is enforced');
sc_dc_assert(sc_dc_mutations_since($beforeScope) === ['START TRANSACTION', 'ROLLBACK'], 'SC-P3-006 scope denial rolls back before write');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ALL=>true, Capabilities::MANAGE_COLLECTIONS=>true];
$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment(['contract_is_archived'=>'1'])]];
$beforeArchived = count($GLOBALS['sc_test_queries']);
sc_dc_expect(DomainException::class, fn () => $collections->record(['payment_id'=>7001,'amount'=>'25','collection_date'=>'2026-08-15','payment_method_id'=>1]), 'SC-P3-006 archived contract blocks collection recording');
sc_dc_assert(sc_dc_mutations_since($beforeArchived) === ['START TRANSACTION', 'ROLLBACK'], 'SC-P3-006 archived contract denial is non-mutating');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ALL=>true];
$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment()], [[
    'id'=>'8101','payment_id'=>'7001','financial_direction'=>'receivable','currency_code'=>'XXX',
    'amount'=>'125.5000','collection_date'=>'2026-08-15','payment_method_id'=>'2',
    'reference'=>'REF-1','details'=>'First collection','proof_media_id'=>null,'created_by'=>'42','updated_by'=>'42',
    'created_at'=>'2026-08-15 10:30:00','updated_at'=>'2026-08-15 10:30:00',
]]];
$rows = $collections->forPayment(7001);
sc_dc_assert(count($rows) === 1 && $rows[0]['payment_method_id'] === 2, 'SC-P3-006 collection ledger is readable in scope');
sc_dc_assert($rows[0]['details'] === 'First collection', 'SC-P3-006 collection details are normalized on read');
sc_dc_assert($rows[0]['proof_media_id'] === null, 'SC-P3-008 missing proof remains optional on read');

$dbDeltaCount = count($GLOBALS['sc_test_dbdelta']);
do_action('plugins_loaded');
sc_dc_assert(count($GLOBALS['sc_test_dbdelta']) === $dbDeltaCount, 'SC-P3-006 collection migration is idempotent');

echo "SafeContracts P3 due/collection tests SC-P3-004..008 passed ({$tests} assertions).\n";
