<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Finance\ContractFinancialCollectionReceiptPolicy;
use SafeContracts\Finance\ContractFinancialCollectionReceiptRevisionRepository;
use SafeContracts\Finance\ContractFinancialCollectionReversalPolicy;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p9_018_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @param class-string<Throwable> $expected */
function esc_p9_018_expect_throw(callable $callback, string $expected, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p9_018_assert($error instanceof $expected, $message . ' (received ' . $error::class . ')');
        return;
    }
    esc_p9_018_assert(false, $message . ' (no exception)');
}

/** @return array<string,mixed> */
function esc_p9_018_schedule_row(
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
function esc_p9_018_receipt_row(
    string $receiptUuid,
    string $scheduleUuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    int $sequence = 1,
    string $amount = '100.0000',
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
        'created_at' => '2026-08-17 17:40:00',
    ];
}

/** @return array<string,mixed> */
function esc_p9_018_reversal_row(
    string $reversalUuid,
    string $receiptUuid,
    string $scheduleUuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    int $sequence = 1,
    string $amount = '40.0000',
    string $state = 'recorded',
    int $id = 2001,
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
        'external_reference' => 'REV-' . $id,
        'reversal_date' => '2026-10-02',
        'amount' => $amount,
        'currency_code' => $currency,
        'reversal_state' => $state,
        'created_by' => '42',
        'created_at' => '2026-08-17 17:41:00',
    ];
}

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialCollectionReceiptRevisionRepository.php');
$reversalRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialCollectionReversalRevisionRepository.php');
$settlementRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialScheduleSettlementRepository.php');

esc_p9_018_assert(Migrator::LATEST_VERSION === '1.57.0', 'P9-018 keeps schema exactly 1.57.0');
esc_p9_018_assert(str_contains($migratorSource, "'1.57.0' => Migration0058EnterpriseContractFinancialCollectionReversalRevisions::class"), 'P9-018 preserves exact Migration0058 mapping');
esc_p9_018_assert(! str_contains($migratorSource, 'Migration0059'), 'P9-018 introduces no Migration0059');

foreach ([
    'safecontracts_contract_financial_collection_reversal_revisions',
    'ContractFinancialCollectionReversalPolicy::MAX_REVERSALS + 1',
    'ORDER BY rv.receipt_uuid ASC, rv.reversal_uuid ASC',
    'normalizeCapacityReversal(',
    '$receiptMoney->subtract($reversed)',
    '$proposedAmount->subtract($targetReversed)',
    'cannot be revised below its recorded reversal total',
    'would exceed the linked payment schedule amount after reversals',
] as $marker) {
    esc_p9_018_assert(str_contains($repositorySource, $marker), 'P9-018 capacity implementation contains ' . $marker);
}
esc_p9_018_assert(! str_contains(strtoupper($repositorySource), 'SUM('), 'P9-018 uses no SQL SUM');
esc_p9_018_assert(! str_contains($repositorySource, 'float') && ! str_contains($repositorySource, 'round('), 'P9-018 uses no float/rounding path');
esc_p9_018_assert(substr_count($repositorySource, 'assertCollectionCapacity(') === 3, 'P9-018 preserves one capacity helper used only by create/revise');
esc_p9_018_assert(strpos($repositorySource, "commit('idempotent Enterprise Contract collection receipt revision')") < strpos($repositorySource, 'assertCollectionCapacity(', strpos($repositorySource, 'public function reviseReceipt')), 'exact receipt retry remains before reversal-aware capacity aggregation');
esc_p9_018_assert(! str_contains($reversalRepositorySource, 'ContractFinancialCollectionReceiptRevisionRepository'), 'P9-016 reversal mutation remains independent of receipt capacity implementation');
esc_p9_018_assert(str_contains($settlementRepositorySource, 'gross_collected_amount') && str_contains($settlementRepositorySource, 'reversed_amount'), 'P9-017 settlement read model remains reversal-aware');

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(7);
$repository = new ContractFinancialCollectionReceiptRevisionRepository();
$contract = [['id' => '55', 'accountant_user_id' => '42', 'status' => 'active', 'is_archived' => '0']];
$profile = [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']];
$scheduleUuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$targetUuid = '11111111-1111-4111-8111-111111111111';
$otherUuid = '22222222-2222-4222-8222-222222222222';
$newReceiptUuid = '33333333-3333-4333-8333-333333333333';

// Gross 100 reversed by 40 consumes net 60, reopening exactly 40 of schedule capacity.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contract,
    $profile,
    [esc_p9_018_schedule_row($scheduleUuid, 1, '100')],
    [['total' => '1']],
    [],
    [esc_p9_018_receipt_row($targetUuid, $scheduleUuid, 1, '100', 'recorded', 1101)],
    [esc_p9_018_reversal_row('44444444-4444-4444-8444-444444444444', $targetUuid, $scheduleUuid, 1, '40', 'recorded', 2101)],
];
$GLOBALS['wpdb']->insert_id = 0;
$created = $repository->createReceipt(55, $scheduleUuid, $newReceiptUuid, '55555555-5555-4555-8555-555555555555', 'NEW-40', '2026-10-03', '40', 42, static function (array $locked): void {});
esc_p9_018_assert($created === 1001, 'recorded reversal reopens exact collection capacity for a new receipt');
$reads = implode("\n", array_map('strval', $GLOBALS['sc_test_read_queries']));
esc_p9_018_assert(str_contains($reads, 'collection_reversal_revisions') && str_contains($reads, 'FOR UPDATE'), 'capacity locks latest reversal evidence inside the mutation transaction');

