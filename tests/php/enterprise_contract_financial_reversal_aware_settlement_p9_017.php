<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Finance\ContractFinancialScheduleSettlementRepository;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p9_017_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @param class-string<Throwable> $expected */
function esc_p9_017_expect_throw(callable $callback, string $expected, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p9_017_assert($error instanceof $expected, $message . ' (received ' . $error::class . ')');
        return;
    }
    esc_p9_017_assert(false, $message . ' (no exception)');
}

/** @return array<string,mixed> */
function esc_p9_017_schedule_row(
    string $uuid,
    int $sequence,
    string $amount = '100.0000',
    string $state = 'scheduled',
    int $id = 1001,
    int $profileId = 31,
    string $currency = 'USD'
): array {
    return [
        'id' => (string) $id,
        'contract_id' => '55',
        'financial_currency_profile_id' => (string) $profileId,
        'schedule_entry_uuid' => $uuid,
        'revision_number' => '2',
        'sequence_no' => (string) $sequence,
        'reference' => 'P-' . $sequence,
        'due_date' => '2026-12-31',
        'amount' => $amount,
        'currency_code' => $currency,
        'schedule_entry_state' => $state,
        'created_by' => '42',
        'created_at' => '2026-08-17 21:00:00',
    ];
}

/** @return array<string,mixed> */
function esc_p9_017_receipt_row(
    string $receiptUuid,
    string $scheduleUuid,
    int $sequence,
    string $amount,
    string $state = 'recorded',
    int $id = 2001,
    int $profileId = 31,
    string $currency = 'USD'
): array {
    return [
        'id' => (string) $id,
        'contract_id' => '55',
        'financial_currency_profile_id' => (string) $profileId,
        'receipt_uuid' => $receiptUuid,
        'revision_number' => '2',
        'schedule_entry_uuid' => $scheduleUuid,
        'schedule_sequence_no' => (string) $sequence,
        'external_reference' => 'R-' . $id,
        'received_date' => '2026-10-01',
        'amount' => $amount,
        'currency_code' => $currency,
        'receipt_state' => $state,
        'created_by' => '42',
        'created_at' => '2026-08-17 21:01:00',
    ];
}

/** @return array<string,mixed> */
function esc_p9_017_reversal_row(
    string $reversalUuid,
    string $receiptUuid,
    string $scheduleUuid,
    int $sequence,
    string $amount,
    string $state = 'recorded',
    int $id = 3001,
    int $profileId = 31,
    string $currency = 'USD'
): array {
    return [
        'id' => (string) $id,
        'revision_uuid' => sprintf('90000000-0000-4000-8000-%012x', $id),
        'contract_id' => '55',
        'financial_currency_profile_id' => (string) $profileId,
        'reversal_uuid' => $reversalUuid,
        'revision_number' => '2',
        'receipt_uuid' => $receiptUuid,
        'schedule_entry_uuid' => $scheduleUuid,
        'schedule_sequence_no' => (string) $sequence,
        'external_reference' => 'RV-' . $id,
        'reversal_date' => '2026-10-02',
        'amount' => $amount,
        'currency_code' => $currency,
        'reversal_state' => $state,
        'created_by' => '42',
        'created_at' => '2026-08-17 21:02:00',
    ];
}

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialScheduleSettlementRepository.php');
$receiptRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialCollectionReceiptRevisionRepository.php');
$reversalRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialCollectionReversalRevisionRepository.php');

// P9-017 is schema-free over the P9-016 1.57.0 / Migration0058 boundary.
esc_p9_017_assert(Migrator::LATEST_VERSION === '1.57.0', 'P9-017 keeps schema exactly 1.57.0');
esc_p9_017_assert(str_contains($migratorSource, "'1.57.0' => Migration0058EnterpriseContractFinancialCollectionReversalRevisions::class"), 'P9-017 preserves exact Migration0058 mapping');
esc_p9_017_assert(! str_contains($migratorSource, 'Migration0059'), 'P9-017 introduces no Migration0059');

foreach ([
    'ContractFinancialCollectionReversalPolicy::MAX_REVERSALS + 1',
    'safecontracts_contract_financial_collection_reversal_revisions',
    'ORDER BY rv.receipt_uuid ASC, rv.reversal_date ASC, rv.reversal_uuid ASC',
    'gross_collected_amount',
    'reversed_amount',
    'gross_collected_total',
    'reversed_total',
    'recorded reversals exceed the linked receipt amount',
    'normalizeReversal',
] as $marker) {
    esc_p9_017_assert(str_contains($repositorySource, $marker), 'P9-017 settlement repository contains ' . $marker);
}
esc_p9_017_assert(! str_contains(strtoupper($repositorySource), 'INSERT INTO') && ! str_contains(strtoupper($repositorySource), 'UPDATE ') && ! str_contains(strtoupper($repositorySource), 'DELETE FROM'), 'P9-017 settlement remains read-only');
esc_p9_017_assert(! str_contains(strtoupper($repositorySource), 'SUM(') && ! str_contains($repositorySource, 'float') && ! str_contains($repositorySource, 'round('), 'P9-017 uses Money rather than SQL/float aggregation');
esc_p9_017_assert(! str_contains($receiptRepositorySource, 'ScheduleSettlement'), 'P9-015 receipt mutation repository remains uncoupled from settlement');
esc_p9_017_assert(! str_contains($reversalRepositorySource, 'ScheduleSettlement'), 'P9-016 reversal mutation repository remains uncoupled from settlement');

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(7);

