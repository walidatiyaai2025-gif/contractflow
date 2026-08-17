<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Finance\ContractFinancialReconciliationCalculator;
use SafeContracts\Finance\ContractFinancialScheduleCoveragePolicy;
use SafeContracts\Finance\ContractFinancialScheduleCoverageRepository;
use SafeContracts\Finance\ContractFinancialScheduleCoverageService;
use SafeContracts\Finance\Money;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p9_019_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @param class-string<Throwable> $expected */
function esc_p9_019_expect_throw(callable $callback, string $expected, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p9_019_assert($error instanceof $expected, $message . ' (received ' . $error::class . ')');
        return;
    }
    esc_p9_019_assert(false, $message . ' (no exception)');
}

/** @return array<string,mixed> */
function esc_p9_019_base(string $amount = '100.0000', string $currency = 'USD', int $profileId = 31): array
{
    return [
        'id' => '80',
        'uuid' => '11111111-1111-4111-8111-111111111111',
        'contract_id' => '55',
        'financial_currency_profile_id' => (string) $profileId,
        'revision_number' => '2',
        'amount' => $amount,
        'currency_code' => $currency,
        'created_by' => '42',
    ];
}

/** @return array<string,mixed> */
function esc_p9_019_adjustment(
    string $lineUuid,
    string $kind,
    string $amount,
    string $state = 'active',
    int $id = 1001,
    int $profileId = 31,
    string $currency = 'USD'
): array {
    return [
        'id' => (string) $id,
        'revision_uuid' => sprintf('20000000-0000-4000-8000-%012x', $id),
        'contract_id' => '55',
        'financial_currency_profile_id' => (string) $profileId,
        'line_uuid' => $lineUuid,
        'revision_number' => '1',
        'adjustment_kind' => $kind,
        'description' => 'Coverage adjustment ' . $id,
        'amount' => $amount,
        'currency_code' => $currency,
        'line_state' => $state,
        'created_by' => '42',
    ];
}

/** @return array<string,mixed> */
function esc_p9_019_schedule(
    string $scheduleUuid,
    int $sequence,
    string $amount,
    string $state = 'scheduled',
    int $id = 2001,
    int $profileId = 31,
    string $currency = 'USD'
): array {
    return [
        'id' => (string) $id,
        'revision_uuid' => sprintf('30000000-0000-4000-8000-%012x', $id),
        'contract_id' => '55',
        'financial_currency_profile_id' => (string) $profileId,
        'schedule_entry_uuid' => $scheduleUuid,
        'revision_number' => '2',
        'sequence_no' => (string) $sequence,
        'reference' => 'S-' . $sequence,
        'due_date' => '2026-12-31',
        'amount' => $amount,
        'currency_code' => $currency,
        'schedule_entry_state' => $state,
        'created_by' => '42',
    ];
}

/** @return array<string,mixed> */
function esc_p9_019_membership(): array
{
    return [
        'id' => '9',
        'tenant_id' => '7',
        'user_id' => '42',
        'role_code' => 'member',
        'is_owner' => '0',
    ];
}

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$calculatorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialReconciliationCalculator.php');
$p9_006ServiceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialReconciliationService.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialScheduleCoverageRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialScheduleCoverageService.php');
$scheduleRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialPaymentScheduleRevisionRepository.php');
$settlementRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialScheduleSettlementRepository.php');
$receiptRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialCollectionReceiptRevisionRepository.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

// P9-019 is schema-free over the P9-016/P9-018 1.57.0 boundary.
esc_p9_019_assert(Migrator::LATEST_VERSION === '1.57.0', 'P9-019 keeps schema exactly 1.57.0');
esc_p9_019_assert(str_contains($migratorSource, "'1.57.0' => Migration0058EnterpriseContractFinancialCollectionReversalRevisions::class"), 'P9-019 preserves Migration0058 mapping');
esc_p9_019_assert(! str_contains($migratorSource, 'Migration0059'), 'P9-019 introduces no Migration0059');

