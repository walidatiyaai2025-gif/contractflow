<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0057EnterpriseContractFinancialCollectionReceiptRevisions;
use SafeContracts\Finance\ContractFinancialCollectionReceiptPolicy;
use SafeContracts\Finance\ContractFinancialCollectionReceiptRevisionRepository;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;
function esc_p9_013_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @param class-string<Throwable> $expected */
function esc_p9_013_expect_throw(callable $callback, string $expected, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p9_013_assert($error instanceof $expected, $message . ' (received ' . $error::class . ')');
        return;
    }
    esc_p9_013_assert(false, $message . ' (no exception)');
}

/** @return array<string,mixed> */
function esc_p9_013_receipt_row(
    string $receiptUuid,
    int $revisionNumber,
    string $scheduleUuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    int|string $sequence = 1,
    mixed $reference = 'RCPT-1',
    mixed $receivedDate = '2026-10-01',
    mixed $amount = '100.0000',
    mixed $currency = 'USD',
    mixed $state = 'recorded',
    int $id = 1001,
    int $profileId = 31
): array {
    return [
        'id' => (string) $id,
        'revision_uuid' => sprintf('20000000-0000-4000-8000-%012x', $id),
        'contract_id' => '55',
        'financial_currency_profile_id' => (string) $profileId,
        'receipt_uuid' => $receiptUuid,
        'revision_number' => (string) $revisionNumber,
        'schedule_entry_uuid' => $scheduleUuid,
        'schedule_sequence_no' => (string) $sequence,
        'external_reference' => $reference,
        'received_date' => $receivedDate,
        'amount' => $amount,
        'currency_code' => $currency,
        'receipt_state' => $state,
        'created_by' => '42',
        'created_at' => '2026-08-17 20:30:00',
    ];
}

/** @return array<string,mixed> */
function esc_p9_013_joined_receipt_row(
    string $receiptUuid,
    int $revisionNumber,
    string $scheduleUuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    int|string $sequence = 1,
    mixed $state = 'recorded',
    int $id = 1001,
    int $profileId = 31,
    mixed $scheduleState = 'scheduled',
    ?int $scheduleProfileId = 31,
    int|string|null $authoritativeSequence = 1
): array {
    $row = esc_p9_013_receipt_row($receiptUuid, $revisionNumber, $scheduleUuid, $sequence, ' RCPT-1 ', '2026-10-01', '125.5', 'usd', $state, $id, $profileId);
    $row['authoritative_schedule_profile_id'] = $scheduleProfileId === null ? null : (string) $scheduleProfileId;
    $row['authoritative_schedule_sequence_no'] = $authoritativeSequence === null ? null : (string) $authoritativeSequence;
    $row['authoritative_schedule_state'] = $scheduleState;
    return $row;
}

/** @return array<string,mixed> */
function esc_p9_013_schedule_row(
    string $scheduleUuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    int|string $sequence = 1,
    string $state = 'scheduled',
    int $profileId = 31,
    string $currency = 'USD',
    string $amount = '1000.0000'
): array {
    return [
        'id' => '901',
        'contract_id' => '55',
        'financial_currency_profile_id' => (string) $profileId,
        'schedule_entry_uuid' => $scheduleUuid,
        'revision_number' => '2',
        'sequence_no' => (string) $sequence,
        'amount' => $amount,
        'currency_code' => $currency,
        'schedule_entry_state' => $state,
    ];
}

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0057EnterpriseContractFinancialCollectionReceiptRevisions.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialCollectionReceiptPolicy.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialCollectionReceiptRevisionRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialCollectionReceiptRevisionService.php');
$scheduleRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialPaymentScheduleRevisionRepository.php');
$legacyPaymentRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Payments/PaymentRepository.php');
$legacyCollectionRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Collections/CollectionRepository.php');
$reconciliationRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialReconciliationRepository.php');
$reconciliationServiceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialReconciliationService.php');
$routerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

