<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Finance\ContractFinancialCollectionReceiptPolicy;
use SafeContracts\Finance\ContractFinancialCollectionReceiptRevisionRepository;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;
function esc_p9_015_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @param class-string<Throwable> $expected */
function esc_p9_015_expect_throw(callable $callback, string $expected, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p9_015_assert($error instanceof $expected, $message . ' (received ' . $error::class . ')');
        return;
    }
    esc_p9_015_assert(false, $message . ' (no exception)');
}

/** @return array<string,mixed> */
function esc_p9_015_schedule_row(
    string $scheduleUuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    int $sequence = 1,
    string $amount = '100.0000',
    string $state = 'scheduled',
    int $profileId = 31,
    string $currency = 'USD'
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

/** @return array<string,mixed> */
function esc_p9_015_receipt_row(
    string $receiptUuid,
    string $scheduleUuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    int $sequence = 1,
    string $amount = '40.0000',
    string $state = 'recorded',
    int $id = 1001,
    int $profileId = 31,
    string $currency = 'USD',
    string $reference = 'R-1',
    string $date = '2026-10-01'
): array {
    return [
        'id' => (string) $id,
        'revision_uuid' => sprintf('20000000-0000-4000-8000-%012x', $id),
        'contract_id' => '55',
        'financial_currency_profile_id' => (string) $profileId,
        'receipt_uuid' => $receiptUuid,
        'revision_number' => '2',
        'schedule_entry_uuid' => $scheduleUuid,
        'schedule_sequence_no' => (string) $sequence,
        'external_reference' => $reference,
        'received_date' => $date,
        'amount' => $amount,
        'currency_code' => $currency,
        'receipt_state' => $state,
        'created_by' => '42',
        'created_at' => '2026-08-17 22:00:00',
    ];
}

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialCollectionReceiptRevisionRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialCollectionReceiptRevisionService.php');
$scheduleRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialPaymentScheduleRevisionRepository.php');
$settlementRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialScheduleSettlementRepository.php');
$legacyPaymentSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Payments/PaymentRepository.php');
$legacyCollectionSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Collections/CollectionRepository.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

// P9-015 remains a historical 1.56.0 boundary after later additive/reversal-aware stages.
esc_p9_015_assert(version_compare(Migrator::LATEST_VERSION, '1.56.0', '>='), 'P9-015 historical schema boundary remains available at or after 1.56.0');
esc_p9_015_assert(str_contains($migratorSource, "'1.56.0' => Migration0057EnterpriseContractFinancialCollectionReceiptRevisions::class"), 'P9-015 preserves exact Migration0057 mapping');

foreach ([
    'assertCollectionCapacity(',
    'ContractFinancialCollectionReceiptPolicy::MAX_RECEIPTS + 1',
    'ORDER BY r.receipt_uuid ASC',
    'LIMIT %d FOR UPDATE',
    '$used = $used->add(',
    'would exceed the linked payment schedule amount',
] as $marker) {
    esc_p9_015_assert(str_contains($repositorySource, $marker), 'P9-015 capacity implementation retains ' . $marker);
}
esc_p9_015_assert(
    str_contains($repositorySource, '$used->add($proposedAmount)->compare($scheduledMoney) > 0')
    || str_contains($repositorySource, '$used->add($effectiveProposed)->compare($scheduledMoney) > 0'),
    'P9-015 final scheduled-capacity comparison remains enforced after later net-capacity stages'
);
esc_p9_015_assert(! str_contains(strtoupper($repositorySource), 'SUM('), 'capacity uses no SQL SUM');
esc_p9_015_assert(! str_contains($repositorySource, 'float') && ! str_contains($repositorySource, 'round('), 'capacity uses no float/rounding path');
esc_p9_015_assert(substr_count($repositorySource, 'assertCollectionCapacity(') === 3, 'capacity helper remains defined once and called only by create/revise');
esc_p9_015_assert(strpos($repositorySource, "commit('idempotent Enterprise Contract collection receipt revision')") < strpos($repositorySource, 'assertCollectionCapacity(', strpos($repositorySource, 'public function reviseReceipt')), 'exact revise retry still returns before capacity check');
esc_p9_015_assert(! str_contains($scheduleRepositorySource, 'assertCollectionCapacity'), 'P9-012 schedule repository remains unchanged');
esc_p9_015_assert(! str_contains($legacyPaymentSource, 'assertCollectionCapacity') && ! str_contains($legacyCollectionSource, 'assertCollectionCapacity'), 'legacy Payments/Collections remain isolated');
esc_p9_015_assert(str_contains($serviceSource, 'MANAGE_COLLECTIONS'), 'P9-013 MANAGE_COLLECTIONS authorization remains unchanged');

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(7);
$repository = new ContractFinancialCollectionReceiptRevisionRepository();
$contractActive = [['id' => '55', 'accountant_user_id' => '42', 'status' => 'active', 'is_archived' => '0']];
$profile = [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']];
$scheduleUuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$targetUuid = '11111111-1111-4111-8111-111111111111';
$otherUuid = '22222222-2222-4222-8222-222222222222';
$voidedUuid = '33333333-3333-4333-8333-333333333333';

// Historical P9-015 behavior with no reversals: 40 recorded + 60 proposed = exact schedule capacity.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [esc_p9_015_schedule_row($scheduleUuid, 1, '100')],
    [['total' => '2']],
    [],
    [
        esc_p9_015_receipt_row($otherUuid, $scheduleUuid, 1, '40', 'recorded', 1101),
        esc_p9_015_receipt_row($voidedUuid, $scheduleUuid, 1, '30', 'voided', 1102),
    ],
    [],
];
$GLOBALS['wpdb']->insert_id = 0;
$created = $repository->createReceipt(55, $scheduleUuid, '44444444-4444-4444-8444-444444444444', '55555555-5555-4555-8555-555555555555', 'NEW-60', '2026-10-02', '60', 42, static function (array $contract): void {});
esc_p9_015_assert($created === 1001, 'create exactly at historical gross capacity succeeds when no reversals exist');
$reads = implode("\n", array_map('strval', $GLOBALS['sc_test_read_queries']));
esc_p9_015_assert(str_contains($reads, 'LIMIT 1001 FOR UPDATE'), 'capacity reads remain bounded and locked');
esc_p9_015_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), "'60.0000', p.contract_currency, 'recorded', 42"), 'capacity-approved create appends canonical Money');

