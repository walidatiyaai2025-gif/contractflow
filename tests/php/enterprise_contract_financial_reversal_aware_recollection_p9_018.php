<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Finance\ContractFinancialCollectionReceiptRevisionRepository;
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
function esc_p9_018_schedule_row(string $uuid, string $amount = '100.0000'): array
{
    return [
        'id' => '901', 'contract_id' => '55', 'financial_currency_profile_id' => '31',
        'schedule_entry_uuid' => $uuid, 'revision_number' => '2', 'sequence_no' => '1',
        'amount' => $amount, 'currency_code' => 'USD', 'schedule_entry_state' => 'scheduled',
    ];
}

/** @return array<string,mixed> */
function esc_p9_018_receipt_row(string $uuid, string $scheduleUuid, string $amount, string $state = 'recorded', int $id = 1001): array
{
    return [
        'id' => (string) $id,
        'revision_uuid' => sprintf('20000000-0000-4000-8000-%012x', $id),
        'contract_id' => '55', 'financial_currency_profile_id' => '31',
        'receipt_uuid' => $uuid, 'revision_number' => '2',
        'schedule_entry_uuid' => $scheduleUuid, 'schedule_sequence_no' => '1',
        'external_reference' => 'R-' . $id, 'received_date' => '2026-10-01',
        'amount' => $amount, 'currency_code' => 'USD', 'receipt_state' => $state,
        'created_by' => '42', 'created_at' => '2026-08-17 17:40:00',
    ];
}

/** @return array<string,mixed> */
function esc_p9_018_reversal_row(
    string $reversalUuid,
    string $receiptUuid,
    string $scheduleUuid,
    string $amount,
    string $state = 'recorded',
    int $id = 2001,
    int $profileId = 31,
    string $currency = 'USD',
    int $sequence = 1
): array {
    return [
        'id' => (string) $id,
        'revision_uuid' => sprintf('30000000-0000-4000-8000-%012x', $id),
        'contract_id' => '55', 'financial_currency_profile_id' => (string) $profileId,
        'reversal_uuid' => $reversalUuid, 'revision_number' => '2',
        'receipt_uuid' => $receiptUuid, 'schedule_entry_uuid' => $scheduleUuid,
        'schedule_sequence_no' => (string) $sequence, 'external_reference' => 'REV-' . $id,
        'reversal_date' => '2026-10-02', 'amount' => $amount,
        'currency_code' => $currency, 'reversal_state' => $state,
        'created_by' => '42', 'created_at' => '2026-08-17 17:41:00',
    ];
}

$root = dirname(__DIR__, 2);
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialCollectionReceiptRevisionRepository.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

