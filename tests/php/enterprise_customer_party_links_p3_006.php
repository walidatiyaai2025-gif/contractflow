<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0025EnterpriseCustomerPartyLinks;
use SafeContracts\Parties\CustomerPartyLinkService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p3_bridge_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p3_bridge_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p3_bridge_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ')');
        return;
    }
    esc_p3_bridge_assert(false, $message . ' (no exception)');
}

function esc_p3_bridge_actor(string $role = 'tenant_admin'): array
{
    return [[
        'id' => '1',
        'tenant_id' => '17',
        'user_id' => '42',
        'role_code' => $role,
        'is_owner' => '0',
    ]];
}

function esc_p3_bridge_customer(int $id = 10): array
{
    return [[
        'id' => (string) $id,
        'internal_code' => 'C-' . $id,
        'name' => 'Legacy Customer ' . $id,
        'contact_name' => '',
        'email' => '',
        'phone' => '',
        'notes' => '',
        'is_active' => '1',
    ]];
}

function esc_p3_bridge_party(int $id = 20): array
{
    return [[
        'id' => (string) $id,
        'uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'party_code' => 'P-' . $id,
        'display_name' => 'Party ' . $id,
        'legal_name' => '',
        'party_kind' => 'organization',
        'status' => 'active',
    ]];
}

function esc_p3_bridge_link(int $customerId = 10, int $partyId = 20): array
{
    return [[
        'id' => '7',
        'customer_id' => (string) $customerId,
        'party_id' => (string) $partyId,
        'provenance' => 'manual',
        'linked_by' => '42',
        'created_at' => '2026-08-16 20:00:00',
        'updated_at' => '2026-08-16 20:00:00',
    ]];
}

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0025EnterpriseCustomerPartyLinks.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Parties/CustomerPartyLinkRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Parties/CustomerPartyLinkService.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$customerRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Customers/CustomerRepository.php');
$contractRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Contracts/ContractRepository.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0025EnterpriseCustomerPartyLinks())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);
esc_p3_bridge_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_customer_party_links'), 'P3-006 creates a dedicated compatibility bridge table');
esc_p3_bridge_assert(str_contains($schema, 'tenant_id bigint(20) unsigned NOT NULL'), 'compatibility bridge tenant ownership is mandatory');
esc_p3_bridge_assert(str_contains($schema, 'customer_id bigint(20) unsigned NOT NULL'), 'bridge requires legacy Customer identity');
esc_p3_bridge_assert(str_contains($schema, 'party_id bigint(20) unsigned NOT NULL'), 'bridge requires Party identity');
esc_p3_bridge_assert(str_contains($schema, "provenance varchar(32) NOT NULL DEFAULT 'manual'"), 'bridge provenance is explicit and server-controlled');
esc_p3_bridge_assert(str_contains($schema, 'UNIQUE KEY tenant_customer (tenant_id, customer_id)'), 'one Customer can map to only one Party per tenant');
esc_p3_bridge_assert(str_contains($schema, 'UNIQUE KEY tenant_party (tenant_id, party_id)'), 'one Party can map to only one legacy Customer per tenant');
esc_p3_bridge_assert(str_contains($schema, 'KEY tenant_pair (tenant_id, customer_id, party_id)'), 'pair lookup index is tenant-first');
esc_p3_bridge_assert(version_compare(Migrator::LATEST_VERSION, '1.24.0', '>='), 'P3-006 schema remains reachable after future migrations');
esc_p3_bridge_assert(str_contains($migratorSource, "'1.24.0' => Migration0025EnterpriseCustomerPartyLinks::class"), 'P3-006 migration is registered specifically at schema 1.24.0');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::MANAGE_REFERENCE_DATA => true,
];
$service = new CustomerPartyLinkService();

CoreTenantEnforcement::disable();
TenantContextStore::reset();
esc_p3_bridge_throws(
    static fn () => $service->findByCustomer(10),
    RuntimeException::class,
    'Customer Party bridge fails closed outside Enterprise core tenant enforcement'
);

update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
esc_p3_bridge_throws(
    static fn () => $service->findByCustomer(10),
    RuntimeException::class,
    'Customer Party bridge requires locked tenant context'
);