// P9-013 schema remains exactly historical at 1.56.0.
esc_p9_013_assert(Migrator::LATEST_VERSION === '1.56.0', 'P9-013 schema remains 1.56.0');
esc_p9_013_assert(str_contains($migratorSource, "'1.55.0' => Migration0056EnterpriseContractFinancialPaymentScheduleEntryRevisions::class"), 'Migration0056 remains historically mapped to 1.55.0');
esc_p9_013_assert(str_contains($migratorSource, "'1.56.0' => Migration0057EnterpriseContractFinancialCollectionReceiptRevisions::class"), 'Migration0057 remains historically mapped to 1.56.0');
esc_p9_013_assert(! str_contains($migratorSource, 'Migration0058'), 'P9-013/P9-015 introduce no Migration0058');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0057EnterpriseContractFinancialCollectionReceiptRevisions())->up($GLOBALS['wpdb']);
esc_p9_013_assert(count($GLOBALS['sc_test_dbdelta']) === 1, 'P9-013 emits one additive table definition');
$schema = (string) ($GLOBALS['sc_test_dbdelta'][0] ?? '');
foreach ([
    'safecontracts_contract_financial_collection_receipt_revisions',
    'financial_currency_profile_id bigint(20) unsigned NOT NULL',
    'receipt_uuid char(36) NOT NULL',
    'schedule_entry_uuid char(36) NOT NULL',
    'schedule_sequence_no int(11) unsigned NOT NULL',
    'external_reference varchar(120) NULL',
    'received_date date NOT NULL',
    'amount decimal(20,4) NOT NULL',
    'currency_code char(3) NOT NULL',
    "receipt_state varchar(16) NOT NULL DEFAULT 'recorded'",
    'UNIQUE KEY tenant_contract_collection_receipt_revision (tenant_id, contract_id, receipt_uuid, revision_number)',
    'KEY tenant_contract_collection_receipt_schedule (tenant_id, contract_id, schedule_entry_uuid, receipt_state, id)',
] as $marker) {
    esc_p9_013_assert(str_contains($schema, $marker), 'P9-013 schema contains ' . $marker);
}
esc_p9_013_assert(! str_contains(strtoupper($migrationSource), 'DROP TABLE') && ! str_contains(strtoupper($migrationSource), 'ALTER TABLE'), 'P9-013 migration remains additive/non-destructive');

// Policy behavior remains unchanged.
esc_p9_013_assert(ContractFinancialCollectionReceiptPolicy::normalizeState('RECORDED') === 'recorded', 'recorded receipt state canonicalizes');
esc_p9_013_assert(ContractFinancialCollectionReceiptPolicy::normalizeState('voided') === 'voided', 'voided receipt state canonicalizes');
esc_p9_013_expect_throw(static fn (): string => ContractFinancialCollectionReceiptPolicy::normalizeState('settled'), InvalidArgumentException::class, 'settlement state remains outside P9-013');
esc_p9_013_assert(ContractFinancialCollectionReceiptPolicy::MAX_RECEIPTS === 1000, 'receipt identities remain bounded to 1000');
esc_p9_013_assert(ContractFinancialCollectionReceiptPolicy::normalizeReference(' RCPT-1 ') === 'RCPT-1', 'receipt reference canonicalizes');
esc_p9_013_assert(ContractFinancialCollectionReceiptPolicy::normalizeReference(' ') === null, 'blank receipt reference canonicalizes to null');
esc_p9_013_assert(ContractFinancialCollectionReceiptPolicy::normalizeReceivedDate('2028-02-29') === '2028-02-29', 'valid receipt leap date is accepted');
esc_p9_013_expect_throw(static fn (): string => ContractFinancialCollectionReceiptPolicy::normalizeReceivedDate('2027-02-29'), InvalidArgumentException::class, 'invalid receipt date fails closed');