esc_p9_018_assert(Migrator::LATEST_VERSION === '1.57.0', 'P9-018 is schema-free at 1.57.0');
foreach ([
    'safecontracts_contract_financial_collection_reversal_revisions',
    'ContractFinancialCollectionReversalPolicy::MAX_REVERSALS + 1',
    'ORDER BY r.reversal_uuid ASC',
    'reversal_state',
    'reversal aggregate exceeds its recorded receipt amount',
    'revised receipt amount cannot be below its recorded reversal aggregate',
] as $marker) {
    esc_p9_018_assert(str_contains($repositorySource, $marker), 'P9-018 repository contains ' . $marker);
}
esc_p9_018_assert(! str_contains(strtoupper($repositorySource), 'SUM('), 'P9-018 uses no SQL SUM');
esc_p9_018_assert(! str_contains($repositorySource, 'float') && ! str_contains($repositorySource, 'round('), 'P9-018 uses no float/rounding path');

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(7);
$repository = new ContractFinancialCollectionReceiptRevisionRepository();
$contract = [['id' => '55', 'accountant_user_id' => '42', 'status' => 'active', 'is_archived' => '0']];
$profile = [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']];
$scheduleUuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$receiptA = '11111111-1111-4111-8111-111111111111';
$receiptB = '22222222-2222-4222-8222-222222222222';
$reversalA = '33333333-3333-4333-8333-333333333333';
$reversalB = '44444444-4444-4444-8444-444444444444';

// Gross receipt 100 with recorded reversal 40 leaves exact 40 of recollection capacity.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contract, $profile, [esc_p9_018_schedule_row($scheduleUuid)], [['total' => '1']], [],
    [esc_p9_018_receipt_row($receiptA, $scheduleUuid, '100', 'recorded', 1101)],
    [esc_p9_018_reversal_row($reversalA, $receiptA, $scheduleUuid, '40', 'recorded', 2101)],
];
$GLOBALS['wpdb']->insert_id = 0;
$created = $repository->createReceipt(
    55, $scheduleUuid,
    '55555555-5555-4555-8555-555555555555',
    '66666666-6666-4666-8666-666666666666',
    'RECOLLECT-40', '2026-10-03', '40', 42,
    static function (array $locked): void {}
);
esc_p9_018_assert($created === 1001, 'recorded reversal restores exact create capacity');
$reads = implode("\n", array_map('strval', $GLOBALS['sc_test_read_queries']));
esc_p9_018_assert(str_contains($reads, 'collection_reversal_revisions') && str_contains($reads, 'LIMIT 1001 FOR UPDATE'), 'capacity locks bounded latest reversal evidence');

// Recollection beyond net scheduled capacity is rejected.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contract, $profile, [esc_p9_018_schedule_row($scheduleUuid)], [['total' => '1']], [],
    [esc_p9_018_receipt_row($receiptA, $scheduleUuid, '100', 'recorded', 1201)],
    [esc_p9_018_reversal_row($reversalA, $receiptA, $scheduleUuid, '40', 'recorded', 2201)],
];
esc_p9_018_expect_throw(
    static fn (): int => $repository->createReceipt(55, $scheduleUuid, '55555555-5555-4555-8555-555555555555', '66666666-6666-4666-8666-666666666666', null, '2026-10-03', '40.0001', 42, static function (array $locked): void {}),
    RuntimeException::class,
    'recollection cannot exceed schedule after reversal netting'
);
esc_p9_018_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'over-capacity recollection appends nothing');

// Voided reversal contributes zero: full gross usage remains at 100, so another cent is rejected.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contract, $profile, [esc_p9_018_schedule_row($scheduleUuid)], [['total' => '1']], [],
    [esc_p9_018_receipt_row($receiptA, $scheduleUuid, '100', 'recorded', 1301)],
    [esc_p9_018_reversal_row($reversalA, $receiptA, $scheduleUuid, '40', 'voided', 2301)],
];
esc_p9_018_expect_throw(
    static fn (): int => $repository->createReceipt(55, $scheduleUuid, '55555555-5555-4555-8555-555555555555', '66666666-6666-4666-8666-666666666666', null, '2026-10-03', '0.0001', 42, static function (array $locked): void {}),
    RuntimeException::class,
    'voided reversal restores no recollection capacity'
);

// Revise target preserves its reversal evidence: target gross 60/reversed 20 -> revised gross 50/net 30; other net 70 => 100.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contract, $profile, [esc_p9_018_schedule_row($scheduleUuid)],
    [esc_p9_018_receipt_row($receiptA, $scheduleUuid, '60', 'recorded', 1401)],
    [
        esc_p9_018_receipt_row($receiptA, $scheduleUuid, '60', 'recorded', 1401),
        esc_p9_018_receipt_row($receiptB, $scheduleUuid, '70', 'recorded', 1402),
    ],
    [esc_p9_018_reversal_row($reversalA, $receiptA, $scheduleUuid, '20', 'recorded', 2401)],
];
$GLOBALS['wpdb']->insert_id = 0;
$revised = $repository->reviseReceipt(55, $scheduleUuid, $receiptA, '77777777-7777-4777-8777-777777777777', 'REVISED', '2026-10-04', '50', 42, static function (array $locked): void {});
esc_p9_018_assert($revised === 1001, 'revised target uses proposed gross minus its recorded reversals');