// One ten-thousandth above reopened capacity is rejected and appends nothing.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contract,
    $profile,
    [esc_p9_018_schedule_row($scheduleUuid, 1, '100')],
    [['total' => '1']],
    [],
    [esc_p9_018_receipt_row($targetUuid, $scheduleUuid, 1, '100', 'recorded', 1201)],
    [esc_p9_018_reversal_row('66666666-6666-4666-8666-666666666666', $targetUuid, $scheduleUuid, 1, '40', 'recorded', 2201)],
];
esc_p9_018_expect_throw(
    static fn (): int => $repository->createReceipt(55, $scheduleUuid, $newReceiptUuid, '77777777-7777-4777-8777-777777777777', null, '2026-10-03', '40.0001', 42, static function (array $locked): void {}),
    RuntimeException::class,
    'create above reversal-reopened capacity fails closed'
);
esc_p9_018_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'over-capacity create appends no receipt revision');

// Multiple recorded reversals aggregate; a voided reversal releases no capacity.
$GLOBALS['sc_test_result_queue'] = [
    $contract,
    $profile,
    [esc_p9_018_schedule_row($scheduleUuid, 1, '100')],
    [['total' => '1']],
    [],
    [esc_p9_018_receipt_row($targetUuid, $scheduleUuid, 1, '100', 'recorded', 1301)],
    [
        esc_p9_018_reversal_row('88888888-8888-4888-8888-888888888881', $targetUuid, $scheduleUuid, 1, '25', 'recorded', 2301),
        esc_p9_018_reversal_row('88888888-8888-4888-8888-888888888882', $targetUuid, $scheduleUuid, 1, '15', 'recorded', 2302),
        esc_p9_018_reversal_row('88888888-8888-4888-8888-888888888883', $targetUuid, $scheduleUuid, 1, '50', 'voided', 2303),
    ],
];
$GLOBALS['wpdb']->insert_id = 0;
esc_p9_018_assert(
    $repository->createReceipt(55, $scheduleUuid, $newReceiptUuid, '99999999-9999-4999-8999-999999999999', null, '2026-10-03', '40', 42, static function (array $locked): void {}) === 1001,
    'multiple recorded reversals reopen their exact aggregate while voided reversal releases zero'
);

// Revise uses net target amount and net usage of other receipts.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contract,
    $profile,
    [esc_p9_018_schedule_row($scheduleUuid, 1, '100')],
    [esc_p9_018_receipt_row($targetUuid, $scheduleUuid, 1, '100', 'recorded', 1401, 31, 'USD', 'OLD')],
    [
        esc_p9_018_receipt_row($targetUuid, $scheduleUuid, 1, '100', 'recorded', 1401, 31, 'USD', 'OLD'),
        esc_p9_018_receipt_row($otherUuid, $scheduleUuid, 1, '40', 'recorded', 1402, 31, 'USD', 'OTHER'),
    ],
    [
        esc_p9_018_reversal_row('aaaaaaaa-bbbb-4ccc-8ddd-aaaaaaaaaaa1', $targetUuid, $scheduleUuid, 1, '40', 'recorded', 2401),
        esc_p9_018_reversal_row('aaaaaaaa-bbbb-4ccc-8ddd-aaaaaaaaaaa2', $otherUuid, $scheduleUuid, 1, '10', 'recorded', 2402),
    ],
];
$GLOBALS['wpdb']->insert_id = 0;
$revised = $repository->reviseReceipt(55, $scheduleUuid, $targetUuid, 'aaaaaaaa-bbbb-4ccc-8ddd-aaaaaaaaaaa3', 'NEW', '2026-10-04', '70', 42, static function (array $locked): void {});
esc_p9_018_assert($revised === 1001, 'revise capacity uses proposed gross minus target reversals and other net usage');