// Core P9-013 architecture remains Contract-first, immutable and legacy-isolated.
foreach ([
    'TenantContextStore::context()->requireTenantId()',
    '$authorizeLockedContract($contract)',
    'ContractFinancialCollectionReceiptPolicy::MAX_RECEIPTS + 1',
    'LEFT JOIN {$schedules} s',
    'authoritative_schedule_sequence_no',
    'COUNT(DISTINCT receipt_uuid)',
    'Money::of($amount, $currency)',
    'must be greater than zero',
    "s.schedule_entry_state = 'scheduled'",
    'cannot be relinked to another payment schedule entry',
    'cannot be revised or reactivated',
] as $marker) {
    esc_p9_013_assert(str_contains($repositorySource, $marker), 'P9-013 repository architecture contains ' . $marker);
}
esc_p9_013_assert(! str_contains(strtoupper($repositorySource), 'UPDATE ') && ! str_contains(strtoupper($repositorySource), 'DELETE FROM'), 'receipt evidence remains append-only');
esc_p9_013_assert(! str_contains($repositorySource, 'remaining_amount') && ! str_contains($repositorySource, 'paid_amount'), 'P9-013 still persists no settlement balances');
foreach (['safecontracts_scheduled_payments', 'safecontracts_payment_collections', 'PaymentStatus', 'payment_method_id', 'proof_media_id'] as $forbidden) {
    esc_p9_013_assert(! str_contains($repositorySource, $forbidden) && ! str_contains($serviceSource, $forbidden), 'P9-013 avoids legacy coupling: ' . $forbidden);
}
esc_p9_013_assert(! str_contains($scheduleRepositorySource, 'ContractFinancialCollectionReceipt'), 'P9-012 schedule repository has no reverse receipt coupling');
esc_p9_013_assert(! str_contains($legacyPaymentRepositorySource, 'ContractFinancialCollectionReceipt'), 'legacy PaymentRepository has no Enterprise receipt coupling');
esc_p9_013_assert(! str_contains($legacyCollectionRepositorySource, 'ContractFinancialCollectionReceipt'), 'legacy CollectionRepository has no Enterprise receipt coupling');
esc_p9_013_assert(! str_contains($reconciliationRepositorySource, 'financial_collection_receipt'), 'P9-006 reconciliation does not query receipt evidence');
esc_p9_013_assert(! str_contains($reconciliationServiceSource, 'ContractFinancialCollectionReceipt'), 'P9-006 service has no implicit receipt effect');
esc_p9_013_assert(! str_contains($routerSource, 'ContractFinancialCollectionReceiptRevision'), 'P9-013 exposes no REST route');

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(7);
$repository = new ContractFinancialCollectionReceiptRevisionRepository();
$contractActive = [['id' => '55', 'accountant_user_id' => '42', 'status' => 'active', 'is_archived' => '0']];
$profile = [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']];
$scheduleUuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$receiptUuid = '11111111-1111-4111-8111-111111111111';

// Current read authorizes before profile/receipt reads and validates linked schedule snapshot.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_013_joined_receipt_row($receiptUuid, 2)]];
$authorized = false;
$current = $repository->listCurrentForContract(55, static function (array $contract) use (&$authorized): void {
    esc_p9_013_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'receipt authorization runs before profile/receipt-schedule reads');
    esc_p9_013_assert(str_contains((string) $GLOBALS['sc_test_read_queries'][0], 'FOR UPDATE'), 'receipt authorization is protected by Contract lock');
    $authorized = true;
});
esc_p9_013_assert($authorized && count($current) === 1, 'current receipt read authorizes and returns one receipt');
esc_p9_013_assert($current[0]['external_reference'] === 'RCPT-1' && $current[0]['amount'] === '125.5000', 'current receipt fields canonicalize');
esc_p9_013_assert($current[0]['schedule_sequence_no'] === 1 && $current[0]['currency_code'] === 'USD', 'current receipt schedule/currency snapshots canonicalize');
esc_p9_013_assert(str_contains((string) end($GLOBALS['sc_test_read_queries']), 'LIMIT 1001'), 'current receipt read uses 1001st overflow sentinel');

// Historical receipt remains readable if linked schedule is later voided.
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_013_joined_receipt_row($receiptUuid, 2, $scheduleUuid, 1, 'recorded', 1001, 31, 'voided')]];
esc_p9_013_assert(count($repository->listCurrentForContract(55, static function (array $contract): void {})) === 1, 'receipt history remains readable after linked schedule void');