// Shared authoritative P9-006 arithmetic is extracted once and reused.
esc_p9_019_assert(str_contains($calculatorSource, '$gross = $base->add($additions)') && str_contains($calculatorSource, '$net = $gross->subtract($discounts)'), 'shared calculator owns authoritative base/addition/discount net arithmetic');
esc_p9_019_assert(str_contains($p9_006ServiceSource, 'ContractFinancialReconciliationCalculator::reconcile($lockedSnapshot)'), 'P9-006 service delegates to shared calculator');
esc_p9_019_assert(str_contains($serviceSource, 'ContractFinancialReconciliationCalculator::reconcile($snapshot[\'financial\'])'), 'P9-019 service reuses the same authoritative calculator');

// One Contract-first snapshot contains both financial evidence and schedules.
foreach ([
    "query('START TRANSACTION')",
    'LIMIT 2 FOR UPDATE',
    '$authorizeLockedContract($contract)',
    'ContractFinancialAdjustmentPolicy::MAX_LINES + 1',
    'ContractFinancialPaymentSchedulePolicy::MAX_ENTRIES + 1',
    'ORDER BY r.sequence_no ASC, r.schedule_entry_uuid ASC',
    'duplicate latest payment schedule sequence numbers',
] as $marker) {
    esc_p9_019_assert(str_contains($repositorySource, $marker), 'P9-019 repository contains ' . $marker);
}
$writePattern = '/\b(?:INSERT\s+INTO|DELETE\s+FROM|UPDATE\s+[A-Za-z0-9_`{}.]+\s+SET)\b/i';
esc_p9_019_assert(! preg_match($writePattern, $repositorySource) && ! preg_match($writePattern, $serviceSource), 'P9-019 is read-only');
esc_p9_019_assert(! str_contains(strtoupper($repositorySource), 'SUM(') && ! str_contains(strtoupper($serviceSource), 'SUM('), 'P9-019 uses no SQL SUM');
esc_p9_019_assert(! str_contains($repositorySource, 'float') && ! str_contains($repositorySource, 'round(') && ! str_contains($serviceSource, 'float') && ! str_contains($serviceSource, 'round('), 'P9-019 uses no float/rounding path');
esc_p9_019_assert(! str_contains($scheduleRepositorySource, 'ScheduleCoverage'), 'P9-012 schedule mutation remains uncoupled from P9-019');
esc_p9_019_assert(! str_contains($settlementRepositorySource, 'ScheduleCoverage'), 'P9-014/P9-017 settlement remains independent');
esc_p9_019_assert(! str_contains($receiptRepositorySource, 'ScheduleCoverage'), 'P9-018 receipt capacity remains independent');
esc_p9_019_assert(str_contains($serviceSource, 'current_user_can(Capabilities::ACCESS)') && str_contains($serviceSource, 'TenantAuthorization::allowsCapability(Capabilities::ACCESS)'), 'P9-019 requires ACCESS with tenant-role narrowing');
esc_p9_019_assert(str_contains($serviceSource, 'Capabilities::VIEW_ALL') && str_contains($serviceSource, 'Capabilities::VIEW_ASSIGNED'), 'P9-019 preserves locked Contract data scope');

