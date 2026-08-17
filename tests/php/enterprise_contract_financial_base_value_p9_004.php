<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use RuntimeException;
use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0049EnterpriseContractFinancialBaseValueRevisions;
use SafeContracts\Finance\ContractFinancialBaseValueRevisionRepository;
use SafeContracts\Finance\Money;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use Throwable;
use UnexpectedValueException;

$assertions = 0;
function esc_p9_004_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @param class-string<Throwable> $expected */
function esc_p9_004_expect_throw(callable $callback, string $expected, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p9_004_assert($error instanceof $expected, $message . ' (received ' . $error::class . ')');
        return;
    }
    esc_p9_004_assert(false, $message . ' (no exception)');
}

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0049EnterpriseContractFinancialBaseValueRevisions.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialBaseValueRevisionRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialBaseValueRevisionService.php');
$routerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
$legacyContractSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Contracts/ContractService.php');
$pluginSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/safecontracts.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

// Migration0049 is additive and preserves the historical P9-003 mapping.
esc_p9_004_assert(Migrator::LATEST_VERSION === '1.48.0', 'P9-004 advances the Enterprise schema to 1.48.0');
esc_p9_004_assert(str_contains($migratorSource, "'1.47.0' => Migration0048EnterpriseContractFinancialCurrencyProfiles::class"), 'Migration0048 remains historically mapped to 1.47.0');
esc_p9_004_assert(str_contains($migratorSource, "'1.48.0' => Migration0049EnterpriseContractFinancialBaseValueRevisions::class"), 'Migration0049 is mapped to schema 1.48.0');
esc_p9_004_assert(str_contains($pluginSource, 'Version: 0.1.0'), 'database migration does not change the plugin release version');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0049EnterpriseContractFinancialBaseValueRevisions())->up($GLOBALS['wpdb']);
esc_p9_004_assert(count($GLOBALS['sc_test_dbdelta']) === 1, 'P9-004 emits exactly one additive table definition');
$schema = (string) ($GLOBALS['sc_test_dbdelta'][0] ?? '');
foreach ([
    'safecontracts_contract_financial_base_value_revisions',
    'tenant_id bigint(20) unsigned NOT NULL',
    'uuid char(36) NOT NULL',
    'contract_id bigint(20) unsigned NOT NULL',
    'financial_currency_profile_id bigint(20) unsigned NOT NULL',
    'revision_number bigint(20) unsigned NOT NULL',
    'amount decimal(20,4) NOT NULL',
    'currency_code char(3) NOT NULL',
    'created_by bigint(20) unsigned NOT NULL',
    'created_at datetime NOT NULL',
    'UNIQUE KEY financial_base_value_revision_uuid (uuid)',
    'UNIQUE KEY tenant_contract_base_value_revision (tenant_id, contract_id, revision_number)',
    'KEY tenant_contract_latest_base_value (tenant_id, contract_id, revision_number, id)',
] as $marker) {
    esc_p9_004_assert(str_contains($schema, $marker), 'P9-004 schema contains ' . $marker);
}
esc_p9_004_assert(! str_contains(strtoupper($migrationSource), 'DROP TABLE'), 'P9-004 migration is non-destructive');
esc_p9_004_assert(! str_contains(strtoupper($migrationSource), 'ALTER TABLE'), 'P9-004 migration does not rewrite legacy tables');
esc_p9_004_assert(! str_contains($schema, 'updated_at') && ! str_contains($schema, 'updated_by'), 'base-value revisions expose no mutable update audit columns');

