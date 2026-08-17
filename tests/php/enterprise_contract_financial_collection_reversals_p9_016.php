<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0058EnterpriseContractFinancialCollectionReversalRevisions;
use SafeContracts\Finance\ContractFinancialCollectionReversalPolicy;
use SafeContracts\Finance\ContractFinancialCollectionReversalRevisionRepository;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;
function esc_p9_016_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @param class-string<Throwable> $expected */
function esc_p9_016_expect_throw(callable $callback, string $expected, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p9_016_assert($error instanceof $expected, $message . ' (received ' . $error::class . ')');
        return;
    }
    esc_p9_016_assert(false, $message . ' (no exception)');
}

/** @return array<string,mixed> */
function esc_p9_016_receipt_row(
    string $receiptUuid = '11111111-1111-4111-8111-111111111111',
    string $scheduleUuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    int $sequence = 1,
    string $amount = '100.0000',
    string $state = 'recorded',
    int $profileId = 31,
    string $currency = 'USD',
    int $id = 801
): array {
    return [
        'id' => (string) $id,
        'revision_uuid' => sprintf('10000000-0000-4000-8000-%012x', $id),
        'contract_id' => '55',
        'financial_currency_profile_id' => (string) $profileId,
        'receipt_uuid' => $receiptUuid,
        'revision_number' => '2',
        'schedule_entry_uuid' => $scheduleUuid,
        'schedule_sequence_no' => (string) $sequence,
        'external_reference' => 'REC-' . $id,
        'received_date' => '2026-10-01',
        'amount' => $amount,
        'currency_code' => $currency,
        'receipt_state' => $state,
        'created_by' => '42',
        'created_at' => '2026-08-17 17:10:00',
    ];
}

/** @return array<string,mixed> */
function esc_p9_016_schedule_row(
    string $scheduleUuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    int $sequence = 1,
    string $state = 'scheduled',
    int $profileId = 31,
    string $currency = 'USD',
    int $id = 901
): array {
    return [
        'id' => (string) $id,
        'contract_id' => '55',
        'financial_currency_profile_id' => (string) $profileId,
        'schedule_entry_uuid' => $scheduleUuid,
        'revision_number' => '2',
        'sequence_no' => (string) $sequence,
        'amount' => '100.0000',
        'currency_code' => $currency,
        'schedule_entry_state' => $state,
    ];
}

/** @return array<string,mixed> */
function esc_p9_016_reversal_row(
    string $reversalUuid,
    string $receiptUuid = '11111111-1111-4111-8111-111111111111',
    string $scheduleUuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    int $sequence = 1,
    string $amount = '40.0000',
    string $state = 'recorded',
    int $id = 1001,
    int $profileId = 31,
    string $currency = 'USD',
    string $reference = 'REV-1',
    string $date = '2026-10-05',
    int $revisionNumber = 2
): array {
    return [
        'id' => (string) $id,
        'revision_uuid' => sprintf('90000000-0000-4000-8000-%012x', $id),
        'contract_id' => '55',
        'financial_currency_profile_id' => (string) $profileId,
        'reversal_uuid' => $reversalUuid,
        'revision_number' => (string) $revisionNumber,
        'receipt_uuid' => $receiptUuid,
        'schedule_entry_uuid' => $scheduleUuid,
        'schedule_sequence_no' => (string) $sequence,
        'external_reference' => $reference,
        'reversal_date' => $date,
        'amount' => $amount,
        'currency_code' => $currency,
        'reversal_state' => $state,
        'created_by' => '42',
        'created_at' => '2026-08-17 17:10:00',
    ];
}