// A revised gross may never fall below already-recorded reversal evidence.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contract,
    $profile,
    [esc_p9_018_schedule_row($scheduleUuid, 1, '100')],
    [esc_p9_018_receipt_row($targetUuid, $scheduleUuid, 1, '100', 'recorded', 1501, 31, 'USD', 'OLD')],
    [esc_p9_018_receipt_row($targetUuid, $scheduleUuid, 1, '100', 'recorded', 1501, 31, 'USD', 'OLD')],
    [esc_p9_018_reversal_row('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb1', $targetUuid, $scheduleUuid, 1, '40', 'recorded', 2501)],
];
esc_p9_018_expect_throw(
    static fn (): int => $repository->reviseReceipt(55, $scheduleUuid, $targetUuid, 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb2', 'LOW', '2026-10-04', '39.9999', 42, static function (array $locked): void {}),
    RuntimeException::class,
    'receipt revise below recorded reversal total fails closed'
);
esc_p9_018_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'below-reversal revise appends no revision');

// Net revise can still exceed schedule capacity and must fail.
$GLOBALS['sc_test_result_queue'] = [
    $contract,
    $profile,
    [esc_p9_018_schedule_row($scheduleUuid, 1, '100')],
    [esc_p9_018_receipt_row($targetUuid, $scheduleUuid, 1, '60', 'recorded', 1601, 31, 'USD', 'OLD')],
    [
        esc_p9_018_receipt_row($targetUuid, $scheduleUuid, 1, '60', 'recorded', 1601, 31, 'USD', 'OLD'),
        esc_p9_018_receipt_row($otherUuid, $scheduleUuid, 1, '80', 'recorded', 1602),
    ],
    [
        esc_p9_018_reversal_row('cccccccc-cccc-4ccc-8ccc-ccccccccccc1', $targetUuid, $scheduleUuid, 1, '20', 'recorded', 2601),
        esc_p9_018_reversal_row('cccccccc-cccc-4ccc-8ccc-ccccccccccc2', $otherUuid, $scheduleUuid, 1, '10', 'recorded', 2602),
    ],
];
esc_p9_018_expect_throw(
    static fn (): int => $repository->reviseReceipt(55, $scheduleUuid, $targetUuid, 'cccccccc-cccc-4ccc-8ccc-ccccccccccc3', 'HIGH', '2026-10-04', '51', 42, static function (array $locked): void {}),
    RuntimeException::class,
    'net revise exceeding scheduled capacity fails closed'
);

// Current voided receipt consumes zero even when linked reversal history remains valid.
$GLOBALS['sc_test_result_queue'] = [
    $contract,
    $profile,
    [esc_p9_018_schedule_row($scheduleUuid, 1, '100')],
    [['total' => '1']],
    [],
    [esc_p9_018_receipt_row($targetUuid, $scheduleUuid, 1, '100', 'voided', 1701)],
    [esc_p9_018_reversal_row('dddddddd-dddd-4ddd-8ddd-ddddddddddd1', $targetUuid, $scheduleUuid, 1, '80', 'recorded', 2701)],
];
$GLOBALS['wpdb']->insert_id = 0;
esc_p9_018_assert(
    $repository->createReceipt(55, $scheduleUuid, $newReceiptUuid, 'dddddddd-dddd-4ddd-8ddd-ddddddddddd2', null, '2026-10-03', '100', 42, static function (array $locked): void {}) === 1001,
    'voided current receipt remains zero usage while historical reversal evidence is validated'
);