// Repository is tenant-owned, append-only and concurrency-safe.
esc_p9_004_assert(str_contains($repositorySource, 'TenantContextStore::context()->requireTenantId()'), 'repository derives tenant identity from locked TenantContextStore');
esc_p9_004_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()'), 'repository fails closed unless core tenant enforcement is enabled');
esc_p9_004_assert(substr_count($repositorySource, 'FOR UPDATE') >= 3, 'contract, profile and latest revision are locked before append');
esc_p9_004_assert(str_contains($repositorySource, 'LEFT JOIN {$profiles} p'), 'latest read validates the stored revision against its current-tenant financial profile');
esc_p9_004_assert(str_contains($repositorySource, 'profile_match_id'), 'latest read fails closed when the stored financial profile does not match');
esc_p9_004_assert(str_contains($repositorySource, "c.status = 'draft' AND c.is_archived = 0"), 'atomic append revalidates draft/unarchived contract state');
esc_p9_004_assert(str_contains($repositorySource, 'p.contract_currency = %s'), 'atomic append revalidates the locked profile currency');
esc_p9_004_assert(str_contains($repositorySource, '$storedMoney->equals($money)'), 'same-amount retry is explicitly idempotent');
esc_p9_004_assert(str_contains($repositorySource, '$nextRevision = $currentRevision + 1'), 'changed amount advances revision monotonically');
esc_p9_004_assert(! str_contains(strtoupper($repositorySource), 'UPDATE '), 'base-value repository has no UPDATE path');
esc_p9_004_assert(! str_contains(strtoupper($repositorySource), 'DELETE FROM'), 'base-value repository has no DELETE path');
foreach (['ContractMoney', 'GeneralSettings', 'exchange_rate', 'currency_convert', 'safecontracts_contract_financial_items', 'safecontracts_contract_adjustments'] as $forbidden) {
    esc_p9_004_assert(! str_contains($repositorySource, $forbidden), 'base-value repository avoids legacy/FX coupling: ' . $forbidden);
}

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(7);
$repository = new ContractFinancialBaseValueRevisionRepository();

// Tenant-scoped read canonicalizes persisted Money and validates profile linkage.
$GLOBALS['sc_test_results'] = [[
    'id' => '80',
    'uuid' => '11111111-1111-4111-8111-111111111111',
    'contract_id' => '55',
    'financial_currency_profile_id' => '31',
    'revision_number' => '2',
    'amount' => '125.5',
    'currency_code' => 'usd',
    'created_by' => '42',
    'created_at' => '2026-08-17 12:00:00',
    'profile_match_id' => '31',
    'profile_currency' => 'USD',
]];
$latest = $repository->findLatestForContract(55);
esc_p9_004_assert(is_array($latest) && $latest['amount'] === '125.5000', 'persisted base value canonicalizes through P9-001 Money');
esc_p9_004_assert(is_array($latest) && $latest['currency_code'] === 'USD', 'persisted revision currency canonicalizes through P9-001 CurrencyCode');
esc_p9_004_assert(is_array($latest) && $latest['revision_number'] === 2, 'persisted revision number is normalized');
$readSql = (string) end($GLOBALS['sc_test_read_queries']);
esc_p9_004_assert(str_contains($readSql, 'tenant_id = 7') && str_contains($readSql, 'contract_id = 55'), 'latest read is scoped to current tenant and contract');
esc_p9_004_assert(str_contains($readSql, 'ORDER BY r.revision_number DESC, r.id DESC'), 'latest read is bounded and deterministic');

$GLOBALS['sc_test_results'] = [[
    'id' => '80',
    'uuid' => '11111111-1111-4111-8111-111111111111',
    'contract_id' => '55',
    'financial_currency_profile_id' => '31',
    'revision_number' => '2',
    'amount' => '125.5000',
    'currency_code' => 'USD',
    'created_by' => '42',
    'created_at' => '2026-08-17 12:00:00',
    'profile_match_id' => null,
    'profile_currency' => null,
]];
esc_p9_004_expect_throw(
    static fn (): ?array => $repository->findLatestForContract(55),
    UnexpectedValueException::class,
    'orphaned or cross-profile latest revision fails closed on read'
);

