<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0050EnterpriseContractFinancialAdjustmentRevisions;
use SafeContracts\Finance\ContractFinancialAdjustmentPolicy;
use SafeContracts\Finance\ContractFinancialAdjustmentRevisionRepository;
use SafeContracts\Finance\Money;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;
function esc_p9_005_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @param class-string<Throwable> $expected */
function esc_p9_005_expect_throw(callable $callback, string $expected, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p9_005_assert($error instanceof $expected, $message . ' (received ' . $error::class . ')');
        return;
    }
    esc_p9_005_assert(false, $message . ' (no exception)');
}

function esc_p9_005_row(
    string $lineUuid,
    int $revisionNumber,
    string $kind = 'addition',
    string $description = 'Mobilization',
    string $amount = '100.0000',
    string $currency = 'USD',
    string $state = 'active',
    int $id = 1001,
    int $profileId = 31
): array {
    return [
        'id' => (string) $id,
        'revision_uuid' => sprintf('20000000-0000-4000-8000-%012x', $id),
        'contract_id' => '55',
        'financial_currency_profile_id' => (string) $profileId,
        'line_uuid' => $lineUuid,
        'revision_number' => (string) $revisionNumber,
        'adjustment_kind' => $kind,
        'description' => $description,
        'amount' => $amount,
        'currency_code' => $currency,
        'line_state' => $state,
        'created_by' => '42',
        'created_at' => '2026-08-17 14:00:00',
    ];
}

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0050EnterpriseContractFinancialAdjustmentRevisions.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialAdjustmentPolicy.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialAdjustmentRevisionRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialAdjustmentRevisionService.php');
$routerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
$legacyRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Contracts/ContractRepository.php');
$legacyServiceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Contracts/ContractService.php');
$pluginSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/safecontracts.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

// Schema and historical migration boundaries.
esc_p9_005_assert(version_compare(Migrator::LATEST_VERSION, '1.49.0', '>='), 'current Enterprise schema is at or beyond the P9-005 1.49.0 boundary');
esc_p9_005_assert(str_contains($migratorSource, "'1.48.0' => Migration0049EnterpriseContractFinancialBaseValueRevisions::class"), 'Migration0049 remains historically mapped to 1.48.0');
esc_p9_005_assert(str_contains($migratorSource, "'1.49.0' => Migration0050EnterpriseContractFinancialAdjustmentRevisions::class"), 'Migration0050 is mapped to 1.49.0');
esc_p9_005_assert(str_contains($pluginSource, 'Version: 0.1.0'), 'schema migration does not alter plugin release version');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0050EnterpriseContractFinancialAdjustmentRevisions())->up($GLOBALS['wpdb']);
esc_p9_005_assert(count($GLOBALS['sc_test_dbdelta']) === 1, 'P9-005 emits one additive table definition');
$schema = (string) ($GLOBALS['sc_test_dbdelta'][0] ?? '');
foreach ([
    'safecontracts_contract_financial_adjustment_revisions',
    'tenant_id bigint(20) unsigned NOT NULL',
    'revision_uuid char(36) NOT NULL',
    'contract_id bigint(20) unsigned NOT NULL',
    'financial_currency_profile_id bigint(20) unsigned NOT NULL',
    'line_uuid char(36) NOT NULL',
    'revision_number bigint(20) unsigned NOT NULL',
    'adjustment_kind varchar(16) NOT NULL',
    'description varchar(191) NOT NULL',
    'amount decimal(20,4) NOT NULL',
    'currency_code char(3) NOT NULL',
    "line_state varchar(16) NOT NULL DEFAULT 'active'",
    'created_by bigint(20) unsigned NOT NULL',
    'created_at datetime NOT NULL',
    'UNIQUE KEY financial_adjustment_revision_uuid (revision_uuid)',
    'UNIQUE KEY tenant_contract_adjustment_revision (tenant_id, contract_id, line_uuid, revision_number)',
    'KEY tenant_contract_adjustment_latest (tenant_id, contract_id, line_uuid, revision_number, id)',
] as $marker) {
    esc_p9_005_assert(str_contains($schema, $marker), 'P9-005 schema contains ' . $marker);
}
esc_p9_005_assert(! str_contains(strtoupper($migrationSource), 'DROP TABLE'), 'P9-005 migration is non-destructive');
esc_p9_005_assert(! str_contains(strtoupper($migrationSource), 'ALTER TABLE'), 'P9-005 does not rewrite legacy tables');
esc_p9_005_assert(! str_contains($schema, 'updated_at') && ! str_contains($schema, 'updated_by'), 'adjustment revisions have no mutable update columns');

