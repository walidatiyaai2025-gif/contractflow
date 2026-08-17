<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use RuntimeException;
use SafeContracts\Database\Migrator;
use SafeContracts\Finance\TenantBaseCurrencyRepository;
use SafeContracts\Tenancy\TenantContext;
use Throwable;
use UnexpectedValueException;

$assertions = 0;
function esc_p9_002_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @param class-string<Throwable> $expected */
function esc_p9_002_expect_throw(callable $callback, string $expected, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p9_002_assert($error instanceof $expected, $message . ' (received ' . $error::class . ')');
        return;
    }
    esc_p9_002_assert(false, $message . ' (no exception)');
}

$root = dirname(__DIR__, 2);
$source = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/TenantBaseCurrencyRepository.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$tenantMigrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0016EnterpriseTenancy.php');
$tenantDirectorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Tenancy/TenantDirectoryRepository.php');
$tenantsControllerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/TenantsController.php');
$routerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
$generalSettingsSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Settings/GeneralSettings.php');
$legacyMoneySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Contracts/ContractMoney.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

// P9-002 deliberately reuses the P1 tenant metadata and consumes no schema of its own.
esc_p9_002_assert(version_compare(Migrator::LATEST_VERSION, '1.46.0', '>='), 'Enterprise schema remains at or beyond the P9-002 baseline');
esc_p9_002_assert(str_contains($migratorSource, "'1.46.0' => Migration0047EnterpriseContractDeliverables::class"), 'P9-002 preserves the historical schema version immediately preceding persisted Finance profiles');
esc_p9_002_assert(str_contains($tenantMigrationSource, "default_currency char(3) NOT NULL DEFAULT 'USD'"), 'existing tenant default_currency remains the persisted source');
esc_p9_002_assert(! str_contains($tenantMigrationSource, 'base_currency') && ! str_contains($tenantMigrationSource, 'reporting_currency'), 'P9-002 adds no duplicate tenant financial currency column');

$repository = new TenantBaseCurrencyRepository();
$missingContext = new TenantContext();
esc_p9_002_expect_throw(static fn () => $repository->resolve($missingContext), RuntimeException::class, 'missing tenant context fails closed before lookup');
esc_p9_002_assert($GLOBALS['sc_test_read_queries'] === [], 'missing tenant context performs no database read');

$tenant7 = new TenantContext();
$tenant7->setTenantId(7);
$GLOBALS['sc_test_results'] = [['id' => '7', 'default_currency' => 'kwd']];
$kwd = $repository->resolve($tenant7);
esc_p9_002_assert($kwd->value() === 'KWD', 'stored tenant currency canonicalizes through CurrencyCode');
$query = (string) end($GLOBALS['sc_test_read_queries']);
esc_p9_002_assert(str_contains($query, 'SELECT id, default_currency'), 'lookup reads only tenant identity and currency metadata');
esc_p9_002_assert(str_contains($query, 'WHERE id = 7'), 'lookup is constrained to the locked tenant id');
esc_p9_002_assert(str_contains($query, "status = 'active'"), 'lookup requires active tenant status');
esc_p9_002_assert(str_contains($query, 'LIMIT 1'), 'lookup is bounded to one tenant row');
esc_p9_002_assert(! str_contains($query, 'tenant_memberships'), 'Finance does not infer tenant identity from membership scans');

$GLOBALS['sc_test_results'] = [['id' => '7', 'default_currency' => 'uSd']];
esc_p9_002_assert($repository->resolve($tenant7)->value() === 'USD', 'mixed-case persisted code canonicalizes deterministically');

$GLOBALS['sc_test_results'] = [];
esc_p9_002_expect_throw(static fn () => $repository->resolve($tenant7), RuntimeException::class, 'missing or inactive tenant row fails closed');

$GLOBALS['sc_test_results'] = [['id' => '8', 'default_currency' => 'USD']];
esc_p9_002_expect_throw(static fn () => $repository->resolve($tenant7), RuntimeException::class, 'mismatched tenant row is rejected even if database adapter returns it');