/** @return array<string,mixed> */
function esc_p9_016_joined_reversal_row(
    string $reversalUuid,
    string $receiptUuid = '11111111-1111-4111-8111-111111111111',
    string $scheduleUuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    int $sequence = 1,
    string $amount = '40.0000',
    string $reversalState = 'recorded',
    string $receiptState = 'recorded',
    string $scheduleState = 'scheduled',
    int $id = 1001,
    int $profileId = 31,
    string $currency = 'USD'
): array {
    $row = esc_p9_016_reversal_row($reversalUuid, $receiptUuid, $scheduleUuid, $sequence, $amount, $reversalState, $id, $profileId, $currency);
    $row['authoritative_receipt_profile_id'] = (string) $profileId;
    $row['authoritative_receipt_schedule_uuid'] = $scheduleUuid;
    $row['authoritative_receipt_schedule_sequence_no'] = (string) $sequence;
    $row['authoritative_receipt_amount'] = '100.0000';
    $row['authoritative_receipt_currency'] = $currency;
    $row['authoritative_receipt_state'] = $receiptState;
    $row['authoritative_schedule_profile_id'] = (string) $profileId;
    $row['authoritative_schedule_sequence_no'] = (string) $sequence;
    $row['authoritative_schedule_currency'] = $currency;
    $row['authoritative_schedule_state'] = $scheduleState;
    return $row;
}

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0058EnterpriseContractFinancialCollectionReversalRevisions.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialCollectionReversalRevisionRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialCollectionReversalRevisionService.php');
$receiptRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialCollectionReceiptRevisionRepository.php');
$scheduleRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialPaymentScheduleRevisionRepository.php');
$settlementRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialScheduleSettlementRepository.php');
$legacyPaymentSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Payments/PaymentRepository.php');
$legacyCollectionSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Collections/CollectionRepository.php');
$routerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

// Historical P9-016 schema/policy boundary remains exact even when later stages consume reversal evidence.
esc_p9_016_assert(Migrator::LATEST_VERSION === '1.57.0', 'P9-016 schema remains exactly 1.57.0');
esc_p9_016_assert(str_contains($migratorSource, "'1.56.0' => Migration0057EnterpriseContractFinancialCollectionReceiptRevisions::class"), 'P9-013 historical mapping remains 1.56.0');
esc_p9_016_assert(str_contains($migratorSource, "'1.57.0' => Migration0058EnterpriseContractFinancialCollectionReversalRevisions::class"), 'P9-016 maps Migration0058 exactly to 1.57.0');
$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0058EnterpriseContractFinancialCollectionReversalRevisions())->up($GLOBALS['wpdb']);
esc_p9_016_assert(count($GLOBALS['sc_test_dbdelta']) === 1, 'P9-016 emits one additive reversal table definition');
$schema = (string) ($GLOBALS['sc_test_dbdelta'][0] ?? '');
foreach ([
    'safecontracts_contract_financial_collection_reversal_revisions',
    'reversal_uuid char(36) NOT NULL',
    'receipt_uuid char(36) NOT NULL',
    'schedule_entry_uuid char(36) NOT NULL',
    'amount decimal(20,4) NOT NULL',
    "reversal_state varchar(16) NOT NULL DEFAULT 'recorded'",
    'UNIQUE KEY tenant_contract_collection_reversal_revision',
] as $marker) {
    esc_p9_016_assert(str_contains($schema, $marker), 'P9-016 schema retains ' . $marker);
}
esc_p9_016_assert(! str_contains(strtoupper($migrationSource), 'DROP TABLE') && ! str_contains(strtoupper($migrationSource), 'ALTER TABLE'), 'P9-016 migration remains additive/non-destructive');
esc_p9_016_assert(ContractFinancialCollectionReversalPolicy::MAX_REVERSALS === 1000, 'reversal identities remain bounded to 1000');
esc_p9_016_assert(ContractFinancialCollectionReversalPolicy::normalizeState('RECORDED') === 'recorded', 'recorded reversal canonicalizes');
esc_p9_016_assert(ContractFinancialCollectionReversalPolicy::normalizeState('voided') === 'voided', 'voided reversal canonicalizes');
esc_p9_016_expect_throw(static fn (): string => ContractFinancialCollectionReversalPolicy::normalizeState('refunded'), InvalidArgumentException::class, 'unsupported reversal state fails closed');
esc_p9_016_assert(ContractFinancialCollectionReversalPolicy::normalizeReference(' REV-1 ') === 'REV-1', 'reversal reference canonicalizes');
esc_p9_016_assert(ContractFinancialCollectionReversalPolicy::normalizeReversalDate('2028-02-29') === '2028-02-29', 'valid reversal date is accepted');