// Missing/corrupt schedule linkage fails closed.
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_013_joined_receipt_row($receiptUuid, 1, $scheduleUuid, 1, 'recorded', 1001, 31, 'scheduled', null, null)]];
esc_p9_013_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'receipt with missing linked schedule fails closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_013_joined_receipt_row($receiptUuid, 1, $scheduleUuid, 1, 'recorded', 1001, 31, 'scheduled', 31, 2)]];
esc_p9_013_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'receipt schedule-sequence snapshot mismatch fails closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_013_receipt_row($receiptUuid, 1, $scheduleUuid, 1, 'R', '2026-10-01', '1', 'EUR') + [
    'authoritative_schedule_profile_id' => '31',
    'authoritative_schedule_sequence_no' => '1',
    'authoritative_schedule_state' => 'scheduled',
]]];
esc_p9_013_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'cross-currency receipt fails closed');

// 1001 current receipt identities fail closed.
$overflow = [];
for ($i = 1; $i <= 1001; $i++) {
    $overflow[] = esc_p9_013_joined_receipt_row(sprintf('30000000-0000-4000-8000-%012x', $i), 1, $scheduleUuid, 1, 'recorded', 2000 + $i);
}
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, $overflow];
esc_p9_013_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), RuntimeException::class, '1001 current receipt identities fail closed');

// Create uses authoritative schedule Money and remains immutable; empty current-capacity fixture permits normal P9-013 create.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [esc_p9_013_schedule_row($scheduleUuid, 1, 'scheduled', 31, 'USD', '1000')],
    [['total' => '0']],
    [],
    [],
];
$GLOBALS['wpdb']->insert_id = 0;
$created = $repository->createReceipt(55, $scheduleUuid, '22222222-2222-4222-8222-222222222222', '33333333-3333-4333-8333-333333333333', ' RCPT-2 ', '2026-10-02', '50.5', 42, static function (array $contract): void {
    esc_p9_013_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'receipt create authorizes immediately after Contract lock');
});
esc_p9_013_assert($created === 1001, 'active Contract receipt creation succeeds');
$createReads = implode("\n", array_map('strval', $GLOBALS['sc_test_read_queries']));
$createWrites = implode("\n", array_map('strval', $GLOBALS['sc_test_queries']));
esc_p9_013_assert(strpos($createReads, 'safecontracts_contracts') < strpos($createReads, 'financial_currency_profiles') && strpos($createReads, 'financial_currency_profiles') < strpos($createReads, 'payment_schedule_entry_revisions'), 'receipt mutation lock order remains Contract then profile then schedule');
esc_p9_013_assert(str_contains($createWrites, "'50.5000', p.contract_currency, 'recorded', 42"), 'receipt create persists canonical Money and recorded state');
esc_p9_013_assert(str_contains($createWrites, "s.schedule_entry_state = 'scheduled'") && str_contains($createWrites, 'NOT EXISTS ('), 'guarded receipt insert revalidates latest scheduled P9-012 entry');

// Voided linked schedule and non-active Contract lifecycle still block mutation.
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_013_schedule_row($scheduleUuid, 1, 'voided')]];
esc_p9_013_expect_throw(static fn (): int => $repository->createReceipt(55, $scheduleUuid, '22222222-2222-4222-8222-222222222222', '33333333-3333-4333-8333-333333333333', null, '2026-10-02', '1', 42, static function (array $contract): void {}), RuntimeException::class, 'receipt cannot mutate against voided schedule entry');
foreach ([['draft', '0'], ['completed', '0'], ['cancelled', '0'], ['active', '1']] as [$status, $archived]) {
    $GLOBALS['sc_test_result_queue'] = [[['id' => '55', 'accountant_user_id' => '42', 'status' => $status, 'is_archived' => $archived]]];
    esc_p9_013_expect_throw(static fn (): int => $repository->createReceipt(55, $scheduleUuid, '22222222-2222-4222-8222-222222222222', '33333333-3333-4333-8333-333333333333', null, '2026-10-02', '1', 42, static function (array $contract): void {}), RuntimeException::class, "{$status}/archived Contract cannot mutate receipts");
}

// Negative caller Money still fails after authoritative schedule/profile lock.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_013_schedule_row()]];
esc_p9_013_expect_throw(static fn (): int => $repository->createReceipt(55, $scheduleUuid, '22222222-2222-4222-8222-222222222222', '33333333-3333-4333-8333-333333333333', null, '2026-10-02', '-0.0001', 42, static function (array $contract): void {}), InvalidArgumentException::class, 'negative receipt Money fails closed');
esc_p9_013_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'ROLLBACK'), 'invalid receipt Money rolls back');

