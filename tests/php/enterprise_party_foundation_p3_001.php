<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0020EnterpriseParties;
use SafeContracts\Parties\PartyPolicy;
use SafeContracts\Parties\PartyService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p3_party_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p3_party_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p3_party_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ')');
        return;
    }
    esc_p3_party_assert(false, $message . ' (no exception)');
}

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0020EnterpriseParties.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Parties/PartyRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Parties/PartyService.php');
$customerRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Customers/CustomerRepository.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0020EnterpriseParties())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);
esc_p3_party_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_parties'), 'P3-001 creates a dedicated Enterprise Party table');
esc_p3_party_assert(str_contains($schema, 'tenant_id bigint(20) unsigned NOT NULL'), 'Party ownership is mandatory at schema level');
esc_p3_party_assert(str_contains($schema, 'uuid char(36) NOT NULL'), 'Party carries stable UUID identity');
esc_p3_party_assert(str_contains($schema, 'UNIQUE KEY uuid (uuid)'), 'Party UUID is globally unique');
esc_p3_party_assert(str_contains($schema, 'UNIQUE KEY tenant_code (tenant_id, party_code)'), 'Party code is unique only inside a tenant');
esc_p3_party_assert(str_contains($schema, 'KEY tenant_status_name (tenant_id, status, display_name, id)'), 'Party listing index is tenant-first');
esc_p3_party_assert(str_contains($schema, 'KEY tenant_kind_name (tenant_id, party_kind, display_name, id)'), 'Party kind lookup index is tenant-first');
esc_p3_party_assert(str_contains($schema, 'KEY tenant_registration (tenant_id, country_code, registration_number)'), 'registration lookup is tenant-first');
esc_p3_party_assert(! str_contains($migrationSource, 'safecontracts_customers'), 'P3-001 migration does not rewrite legacy customers');
esc_p3_party_assert(version_compare(Migrator::LATEST_VERSION, '1.19.0', '>='), 'P3-001 Party schema remains reachable after later Enterprise migrations');

esc_p3_party_assert(PartyPolicy::kinds() === ['organization', 'individual', 'government', 'other'], 'Party kind policy models intrinsic identity only');
esc_p3_party_assert(! PartyPolicy::isKind('customer') && ! PartyPolicy::isKind('supplier') && ! PartyPolicy::isKind('vendor'), 'business roles are not overloaded into party_kind');
esc_p3_party_assert(PartyPolicy::statuses() === ['active', 'inactive'], 'Party status policy is explicit');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::MANAGE_REFERENCE_DATA => true,
];
$service = new PartyService();

CoreTenantEnforcement::disable();
TenantContextStore::reset();
esc_p3_party_throws(
    static fn () => $service->find(10),
    RuntimeException::class,
    'Party repository fails closed when Enterprise core tenant enforcement is disabled'
);

update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
esc_p3_party_throws(
    static fn () => $service->find(10),
    RuntimeException::class,
    'Party repository requires a locked tenant context'
);

TenantContextStore::context()->setTenantId(17);
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_results'] = [];
$partyId = $service->save([
    'display_name' => 'Acme Trading',
    'party_kind' => 'organization',
    'country_code' => 'kw',
    'email' => 'ops@example.test',
    'metadata' => ['source' => 'enterprise'],
]);
esc_p3_party_assert($partyId > 0, 'Party create returns the persisted identifier');
$createSql = end($GLOBALS['sc_test_queries']);
$createSql = is_string($createSql) ? $createSql : '';
esc_p3_party_assert(str_contains($createSql, 'INSERT INTO wp_safecontracts_parties'), 'Party create uses the dedicated table');
esc_p3_party_assert(str_contains($createSql, 'VALUES (17,'), 'Party create derives tenant ownership from locked server context');
esc_p3_party_assert(str_contains($createSql, ', NULL,'), 'empty tenant-local party code is stored as NULL, allowing multiple uncoded parties');
esc_p3_party_assert(str_contains($createSql, "'KW'"), 'country code is normalized');
esc_p3_party_assert(str_contains($createSql, 'source') && str_contains($createSql, 'enterprise'), 'bounded metadata key/value is JSON encoded before persistence');
esc_p3_party_assert(preg_match("/'[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}'/", $createSql) === 1, 'Party UUID is generated server-side as UUIDv4');

$GLOBALS['sc_test_queries'] = [];
esc_p3_party_throws(
    static fn () => $service->save(['tenant_id' => 999, 'display_name' => 'Spoof', 'party_kind' => 'organization']),
    InvalidArgumentException::class,
    'client mutation data cannot supply tenant_id'
);
esc_p3_party_throws(
    static fn () => $service->save(['uuid' => '00000000-0000-4000-8000-000000000000', 'display_name' => 'Spoof', 'party_kind' => 'organization']),
    InvalidArgumentException::class,
    'client mutation data cannot supply UUID identity'
);
esc_p3_party_assert($GLOBALS['sc_test_queries'] === [], 'spoofed reserved Party fields fail before persistence');
esc_p3_party_throws(
    static fn () => $service->save(['id' => 0, 'display_name' => 'Invalid ID', 'party_kind' => 'organization']),
    InvalidArgumentException::class,
    'supplied non-positive Party ID cannot silently turn into create'
);
esc_p3_party_throws(
    static fn () => $service->save(['display_name' => 'Role misuse', 'party_kind' => 'customer']),
    InvalidArgumentException::class,
    'customer cannot be deliberately stored as an intrinsic Party kind'
);