// Pure coverage states are exact Money comparisons and preserve signed nets.
$usd = SafeContracts\Finance\CurrencyCode::from('USD');
esc_p9_019_assert(ContractFinancialScheduleCoveragePolicy::derive(Money::of('100', $usd), Money::of('120', $usd)) === ContractFinancialScheduleCoveragePolicy::STATE_UNDER_SCHEDULED, '100 scheduled vs 120 net is under-scheduled');
esc_p9_019_assert(ContractFinancialScheduleCoveragePolicy::derive(Money::of('120', $usd), Money::of('120', $usd)) === ContractFinancialScheduleCoveragePolicy::STATE_ALIGNED, 'equal schedule/net is aligned');
esc_p9_019_assert(ContractFinancialScheduleCoveragePolicy::derive(Money::of('130', $usd), Money::of('120', $usd)) === ContractFinancialScheduleCoveragePolicy::STATE_OVER_SCHEDULED, '130 scheduled vs 120 net is over-scheduled');
esc_p9_019_assert(ContractFinancialScheduleCoveragePolicy::derive(Money::of('0', $usd), Money::of('-25', $usd)) === ContractFinancialScheduleCoveragePolicy::STATE_OVER_SCHEDULED, 'negative authoritative net remains signed and compares mathematically');
esc_p9_019_expect_throw(
    static fn (): string => ContractFinancialScheduleCoveragePolicy::derive(Money::of('1', 'USD'), Money::of('1', 'EUR')),
    InvalidArgumentException::class,
    'coverage policy rejects cross-currency comparison'
);

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(7);
$repository = new ContractFinancialScheduleCoverageRepository();
$contract = [['id' => '55', 'accountant_user_id' => '42', 'status' => 'active', 'is_archived' => '0']];
$profile = [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']];
$additionUuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$discountUuid = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
$s1 = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
$s2 = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';
$s3 = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';

// Repository proves locked authorization happens before finance/schedule reads and returns one coherent snapshot.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contract,
    $profile,
    [esc_p9_019_base('100')],
    [
        esc_p9_019_adjustment($additionUuid, 'addition', '25', 'active', 1001),
        esc_p9_019_adjustment($discountUuid, 'discount', '5', 'active', 1002),
    ],
    [
        esc_p9_019_schedule($s1, 1, '70', 'scheduled', 2001),
        esc_p9_019_schedule($s2, 2, '50', 'scheduled', 2002),
        esc_p9_019_schedule($s3, 3, '30', 'voided', 2003),
    ],
];
$authorized = false;
$raw = $repository->snapshot(55, static function (array $locked) use (&$authorized): void {
    esc_p9_019_assert((int) ($locked['id'] ?? 0) === 55, 'coverage authorization receives exact locked Contract');
    esc_p9_019_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'coverage authorizes before financial/schedule reads');
    esc_p9_019_assert(str_contains((string) $GLOBALS['sc_test_read_queries'][0], 'FOR UPDATE'), 'coverage authorization boundary is Contract FOR UPDATE');
    $authorized = true;
});
esc_p9_019_assert($authorized, 'coverage locked authorization callback executes');
esc_p9_019_assert(count($raw['financial']['adjustments']) === 2 && count($raw['schedules']) === 3, 'coverage repository returns one coherent financial/schedule snapshot');
esc_p9_019_assert(implode("\n", $GLOBALS['sc_test_queries']) === "START TRANSACTION\nCOMMIT", 'successful coverage snapshot performs transaction control only');
$readSql = implode("\n", array_map('strval', $GLOBALS['sc_test_read_queries']));
esc_p9_019_assert(str_contains($readSql, 'LIMIT 201') && str_contains($readSql, 'LIMIT 501'), 'financial and schedule reads use 201st/501st sentinels');

// Service authorization setup.
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::VIEW_ASSIGNED => true,
];
$service = new ContractFinancialScheduleCoverageService();

// Aligned: 100 + 25 - 5 = 120, current schedule = 70 + 50 = 120, voided 30 is historical only.
$GLOBALS['sc_test_result_queue'] = [
    [esc_p9_019_membership()],
    $contract,
    $profile,
    [esc_p9_019_base('100')],
    [
        esc_p9_019_adjustment($additionUuid, 'addition', '25', 'active', 1101),
        esc_p9_019_adjustment($discountUuid, 'discount', '5', 'active', 1102),
    ],
    [
        esc_p9_019_schedule($s1, 1, '70', 'scheduled', 2101),
        esc_p9_019_schedule($s2, 2, '50', 'scheduled', 2102),
        esc_p9_019_schedule($s3, 3, '30', 'voided', 2103),
    ],
];
$aligned = $service->reconcile(55);
esc_p9_019_assert($aligned['contract_net_value'] === '120.0000', 'P9-019 reuses P9-006 authoritative net value');
esc_p9_019_assert($aligned['scheduled_total'] === '120.0000' && $aligned['voided_scheduled_total'] === '30.0000', 'P9-019 separates current and voided scheduled evidence');
esc_p9_019_assert($aligned['schedule_delta'] === '0.0000' && $aligned['coverage_state'] === 'aligned', 'exact schedule coverage is aligned');
esc_p9_019_assert($aligned['scheduled_entry_count'] === 2 && $aligned['voided_entry_count'] === 1, 'coverage counts current and voided entries separately');

