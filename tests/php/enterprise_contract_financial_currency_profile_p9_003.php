<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use RuntimeException;
use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0048EnterpriseContractFinancialCurrencyProfiles;
use SafeContracts\Finance\ContractFinancialCurrencyProfileRepository;
use SafeContracts\Finance\CurrencyCode;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use Throwable;

$assertions = 0;
function esc_p9_003_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @param class-string<Throwable> $expected */
function esc_p9_003_expect_throw(callable $callback, string $expected, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p9_003_assert($error instanceof $expected, $message . ' (received ' . $error::class . ')');
        return;
    }
    esc_p9_003_assert(false, $message . ' (no exception)');
}

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0048EnterpriseContractFinancialCurrencyProfiles.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialCurrencyProfileRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialCurrencyProfileService.php');
$routerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
$legacyMoneySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Contracts/ContractMoney.php');
$generalSettingsSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Settings/GeneralSettings.php');
$pluginSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/safecontracts.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

// Migration0048 is additive and advances only the ESC database schema.
esc_p9_003_assert(Migrator::LATEST_VERSION === '1.47.0', 'P9-003 advances the Enterprise schema to 1.47.0');
esc_p9_003_assert(str_contains($migratorSource, "'1.46.0' => Migration0047EnterpriseContractDeliverables::class"), 'Migration0047 remains historically mapped to 1.46.0');
esc_p9_003_assert(str_contains($migratorSource, "'1.47.0' => Migration0048EnterpriseContractFinancialCurrencyProfiles::class"), 'Migration0048 is mapped to schema 1.47.0');
esc_p9_003_assert(str_contains($pluginSource, 'Version: 0.1.0'), 'database migration does not change the plugin release version');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0048EnterpriseContractFinancialCurrencyProfiles())->up($GLOBALS['wpdb']);
esc_p9_003_assert(count($GLOBALS['sc_test_dbdelta']) === 1, 'P9-003 emits exactly one additive table definition');
$schema = (string) ($GLOBALS['sc_test_dbdelta'][0] ?? '');
foreach ([
    'safecontracts_contract_financial_currency_profiles',
    'tenant_id bigint(20) unsigned NOT NULL',
    'uuid char(36) NOT NULL',
    'contract_id bigint(20) unsigned NOT NULL',
    'contract_currency char(3) NOT NULL',
    'tenant_base_currency_snapshot char(3) NOT NULL',
    'created_by bigint(20) unsigned NOT NULL',
    'created_at datetime NOT NULL',
    'UNIQUE KEY financial_currency_profile_uuid (uuid)',
    'UNIQUE KEY tenant_contract_financial_currency_profile (tenant_id, contract_id)',
    'KEY tenant_contract_currency (tenant_id, contract_currency, contract_id)',
] as $marker) {
    esc_p9_003_assert(str_contains($schema, $marker), 'P9-003 schema contains ' . $marker);
}
esc_p9_003_assert(! str_contains(strtoupper($migrationSource), 'DROP TABLE'), 'P9-003 migration is non-destructive');
esc_p9_003_assert(! str_contains(strtoupper($migrationSource), 'ALTER TABLE'), 'P9-003 migration does not rewrite legacy tables');
esc_p9_003_assert(! str_contains($schema, 'updated_at') && ! str_contains($schema, 'updated_by'), 'financial currency profile has no mutable update audit columns');

// Repository is tenant-owned, immutable and concurrency-safe.
esc_p9_003_assert(str_contains($repositorySource, 'TenantContextStore::context()->requireTenantId()'), 'repository derives tenant identity from locked TenantContextStore');
esc_p9_003_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()'), 'repository fails closed unless core tenant enforcement is enabled');
esc_p9_003_assert(! str_contains($repositorySource, 'public function tenantId('), 'repository exposes no caller tenant selector');
esc_p9_003_assert(substr_count($repositorySource, 'tenant_id = %d') >= 4, 'repository scopes reads and locks by tenant');
esc_p9_003_assert(str_contains($repositorySource, 'START TRANSACTION'), 'profile creation is transactional');
esc_p9_003_assert(substr_count($repositorySource, 'FOR UPDATE') >= 2, 'contract and existing profile are locked before persistence');
esc_p9_003_assert(str_contains($repositorySource, 'is_archived = 0'), 'repository rejects archived contracts under the write lock');
esc_p9_003_assert(str_contains($repositorySource, 'count($existingRows) > 1'), 'unexpected duplicate profile cardinality fails closed');
esc_p9_003_assert(str_contains($repositorySource, '$storedCurrency->equals($contractCurrency)'), 'exact contract-currency retry is explicitly distinguished from conflict');
esc_p9_003_assert(str_contains($repositorySource, 'tenant_base_currency_snapshot'), 'repository persists the tenant base-currency snapshot');
esc_p9_003_assert(! str_contains(strtoupper($repositorySource), 'UPDATE '), 'financial currency profiles have no update path');
esc_p9_003_assert(! str_contains(strtoupper($repositorySource), 'DELETE FROM'), 'financial currency profiles have no physical delete path');
foreach (['ContractMoney', 'GeneralSettings', 'safecontracts_payments', 'exchange_rate', 'currency_convert'] as $forbidden) {
    esc_p9_003_assert(! str_contains($repositorySource, $forbidden), 'profile repository avoids legacy/FX coupling: ' . $forbidden);
}