// Historical over-capacity create still fails closed when no reversals exist.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [esc_p9_015_schedule_row($scheduleUuid, 1, '100')],
    [['total' => '1']],
    [],
    [esc_p9_015_receipt_row($otherUuid, $scheduleUuid, 1, '40', 'recorded', 1201)],
    [],
];
esc_p9_015_expect_throw(
    static fn (): int => $repository->createReceipt(55, $scheduleUuid, '44444444-4444-4444-8444-444444444444', '55555555-5555-4555-8555-555555555555', null, '2026-10-02', '60.0001', 42, static function (array $contract): void {}),
    RuntimeException::class,
    'create exceeding historical schedule capacity fails closed'
);
$writes = implode("\n", $GLOBALS['sc_test_queries']);
esc_p9_015_assert(str_contains($writes, 'ROLLBACK') && ! str_contains($writes, 'INSERT INTO'), 'over-capacity create rolls back with no append');

// Voided current receipts remain zero capacity usage.
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [esc_p9_015_schedule_row($scheduleUuid, 1, '100')],
    [['total' => '1']],
    [],
    [esc_p9_015_receipt_row($voidedUuid, $scheduleUuid, 1, '99', 'voided', 1301)],
    [],
];
$GLOBALS['wpdb']->insert_id = 0;
esc_p9_015_assert(
    $repository->createReceipt(55, $scheduleUuid, '44444444-4444-4444-8444-444444444444', '55555555-5555-4555-8555-555555555555', null, '2026-10-02', '100', 42, static function (array $contract): void {}) === 1001,
    'voided current receipts consume zero historical capacity'
);

// Changed revise excludes target stable receipt before adding revised gross when no reversals exist.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [esc_p9_015_schedule_row($scheduleUuid, 1, '100')],
    [esc_p9_015_receipt_row($targetUuid, $scheduleUuid, 1, '40', 'recorded', 1401, 31, 'USD', 'OLD')],
    [
        esc_p9_015_receipt_row($targetUuid, $scheduleUuid, 1, '40', 'recorded', 1401, 31, 'USD', 'OLD'),
        esc_p9_015_receipt_row($otherUuid, $scheduleUuid, 1, '50', 'recorded', 1402),
        esc_p9_015_receipt_row($voidedUuid, $scheduleUuid, 1, '75', 'voided', 1403),
    ],
    [],
];
$GLOBALS['wpdb']->insert_id = 0;
$revised = $repository->reviseReceipt(55, $scheduleUuid, $targetUuid, '66666666-6666-4666-8666-666666666666', 'NEW', '2026-10-03', '50', 42, static function (array $contract): void {});
esc_p9_015_assert($revised === 1001, 'historical revise succeeds when other 50 + revised target 50 equals capacity');
esc_p9_015_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), "'50.0000', p.contract_currency, 'recorded', 42"), 'capacity-approved revise appends revised Money');