// Recorded reversal aggregate can never exceed linked receipt gross.
$GLOBALS['sc_test_result_queue'] = [
    $contract,
    $profile,
    [esc_p9_018_schedule_row($scheduleUuid, 1, '100')],
    [['total' => '1']],
    [],
    [esc_p9_018_receipt_row($targetUuid, $scheduleUuid, 1, '50', 'recorded', 1801)],
    [
        esc_p9_018_reversal_row('eeeeeeee-eeee-4eee-8eee-eeeeeeeeeee1', $targetUuid, $scheduleUuid, 1, '30', 'recorded', 2801),
        esc_p9_018_reversal_row('eeeeeeee-eeee-4eee-8eee-eeeeeeeeeee2', $targetUuid, $scheduleUuid, 1, '21', 'recorded', 2802),
    ],
];
esc_p9_018_expect_throw(
    static fn (): int => $repository->createReceipt(55, $scheduleUuid, $newReceiptUuid, 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeee3', null, '2026-10-03', '1', 42, static function (array $locked): void {}),
    UnexpectedValueException::class,
    'recorded reversals exceeding linked receipt gross fail closed'
);

// Orphan/cross-profile/cross-currency/sequence corruption is rejected.
$corruptReversals = [
    esc_p9_018_reversal_row('f1111111-1111-4111-8111-111111111111', 'f2222222-2222-4222-8222-222222222222', $scheduleUuid, 1, '10', 'recorded', 2901),
    esc_p9_018_reversal_row('f3333333-3333-4333-8333-333333333333', $targetUuid, $scheduleUuid, 1, '10', 'recorded', 2902, 99, 'USD'),
    esc_p9_018_reversal_row('f4444444-4444-4444-8444-444444444444', $targetUuid, $scheduleUuid, 1, '10', 'recorded', 2903, 31, 'EUR'),
    esc_p9_018_reversal_row('f5555555-5555-4555-8555-555555555555', $targetUuid, $scheduleUuid, 2, '10', 'recorded', 2904),
];
foreach ($corruptReversals as $index => $corrupt) {
    $GLOBALS['sc_test_result_queue'] = [
        $contract,
        $profile,
        [esc_p9_018_schedule_row($scheduleUuid, 1, '100')],
        [['total' => '1']],
        [],
        [esc_p9_018_receipt_row($targetUuid, $scheduleUuid, 1, '50', 'recorded', 1900 + $index)],
        [$corrupt],
    ];
    esc_p9_018_expect_throw(
        static fn (): int => $repository->createReceipt(55, $scheduleUuid, $newReceiptUuid, 'f6666666-6666-4666-8666-666666666666', null, '2026-10-03', '1', 42, static function (array $locked): void {}),
        UnexpectedValueException::class,
        'reversal corruption case ' . ($index + 1) . ' fails closed'
    );
}

// Reversal 1001st sentinel fails closed before arithmetic.
$GLOBALS['sc_test_result_queue'] = [
    $contract,
    $profile,
    [esc_p9_018_schedule_row($scheduleUuid, 1, '100')],
    [['total' => '1']],
    [],
    [esc_p9_018_receipt_row($targetUuid, $scheduleUuid, 1, '50', 'recorded', 2001)],
    array_fill(0, ContractFinancialCollectionReversalPolicy::MAX_REVERSALS + 1, []),
];
esc_p9_018_expect_throw(
    static fn (): int => $repository->createReceipt(55, $scheduleUuid, $newReceiptUuid, 'f7777777-7777-4777-8777-777777777777', null, '2026-10-03', '1', 42, static function (array $locked): void {}),
    RuntimeException::class,
    '1001st reversal capacity row fails closed'
);

// Exact revise retry and void still avoid capacity/reversal aggregation entirely.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contract,
    $profile,
    [esc_p9_018_schedule_row($scheduleUuid, 1, '100')],
    [esc_p9_018_receipt_row($targetUuid, $scheduleUuid, 1, '60', 'recorded', 2101, 31, 'USD', 'SAME', '2026-10-05')],
];
$retry = $repository->reviseReceipt(55, $scheduleUuid, $targetUuid, 'f8888888-8888-4888-8888-888888888888', 'SAME', '2026-10-05', '60', 42, static function (array $locked): void {});
esc_p9_018_assert($retry === 2101 && count($GLOBALS['sc_test_read_queries']) === 4, 'exact revise retry performs no receipt/reversal capacity reads');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contract,
    $profile,
    [esc_p9_018_schedule_row($scheduleUuid, 1, '100')],
    [esc_p9_018_receipt_row($targetUuid, $scheduleUuid, 1, '60', 'recorded', 2201)],
];
$GLOBALS['wpdb']->insert_id = 0;
esc_p9_018_assert(
    $repository->voidReceipt(55, $scheduleUuid, $targetUuid, 'f9999999-9999-4999-8999-999999999999', 42, static function (array $locked): void {}) === 1001,
    'receipt void remains available without reversal-aware capacity aggregation'
);
esc_p9_018_assert(count($GLOBALS['sc_test_read_queries']) === 4, 'receipt void performs no capacity/reversal read');

fwrite(STDOUT, "ESC P9-018 reversal-aware capacity regression passed ({$assertions} assertions).\n");