$GLOBALS['sc_test_results'] = [
    ['id' => '7', 'default_currency' => 'USD'],
    ['id' => '8', 'default_currency' => 'KWD'],
];
esc_p9_002_expect_throw(static fn () => $repository->resolve($tenant7), RuntimeException::class, 'unexpected multi-row result fails closed rather than considering another tenant');

foreach (['', 'US', 'USDX', 'U$D', 'ÜSD', 'د.ك'] as $invalidCurrency) {
    $GLOBALS['sc_test_results'] = [['id' => '7', 'default_currency' => $invalidCurrency]];
    esc_p9_002_expect_throw(static fn () => $repository->resolve($tenant7), UnexpectedValueException::class, 'malformed stored tenant currency fails closed: ' . $invalidCurrency);
}
$GLOBALS['sc_test_results'] = [['id' => '7']];
esc_p9_002_expect_throw(static fn () => $repository->resolve($tenant7), UnexpectedValueException::class, 'missing stored tenant currency fails closed');
$GLOBALS['sc_test_results'] = [['id' => '7', 'default_currency' => null]];
esc_p9_002_expect_throw(static fn () => $repository->resolve($tenant7), UnexpectedValueException::class, 'null stored tenant currency fails closed');

// Resolver is strictly read-only and has no legacy/global/default fallback or conversion path.
foreach (['get_option(', 'update_option(', 'GeneralSettings', "'USD'", 'exchange_rate', 'currency_convert', 'wp_remote_', 'curl_', 'current_time(', 'gmdate('] as $forbidden) {
    esc_p9_002_assert(! str_contains($source, $forbidden), 'Finance tenant currency resolver contains no forbidden fallback/dependency: ' . $forbidden);
}
foreach (['->query(', '->insert(', '->update(', '->delete(', 'INSERT ', 'UPDATE ', 'DELETE '] as $writeToken) {
    esc_p9_002_assert(! str_contains($source, $writeToken), 'Finance tenant currency resolver is read-only: ' . $writeToken);
}
esc_p9_002_assert(str_contains($source, 'TenantContext $context'), 'resolver requires an explicit TenantContext object');
esc_p9_002_assert(str_contains($source, '$context->requireTenantId()'), 'resolver derives authority only from locked tenant context');
esc_p9_002_assert(str_contains($source, 'CurrencyCode::from'), 'resolver delegates currency validation to P9-001 CurrencyCode');
esc_p9_002_assert(! str_contains($source, 'TenantContextStore'), 'resolver does not hide tenant context behind mutable global request state');

// Existing tenant-directory REST contract and legacy financial semantics remain intact.
esc_p9_002_assert(str_contains($tenantDirectorySource, "'default_currency' =>"), 'tenant directory continues returning default_currency metadata');
esc_p9_002_assert(str_contains($tenantsControllerSource, "register_rest_route(Router::NAMESPACE, '/tenants'"), 'existing /tenants route remains registered');
esc_p9_002_assert(! str_contains($tenantsControllerSource, 'TenantBaseCurrencyRepository'), 'P9-002 does not change the tenant REST payload implementation');
esc_p9_002_assert(! str_contains($routerSource, 'TenantBaseCurrencyRepository'), 'P9-002 exposes no new REST route/runtime surface');
esc_p9_002_assert(str_contains($generalSettingsSource, "'currency_code'"), 'legacy General Settings currency remains present and separate');
esc_p9_002_assert(str_contains($legacyMoneySource, 'final class ContractMoney'), 'legacy ContractMoney remains unchanged and separate');
esc_p9_002_assert(str_contains($gateSource, 'enterprise_money_p9_001.php'), 'P9-001 Money regression remains in global gate');
esc_p9_002_assert(str_contains($gateSource, 'enterprise_tenant_base_currency_p9_002.php'), 'P9-002 regression is wired into global gate');

fwrite(STDOUT, "P9-002 tenant base currency policy passed ({$assertions} assertions).\n");
