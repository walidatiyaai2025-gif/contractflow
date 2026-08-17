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

// Schema remains unchanged.
esc_p9_015_assert(Migrator::LATEST_VERSION === '1.56.0', 'P9-015 leaves Enterprise schema exactly at 1.56.0');
esc_p9_015_assert(str_contains($migratorSource, "'1.56.0' => Migration0057EnterpriseContractFinancialCollectionReceiptRevisions::class"), 'P9-015 preserves exact Migration0057 mapping');
esc_p9_015_assert(! str_contains($migratorSource, 'Migration0058'), 'P9-015 introduces no Migration0058');

// Capacity is integrated in P9-013 repository, inside the existing transaction/lock protocol.
foreach ([
    'assertCollectionCapacity(',
    'ContractFinancialCollectionReceiptPolicy::MAX_RECEIPTS + 1',
    'ORDER BY r.receipt_uuid ASC',
    'LIMIT %d FOR UPDATE',
    '$used = $used->add(',
    '$used->add($proposedAmount)->compare($scheduledMoney) > 0',
    'would exceed the linked payment schedule amount',
    '$receiptUuid',
] as $marker) {
    esc_p9_015_assert(str_contains($repositorySource, $marker), 'P9-015 capacity implementation contains ' . $marker);
}
esc_p9_015_assert(! str_contains(strtoupper($repositorySource), 'SUM('), 'capacity uses no SQL SUM');
esc_p9_015_assert(! str_contains($repositorySource, 'float') && ! str_contains($repositorySource, 'round('), 'capacity uses no float/rounding path');
esc_p9_015_assert(substr_count($repositorySource, 'assertCollectionCapacity(') === 3, 'capacity helper is defined once and called only by create/revise');
esc_p9_015_assert(strpos($repositorySource, "commit('idempotent Enterprise Contract collection receipt revision')") < strpos($repositorySource, "assertCollectionCapacity(\n                \$contractId,\n                \$scheduleEntryUuid,", strpos($repositorySource, 'public function reviseReceipt')), 'exact revise retry returns before capacity check');
esc_p9_015_assert(! str_contains($scheduleRepositorySource, 'assertCollectionCapacity'), 'P9-012 schedule repository remains unchanged');
esc_p9_015_assert(str_contains($settlementRepositorySource, 'STATE_OVER_COLLECTED') || str_contains($settlementRepositorySource, 'over_collected'), 'P9-014 remains able to surface historical over-collection');
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

// Create exactly to capacity: recorded 40 + proposed 60 = schedule 100; voided 30 consumes zero.
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
];
$GLOBALS['wpdb']->insert_id = 0;
$created = $repository->createReceipt(
    55,
    $scheduleUuid,
    '44444444-4444-4444-8444-444444444444',
    '55555555-5555-4555-8555-555555555555',
    'NEW-60',
    '2026-10-02',
    '60',
    42,
    static function (array $contract): void {}
);
esc_p9_015_assert($created === 1001, 'create exactly at collection capacity succeeds');
$reads = implode("\n", array_map('strval', $GLOBALS['sc_test_read_queries']));
esc_p9_015_assert(str_contains($reads, 'LIMIT 1001 FOR UPDATE'), 'capacity reads are bounded and locked');
esc_p9_015_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), "'60.0000', p.contract_currency, 'recorded', 42"), 'capacity-approved create appends canonical Money');

// Create over capacity fails closed with rollback and no receipt append.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [esc_p9_015_schedule_row($scheduleUuid, 1, '100')],
    [['total' => '1']],
    [],
    [esc_p9_015_receipt_row($otherUuid, $scheduleUuid, 1, '40', 'recorded', 1201)],
];
esc_p9_015_expect_throw(
    static fn (): int => $repository->createReceipt(55, $scheduleUuid, '44444444-4444-4444-8444-444444444444', '55555555-5555-4555-8555-555555555555', null, '2026-10-02', '60.0001', 42, static function (array $contract): void {}),
    RuntimeException::class,
    'create exceeding schedule capacity fails closed'
);
$writes = implode("\n", $GLOBALS['sc_test_queries']);
esc_p9_015_assert(str_contains($writes, 'ROLLBACK'), 'over-capacity create rolls back');
esc_p9_015_assert(! str_contains($writes, 'INSERT INTO'), 'over-capacity create appends no receipt revision');