// First append locks parent/profile/latest and writes revision 1 in the profile currency.
$GLOBALS['sc_test_results'] = [];
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'status' => 'draft', 'is_archived' => '0']],
    [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']],
    [],
];
$GLOBALS['wpdb']->insert_id = 0;
$firstId = $repository->appendOrGetLatest(
    55,
    '22222222-2222-4222-8222-222222222222',
    Money::of('100', 'USD'),
    42
);
esc_p9_004_assert($firstId === 1001, 'first base-value append returns inserted revision identifier');
$firstSql = implode("\n", array_map('strval', $GLOBALS['sc_test_queries']));
esc_p9_004_assert(str_contains($firstSql, 'START TRANSACTION') && str_contains($firstSql, 'COMMIT'), 'first append is transactional');
esc_p9_004_assert(substr_count($firstSql, 'FOR UPDATE') >= 3, 'first append acquires all required row locks');
esc_p9_004_assert(str_contains($firstSql, "SELECT 7, '22222222-2222-4222-8222-222222222222', c.id, p.id, 1, '100.0000', p.contract_currency, 42, UTC_TIMESTAMP()"), 'first append persists revision 1 amount and server-derived profile currency');
esc_p9_004_assert(! str_contains($firstSql, 'UPDATE '), 'first append never rewrites an existing financial value');

// Exact same-amount retry returns the immutable latest revision with no INSERT.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'status' => 'draft', 'is_archived' => '0']],
    [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']],
    [[
        'id' => '1001',
        'uuid' => '22222222-2222-4222-8222-222222222222',
        'contract_id' => '55',
        'financial_currency_profile_id' => '31',
        'revision_number' => '1',
        'amount' => '100.0000',
        'currency_code' => 'USD',
        'created_by' => '42',
        'created_at' => '2026-08-17 12:00:00',
    ]],
];
$retryId = $repository->appendOrGetLatest(
    55,
    '33333333-3333-4333-8333-333333333333',
    Money::of('100.0000', 'USD'),
    42
);
esc_p9_004_assert($retryId === 1001, 'same-amount retry returns latest revision identifier');
$retrySql = implode("\n", array_map('strval', $GLOBALS['sc_test_queries']));
esc_p9_004_assert(! str_contains($retrySql, 'INSERT INTO'), 'same-amount retry creates no duplicate revision');
esc_p9_004_assert(str_contains($retrySql, 'COMMIT'), 'same-amount retry commits the locked idempotent read');

// Changed draft amount appends revision 2 instead of updating revision 1.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'status' => 'draft', 'is_archived' => '0']],
    [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']],
    [[
        'id' => '1001',
        'uuid' => '22222222-2222-4222-8222-222222222222',
        'contract_id' => '55',
        'financial_currency_profile_id' => '31',
        'revision_number' => '1',
        'amount' => '100.0000',
        'currency_code' => 'USD',
        'created_by' => '42',
        'created_at' => '2026-08-17 12:00:00',
    ]],
];
$GLOBALS['wpdb']->insert_id = 0;
$repository->appendOrGetLatest(
    55,
    '44444444-4444-4444-8444-444444444444',
    Money::of('125.50', 'USD'),
    42
);
$changedSql = implode("\n", array_map('strval', $GLOBALS['sc_test_queries']));
esc_p9_004_assert(str_contains($changedSql, "p.id, 2, '125.5000', p.contract_currency"), 'changed amount appends revision 2 with canonical Money amount');
esc_p9_004_assert(substr_count($changedSql, 'INSERT INTO') === 1, 'changed amount emits exactly one append INSERT');
esc_p9_004_assert(! str_contains($changedSql, 'UPDATE '), 'changed amount preserves prior revision immutably');