$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_results'] = [['id' => '55', 'display_name' => 'Tenant 17 Party']];
$found = $service->find(55);
esc_p3_party_assert(($found['id'] ?? null) === '55', 'Party find returns current-tenant row');
$findSql = end($GLOBALS['sc_test_read_queries']);
$findSql = is_string($findSql) ? $findSql : '';
esc_p3_party_assert(str_contains($findSql, 'WHERE id = 55 AND tenant_id = 17'), 'Party find always scopes object ID by tenant');

$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_results'] = [];
$service->search('', 500, -50);
$searchSql = end($GLOBALS['sc_test_read_queries']);
$searchSql = is_string($searchSql) ? $searchSql : '';
esc_p3_party_assert(str_contains($searchSql, 'WHERE tenant_id = 17'), 'Party search always starts from tenant predicate');
esc_p3_party_assert(str_contains($searchSql, 'LIMIT 100 OFFSET 0'), 'Party search pagination is bounded');

$GLOBALS['sc_test_read_queries'] = [];
$service->search('Acme', 20, 10);
$filteredSearchSql = end($GLOBALS['sc_test_read_queries']);
$filteredSearchSql = is_string($filteredSearchSql) ? $filteredSearchSql : '';
esc_p3_party_assert(str_contains($filteredSearchSql, "tenant_id = 17 AND (display_name LIKE '%Acme%'"), 'Party text search remains inside tenant scope');

TenantContextStore::reset();
TenantContextStore::context()->setTenantId(18);
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [[]];
esc_p3_party_throws(
    static fn () => $service->save([
        'id' => 55,
        'display_name' => 'Foreign update',
        'party_kind' => 'organization',
    ]),
    InvalidArgumentException::class,
    'foreign tenant object ID cannot be updated when current-tenant lookup misses'
);
esc_p3_party_assert($GLOBALS['sc_test_queries'] === [], 'foreign tenant miss performs no update mutation');
$foreignLookup = end($GLOBALS['sc_test_read_queries']);
$foreignLookup = is_string($foreignLookup) ? $foreignLookup : '';
esc_p3_party_assert(str_contains($foreignLookup, 'WHERE id = 55 AND tenant_id = 18'), 'foreign object lookup remains tenant-scoped');

TenantContextStore::reset();
TenantContextStore::context()->setTenantId(17);
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [[['id' => '55', 'display_name' => 'Tenant 17 Party']]];
$updatedId = $service->save([
    'id' => 55,
    'party_code' => 'ACME',
    'display_name' => 'Acme Trading Updated',
    'party_kind' => 'organization',
    'status' => 'inactive',
]);
esc_p3_party_assert($updatedId === 55, 'current-tenant Party update preserves object ID');
$updateSql = end($GLOBALS['sc_test_queries']);
$updateSql = is_string($updateSql) ? $updateSql : '';
esc_p3_party_assert(str_contains($updateSql, 'WHERE id = 55 AND tenant_id = 17'), 'Party update always includes tenant predicate');
esc_p3_party_assert(! str_contains($updateSql, 'tenant_id = 999'), 'Party update cannot redirect tenant ownership');

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = false;
esc_p3_party_throws(
    static fn () => $service->save(['display_name' => 'Denied', 'party_kind' => 'organization']),
    DomainException::class,
    'Party mutation requires tenant-aware MANAGE_REFERENCE_DATA capability'
);
$GLOBALS['sc_test_current_caps'][Capabilities::ACCESS] = false;
esc_p3_party_throws(
    static fn () => $service->find(55),
    DomainException::class,
    'Party reads require tenant-aware ACCESS capability'
);

esc_p3_party_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()'), 'repository has explicit Enterprise-only enforcement boundary');
esc_p3_party_assert(str_contains($repositorySource, 'requireTenantId()'), 'repository never falls back to an unscoped Party query');
esc_p3_party_assert(! str_contains($repositorySource, "tenantId === null ? ''"), 'repository contains no legacy unscoped tenant fallback');
esc_p3_party_assert(str_contains($serviceSource, "'tenant_id'") === false, 'tenant_id is absent from supported Party mutation fields');
esc_p3_party_assert(str_contains($serviceSource, "'uuid'") === false, 'uuid is absent from supported Party mutation fields');
esc_p3_party_assert(str_contains($customerRepositorySource, 'safecontracts_customers'), 'legacy Customer repository remains present and separate');

CoreTenantEnforcement::disable();
TenantContextStore::reset();

fwrite(STDOUT, "Enterprise Party foundation P3-001 passed ({$assertions} assertions).\n");