// Historical over-capacity revise still fails.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [esc_p9_015_schedule_row($scheduleUuid, 1, '100')],
    [esc_p9_015_receipt_row($targetUuid, $scheduleUuid, 1, '40', 'recorded', 1501, 31, 'USD', 'OLD')],
    [
        esc_p9_015_receipt_row($targetUuid, $scheduleUuid, 1, '40', 'recorded', 1501, 31, 'USD', 'OLD'),
        esc_p9_015_receipt_row($otherUuid, $scheduleUuid, 1, '70', 'recorded', 1502),
    ],
    [],
];
esc_p9_015_expect_throw(
    static fn (): int => $repository->reviseReceipt(55, $scheduleUuid, $targetUuid, '66666666-6666-4666-8666-666666666666', 'NEW', '2026-10-03', '30.0001', 42, static function (array $contract): void {}),
    RuntimeException::class,
    'historical revise exceeding capacity fails closed'
);
esc_p9_015_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'over-capacity revise appends no revision');

// Exact retry still returns before any capacity/reversal read.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [esc_p9_015_schedule_row($scheduleUuid, 1, '100')],
    [esc_p9_015_receipt_row($targetUuid, $scheduleUuid, 1, '60', 'recorded', 1601, 31, 'USD', 'SAME', '2026-10-04')],
];
$retry = $repository->reviseReceipt(55, $scheduleUuid, $targetUuid, '77777777-7777-4777-8777-777777777777', 'SAME', '2026-10-04', '60', 42, static function (array $contract): void {});
esc_p9_015_assert($retry === 1601, 'exact revise retry remains idempotent');
esc_p9_015_assert(count($GLOBALS['sc_test_read_queries']) === 4, 'exact retry performs no capacity read after target lock');
esc_p9_015_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'exact retry writes no revision');

// Existing receipt 1001st sentinel still fails before any reversal read.
$overflow = [];
for ($i = 1; $i <= ContractFinancialCollectionReceiptPolicy::MAX_RECEIPTS + 1; $i++) {
    $overflow[] = esc_p9_015_receipt_row(sprintf('80000000-0000-4000-8000-%012x', $i), $scheduleUuid, 1, '0.0001', 'recorded', 2000 + $i);
}
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [esc_p9_015_schedule_row($scheduleUuid, 1, '100')],
    [['total' => '1']],
    [],
    $overflow,
];
esc_p9_015_expect_throw(
    static fn (): int => $repository->createReceipt(55, $scheduleUuid, '44444444-4444-4444-8444-444444444444', '55555555-5555-4555-8555-555555555555', null, '2026-10-02', '1', 42, static function (array $contract): void {}),
    RuntimeException::class,
    '1001 current receipt capacity rows fail closed'
);

// Corrupt current receipt capacity evidence still fails before reversal arithmetic.
foreach ([
    esc_p9_015_receipt_row($otherUuid, $scheduleUuid, 2, '10', 'recorded', 3101),
    esc_p9_015_receipt_row($otherUuid, $scheduleUuid, 1, '10', 'recorded', 3102, 99),
    esc_p9_015_receipt_row($otherUuid, $scheduleUuid, 1, '10', 'recorded', 3103, 31, 'EUR'),
] as $corruptRow) {
    $GLOBALS['sc_test_result_queue'] = [
        $contractActive,
        $profile,
        [esc_p9_015_schedule_row($scheduleUuid, 1, '100')],
        [['total' => '1']],
        [],
        [$corruptRow],
    ];
    esc_p9_015_expect_throw(
        static fn (): int => $repository->createReceipt(55, $scheduleUuid, '44444444-4444-4444-8444-444444444444', '55555555-5555-4555-8555-555555555555', null, '2026-10-02', '1', 42, static function (array $contract): void {}),
        UnexpectedValueException::class,
        'corrupt historical capacity receipt fails closed'
    );
}

// Void remains outside capacity aggregation and only reduces current usage.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [esc_p9_015_schedule_row($scheduleUuid, 1, '100')],
    [esc_p9_015_receipt_row($targetUuid, $scheduleUuid, 1, '60', 'recorded', 1701)],
];
$GLOBALS['wpdb']->insert_id = 0;
esc_p9_015_assert(
    $repository->voidReceipt(55, $scheduleUuid, $targetUuid, '99999999-9999-4999-8999-999999999999', 42, static function (array $contract): void {}) === 1001,
    'void remains available to reduce collection usage'
);
esc_p9_015_assert(count($GLOBALS['sc_test_read_queries']) === 4, 'void performs no capacity/reversal aggregation read');

esc_p9_015_assert(! str_contains(strtoupper($settlementRepositorySource), 'INSERT INTO') && ! str_contains(strtoupper($settlementRepositorySource), 'UPDATE '), 'P9-014/P9-017 settlement path remains read-only');
esc_p9_015_assert(str_contains($settlementRepositorySource, 'over_collected_amount') && str_contains($settlementRepositorySource, 'over_collected_total'), 'later settlement remains able to surface over-collection evidence');
esc_p9_015_assert(str_contains($gateSource, 'enterprise_contract_financial_collection_capacity_p9_015.php'), 'P9-015 regression remains wired into global backend gate');

fwrite(STDOUT, "P9-015 Enterprise Contract collection capacity historical guard passed ({$assertions} assertions).\n");