// Bounded declarative policy.
esc_p9_005_assert(ContractFinancialAdjustmentPolicy::normalizeKind(' Addition ') === 'addition', 'addition kind canonicalizes');
esc_p9_005_assert(ContractFinancialAdjustmentPolicy::normalizeKind('DISCOUNT') === 'discount', 'discount kind canonicalizes');
esc_p9_005_expect_throw(static fn (): string => ContractFinancialAdjustmentPolicy::normalizeKind('tax'), InvalidArgumentException::class, 'unsupported kind fails closed');
esc_p9_005_assert(ContractFinancialAdjustmentPolicy::normalizeState('VOIDED') === 'voided', 'voided state canonicalizes');
esc_p9_005_expect_throw(static fn (): string => ContractFinancialAdjustmentPolicy::normalizeState('deleted'), InvalidArgumentException::class, 'unsupported state fails closed');
esc_p9_005_assert(ContractFinancialAdjustmentPolicy::MAX_LINES === 200, 'adjustment identities are bounded to 200 per Contract');
esc_p9_005_assert(ContractFinancialAdjustmentPolicy::normalizeDescription(' Fee ') === 'Fee', 'description canonicalizes');
esc_p9_005_expect_throw(static fn (): string => ContractFinancialAdjustmentPolicy::normalizeDescription(''), InvalidArgumentException::class, 'blank description fails closed');
esc_p9_005_assert(ContractFinancialAdjustmentPolicy::normalizeUuid('AAAAAAAA-AAAA-4AAA-8AAA-AAAAAAAAAAAA') === 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'UUIDv4 canonicalizes');
esc_p9_005_expect_throw(static fn (): string => ContractFinancialAdjustmentPolicy::normalizeUuid('not-a-uuid'), InvalidArgumentException::class, 'invalid UUID fails closed');

// Repository architecture and lock ordering.
esc_p9_005_assert(str_contains($repositorySource, 'TenantContextStore::context()->requireTenantId()'), 'repository derives tenant only from locked context');
esc_p9_005_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()'), 'repository requires core tenant enforcement');
esc_p9_005_assert(str_contains($repositorySource, 'COUNT(DISTINCT line_uuid)'), 'create path enforces bounded stable line identities');
esc_p9_005_assert(str_contains($repositorySource, 'ContractFinancialAdjustmentPolicy::MAX_LINES + 1'), 'current list uses a 201st overflow sentinel');
esc_p9_005_assert(str_contains($repositorySource, 'NOT EXISTS ('), 'current list selects only latest line revisions');
esc_p9_005_assert(str_contains($repositorySource, 'LEFT JOIN {$profiles} p'), 'current list validates profile linkage');
esc_p9_005_assert(substr_count($repositorySource, 'FOR UPDATE') >= 4, 'mutations use Contract/profile/line locking');
$voidStart = strpos($repositorySource, 'public function voidLine');
$voidEnd = strpos($repositorySource, 'private function lockContractAndProfile', $voidStart);
$voidSource = substr($repositorySource, $voidStart, $voidEnd - $voidStart);
esc_p9_005_assert(strpos($voidSource, '$this->lockDraftContract') < strpos($voidSource, '$this->lockProfile'), 'void locks Contract before profile');
esc_p9_005_assert(strpos($voidSource, '$this->lockProfile') < strpos($voidSource, '$this->lockLatestLine'), 'void locks profile before latest line');
esc_p9_005_assert(str_contains($repositorySource, '$storedMoney->equals($money)'), 'exact active revision retry is idempotent');
esc_p9_005_assert(str_contains($repositorySource, 'STATE_VOIDED'), 'voided line state is explicit');
esc_p9_005_assert(str_contains($repositorySource, 'cannot be revised or reactivated'), 'voided lines cannot be reactivated');
esc_p9_005_assert(! str_contains(strtoupper($repositorySource), 'UPDATE '), 'adjustment history has no update statement');
esc_p9_005_assert(! str_contains(strtoupper($repositorySource), 'DELETE FROM'), 'adjustment history has no delete statement');
foreach (['ContractMoney', 'GeneralSettings', 'safecontracts_contract_adjustments', 'exchange_rate', 'currency_convert'] as $forbidden) {
    esc_p9_005_assert(! str_contains($repositorySource, $forbidden), 'P9-005 repository avoids legacy/FX coupling: ' . $forbidden);
}

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(7);
$repository = new ContractFinancialAdjustmentRevisionRepository();
$lineUuid = '11111111-1111-4111-8111-111111111111';