// Exercise tenant-scoped read and immutable/idempotent persistence using the project DB stub.
$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(7);
$repository = new ContractFinancialCurrencyProfileRepository();

$GLOBALS['sc_test_results'] = [[
    'id' => '31',
    'uuid' => '11111111-1111-4111-8111-111111111111',
    'contract_id' => '55',
    'contract_currency' => 'eur',
    'tenant_base_currency_snapshot' => 'kwd',
    'created_by' => '42',
    'created_at' => '2026-08-17 10:00:00',
]];
$found = $repository->findForContract(55);
esc_p9_003_assert(is_array($found) && $found['contract_currency'] === 'EUR', 'persisted contract currency is canonicalized on read');
esc_p9_003_assert(is_array($found) && $found['tenant_base_currency_snapshot'] === 'KWD', 'persisted tenant snapshot is canonicalized on read');
$readSql = (string) end($GLOBALS['sc_test_read_queries']);
esc_p9_003_assert(str_contains($readSql, 'tenant_id = 7') && str_contains($readSql, 'contract_id = 55'), 'profile read is constrained to current tenant and contract');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55']],
    [],
];
$GLOBALS['wpdb']->insert_id = 0;
$createdId = $repository->createOrGet(
    55,
    '22222222-2222-4222-8222-222222222222',
    CurrencyCode::from('eur'),
    CurrencyCode::from('kwd'),
    42
);
esc_p9_003_assert($createdId === 1001, 'first profile creation returns the inserted profile identifier');
$insertSql = '';
foreach ($GLOBALS['sc_test_queries'] as $sql) {
    if (str_starts_with(ltrim((string) $sql), 'INSERT INTO')) {
        $insertSql = (string) $sql;
        break;
    }
}
esc_p9_003_assert($insertSql !== '', 'first profile creation emits one INSERT');
esc_p9_003_assert(str_contains($insertSql, "VALUES (7, '22222222-2222-4222-8222-222222222222', 55, 'EUR', 'KWD', 42, UTC_TIMESTAMP())"), 'INSERT persists tenant, explicit currency, original base snapshot and actor');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55']],
    [[
        'id' => '1001',
        'uuid' => '22222222-2222-4222-8222-222222222222',
        'contract_id' => '55',
        'contract_currency' => 'EUR',
        'tenant_base_currency_snapshot' => 'KWD',
        'created_by' => '42',
        'created_at' => '2026-08-17 10:00:00',
    ]],
];
$retryId = $repository->createOrGet(
    55,
    '33333333-3333-4333-8333-333333333333',
    CurrencyCode::from('EUR'),
    CurrencyCode::from('USD'),
    42
);
esc_p9_003_assert($retryId === 1001, 'same-currency retry returns the existing profile identifier');
$retrySql = implode("\n", array_map('strval', $GLOBALS['sc_test_queries']));
esc_p9_003_assert(! str_contains($retrySql, 'INSERT INTO'), 'same-currency retry does not insert another profile');
esc_p9_003_assert(! str_contains($retrySql, 'UPDATE '), 'same-currency retry does not rewrite the original KWD snapshot when tenant base is now USD');
esc_p9_003_assert(str_contains($retrySql, 'COMMIT'), 'same-currency retry commits the read-locked idempotent transaction');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55']],
    [[
        'id' => '1001',
        'uuid' => '22222222-2222-4222-8222-222222222222',
        'contract_id' => '55',
        'contract_currency' => 'EUR',
        'tenant_base_currency_snapshot' => 'KWD',
        'created_by' => '42',
        'created_at' => '2026-08-17 10:00:00',
    ]],
];
esc_p9_003_expect_throw(
    static fn (): int => $repository->createOrGet(
        55,
        '44444444-4444-4444-8444-444444444444',
        CurrencyCode::from('USD'),
        CurrencyCode::from('KWD'),
        42
    ),
    RuntimeException::class,
    'conflicting contract-currency retry fails closed'
);
$conflictSql = implode("\n", array_map('strval', $GLOBALS['sc_test_queries']));
esc_p9_003_assert(str_contains($conflictSql, 'ROLLBACK'), 'conflicting retry rolls back its transaction');
esc_p9_003_assert(! str_contains($conflictSql, 'INSERT INTO'), 'conflicting retry writes no replacement profile');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [[]];
esc_p9_003_expect_throw(
    static fn (): int => $repository->createOrGet(
        99,
        '55555555-5555-4555-8555-555555555555',
        CurrencyCode::from('USD'),
        CurrencyCode::from('KWD'),
        42
    ),
    RuntimeException::class,
    'missing/current-tenant-mismatched contract fails under the parent row lock'
);
esc_p9_003_assert(str_contains(implode("\n", array_map('strval', $GLOBALS['sc_test_queries'])), 'ROLLBACK'), 'missing parent contract rolls back without persistence');