$repository = new ContractFinancialScheduleSettlementRepository();
$contract = [['id' => '55', 'accountant_user_id' => '42', 'status' => 'active', 'is_archived' => '0']];
$profile = [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']];

$s1 = '11111111-1111-4111-8111-111111111111';
$s2 = '22222222-2222-4222-8222-222222222222';
$s3 = '33333333-3333-4333-8333-333333333333';
$s4 = '44444444-4444-4444-8444-444444444444';
$r1 = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1';
$r2 = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa2';
$r3 = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa3';
$r4 = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa4';

$schedules = [
    esc_p9_017_schedule_row($s1, 1, '100', 'scheduled', 1001),
    esc_p9_017_schedule_row($s2, 2, '50', 'scheduled', 1002),
    esc_p9_017_schedule_row($s3, 3, '80', 'scheduled', 1003),
    esc_p9_017_schedule_row($s4, 4, '25', 'voided', 1004),
];
$receipts = [
    esc_p9_017_receipt_row($r1, $s1, 1, '100', 'recorded', 2001),
    esc_p9_017_receipt_row($r2, $s2, 2, '50', 'recorded', 2002),
    esc_p9_017_receipt_row($r3, $s3, 3, '30', 'voided', 2003),
    esc_p9_017_receipt_row($r4, $s4, 4, '25', 'recorded', 2004),
];
$reversals = [
    esc_p9_017_reversal_row('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb1', $r1, $s1, 1, '25', 'recorded', 3001),
    esc_p9_017_reversal_row('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb2', $r1, $s1, 1, '15', 'recorded', 3002),
    esc_p9_017_reversal_row('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb3', $r2, $s2, 2, '20', 'voided', 3003),
    esc_p9_017_reversal_row('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb4', $r3, $s3, 3, '10', 'recorded', 3004),
    esc_p9_017_reversal_row('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb5', $r4, $s4, 4, '5', 'recorded', 3005),
];

$GLOBALS['sc_test_result_queue'] = [$contract, $profile, $schedules, $receipts, $reversals];
$result = $repository->reconcileContract(55, static function (array $locked): void {
    esc_p9_017_assert((int) ($locked['id'] ?? 0) === 55, 'P9-017 authorizes exact locked Contract before finance reads');
});
esc_p9_017_assert(count($result['entries']) === 4, 'P9-017 returns all current schedules');
esc_p9_017_assert($result['entries'][0]['gross_collected_amount'] === '100.0000', 'P9-017 preserves gross receipt evidence');
esc_p9_017_assert($result['entries'][0]['reversed_amount'] === '40.0000', 'P9-017 aggregates multiple recorded reversals exactly');
esc_p9_017_assert($result['entries'][0]['collected_amount'] === '60.0000', 'P9-017 nets reversals from current collected amount');
esc_p9_017_assert($result['entries'][0]['remaining_amount'] === '40.0000' && $result['entries'][0]['settlement_state'] === 'partial', 'P9-017 derives partial settlement from net collection');
esc_p9_017_assert($result['entries'][1]['reversed_amount'] === '0.0000' && $result['entries'][1]['collected_amount'] === '50.0000' && $result['entries'][1]['settlement_state'] === 'settled', 'voided reversal contributes zero');
esc_p9_017_assert($result['entries'][2]['gross_collected_amount'] === '0.0000' && $result['entries'][2]['reversed_amount'] === '0.0000' && $result['entries'][2]['collected_amount'] === '0.0000' && $result['entries'][2]['settlement_state'] === 'uncollected', 'voided receipt and historical reversal contribute zero current settlement');
esc_p9_017_assert($result['entries'][3]['settlement_state'] === 'voided' && $result['entries'][3]['gross_collected_amount'] === '25.0000' && $result['entries'][3]['reversed_amount'] === '5.0000' && $result['entries'][3]['collected_amount'] === '20.0000', 'voided schedule preserves gross/reversal/net historical evidence');
esc_p9_017_assert($result['summary']['scheduled_total'] === '230.0000', 'P9-017 excludes voided schedule from current scheduled total');
esc_p9_017_assert($result['summary']['gross_collected_total'] === '150.0000', 'P9-017 summarizes gross active-schedule collection');
esc_p9_017_assert($result['summary']['reversed_total'] === '40.0000', 'P9-017 summarizes active-schedule reversals');
esc_p9_017_assert($result['summary']['collected_total'] === '110.0000', 'P9-017 summarizes net active-schedule collection');
esc_p9_017_assert($result['summary']['remaining_total'] === '120.0000', 'P9-017 derives net remaining total');
esc_p9_017_assert($result['summary']['voided_schedule_gross_collected_total'] === '25.0000' && $result['summary']['voided_schedule_reversed_total'] === '5.0000' && $result['summary']['voided_schedule_collected_total'] === '20.0000', 'P9-017 preserves voided schedule gross/reversal/net evidence');

// Full reversal re-opens the read-side balance without mutating schedule state.
$GLOBALS['sc_test_result_queue'] = [
    $contract,
    $profile,
    [esc_p9_017_schedule_row($s1, 1, '100')],
    [esc_p9_017_receipt_row($r1, $s1, 1, '100')],
    [esc_p9_017_reversal_row('cccccccc-cccc-4ccc-8ccc-ccccccccccc1', $r1, $s1, 1, '100')],
];
$full = $repository->reconcileContract(55, static function (array $locked): void {});
esc_p9_017_assert($full['entries'][0]['collected_amount'] === '0.0000' && $full['entries'][0]['remaining_amount'] === '100.0000' && $full['entries'][0]['settlement_state'] === 'uncollected', 'full reversal derives uncollected without schedule mutation');

// Reversal aggregate may never exceed receipt gross.
$GLOBALS['sc_test_result_queue'] = [
    $contract,
    $profile,
    [esc_p9_017_schedule_row($s1, 1, '100')],
    [esc_p9_017_receipt_row($r1, $s1, 1, '100')],
    [
        esc_p9_017_reversal_row('dddddddd-dddd-4ddd-8ddd-ddddddddddd1', $r1, $s1, 1, '60', 'recorded', 3101),
        esc_p9_017_reversal_row('dddddddd-dddd-4ddd-8ddd-ddddddddddd2', $r1, $s1, 1, '50', 'recorded', 3102),
    ],
];
esc_p9_017_expect_throw(static fn (): array => $repository->reconcileContract(55, static function (array $locked): void {}), UnexpectedValueException::class, 'recorded reversals over receipt gross fail closed');

// Orphan reversal fails closed.
$GLOBALS['sc_test_result_queue'] = [
    $contract,
    $profile,
    [esc_p9_017_schedule_row($s1, 1, '100')],
    [esc_p9_017_receipt_row($r1, $s1, 1, '100')],
    [esc_p9_017_reversal_row('eeeeeeee-eeee-4eee-8eee-eeeeeeeeeee1', 'ffffffff-ffff-4fff-8fff-ffffffffffff', $s1, 1, '10')],
];
esc_p9_017_expect_throw(static fn (): array => $repository->reconcileContract(55, static function (array $locked): void {}), UnexpectedValueException::class, 'orphan reversal fails closed');

// Profile/currency/sequence corruption fails closed independently.
foreach ([
    esc_p9_017_reversal_row('12121212-1212-4212-8212-121212121212', $r1, $s1, 1, '10', 'recorded', 3201, 32, 'USD'),
    esc_p9_017_reversal_row('13131313-1313-4313-8313-131313131313', $r1, $s1, 1, '10', 'recorded', 3202, 31, 'EUR'),
    esc_p9_017_reversal_row('14141414-1414-4414-8414-141414141414', $r1, $s1, 2, '10', 'recorded', 3203, 31, 'USD'),
] as $index => $corruptReversal) {
    $GLOBALS['sc_test_result_queue'] = [
        $contract,
        $profile,
        [esc_p9_017_schedule_row($s1, 1, '100')],
        [esc_p9_017_receipt_row($r1, $s1, 1, '100')],
        [$corruptReversal],
    ];
    esc_p9_017_expect_throw(
        static fn (): array => $repository->reconcileContract(55, static function (array $locked): void {}),
        UnexpectedValueException::class,
        'reversal integrity corruption case ' . ($index + 1) . ' fails closed'
    );
}

// Duplicate latest reversal identity and 1001st sentinel fail closed.
$duplicate = esc_p9_017_reversal_row('15151515-1515-4515-8515-151515151515', $r1, $s1, 1, '10', 'recorded', 3301);
$GLOBALS['sc_test_result_queue'] = [
    $contract,
    $profile,
    [esc_p9_017_schedule_row($s1, 1, '100')],
    [esc_p9_017_receipt_row($r1, $s1, 1, '100')],
    [$duplicate, array_replace($duplicate, ['id' => '3302', 'revision_uuid' => '16161616-1616-4616-8616-161616161616'])],
];
esc_p9_017_expect_throw(static fn (): array => $repository->reconcileContract(55, static function (array $locked): void {}), UnexpectedValueException::class, 'duplicate latest reversal identities fail closed');

$GLOBALS['sc_test_result_queue'] = [
    $contract,
    $profile,
    [esc_p9_017_schedule_row($s1, 1, '100')],
    [esc_p9_017_receipt_row($r1, $s1, 1, '100')],
    array_fill(0, 1001, []),
];
esc_p9_017_expect_throw(static fn (): array => $repository->reconcileContract(55, static function (array $locked): void {}), RuntimeException::class, '1001st reversal sentinel fails closed');

fwrite(STDOUT, "ESC P9-017 reversal-aware settlement regression passed ({$assertions} assertions).\n");