TenantContextStore::context()->setTenantId(17);
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p3_bridge_actor(),
    esc_p3_bridge_customer(10),
    esc_p3_bridge_party(20),
    [['role_code' => 'customer']],
    [],
    [],
    esc_p3_bridge_link(10, 20),
    esc_p3_bridge_link(10, 20),
];
$service->link(10, 20);
esc_p3_bridge_assert(count($GLOBALS['sc_test_queries']) === 1, 'new bridge mapping performs exactly one mutation');
$insertSql = end($GLOBALS['sc_test_queries']);
$insertSql = is_string($insertSql) ? $insertSql : '';
esc_p3_bridge_assert(str_contains($insertSql, 'INSERT INTO wp_safecontracts_customer_party_links'), 'link writes only the dedicated bridge table');
esc_p3_bridge_assert(str_contains($insertSql, "VALUES (17, 10, 20, 'manual', 42"), 'link derives tenant/provenance/actor server-side');
esc_p3_bridge_assert(str_contains($insertSql, 'ON DUPLICATE KEY UPDATE id = id'), 'concurrent duplicate/conflict insert never rewrites the winning mapping');
esc_p3_bridge_assert(str_contains($GLOBALS['sc_test_read_queries'][1] ?? '', 'WHERE id = 10 AND tenant_id = 17'), 'legacy Customer ownership is verified in locked tenant');
esc_p3_bridge_assert(str_contains($GLOBALS['sc_test_read_queries'][2] ?? '', 'WHERE id = 20 AND tenant_id = 17'), 'Party ownership is verified in locked tenant');
esc_p3_bridge_assert(str_contains($GLOBALS['sc_test_read_queries'][3] ?? '', "tenant_id = 17 AND party_id = 20 AND status = 'active'"), 'Party customer role is verified in locked tenant');
esc_p3_bridge_assert(str_contains($GLOBALS['sc_test_read_queries'][6] ?? '', 'tenant_id = 17 AND customer_id = 10'), 'post-insert Customer direction is verified');
esc_p3_bridge_assert(str_contains($GLOBALS['sc_test_read_queries'][7] ?? '', 'tenant_id = 17 AND party_id = 20'), 'post-insert Party direction is verified');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p3_bridge_actor(),
    esc_p3_bridge_customer(10),
    esc_p3_bridge_party(20),
    [['role_code' => 'customer']],
    esc_p3_bridge_link(10, 20),
];
$service->link(10, 20);
esc_p3_bridge_assert($GLOBALS['sc_test_queries'] === [], 'repeating the exact mapping is idempotent before mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p3_bridge_actor(),
    esc_p3_bridge_customer(10),
    esc_p3_bridge_party(30),
    [['role_code' => 'customer']],
    esc_p3_bridge_link(10, 20),
];
esc_p3_bridge_throws(
    static fn () => $service->link(10, 30),
    InvalidArgumentException::class,
    'Customer already mapped to another Party fails closed'
);
esc_p3_bridge_assert($GLOBALS['sc_test_queries'] === [], 'Customer-side one-to-one conflict performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p3_bridge_actor(),
    esc_p3_bridge_customer(11),
    esc_p3_bridge_party(20),
    [['role_code' => 'customer']],
    [],
    esc_p3_bridge_link(10, 20),
];
esc_p3_bridge_throws(
    static fn () => $service->link(11, 20),
    InvalidArgumentException::class,
    'Party already mapped to another Customer fails closed'
);
esc_p3_bridge_assert($GLOBALS['sc_test_queries'] === [], 'Party-side one-to-one conflict performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p3_bridge_actor(),
    esc_p3_bridge_customer(10),
    esc_p3_bridge_party(20),
    [['role_code' => 'supplier']],
];
esc_p3_bridge_throws(
    static fn () => $service->link(10, 20),
    InvalidArgumentException::class,
    'Party without active customer role cannot be linked'
);
esc_p3_bridge_assert($GLOBALS['sc_test_queries'] === [], 'missing customer Party role performs no bridge mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p3_bridge_actor(),
    [],
];
esc_p3_bridge_throws(
    static fn () => $service->link(999, 20),
    InvalidArgumentException::class,
    'foreign Customer ID cannot be linked'
);
esc_p3_bridge_assert($GLOBALS['sc_test_queries'] === [], 'foreign Customer rejection performs no mutation');
esc_p3_bridge_assert(str_contains($GLOBALS['sc_test_read_queries'][1] ?? '', 'WHERE id = 999 AND tenant_id = 17'), 'foreign Customer lookup remains tenant-scoped');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p3_bridge_actor(),
    esc_p3_bridge_customer(10),
    [],
];
esc_p3_bridge_throws(
    static fn () => $service->link(10, 999),
    InvalidArgumentException::class,
    'foreign Party ID cannot be linked'
);
esc_p3_bridge_assert($GLOBALS['sc_test_queries'] === [], 'foreign Party rejection performs no mutation');
esc_p3_bridge_assert(str_contains($GLOBALS['sc_test_read_queries'][2] ?? '', 'WHERE id = 999 AND tenant_id = 17'), 'foreign Party lookup remains tenant-scoped');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p3_bridge_actor(),
    esc_p3_bridge_customer(10),
    esc_p3_bridge_party(20),
    [['role_code' => 'customer']],
    [],
    [],
    esc_p3_bridge_link(10, 30),
    [],
];
esc_p3_bridge_throws(
    static fn () => $service->link(10, 20),
    RuntimeException::class,
    'concurrent conflicting winner fails closed after atomic insert/no-op'
);
esc_p3_bridge_assert(count($GLOBALS['sc_test_queries']) === 1, 'concurrent conflict attempts only the no-rewrite bridge insert');

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = false;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p3_bridge_throws(
    static fn () => $service->link(10, 20),
    DomainException::class,
    'bridge mutations require MANAGE_REFERENCE_DATA global ceiling'
);
esc_p3_bridge_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'global write denial occurs before data access');

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = true;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p3_bridge_actor('manager')];
esc_p3_bridge_throws(
    static fn () => $service->link(10, 20),
    DomainException::class,
    'tenant manager role cannot bypass MANAGE_REFERENCE_DATA tenant-role ceiling'
);
esc_p3_bridge_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'tenant-role denial performs only actor membership read');
esc_p3_bridge_assert($GLOBALS['sc_test_queries'] === [], 'tenant-role denial performs no mutation');

