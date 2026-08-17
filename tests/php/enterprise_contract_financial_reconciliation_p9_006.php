<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Finance\ContractFinancialAdjustmentPolicy;
use SafeContracts\Finance\ContractFinancialReconciliationRepository;
use SafeContracts\Finance\ContractFinancialReconciliationService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;
function esc_p9_006_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @param class-string<Throwable> $expected */
function esc_p9_006_expect_throw(callable $callback, string $expected, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p9_006_assert($error instanceof $expected, $message . ' (received ' . $error::class . ')');
        return;
    }
    esc_p9_006_assert(false, $message . ' (no exception)');
}

/** @return array<string,mixed> */
function esc_p9_006_base(string $amount = '100.0000', string $currency = 'USD', int $profileId = 31): array
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
function esc_p9_006_adjustment(
    string $lineUuid,
    string $kind,
    string $amount,
    string $state = 'active',
    string $currency = 'USD',
    int $id = 1001,
    int $profileId = 31
): array {
    return [
        'id' => (string) $id,
        'revision_uuid' => sprintf('20000000-0000-4000-8000-%012x', $id),
        'contract_id' => '55',
        'financial_currency_profile_id' => (string) $profileId,
        'line_uuid' => $lineUuid,
        'revision_number' => '1',
        'adjustment_kind' => $kind,
        'description' => 'Reconciliation line ' . $id,
        'amount' => $amount,
        'currency_code' => $currency,
        'line_state' => $state,
        'created_by' => '42',
    ];
}

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialReconciliationRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialReconciliationService.php');
$routerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

// P9-006 is code-only and preserves the historical P9-005 schema boundary.
esc_p9_006_assert(version_compare(Migrator::LATEST_VERSION, '1.49.0', '>='), 'current Enterprise schema is at or beyond the P9-006 1.49.0 boundary');
esc_p9_006_assert(str_contains($migratorSource, "'1.49.0' => Migration0050EnterpriseContractFinancialAdjustmentRevisions::class"), 'Migration0050 remains the historical 1.49.0 schema mapping');

// Architecture: one Contract-first read transaction, bounded latest lines, no writes or legacy coupling.
esc_p9_006_assert(str_contains($repositorySource, "query('START TRANSACTION')"), 'reconciliation opens one transaction');
esc_p9_006_assert(str_contains($repositorySource, 'LIMIT 2 FOR UPDATE'), 'the exact current-tenant Contract is locked first');
esc_p9_006_assert(str_contains($repositorySource, '$authorizeLockedContract($contract)'), 'locked Contract authorization callback is explicit');
esc_p9_006_assert(str_contains($repositorySource, 'ContractFinancialAdjustmentPolicy::MAX_LINES + 1'), 'adjustment snapshot uses a 201st overflow sentinel');
esc_p9_006_assert(str_contains($repositorySource, 'NOT EXISTS ('), 'only latest adjustment revisions participate');
esc_p9_006_assert(str_contains($repositorySource, 'ORDER BY revision_number DESC, id DESC') && str_contains($repositorySource, 'LIMIT 1'), 'latest base revision is selected deterministically');
$financialWritePattern = '/\b(?:INSERT\s+INTO|DELETE\s+FROM|UPDATE\s+[A-Za-z0-9_`{}.]+\s+SET)\b/i';
esc_p9_006_assert(! preg_match($financialWritePattern, $repositorySource), 'reconciliation repository contains no financial write statement');
esc_p9_006_assert(! preg_match($financialWritePattern, $serviceSource), 'reconciliation service contains no write statement');
foreach (['safecontracts_contracts.base_value', 'safecontracts_contract_adjustments', 'financialTotals', 'ContractMoney', 'exchange_rate', 'currency_convert'] as $forbidden) {
    esc_p9_006_assert(! str_contains($repositorySource, $forbidden) && ! str_contains($serviceSource, $forbidden), 'P9-006 avoids legacy/FX coupling: ' . $forbidden);
}
esc_p9_006_assert(str_contains($serviceSource, 'authorize(Capabilities::ACCESS)'), 'reconciliation requires ACCESS');
esc_p9_006_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'tenant role narrows the global capability grant');
esc_p9_006_assert(str_contains($serviceSource, 'Capabilities::VIEW_ALL') && str_contains($serviceSource, 'Capabilities::VIEW_ASSIGNED'), 'locked Contract scope preserves VIEW_ALL / own VIEW_ASSIGNED');
esc_p9_006_assert(str_contains($serviceSource, '$gross->subtract($discounts)'), 'net uses signed P9-001 Money subtraction');
esc_p9_006_assert(! str_contains($routerSource, 'ContractFinancialReconciliation'), 'P9-006 exposes no REST route');

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(7);
$repository = new ContractFinancialReconciliationRepository();