// Under-scheduled by 20.
$GLOBALS['sc_test_result_queue'] = [
    [esc_p9_019_membership()], $contract, $profile, [esc_p9_019_base('120')], [],
    [esc_p9_019_schedule($s1, 1, '100', 'scheduled', 2201)],
];
$under = $service->reconcile(55);
esc_p9_019_assert($under['scheduled_total'] === '100.0000' && $under['schedule_delta'] === '-20.0000' && $under['coverage_state'] === 'under_scheduled', 'under-scheduled coverage preserves signed negative delta');

// Over-scheduled by 10.
$GLOBALS['sc_test_result_queue'] = [
    [esc_p9_019_membership()], $contract, $profile, [esc_p9_019_base('120')], [],
    [esc_p9_019_schedule($s1, 1, '130', 'scheduled', 2301)],
];
$over = $service->reconcile(55);
esc_p9_019_assert($over['scheduled_total'] === '130.0000' && $over['schedule_delta'] === '10.0000' && $over['coverage_state'] === 'over_scheduled', 'over-scheduled coverage preserves signed positive delta');

// Negative P9-006 net is not clamped: base 100 - discount 125 = -25; zero current schedule is mathematically over by 25.
$GLOBALS['sc_test_result_queue'] = [
    [esc_p9_019_membership()], $contract, $profile, [esc_p9_019_base('100')],
    [esc_p9_019_adjustment($discountUuid, 'discount', '125', 'active', 1401)],
    [],
];
$negative = $service->reconcile(55);
esc_p9_019_assert($negative['contract_net_value'] === '-25.0000', 'negative authoritative Contract net remains signed');
esc_p9_019_assert($negative['scheduled_total'] === '0.0000' && $negative['schedule_delta'] === '25.0000' && $negative['coverage_state'] === 'over_scheduled', 'negative net comparison is mathematical and unclamped');

// Duplicate current schedule sequences fail closed.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contract, $profile, [esc_p9_019_base('100')], [],
    [
        esc_p9_019_schedule($s1, 1, '50', 'scheduled', 2401),
        esc_p9_019_schedule($s2, 1, '50', 'scheduled', 2402),
    ],
];
esc_p9_019_expect_throw(static fn (): array => $repository->snapshot(55, static function (array $locked): void {}), UnexpectedValueException::class, 'duplicate current schedule sequences fail closed');
esc_p9_019_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'ROLLBACK'), 'schedule sequence corruption rolls back snapshot');

// Cross-currency schedule fails closed.
$GLOBALS['sc_test_result_queue'] = [
    $contract, $profile, [esc_p9_019_base('100')], [],
    [esc_p9_019_schedule($s1, 1, '100', 'scheduled', 2501, 31, 'EUR')],
];
esc_p9_019_expect_throw(static fn (): array => $repository->snapshot(55, static function (array $locked): void {}), UnexpectedValueException::class, 'cross-currency schedule evidence fails closed');

// Cross-profile financial adjustment fails closed.
$GLOBALS['sc_test_result_queue'] = [
    $contract, $profile, [esc_p9_019_base('100')],
    [esc_p9_019_adjustment($additionUuid, 'addition', '1', 'active', 1601, 99)],
];
esc_p9_019_expect_throw(static fn (): array => $repository->snapshot(55, static function (array $locked): void {}), UnexpectedValueException::class, 'cross-profile adjustment evidence fails closed');

// 201st adjustment and 501st schedule sentinels fail closed before arithmetic.
$GLOBALS['sc_test_result_queue'] = [
    $contract, $profile, [esc_p9_019_base('100')], array_fill(0, 201, []),
];
esc_p9_019_expect_throw(static fn (): array => $repository->snapshot(55, static function (array $locked): void {}), RuntimeException::class, '201st adjustment sentinel fails closed');

$GLOBALS['sc_test_result_queue'] = [
    $contract, $profile, [esc_p9_019_base('100')], [], array_fill(0, 501, []),
];
esc_p9_019_expect_throw(static fn (): array => $repository->snapshot(55, static function (array $locked): void {}), RuntimeException::class, '501st schedule sentinel fails closed');

esc_p9_019_assert(str_contains($gateSource, 'enterprise_contract_financial_schedule_coverage_p9_019.php'), 'P9-019 regression is wired into global backend gate');

fwrite(STDOUT, "ESC P9-019 schedule coverage reconciliation passed ({$assertions} assertions).\n");