// Service preserves Contract authorization/scope and snapshots the P9-002 tenant currency only for first creation.
esc_p9_003_assert(str_contains($serviceSource, 'authorize(Capabilities::ACCESS)'), 'profile reads require ACCESS');
esc_p9_003_assert(str_contains($serviceSource, 'authorize(Capabilities::EDIT_CONTRACTS)'), 'profile creation requires EDIT_CONTRACTS');
esc_p9_003_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'tenant role remains a narrowing authorization ceiling');
esc_p9_003_assert(str_contains($serviceSource, 'Capabilities::VIEW_ALL') && str_contains($serviceSource, 'Capabilities::VIEW_ASSIGNED'), 'service preserves Contract VIEW_ALL / own VIEW_ASSIGNED scope');
esc_p9_003_assert(str_contains($serviceSource, '$accountantUserId === get_current_user_id()'), 'assigned Contract scope is restricted to the current accountant');
esc_p9_003_assert(str_contains($serviceSource, 'assertContractMutable($contract)'), 'service rejects archived Contract creation attempts');
esc_p9_003_assert(str_contains($serviceSource, 'CurrencyCode::from($contractCurrency)'), 'caller contract currency is canonicalized through P9-001 CurrencyCode');
esc_p9_003_assert(str_contains($serviceSource, 'TenantBaseCurrencyRepository'), 'tenant base snapshot is resolved through P9-002 policy');
esc_p9_003_assert(str_contains($serviceSource, 'resolve(TenantContextStore::context())'), 'base currency authority uses the locked current TenantContext');
esc_p9_003_assert(strpos($serviceSource, '$existing = $this->repository->findForContract') < strpos($serviceSource, '$tenantBaseCurrency = $this->tenantBaseCurrencyRepository->resolve'), 'existing immutable profile is checked before consulting the tenant current base currency');
esc_p9_003_assert(! str_contains($serviceSource, "'tenant_id'") && ! str_contains($serviceSource, '$tenantId'), 'service accepts no caller tenant identity');
esc_p9_003_assert(str_contains($serviceSource, 'random_bytes(16)') && str_contains($serviceSource, 'wp_generate_uuid4'), 'profile UUID has WordPress and cryptographic fallback generation');

// Foundation exposes no new runtime/public surface and does not reinterpret legacy money configuration.
esc_p9_003_assert(! str_contains($routerSource, 'ContractFinancialCurrencyProfile'), 'P9-003 adds no REST route');
esc_p9_003_assert(str_contains($legacyMoneySource, 'final class ContractMoney'), 'legacy ContractMoney remains present and separate');
esc_p9_003_assert(str_contains($generalSettingsSource, "'currency_code'"), 'legacy General Settings currency remains present and separate');
esc_p9_003_assert(str_contains($gateSource, 'enterprise_money_p9_001.php'), 'P9-001 regression remains in global gate');
esc_p9_003_assert(str_contains($gateSource, 'enterprise_tenant_base_currency_p9_002.php'), 'P9-002 regression remains in global gate');
esc_p9_003_assert(str_contains($gateSource, 'enterprise_contract_financial_currency_profile_p9_003.php'), 'P9-003 regression is wired into global backend gate');

fwrite(STDOUT, "P9-003 Contract financial currency profile foundation passed ({$assertions} assertions).\n");