foreach ([
    'TenantContextStore::context()->requireTenantId()',
    '$authorizeLockedContract($contract)',
    'ContractFinancialCollectionReversalPolicy::MAX_REVERSALS + 1',
    'COUNT(DISTINCT reversal_uuid)',
    'assertReversalCapacity(',
    'ORDER BY r.reversal_uuid ASC LIMIT %d FOR UPDATE',
    '$used = $used->add(',
    '$used->add($proposed)->compare($receiptMoney) > 0',
    'cannot be relinked to another receipt or schedule',
    'cannot be revised or reactivated',
    "rr.receipt_state = 'recorded'",
    "s.schedule_entry_state = 'scheduled'",
] as $marker) {
    esc_p9_016_assert(str_contains($repositorySource, $marker), 'P9-016 reversal repository retains ' . $marker);
}
esc_p9_016_assert(! str_contains(strtoupper($repositorySource), 'SUM('), 'P9-016 uses no SQL SUM');
esc_p9_016_assert(! str_contains($repositorySource, 'float') && ! str_contains($repositorySource, 'round('), 'P9-016 uses no float/rounding path');
esc_p9_016_assert(! str_contains(strtoupper($repositorySource), 'UPDATE ') && ! str_contains(strtoupper($repositorySource), 'DELETE FROM'), 'P9-016 reversal evidence remains append-only');
esc_p9_016_assert(str_contains($serviceSource, 'authorize(Capabilities::ACCESS)') && substr_count($serviceSource, 'authorize(Capabilities::MANAGE_COLLECTIONS)') >= 3, 'P9-016 preserves ACCESS reads and MANAGE_COLLECTIONS mutations');
esc_p9_016_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'P9-016 preserves tenant-role authorization narrowing');
esc_p9_016_assert(! str_contains($routerSource, 'ContractFinancialCollectionReversalRevision'), 'P9-016 still exposes no REST surface');
esc_p9_016_assert(! str_contains($scheduleRepositorySource, 'ContractFinancialCollectionReversal'), 'P9-012 schedule mutation remains independent of reversals');
esc_p9_016_assert(! str_contains($repositorySource, 'ContractFinancialScheduleSettlement'), 'P9-016 reversal mutation remains independent of settlement semantics');
esc_p9_016_assert(str_contains($settlementRepositorySource, 'safecontracts_contract_financial_collection_reversal_revisions'), 'later read-only settlement may consume P9-016 reversal evidence');
esc_p9_016_assert(str_contains($receiptRepositorySource, 'safecontracts_contract_financial_collection_reversal_revisions'), 'later receipt capacity stages may consume P9-016 reversal evidence without changing reversal mutation semantics');
esc_p9_016_assert(! str_contains($legacyPaymentSource, 'ContractFinancialCollectionReversal') && ! str_contains($legacyCollectionSource, 'ContractFinancialCollectionReversal'), 'legacy Payments/Collections remain isolated');

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(7);
$repository = new ContractFinancialCollectionReversalRevisionRepository();
$contractActive = [['id' => '55', 'accountant_user_id' => '42', 'status' => 'active', 'is_archived' => '0']];
$profile = [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']];
$receiptUuid = '11111111-1111-4111-8111-111111111111';
$scheduleUuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$targetUuid = '22222222-2222-4222-8222-222222222222';
$otherUuid = '33333333-3333-4333-8333-333333333333';
$voidedUuid = '44444444-4444-4444-8444-444444444444';

// Latest-current reversal read remains tenant/profile/receipt/schedule validated.
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_016_joined_reversal_row($targetUuid)]];
$authorized = false;
$current = $repository->listCurrentForContract(55, static function (array $contract) use (&$authorized): void { $authorized = true; });
esc_p9_016_assert($authorized && count($current) === 1, 'P9-016 current reversal read returns validated evidence');
esc_p9_016_assert($current[0]['amount'] === '40.0000' && $current[0]['currency_code'] === 'USD', 'P9-016 read canonicalizes Money');