$GLOBALS['sc_test_current_caps'][Capabilities::ACCESS] = false;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p3_bridge_throws(
    static fn () => $service->findByCustomer(10),
    DomainException::class,
    'bridge reads require ACCESS global ceiling'
);
esc_p3_bridge_assert($GLOBALS['sc_test_read_queries'] === [] && $GLOBALS['sc_test_queries'] === [], 'read capability denial occurs before data access');

esc_p3_bridge_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()'), 'bridge repository has explicit Enterprise-only boundary');
esc_p3_bridge_assert(str_contains($repositorySource, 'requireTenantId()'), 'bridge repository has no unscoped tenant fallback');
esc_p3_bridge_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'bridge service enforces tenant-role capability ceiling');
esc_p3_bridge_assert(str_contains($serviceSource, 'PartyRolePolicy::CUSTOMER'), 'bridge requires existing explicit customer Party role');
esc_p3_bridge_assert(! str_contains($serviceSource, '->assign('), 'bridge service does not silently assign Party business roles');
esc_p3_bridge_assert(! str_contains($repositorySource, 'safecontracts_customers'), 'bridge repository cannot mutate legacy Customer storage');
esc_p3_bridge_assert(! str_contains($repositorySource, 'safecontracts_contracts'), 'bridge repository cannot mutate Contract storage');
esc_p3_bridge_assert(! str_contains($repositorySource, 'safecontracts_party_roles'), 'bridge repository cannot mutate Party-role storage');
esc_p3_bridge_assert(str_contains($customerRepositorySource, 'safecontracts_customers'), 'legacy Customer repository remains the compatibility source');
esc_p3_bridge_assert(str_contains($contractRepositorySource, 'customer_id'), 'existing Contract repository continues to use legacy customer_id');
esc_p3_bridge_assert(! str_contains($migrationSource, 'ALTER TABLE'), 'P3-006 bridge is additive and does not rewrite legacy tables');

CoreTenantEnforcement::disable();
TenantContextStore::reset();

fwrite(STDOUT, "Enterprise Customer Party bridge P3-006 passed ({$assertions} assertions).\n");