// Bounded current read returns one normalized latest snapshot per line and validates profile identity.
$row = esc_p9_005_row($lineUuid, 2, 'ADDITION', '  Mobilization  ', '125.5', 'usd', 'ACTIVE', 1001, 31);
$row['profile_match_id'] = '31';
$row['profile_currency'] = 'USD';
$GLOBALS['sc_test_results'] = [$row];
$current = $repository->listCurrentForContract(55);
esc_p9_005_assert(count($current) === 1, 'current adjustment list returns one latest row');
esc_p9_005_assert($current[0]['adjustment_kind'] === 'addition', 'stored adjustment kind canonicalizes');
esc_p9_005_assert($current[0]['description'] === 'Mobilization', 'stored description canonicalizes');
esc_p9_005_assert($current[0]['amount'] === '125.5000' && $current[0]['currency_code'] === 'USD', 'stored adjustment Money canonicalizes');
esc_p9_005_assert($current[0]['line_state'] === 'active', 'stored adjustment state canonicalizes');
$listSql = (string) end($GLOBALS['sc_test_read_queries']);
esc_p9_005_assert(str_contains($listSql, 'r.tenant_id = 7') && str_contains($listSql, 'r.contract_id = 55'), 'current list is tenant and Contract scoped');
esc_p9_005_assert(str_contains($listSql, 'LIMIT 201'), 'current list query includes the overflow sentinel');

$orphan = esc_p9_005_row($lineUuid, 2);
$orphan['profile_match_id'] = null;
$orphan['profile_currency'] = null;
$GLOBALS['sc_test_results'] = [$orphan];
esc_p9_005_expect_throw(static fn (): array => $repository->listCurrentForContract(55), UnexpectedValueException::class, 'orphaned profile current row fails closed');

$overflowRows = [];
for ($i = 1; $i <= 201; $i++) {
    $overflowRow = esc_p9_005_row(sprintf('10000000-0000-4000-8000-%012x', $i), 1, 'addition', 'Line ' . $i, '1.0000', 'USD', 'active', 2000 + $i, 31);
    $overflowRow['profile_match_id'] = '31';
    $overflowRow['profile_currency'] = 'USD';
    $overflowRows[] = $overflowRow;
}
$GLOBALS['sc_test_results'] = $overflowRows;
esc_p9_005_expect_throw(static fn (): array => $repository->listCurrentForContract(55), RuntimeException::class, '201 current line identities fail closed instead of truncating');

// Create revision 1 with Contract -> Profile -> line-identity locking and atomic guarded insert.
$GLOBALS['sc_test_results'] = [];
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'status' => 'draft', 'is_archived' => '0']],
    [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']],
    [['total' => '0']],
    [],
];
$GLOBALS['wpdb']->insert_id = 0;
$created = $repository->createLine(
    55,
    'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    'addition',
    'Mobilization',
    Money::of('100', 'USD'),
    42
);
esc_p9_005_assert($created === 1001, 'first adjustment creation returns inserted revision id');
$createWrites = implode("\n", array_map('strval', $GLOBALS['sc_test_queries']));
$createReads = implode("\n", array_map('strval', $GLOBALS['sc_test_read_queries']));
esc_p9_005_assert(str_contains($createWrites, 'START TRANSACTION') && str_contains($createWrites, 'COMMIT'), 'adjustment create is transactional');
esc_p9_005_assert(strpos($createReads, 'safecontracts_contracts') < strpos($createReads, 'safecontracts_contract_financial_currency_profiles'), 'create locks Contract before profile');
esc_p9_005_assert(str_contains($createReads, 'line_uuid = \'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa\'') && str_contains($createReads, 'FOR UPDATE'), 'create proves generated line identity unused under lock');
esc_p9_005_assert(str_contains($createWrites, "'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 1, 'addition', 'Mobilization', '100.0000', p.contract_currency, 'active', 42"), 'create persists revision 1 with server-derived profile currency');