// Historical reversal remains readable after receipt/schedule become terminal.
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_016_joined_reversal_row($targetUuid, $receiptUuid, $scheduleUuid, 1, '40', 'recorded', 'voided', 'voided')]];
esc_p9_016_assert(count($repository->listCurrentForContract(55, static function (array $contract): void {})) === 1, 'P9-016 historical reversal remains readable after terminal linked state');

// Create exactly to reversal capacity; latest voided reversal consumes zero.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [esc_p9_016_receipt_row($receiptUuid, $scheduleUuid, 1, '100')],
    [esc_p9_016_schedule_row($scheduleUuid, 1)],
    [['total' => '2']],
    [],
    [
        esc_p9_016_reversal_row($otherUuid, $receiptUuid, $scheduleUuid, 1, '40', 'recorded', 1101),
        esc_p9_016_reversal_row($voidedUuid, $receiptUuid, $scheduleUuid, 1, '90', 'voided', 1102),
    ],
];
$GLOBALS['wpdb']->insert_id = 0;
$created = $repository->createReversal(55, $receiptUuid, '55555555-5555-4555-8555-555555555555', '66666666-6666-4666-8666-666666666666', 'NEW-60', '2026-10-06', '60', 42, static function (array $contract): void {});
esc_p9_016_assert($created === 1001, 'P9-016 create exactly at receipt reversal capacity succeeds');

// Over-capacity create rolls back without append.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [esc_p9_016_receipt_row($receiptUuid, $scheduleUuid, 1, '100')],
    [esc_p9_016_schedule_row($scheduleUuid, 1)],
    [['total' => '1']],
    [],
    [esc_p9_016_reversal_row($otherUuid, $receiptUuid, $scheduleUuid, 1, '40', 'recorded', 1201)],
];
esc_p9_016_expect_throw(static fn (): int => $repository->createReversal(55, $receiptUuid, '55555555-5555-4555-8555-555555555555', '66666666-6666-4666-8666-666666666666', null, '2026-10-06', '60.0001', 42, static function (array $contract): void {}), RuntimeException::class, 'P9-016 over-capacity reversal create fails closed');
esc_p9_016_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'P9-016 over-capacity create appends nothing');

// Revise excludes target reversal and exact retry returns before capacity aggregation.
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [esc_p9_016_receipt_row($receiptUuid, $scheduleUuid, 1, '100')],
    [esc_p9_016_schedule_row($scheduleUuid, 1)],
    [esc_p9_016_reversal_row($targetUuid, $receiptUuid, $scheduleUuid, 1, '40', 'recorded', 1301, 31, 'USD', 'OLD')],
    [
        esc_p9_016_reversal_row($targetUuid, $receiptUuid, $scheduleUuid, 1, '40', 'recorded', 1301, 31, 'USD', 'OLD'),
        esc_p9_016_reversal_row($otherUuid, $receiptUuid, $scheduleUuid, 1, '50', 'recorded', 1302),
    ],
];
$GLOBALS['wpdb']->insert_id = 0;
esc_p9_016_assert($repository->reviseReversal(55, $receiptUuid, $targetUuid, '77777777-7777-4777-8777-777777777777', 'NEW', '2026-10-07', '50', 42, static function (array $contract): void {}) === 1001, 'P9-016 revise excludes target stable reversal before capacity');

