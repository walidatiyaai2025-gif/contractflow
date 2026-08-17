<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Finance\ContractFinancialScheduleSettlementPolicy;
use SafeContracts\Finance\ContractFinancialScheduleSettlementRepository;
use SafeContracts\Finance\Money;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;
function esc_p9_014_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @param class-string<Throwable> $expected */
function esc_p9_014_expect_throw(callable $callback, string $expected, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p9_014_assert($error instanceof $expected, $message . ' (received ' . $error::class . ')');
        return;
    }
    esc_p9_014_assert(false, $message . ' (no exception)');
}

/** @return array<string,mixed> */
function esc_p9_014_schedule_row(
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
function esc_p9_014_receipt_row(
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

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialScheduleSettlementPolicy.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialScheduleSettlementRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialScheduleSettlementService.php');
$scheduleRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialPaymentScheduleRevisionRepository.php');
$receiptRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialCollectionReceiptRevisionRepository.php');
$legacyPaymentRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Payments/PaymentRepository.php');
$legacyCollectionRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Collections/CollectionRepository.php');
$reconciliationRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialReconciliationRepository.php');
$reconciliationServiceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialReconciliationService.php');
$routerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

// P9-014 remains a schema-free historical 1.56.0 boundary after later additive migrations.
esc_p9_014_assert(version_compare(Migrator::LATEST_VERSION, '1.56.0', '>='), 'P9-014 historical schema boundary remains available at or after 1.56.0');
esc_p9_014_assert(str_contains($migratorSource, "'1.56.0' => Migration0057EnterpriseContractFinancialCollectionReceiptRevisions::class"), 'P9-014 preserves exact 1.56.0 => Migration0057 mapping');

// Five explicit derived states using exact Money comparison.
$usd = 'USD';
esc_p9_014_assert(ContractFinancialScheduleSettlementPolicy::derive('scheduled', Money::of('100', $usd), Money::of('0', $usd)) === 'uncollected', 'zero collection derives uncollected');
esc_p9_014_assert(ContractFinancialScheduleSettlementPolicy::derive('scheduled', Money::of('100', $usd), Money::of('40', $usd)) === 'partial', 'partial collection derives partial');
esc_p9_014_assert(ContractFinancialScheduleSettlementPolicy::derive('scheduled', Money::of('100', $usd), Money::of('100', $usd)) === 'settled', 'equal collection derives settled');
esc_p9_014_assert(ContractFinancialScheduleSettlementPolicy::derive('scheduled', Money::of('100', $usd), Money::of('120', $usd)) === 'over_collected', 'excess collection derives over_collected');
esc_p9_014_assert(ContractFinancialScheduleSettlementPolicy::derive('voided', Money::of('100', $usd), Money::of('25', $usd)) === 'voided', 'voided schedule derives voided regardless collected history');
esc_p9_014_assert(! str_contains($policySource, 'float') && ! str_contains($policySource, 'round('), 'settlement policy defines no float/rounding path');

// Read-only architecture and bounded latest-current reads.
foreach ([
    'TenantContextStore::context()->requireTenantId()',
    '$authorizeLockedContract($contract)',
    'ContractFinancialPaymentSchedulePolicy::MAX_ENTRIES + 1',
    'ContractFinancialCollectionReceiptPolicy::MAX_RECEIPTS + 1',
    'NOT EXISTS (',
    'ORDER BY s.sequence_no ASC, s.schedule_entry_uuid ASC',
    'ORDER BY r.schedule_entry_uuid ASC, r.received_date ASC, r.receipt_uuid ASC',
    'Money::of(',
    '->add(',
    '->subtract(',
    '->compare(',
    'voided_schedule_collected_total',
] as $marker) {
    esc_p9_014_assert(str_contains($repositorySource, $marker), 'P9-014 repository contains ' . $marker);
}
esc_p9_014_assert(! str_contains(strtoupper($repositorySource), 'INSERT INTO') && ! str_contains(strtoupper($repositorySource), 'UPDATE ') && ! str_contains(strtoupper($repositorySource), 'DELETE FROM'), 'P9-014 repository has no mutation SQL');
esc_p9_014_assert(! str_contains(strtoupper($repositorySource), 'SUM(') && ! str_contains($repositorySource, 'float') && ! str_contains($repositorySource, 'round('), 'P9-014 avoids SQL aggregate/float rounding semantics');
esc_p9_014_assert(! str_contains($scheduleRepositorySource, 'ContractFinancialScheduleSettlement'), 'P9-012 has no reverse settlement coupling');
esc_p9_014_assert(! str_contains($receiptRepositorySource, 'ContractFinancialScheduleSettlement'), 'P9-013 has no reverse settlement coupling');
esc_p9_014_assert(! str_contains($legacyPaymentRepositorySource, 'ContractFinancialScheduleSettlement') && ! str_contains($legacyCollectionRepositorySource, 'ContractFinancialScheduleSettlement'), 'legacy payment/collection repositories have no settlement coupling');
esc_p9_014_assert(! str_contains($reconciliationRepositorySource, 'ScheduleSettlement') && ! str_contains($reconciliationServiceSource, 'ScheduleSettlement'), 'P9-006 Contract-value reconciliation remains independent');
esc_p9_014_assert(! str_contains($routerSource, 'ContractFinancialScheduleSettlement'), 'P9-014 exposes no REST route');

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(7);
$repository = new ContractFinancialScheduleSettlementRepository();
$contractActive = [['id' => '55', 'accountant_user_id' => '42', 'status' => 'active', 'is_archived' => '0']];
$profile = [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']];
$s1 = '11111111-1111-4111-8111-111111111111';
$s2 = '22222222-2222-4222-8222-222222222222';
$s3 = '33333333-3333-4333-8333-333333333333';
$s4 = '44444444-4444-4444-8444-444444444444';
$s5 = '55555555-5555-4555-8555-555555555555';
$schedules = [
    esc_p9_014_schedule_row($s1, 1, '100'),
    esc_p9_014_schedule_row($s2, 2, '100'),
    esc_p9_014_schedule_row($s3, 3, '100'),
    esc_p9_014_schedule_row($s4, 4, '100'),
    esc_p9_014_schedule_row($s5, 5, '100', 'voided'),
];
$receipts = [
    esc_p9_014_receipt_row('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1', $s2, 2, '40', 'recorded', 2001),
    esc_p9_014_receipt_row('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa2', $s3, 3, '100', 'recorded', 2002),
    esc_p9_014_receipt_row('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa3', $s4, 4, '120', 'recorded', 2003),
    esc_p9_014_receipt_row('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa4', $s4, 4, '30', 'voided', 2004),
    esc_p9_014_receipt_row('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa5', $s5, 5, '25', 'recorded', 2005),
];

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, $schedules, $receipts];
$authorized = false;
$result = $repository->reconcileContract(55, static function (array $contract) use (&$authorized): void {
    esc_p9_014_assert((int) ($contract['id'] ?? 0) === 55, 'settlement authorization receives exact locked Contract');
    esc_p9_014_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'settlement authorization occurs before profile/schedule/receipt reads');
    esc_p9_014_assert(str_contains((string) $GLOBALS['sc_test_read_queries'][0], 'FOR UPDATE'), 'settlement authorization is protected by Contract lock');
    $authorized = true;
});
esc_p9_014_assert($authorized, 'settlement locked authorization callback executes');
esc_p9_014_assert(count($result['entries']) === 5, 'settlement returns all current schedule entries');
esc_p9_014_assert($result['entries'][0]['settlement_state'] === 'uncollected' && $result['entries'][0]['remaining_amount'] === '100.0000', 'uncollected row derives full remaining');
esc_p9_014_assert($result['entries'][1]['settlement_state'] === 'partial' && $result['entries'][1]['collected_amount'] === '40.0000' && $result['entries'][1]['remaining_amount'] === '60.0000', 'partial row derives exact collected/remaining');
esc_p9_014_assert($result['entries'][2]['settlement_state'] === 'settled' && $result['entries'][2]['remaining_amount'] === '0.0000', 'settled row derives zero remaining');
esc_p9_014_assert($result['entries'][3]['settlement_state'] === 'over_collected' && $result['entries'][3]['collected_amount'] === '120.0000' && $result['entries'][3]['over_collected_amount'] === '20.0000', 'over-collected row derives exact excess and ignores voided receipt');
esc_p9_014_assert($result['entries'][4]['settlement_state'] === 'voided' && $result['entries'][4]['collected_amount'] === '25.0000' && $result['entries'][4]['remaining_amount'] === '0.0000', 'voided schedule preserves collected history without current outstanding');
esc_p9_014_assert($result['summary']['scheduled_total'] === '400.0000', 'summary excludes voided schedule from current scheduled total');
esc_p9_014_assert($result['summary']['collected_total'] === '260.0000', 'summary counts recorded receipts linked to current scheduled entries only');
esc_p9_014_assert($result['summary']['remaining_total'] === '160.0000', 'summary derives exact current remaining total');
esc_p9_014_assert($result['summary']['over_collected_total'] === '20.0000', 'summary derives exact over-collected total');
esc_p9_014_assert($result['summary']['voided_schedule_collected_total'] === '25.0000', 'summary surfaces recorded receipts linked to voided schedules');
esc_p9_014_assert($result['summary']['currency_code'] === 'USD', 'summary carries authoritative P9-003 currency');
esc_p9_014_assert(implode("\n", $GLOBALS['sc_test_queries']) === "START TRANSACTION\nCOMMIT", 'settlement reconciliation performs transaction control only');

// Historical Contract lifecycle remains readable.
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'accountant_user_id' => '42', 'status' => 'completed', 'is_archived' => '1']],
    $profile,
    [esc_p9_014_schedule_row($s1, 1, '100')],
    [],
];
esc_p9_014_assert(count($repository->reconcileContract(55, static function (array $contract): void {})['entries']) === 1, 'completed/archived Contract settlement remains readable');