// Exact active revision retry is idempotent.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'status' => 'draft', 'is_archived' => '0']],
    [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']],
    [esc_p9_005_row($lineUuid, 1)],
];
$retry = $repository->reviseLine(
    55,
    $lineUuid,
    'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
    'addition',
    'Mobilization',
    Money::of('100.0000', 'USD'),
    42
);
esc_p9_005_assert($retry === 1001, 'exact active revision retry returns existing revision');
$retryWrites = implode("\n", array_map('strval', $GLOBALS['sc_test_queries']));
esc_p9_005_assert(! str_contains($retryWrites, 'INSERT INTO'), 'exact revision retry emits no insert');
esc_p9_005_assert(str_contains($retryWrites, 'COMMIT'), 'exact revision retry commits locked idempotent read');

// Changed draft revision appends revision 2.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'status' => 'draft', 'is_archived' => '0']],
    [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']],
    [esc_p9_005_row($lineUuid, 1)],
];
$GLOBALS['wpdb']->insert_id = 0;
$repository->reviseLine(
    55,
    $lineUuid,
    'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
    'discount',
    'Commercial correction',
    Money::of('25', 'USD'),
    42
);
$reviseWrites = implode("\n", array_map('strval', $GLOBALS['sc_test_queries']));
esc_p9_005_assert(str_contains($reviseWrites, "'11111111-1111-4111-8111-111111111111', 2, 'discount', 'Commercial correction', '25.0000'"), 'changed adjustment appends revision 2 snapshot');
esc_p9_005_assert(substr_count($reviseWrites, 'INSERT INTO') === 1, 'changed adjustment emits exactly one append insert');
esc_p9_005_assert(! str_contains($reviseWrites, 'UPDATE '), 'changed adjustment does not rewrite prior revision');

// Voiding appends a terminal snapshot; repeating void is idempotent.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'status' => 'draft', 'is_archived' => '0']],
    [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']],
    [esc_p9_005_row($lineUuid, 1)],
];
$GLOBALS['wpdb']->insert_id = 0;
$repository->voidLine(55, $lineUuid, 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee', 42);
$voidWrites = implode("\n", array_map('strval', $GLOBALS['sc_test_queries']));
$voidReads = implode("\n", array_map('strval', $GLOBALS['sc_test_read_queries']));
esc_p9_005_assert(strpos($voidReads, 'safecontracts_contracts') < strpos($voidReads, 'safecontracts_contract_financial_currency_profiles'), 'void uses canonical Contract -> Profile lock order');
esc_p9_005_assert(strpos($voidReads, 'safecontracts_contract_financial_currency_profiles') < strpos($voidReads, 'safecontracts_contract_financial_adjustment_revisions'), 'void locks profile before latest line');
esc_p9_005_assert(str_contains($voidWrites, "'voided', 42"), 'void appends terminal voided revision');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'status' => 'draft', 'is_archived' => '0']],
    [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']],
    [esc_p9_005_row($lineUuid, 2, 'addition', 'Mobilization', '100.0000', 'USD', 'voided', 1002)],
];
$voidRetry = $repository->voidLine(55, $lineUuid, 'ffffffff-ffff-4fff-8fff-ffffffffffff', 42);
esc_p9_005_assert($voidRetry === 1002, 'repeated void returns existing terminal revision');
esc_p9_005_assert(! str_contains(implode("\n", array_map('strval', $GLOBALS['sc_test_queries'])), 'INSERT INTO'), 'repeated void writes no duplicate terminal revision');

// Voided lines cannot be revised/reactivated.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'status' => 'draft', 'is_archived' => '0']],
    [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']],
    [esc_p9_005_row($lineUuid, 2, 'addition', 'Mobilization', '100.0000', 'USD', 'voided', 1002)],
];
esc_p9_005_expect_throw(
    static fn (): int => $repository->reviseLine(55, $lineUuid, '12345678-1234-4234-8234-123456789abc', 'addition', 'Reactivate', Money::of('100', 'USD'), 42),
    RuntimeException::class,
    'voided line revision fails closed'
);
esc_p9_005_assert(str_contains(implode("\n", array_map('strval', $GLOBALS['sc_test_queries'])), 'ROLLBACK'), 'voided line revision rolls back');