$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [esc_p9_016_receipt_row($receiptUuid, $scheduleUuid, 1, '100')],
    [esc_p9_016_schedule_row($scheduleUuid, 1)],
    [esc_p9_016_reversal_row($targetUuid, $receiptUuid, $scheduleUuid, 1, '60', 'recorded', 1401, 31, 'USD', 'SAME', '2026-10-08')],
];
$retry = $repository->reviseReversal(55, $receiptUuid, $targetUuid, '88888888-8888-4888-8888-888888888888', 'SAME', '2026-10-08', '60', 42, static function (array $contract): void {});
esc_p9_016_assert($retry === 1401 && count($GLOBALS['sc_test_read_queries']) === 5, 'P9-016 exact revise retry remains idempotent before capacity read');

// Void remains capacity-read free; repeated terminal void stays idempotent.
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [esc_p9_016_receipt_row($receiptUuid, $scheduleUuid, 1, '100')],
    [esc_p9_016_schedule_row($scheduleUuid, 1)],
    [esc_p9_016_reversal_row($targetUuid, $receiptUuid, $scheduleUuid, 1, '60', 'recorded', 1501)],
];
$GLOBALS['wpdb']->insert_id = 0;
esc_p9_016_assert($repository->voidReversal(55, $receiptUuid, $targetUuid, '99999999-9999-4999-8999-999999999999', 42, static function (array $contract): void {}) === 1001, 'P9-016 reversal void succeeds');
esc_p9_016_assert(count($GLOBALS['sc_test_read_queries']) === 5, 'P9-016 void performs no reversal capacity aggregation');

// Terminal receipt/schedule block new reversal mutation.
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_016_receipt_row($receiptUuid, $scheduleUuid, 1, '100', 'voided')]];
esc_p9_016_expect_throw(static fn (): int => $repository->createReversal(55, $receiptUuid, '55555555-5555-4555-8555-555555555555', '66666666-6666-4666-8666-666666666666', null, '2026-10-06', '1', 42, static function (array $contract): void {}), RuntimeException::class, 'voided receipt blocks P9-016 reversal mutation');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_016_receipt_row($receiptUuid, $scheduleUuid, 1, '100')], [esc_p9_016_schedule_row($scheduleUuid, 1, 'voided')]];
esc_p9_016_expect_throw(static fn (): int => $repository->createReversal(55, $receiptUuid, '55555555-5555-4555-8555-555555555555', '66666666-6666-4666-8666-666666666666', null, '2026-10-06', '1', 42, static function (array $contract): void {}), RuntimeException::class, 'voided schedule blocks P9-016 reversal mutation');

// 1001 current reversal identities fail closed.
$overflow = [];
for ($i = 1; $i <= ContractFinancialCollectionReversalPolicy::MAX_REVERSALS + 1; $i++) {
    $overflow[] = esc_p9_016_reversal_row(sprintf('70000000-0000-4000-8000-%012x', $i), $receiptUuid, $scheduleUuid, 1, '0.0001', 'recorded', 3000 + $i);
}
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [esc_p9_016_receipt_row($receiptUuid, $scheduleUuid, 1, '100')],
    [esc_p9_016_schedule_row($scheduleUuid, 1)],
    [['total' => '1']],
    [],
    $overflow,
];
esc_p9_016_expect_throw(static fn (): int => $repository->createReversal(55, $receiptUuid, '55555555-5555-4555-8555-555555555555', '66666666-6666-4666-8666-666666666666', null, '2026-10-06', '1', 42, static function (array $contract): void {}), RuntimeException::class, 'P9-016 1001st reversal sentinel fails closed');

esc_p9_016_assert(str_contains($gateSource, 'enterprise_contract_financial_collection_reversals_p9_016.php'), 'P9-016 regression remains wired into global backend gate');
fwrite(STDOUT, "P9-016 Enterprise Contract collection reversal historical foundation passed ({$assertions} assertions).\n");