// Existing P9-012/P9-013 cardinality sentinels remain authoritative.
$overflowSchedules = [];
for ($i = 1; $i <= 501; $i++) {
    $overflowSchedules[] = esc_p9_014_schedule_row(sprintf('60000000-0000-4000-8000-%012x', $i), $i, '1', 'scheduled', 3000 + $i);
}
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, $overflowSchedules];
esc_p9_014_expect_throw(static fn (): array => $repository->reconcileContract(55, static function (array $contract): void {}), RuntimeException::class, '501 current schedule identities fail settlement closed');

$overflowReceipts = [];
for ($i = 1; $i <= 1001; $i++) {
    $overflowReceipts[] = esc_p9_014_receipt_row(sprintf('70000000-0000-4000-8000-%012x', $i), $s1, 1, '1', 'recorded', 5000 + $i);
}
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_014_schedule_row($s1, 1, '100')], $overflowReceipts];
esc_p9_014_expect_throw(static fn (): array => $repository->reconcileContract(55, static function (array $contract): void {}), RuntimeException::class, '1001 current receipt identities fail settlement closed');

// Duplicate/cross-link/currency corruption fails closed.
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [
    esc_p9_014_schedule_row($s1, 1, '100', 'scheduled', 6001),
    esc_p9_014_schedule_row($s1, 2, '100', 'scheduled', 6002),
], []];
esc_p9_014_expect_throw(static fn (): array => $repository->reconcileContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'duplicate latest schedule identities fail settlement closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [
    esc_p9_014_schedule_row($s1, 1, '100', 'scheduled', 6011),
    esc_p9_014_schedule_row($s2, 1, '100', 'scheduled', 6012),
], []];
esc_p9_014_expect_throw(static fn (): array => $repository->reconcileContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'duplicate latest schedule sequences fail settlement closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_014_schedule_row($s1, 1)], [
    esc_p9_014_receipt_row('88888888-8888-4888-8888-888888888888', $s2, 2, '1'),
]];
esc_p9_014_expect_throw(static fn (): array => $repository->reconcileContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'receipt referencing missing current schedule fails closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_014_schedule_row($s1, 1)], [
    esc_p9_014_receipt_row('88888888-8888-4888-8888-888888888888', $s1, 2, '1'),
]];
esc_p9_014_expect_throw(static fn (): array => $repository->reconcileContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'receipt sequence snapshot mismatch fails closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_014_schedule_row($s1, 1, '100', 'scheduled', 6101, 99)], []];
esc_p9_014_expect_throw(static fn (): array => $repository->reconcileContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'cross-profile schedule fails settlement closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_014_schedule_row($s1, 1, '100', 'scheduled', 6101, 31, 'EUR')], []];
esc_p9_014_expect_throw(static fn (): array => $repository->reconcileContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'cross-currency schedule fails settlement closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_014_schedule_row($s1, 1)], [
    esc_p9_014_receipt_row('88888888-8888-4888-8888-888888888888', $s1, 1, '1', 'recorded', 6201, 99),
]];
esc_p9_014_expect_throw(static fn (): array => $repository->reconcileContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'cross-profile receipt fails settlement closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_014_schedule_row($s1, 1)], [
    esc_p9_014_receipt_row('88888888-8888-4888-8888-888888888888', $s1, 1, '1', 'recorded', 6201, 31, 'EUR'),
]];
esc_p9_014_expect_throw(static fn (): array => $repository->reconcileContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'cross-currency receipt fails settlement closed');

// Service is strictly read-only ACCESS + tenant data scope.
esc_p9_014_assert(str_contains($serviceSource, 'current_user_can(Capabilities::ACCESS)'), 'settlement service requires ACCESS');
esc_p9_014_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability(Capabilities::ACCESS)'), 'tenant role narrows settlement ACCESS');
esc_p9_014_assert(str_contains($serviceSource, 'Capabilities::VIEW_ALL') && str_contains($serviceSource, 'Capabilities::VIEW_ASSIGNED'), 'settlement service preserves locked Contract scope');
foreach (['MANAGE_PAYMENTS', 'MANAGE_COLLECTIONS', 'EDIT_CONTRACTS', 'create(', 'revise(', 'void('] as $forbidden) {
    esc_p9_014_assert(! str_contains($serviceSource, $forbidden), 'settlement service exposes no mutation path: ' . $forbidden);
}
esc_p9_014_assert(str_contains($gateSource, 'enterprise_contract_financial_schedule_settlement_p9_014.php'), 'P9-014 regression is wired into global backend gate');

fwrite(STDOUT, "P9-014 Enterprise Contract schedule settlement reconciliation passed ({$assertions} assertions).\n");