// Negative/currency/lifecycle failures are closed and transactional.
$GLOBALS['sc_test_queries'] = [];
esc_p9_005_expect_throw(
    static fn (): int => $repository->createLine(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'addition', 'Bad', Money::of('-0.0001', 'USD'), 42),
    InvalidArgumentException::class,
    'negative adjustment amount fails before transaction'
);
esc_p9_005_assert($GLOBALS['sc_test_queries'] === [], 'negative amount performs no transaction');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'status' => 'active', 'is_archived' => '0']],
];
esc_p9_005_expect_throw(
    static fn (): int => $repository->createLine(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'addition', 'Late', Money::of('1', 'USD'), 42),
    RuntimeException::class,
    'non-draft Contract cannot create adjustment'
);
esc_p9_005_assert(str_contains(implode("\n", array_map('strval', $GLOBALS['sc_test_queries'])), 'ROLLBACK'), 'non-draft create rolls back');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'status' => 'draft', 'is_archived' => '0']],
    [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'EUR']],
];
esc_p9_005_expect_throw(
    static fn (): int => $repository->createLine(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'addition', 'Mismatch', Money::of('1', 'USD'), 42),
    UnexpectedValueException::class,
    'profile currency mismatch fails closed'
);
esc_p9_005_assert(str_contains(implode("\n", array_map('strval', $GLOBALS['sc_test_queries'])), 'ROLLBACK'), 'profile currency mismatch rolls back');

// Service owns authorization/scope and caller supplies no tenant or currency.
esc_p9_005_assert(str_contains($serviceSource, 'authorize(Capabilities::ACCESS)'), 'adjustment reads require ACCESS');
esc_p9_005_assert(substr_count($serviceSource, 'authorize(Capabilities::EDIT_CONTRACTS)') >= 3, 'all adjustment mutations require EDIT_CONTRACTS');
esc_p9_005_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'tenant role narrows WordPress capability grants');
esc_p9_005_assert(str_contains($serviceSource, 'Capabilities::VIEW_ALL') && str_contains($serviceSource, 'Capabilities::VIEW_ASSIGNED'), 'service preserves Contract data scope');
esc_p9_005_assert(str_contains($serviceSource, 'assertDraftMutable($contract)'), 'service enforces draft mutation boundary');
esc_p9_005_assert(str_contains($serviceSource, 'ContractFinancialCurrencyProfileRepository'), 'service derives currency from P9-003 profile');
esc_p9_005_assert(str_contains($serviceSource, 'Money::of($amount, $currency)'), 'service creates P9-001 Money from server-derived currency');
esc_p9_005_assert(! str_contains($serviceSource, 'mixed $currency') && ! str_contains($serviceSource, 'string $currency'), 'service exposes no caller currency input');
esc_p9_005_assert(! str_contains($serviceSource, "'tenant_id'") && ! str_contains($serviceSource, '$tenantId'), 'service exposes no caller tenant identity');
esc_p9_005_assert(str_contains($serviceSource, 'random_bytes(16)') && str_contains($serviceSource, 'wp_generate_uuid4'), 'line/revision identities are server generated');

// Legacy and public surfaces remain independent.
esc_p9_005_assert(str_contains($legacyRepositorySource, 'safecontracts_contract_adjustments'), 'legacy adjustment table remains present and separate');
esc_p9_005_assert(str_contains($legacyServiceSource, "['addition', 'discount']"), 'legacy adjustment service remains unchanged');
esc_p9_005_assert(! str_contains($repositorySource, 'safecontracts_contract_adjustments') && ! str_contains($serviceSource, 'safecontracts_contract_adjustments'), 'P9-005 never reads or writes legacy adjustments');
esc_p9_005_assert(! str_contains($repositorySource, 'SUM(') && ! str_contains($serviceSource, 'reconcile'), 'P9-005 does not prematurely define authoritative net reconciliation');
esc_p9_005_assert(! str_contains($routerSource, 'ContractFinancialAdjustmentRevision'), 'P9-005 exposes no REST route');
esc_p9_005_assert(str_contains($gateSource, 'enterprise_contract_financial_base_value_p9_004.php'), 'P9-004 regression remains wired');
esc_p9_005_assert(str_contains($gateSource, 'enterprise_contract_financial_adjustments_p9_005.php'), 'P9-005 regression is wired into global backend gate');

fwrite(STDOUT, "P9-005 Enterprise Contract financial adjustment revisions passed ({$assertions} assertions).\n");