// Revised target cannot drop below its own reversal aggregate.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contract, $profile, [esc_p9_018_schedule_row($scheduleUuid)],
    [esc_p9_018_receipt_row($receiptA, $scheduleUuid, '60', 'recorded', 1501)],
    [esc_p9_018_receipt_row($receiptA, $scheduleUuid, '60', 'recorded', 1501)],
    [
        esc_p9_018_reversal_row($reversalA, $receiptA, $scheduleUuid, '20', 'recorded', 2501),
        esc_p9_018_reversal_row($reversalB, $receiptA, $scheduleUuid, '15', 'recorded', 2502),
    ],
];
esc_p9_018_expect_throw(
    static fn (): int => $repository->reviseReceipt(55, $scheduleUuid, $receiptA, '77777777-7777-4777-8777-777777777777', 'TOO-LOW', '2026-10-04', '34.9999', 42, static function (array $locked): void {}),
    RuntimeException::class,
    'revise below recorded reversal aggregate fails closed'
);
esc_p9_018_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'revise below reversal aggregate appends nothing');

// Corrupt reversal linkage/profile/currency/sequence fails closed.
foreach ([
    esc_p9_018_reversal_row($reversalA, '99999999-9999-4999-8999-999999999999', $scheduleUuid, '10', 'recorded', 2601),
    esc_p9_018_reversal_row($reversalA, $receiptA, $scheduleUuid, '10', 'recorded', 2602, 99),
    esc_p9_018_reversal_row($reversalA, $receiptA, $scheduleUuid, '10', 'recorded', 2603, 31, 'EUR'),
    esc_p9_018_reversal_row($reversalA, $receiptA, $scheduleUuid, '10', 'recorded', 2604, 31, 'USD', 2),
] as $badReversal) {
    $GLOBALS['sc_test_queries'] = [];
    $GLOBALS['sc_test_result_queue'] = [
        $contract, $profile, [esc_p9_018_schedule_row($scheduleUuid)], [['total' => '1']], [],
        [esc_p9_018_receipt_row($receiptA, $scheduleUuid, '50', 'recorded', 1601)],
        [$badReversal],
    ];
    esc_p9_018_expect_throw(
        static fn (): int => $repository->createReceipt(55, $scheduleUuid, '55555555-5555-4555-8555-555555555555', '66666666-6666-4666-8666-666666666666', null, '2026-10-03', '1', 42, static function (array $locked): void {}),
        UnexpectedValueException::class,
        'corrupt reversal evidence fails recollection closed'
    );
}

// Aggregate recorded reversals cannot exceed their gross receipt.
$GLOBALS['sc_test_result_queue'] = [
    $contract, $profile, [esc_p9_018_schedule_row($scheduleUuid)], [['total' => '1']], [],
    [esc_p9_018_receipt_row($receiptA, $scheduleUuid, '50', 'recorded', 1701)],
    [
        esc_p9_018_reversal_row($reversalA, $receiptA, $scheduleUuid, '30', 'recorded', 2701),
        esc_p9_018_reversal_row($reversalB, $receiptA, $scheduleUuid, '20.0001', 'recorded', 2702),
    ],
];
esc_p9_018_expect_throw(
    static fn (): int => $repository->createReceipt(55, $scheduleUuid, '55555555-5555-4555-8555-555555555555', '66666666-6666-4666-8666-666666666666', null, '2026-10-03', '1', 42, static function (array $locked): void {}),
    UnexpectedValueException::class,
    'reversal aggregate above gross receipt fails closed'
);

esc_p9_018_assert(str_contains($gateSource, 'enterprise_contract_financial_reversal_aware_recollection_p9_018.php'), 'P9-018 regression is wired into global backend gate');
fwrite(STDOUT, "P9-018 reversal-aware recollection capacity passed ({$assertions} assertions).\n");