// Invalid financial/lifecycle/profile conditions fail closed.
$GLOBALS['sc_test_queries'] = [];
esc_p9_004_expect_throw(
    static fn (): int => $repository->appendOrGetLatest(55, '55555555-5555-4555-8555-555555555555', Money::of('-0.0001', 'USD'), 42),
    UnexpectedValueException::class,
    'negative base value fails before transaction'
);
esc_p9_004_assert($GLOBALS['sc_test_queries'] === [], 'negative amount performs no database write or transaction');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'status' => 'active', 'is_archived' => '0']],
];
esc_p9_004_expect_throw(
    static fn (): int => $repository->appendOrGetLatest(55, '66666666-6666-4666-8666-666666666666', Money::of('100', 'USD'), 42),
    RuntimeException::class,
    'non-draft contract cannot append a base-value revision'
);
esc_p9_004_assert(str_contains(implode("\n", array_map('strval', $GLOBALS['sc_test_queries'])), 'ROLLBACK'), 'non-draft append rolls back');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'status' => 'draft', 'is_archived' => '0']],
    [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'EUR']],
];
esc_p9_004_expect_throw(
    static fn (): int => $repository->appendOrGetLatest(55, '77777777-7777-4777-8777-777777777777', Money::of('100', 'USD'), 42),
    UnexpectedValueException::class,
    'money/profile currency mismatch fails closed'
);
esc_p9_004_assert(str_contains(implode("\n", array_map('strval', $GLOBALS['sc_test_queries'])), 'ROLLBACK'), 'profile currency mismatch rolls back');

// Service owns authorization/scope and derives currency server-side from P9-003.
esc_p9_004_assert(str_contains($serviceSource, 'public function append(int $contractId, mixed $amount): int'), 'service append command accepts contract and amount only');
esc_p9_004_assert(! str_contains($serviceSource, 'mixed $currency') && ! str_contains($serviceSource, 'string $currency'), 'service exposes no caller currency selector');
esc_p9_004_assert(str_contains($serviceSource, 'authorize(Capabilities::ACCESS)'), 'base-value reads require ACCESS');
esc_p9_004_assert(str_contains($serviceSource, 'authorize(Capabilities::EDIT_CONTRACTS)'), 'base-value mutation requires EDIT_CONTRACTS');
esc_p9_004_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'tenant role remains a narrowing authorization ceiling');
esc_p9_004_assert(str_contains($serviceSource, 'Capabilities::VIEW_ALL') && str_contains($serviceSource, 'Capabilities::VIEW_ASSIGNED'), 'service preserves existing Contract data scope');
esc_p9_004_assert(str_contains($serviceSource, 'assertDraftMutable($contract)'), 'service rejects archived/non-draft mutation before repository transaction');
esc_p9_004_assert(str_contains($serviceSource, 'ContractFinancialCurrencyProfileRepository'), 'service resolves the pre-existing P9-003 profile');
esc_p9_004_assert(str_contains($serviceSource, "CurrencyCode::from($profile['contract_currency'] ?? null)"), 'service derives currency only from persisted P9-003 profile');
esc_p9_004_assert(str_contains($serviceSource, 'Money::of($amount, $currency)'), 'service canonicalizes amount and derived currency through P9-001 Money');
esc_p9_004_assert(! str_contains($serviceSource, "'tenant_id'") && ! str_contains($serviceSource, '$tenantId'), 'service accepts no caller tenant identity');

// No legacy financial rewrite or public surface is introduced.
esc_p9_004_assert(! str_contains($repositorySource, 'ContractMoney') && ! str_contains($serviceSource, 'ContractMoney'), 'P9-004 does not reuse legacy ContractMoney');
esc_p9_004_assert(! str_contains($repositorySource, 'updateBaseValue') && ! str_contains($serviceSource, 'updateBaseValue'), 'P9-004 never calls legacy base-value mutation');
esc_p9_004_assert(str_contains($legacyContractSource, 'public function updateBaseValue'), 'legacy SafeContracts base-value path remains present and separate');
esc_p9_004_assert(! str_contains($routerSource, 'ContractFinancialBaseValueRevision'), 'P9-004 adds no REST route');
esc_p9_004_assert(str_contains($gateSource, 'enterprise_contract_financial_currency_profile_p9_003.php'), 'P9-003 regression remains in global gate');
esc_p9_004_assert(str_contains($gateSource, 'enterprise_contract_financial_base_value_p9_004.php'), 'P9-004 regression is wired into global backend gate');

fwrite(STDOUT, "P9-004 Enterprise Contract base-value revisions passed ({$assertions} assertions).\n");