$contract = [['id' => '55', 'accountant_user_id' => '42', 'status' => 'active', 'is_archived' => '0']];
$profile = [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']];

// Authorization callback runs while Contract is locked and before any financial read.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contract, $profile, [esc_p9_006_base()], []];
$authorized = false;
$raw = $repository->snapshot(55, static function (array $lockedContract) use (&$authorized): void {
    esc_p9_006_assert((int) ($lockedContract['id'] ?? 0) === 55, 'authorization receives the locked Contract row');
    esc_p9_006_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'authorization executes before profile/base/adjustment reads');
    esc_p9_006_assert(str_contains((string) $GLOBALS['sc_test_read_queries'][0], 'FOR UPDATE'), 'authorization boundary is protected by Contract FOR UPDATE');
    $authorized = true;
});
esc_p9_006_assert($authorized, 'locked Contract authorization callback executes');
esc_p9_006_assert($raw['base']['amount'] === '100.0000' && $raw['profile']['currency'] === 'USD', 'repository returns canonical base/profile snapshot');
esc_p9_006_assert(implode("\n", $GLOBALS['sc_test_queries']) === "START TRANSACTION\nCOMMIT", 'successful snapshot performs transaction control only');
$repoReads = implode("\n", array_map('strval', $GLOBALS['sc_test_read_queries']));
esc_p9_006_assert(str_contains($repoReads, 'r.tenant_id = 7') && str_contains($repoReads, 'r.contract_id = 55') && str_contains($repoReads, 'LIMIT 201'), 'adjustment read is tenant/Contract scoped and bounded');

// Missing base value is never interpreted as zero.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contract, $profile, []];
esc_p9_006_expect_throw(
    static fn (): array => $repository->snapshot(55, static function (array $lockedContract): void {}),
    RuntimeException::class,
    'missing persisted P9-004 base value fails closed'
);
esc_p9_006_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'ROLLBACK'), 'missing base value rolls back the snapshot transaction');

// Corrupt base identity fails closed.
$badBase = esc_p9_006_base();
$badBase['uuid'] = 'not-a-uuid';
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contract, $profile, [$badBase]];
esc_p9_006_expect_throw(
    static fn (): array => $repository->snapshot(55, static function (array $lockedContract): void {}),
    UnexpectedValueException::class,
    'corrupt P9-004 base revision metadata fails closed'
);

// Cross-currency adjustment state fails closed.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contract,
    $profile,
    [esc_p9_006_base()],
    [esc_p9_006_adjustment('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'addition', '1.0000', 'active', 'EUR')],
];
esc_p9_006_expect_throw(
    static fn (): array => $repository->snapshot(55, static function (array $lockedContract): void {}),
    UnexpectedValueException::class,
    'cross-currency latest adjustment fails closed'
);
esc_p9_006_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'ROLLBACK'), 'cross-currency state rolls back');

// Duplicate latest identities fail closed even if storage integrity is externally corrupted.
$duplicateUuid = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
$GLOBALS['sc_test_result_queue'] = [
    $contract,
    $profile,
    [esc_p9_006_base()],
    [
        esc_p9_006_adjustment($duplicateUuid, 'addition', '1.0000', 'active', 'USD', 1101),
        esc_p9_006_adjustment($duplicateUuid, 'addition', '2.0000', 'active', 'USD', 1102),
    ],
];
esc_p9_006_expect_throw(
    static fn (): array => $repository->snapshot(55, static function (array $lockedContract): void {}),
    UnexpectedValueException::class,
    'duplicate latest adjustment identities fail closed'
);

// The 201st current line is an overflow sentinel, never a silent truncation.
$overflow = [];
for ($i = 1; $i <= ContractFinancialAdjustmentPolicy::MAX_LINES + 1; $i++) {
    $overflow[] = esc_p9_006_adjustment(
        sprintf('30000000-0000-4000-8000-%012x', $i),
        'addition',
        '1.0000',
        'active',
        'USD',
        2000 + $i
    );
}
$GLOBALS['sc_test_result_queue'] = [$contract, $profile, [esc_p9_006_base()], $overflow];
esc_p9_006_expect_throw(
    static fn (): array => $repository->snapshot(55, static function (array $lockedContract): void {}),
    RuntimeException::class,
    '201st current adjustment line fails closed'
);

// End-to-end service uses ACCESS + tenant membership, Money arithmetic and preserves signed net.
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
];
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '9', 'tenant_id' => '7', 'user_id' => '42', 'role_code' => 'member', 'is_owner' => '0']],
    $contract,
    $profile,
    [esc_p9_006_base('100.0000')],
    [
        esc_p9_006_adjustment('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'addition', '25.0000', 'active', 'USD', 3001),
        esc_p9_006_adjustment('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'discount', '150.0000', 'active', 'USD', 3002),
        esc_p9_006_adjustment('cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'addition', '999.0000', 'voided', 'USD', 3003),
    ],
];
$service = new ContractFinancialReconciliationService();
$snapshot = $service->snapshot(55);
esc_p9_006_assert($snapshot['currency'] === 'USD', 'service returns authoritative P9-003 currency');
esc_p9_006_assert($snapshot['base_value'] === '100.0000', 'service returns canonical base value');
esc_p9_006_assert($snapshot['additions_total'] === '25.0000', 'active additions sum through Money');
esc_p9_006_assert($snapshot['discounts_total'] === '150.0000', 'active discounts sum through Money');
esc_p9_006_assert($snapshot['gross_value'] === '125.0000', 'gross equals base plus active additions');
esc_p9_006_assert($snapshot['net_value'] === '-25.0000', 'negative net is returned explicitly and is not clamped');
esc_p9_006_assert($snapshot['active_addition_count'] === 1 && $snapshot['active_discount_count'] === 1, 'active line counts are authoritative');
esc_p9_006_assert($snapshot['voided_line_count'] === 1, 'voided latest lines contribute zero and are counted');
esc_p9_006_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'service reconciliation performs no INSERT');
esc_p9_006_assert(str_contains($gateSource, 'enterprise_contract_financial_reconciliation_p9_006.php'), 'P9-006 regression is wired into the global backend gate');

fwrite(STDOUT, "P9-006 Enterprise Contract financial reconciliation passed ({$assertions} assertions).\n");