// Max stable receipt identity limit remains enforced.
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_013_schedule_row()], [['total' => '1000']]];
esc_p9_013_expect_throw(static fn (): int => $repository->createReceipt(55, $scheduleUuid, '22222222-2222-4222-8222-222222222222', '33333333-3333-4333-8333-333333333333', null, '2026-10-02', '1', 42, static function (array $contract): void {}), RuntimeException::class, '1000-receipt Contract cannot create a 1001st receipt');

// Exact revise retry remains idempotent and schedule relinking remains forbidden.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [esc_p9_013_schedule_row()],
    [esc_p9_013_receipt_row($receiptUuid, 1, $scheduleUuid, 1, 'RCPT-1', '2026-10-01', '100')],
];
$retry = $repository->reviseReceipt(55, $scheduleUuid, $receiptUuid, '44444444-4444-4444-8444-444444444444', 'RCPT-1', '2026-10-01', '100', 42, static function (array $contract): void {});
esc_p9_013_assert($retry === 1001 && ! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'exact receipt revise retry remains idempotent');

$otherSchedule = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [esc_p9_013_schedule_row($otherSchedule, 2)],
    [esc_p9_013_receipt_row($receiptUuid, 1, $scheduleUuid, 1)],
];
esc_p9_013_expect_throw(static fn (): int => $repository->reviseReceipt(55, $otherSchedule, $receiptUuid, '55555555-5555-4555-8555-555555555555', 'Relink', '2026-10-03', '10', 42, static function (array $contract): void {}), UnexpectedValueException::class, 'receipt cannot be relinked to another schedule identity');

// Void preserves Money evidence, is terminal and repeated void is idempotent; it needs no capacity fixture.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [esc_p9_013_schedule_row()],
    [esc_p9_013_receipt_row($receiptUuid, 1, $scheduleUuid, 1, 'RCPT-1', '2026-10-01', '100')],
];
$GLOBALS['wpdb']->insert_id = 0;
$repository->voidReceipt(55, $scheduleUuid, $receiptUuid, '66666666-6666-4666-8666-666666666666', 42, static function (array $contract): void {});
esc_p9_013_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), "'100.0000', p.contract_currency, 'voided', 42"), 'receipt void preserves linked Money evidence and appends terminal state');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [esc_p9_013_schedule_row()],
    [esc_p9_013_receipt_row($receiptUuid, 2, $scheduleUuid, 1, 'RCPT-1', '2026-10-01', '100', 'USD', 'voided', 1002)],
];
esc_p9_013_assert($repository->voidReceipt(55, $scheduleUuid, $receiptUuid, '77777777-7777-4777-8777-777777777777', 42, static function (array $contract): void {}) === 1002, 'repeated receipt void returns terminal revision');
esc_p9_013_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'repeated receipt void writes nothing');

// Service authorization and data scope remain unchanged.
esc_p9_013_assert(str_contains($serviceSource, 'authorize(Capabilities::ACCESS)'), 'receipt reads require ACCESS');
esc_p9_013_assert(substr_count($serviceSource, 'authorize(Capabilities::MANAGE_COLLECTIONS)') >= 3, 'receipt mutations require MANAGE_COLLECTIONS');
esc_p9_013_assert(! str_contains($serviceSource, 'authorize(Capabilities::MANAGE_PAYMENTS)'), 'receipt mutation does not borrow MANAGE_PAYMENTS');
esc_p9_013_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'tenant role narrows collection grants');
esc_p9_013_assert(str_contains($serviceSource, 'Capabilities::VIEW_ALL') && str_contains($serviceSource, 'Capabilities::VIEW_ASSIGNED'), 'receipt service preserves locked Contract scope');
esc_p9_013_assert(! str_contains($serviceSource, 'mixed $currency') && ! str_contains($serviceSource, '$tenantId'), 'receipt service exposes no caller currency/tenant');
esc_p9_013_assert(str_contains($gateSource, 'enterprise_contract_financial_collection_receipts_p9_013.php'), 'P9-013 regression remains wired into global backend gate');

fwrite(STDOUT, "P9-013 Enterprise Contract collection receipt revisions passed ({$assertions} assertions).\n");