// Voided receipts consume zero capacity: proposed full scheduled amount remains allowed.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [esc_p9_015_schedule_row($scheduleUuid, 1, '100')],
    [['total' => '1']],
    [],
    [esc_p9_015_receipt_row($voidedUuid, $scheduleUuid, 1, '99', 'voided', 1301)],
];
$GLOBALS['wpdb']->insert_id = 0;
esc_p9_015_assert(
    $repository->createReceipt(55, $scheduleUuid, '44444444-4444-4444-8444-444444444444', '55555555-5555-4555-8555-555555555555', null, '2026-10-02', '100', 42, static function (array $contract): void {}) === 1001,
    'voided current receipts consume zero capacity'
);

// Changed revise excludes the target stable receipt UUID before adding revised amount.
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
];
$GLOBALS['wpdb']->insert_id = 0;
$revised = $repository->reviseReceipt(55, $scheduleUuid, $targetUuid, '66666666-6666-4666-8666-666666666666', 'NEW', '2026-10-03', '50', 42, static function (array $contract): void {});
esc_p9_015_assert($revised === 1001, 'revise succeeds when other recorded 50 + revised target 50 equals capacity');
esc_p9_015_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), "'50.0000', p.contract_currency, 'recorded', 42"), 'capacity-approved revise appends revised Money');

// Revised target that makes other recorded + revised exceed schedule is rejected.
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
];
esc_p9_015_expect_throw(
    static fn (): int => $repository->reviseReceipt(55, $scheduleUuid, $targetUuid, '66666666-6666-4666-8666-666666666666', 'NEW', '2026-10-03', '30.0001', 42, static function (array $contract): void {}),
    RuntimeException::class,
    'revise exceeding capacity after excluding target fails closed'
);
esc_p9_015_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'over-capacity revise appends no revision');

// Exact retry returns before capacity read, even if historical aggregate could already be over capacity.
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

// Capacity read uses the existing 1001st sentinel.
$overflow = [];
for ($i = 1; $i <= ContractFinancialCollectionReceiptPolicy::MAX_RECEIPTS + 1; $i++) {
    $overflow[] = esc_p9_015_receipt_row(sprintf('80000000-0000-4000-8000-%012x', $i), $scheduleUuid, 1, '0.0001', 'recorded', 2000 + $i);
}
$GLOBALS['sc_test_queries'] = [];
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
    '1001 capacity rows fail closed'
);

// Corrupt capacity rows fail closed before arithmetic can approve a write.
foreach ([
    esc_p9_015_receipt_row($otherUuid, $scheduleUuid, 2, '10', 'recorded', 3101),
    esc_p9_015_receipt_row($otherUuid, $scheduleUuid, 1, '10', 'recorded', 3102, 99),
    esc_p9_015_receipt_row($otherUuid, $scheduleUuid, 1, '10', 'recorded', 3103, 31, 'EUR'),
] as $corruptRow) {
    $GLOBALS['sc_test_queries'] = [];
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
        'corrupt current capacity receipt fails closed'
    );
}

// Void has no capacity read and can reduce recorded collection under existing P9-013 rules.
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
esc_p9_015_assert(count($GLOBALS['sc_test_read_queries']) === 4, 'void performs no capacity aggregation read');

// P9-014 remains read-only and capable of surfacing historical over-collection evidence.
esc_p9_015_assert(! str_contains(strtoupper($settlementRepositorySource), 'INSERT INTO') && ! str_contains(strtoupper($settlementRepositorySource), 'UPDATE '), 'P9-014 remains read-only');
esc_p9_015_assert(str_contains($settlementRepositorySource, 'over_collected_amount') && str_contains($settlementRepositorySource, 'over_collected_total'), 'P9-014 continues surfacing historical over-collection');
esc_p9_015_assert(str_contains($gateSource, 'enterprise_contract_financial_collection_capacity_p9_015.php'), 'P9-015 regression is wired into global backend gate');

fwrite(STDOUT, "P9-015 Enterprise Contract collection capacity guard passed ({$assertions} assertions).\n");
