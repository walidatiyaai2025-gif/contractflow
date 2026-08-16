<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0022EnterprisePartyRelationships;
use SafeContracts\Parties\PartyRelationshipPolicy;
use SafeContracts\Parties\PartyRelationshipService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p3_rel_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p3_rel_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p3_rel_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ')');
        return;
    }
    esc_p3_rel_assert(false, $message . ' (no exception)');
}

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0022EnterprisePartyRelationships.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Parties/PartyRelationshipRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Parties/PartyRelationshipService.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0022EnterprisePartyRelationships())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);
esc_p3_rel_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_party_relationships'), 'P3-003 creates a dedicated Party relationship table');
esc_p3_rel_assert(str_contains($schema, 'tenant_id bigint(20) unsigned NOT NULL'), 'relationship ownership is mandatory');
esc_p3_rel_assert(str_contains($schema, 'source_party_id bigint(20) unsigned NOT NULL'), 'relationship source Party is mandatory');
esc_p3_rel_assert(str_contains($schema, 'target_party_id bigint(20) unsigned NOT NULL'), 'relationship target Party is mandatory');
esc_p3_rel_assert(str_contains($schema, 'relationship_code varchar(64) NOT NULL'), 'relationship type uses a stable machine code');
esc_p3_rel_assert(str_contains($schema, 'UNIQUE KEY tenant_relationship (tenant_id, source_party_id, target_party_id, relationship_code)'), 'relationship identity is unique within tenant/source/target/type');
esc_p3_rel_assert(str_contains($schema, 'KEY tenant_source_status (tenant_id, source_party_id, status, relationship_code, target_party_id)'), 'outgoing relationship lookup is tenant-first');
esc_p3_rel_assert(str_contains($schema, 'KEY tenant_target_status (tenant_id, target_party_id, status, relationship_code, source_party_id)'), 'incoming relationship lookup is tenant-first');
esc_p3_rel_assert(str_contains($schema, 'KEY tenant_type_status (tenant_id, relationship_code, status, source_party_id, target_party_id)'), 'type lookup is tenant-first');
esc_p3_rel_assert(version_compare(Migrator::LATEST_VERSION, '1.21.0', '>='), 'P3-003 schema remains reachable after future migrations');
esc_p3_rel_assert(str_contains($migratorSource, "'1.21.0' => Migration0022EnterprisePartyRelationships::class"), 'P3-003 migration is registered specifically at schema 1.21.0');

$definitions = PartyRelationshipPolicy::definitions();
esc_p3_rel_assert(isset($definitions['parent_of'], $definitions['represents'], $definitions['guarantees_for'], $definitions['contact_for'], $definitions['owns'], $definitions['affiliated_with']), 'baseline relationship policy is explicit');
esc_p3_rel_assert(PartyRelationshipPolicy::inverseCode('parent_of') === 'child_of', 'directional relationship exposes derived inverse label code');
esc_p3_rel_assert(PartyRelationshipPolicy::inverseCode('represents') === 'represented_by', 'represents relationship exposes derived inverse label code');
esc_p3_rel_assert(PartyRelationshipPolicy::isSymmetric('affiliated_with'), 'affiliated_with is explicitly symmetric');
esc_p3_rel_assert(! PartyRelationshipPolicy::isSymmetric('owns'), 'owns remains directional');
esc_p3_rel_assert(! PartyRelationshipPolicy::isSupported('child_of'), 'derived inverse labels are not stored as independent relationship rows');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::MANAGE_REFERENCE_DATA => true,
];
$service = new PartyRelationshipService();

CoreTenantEnforcement::disable();
TenantContextStore::reset();
esc_p3_rel_throws(
    static fn () => $service->relationshipsForParty(55),
    RuntimeException::class,
    'relationship reads fail closed outside Enterprise core enforcement'
);

update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
esc_p3_rel_throws(
    static fn () => $service->relationshipsForParty(55),
    RuntimeException::class,
    'relationship reads require locked tenant context'
);

TenantContextStore::context()->setTenantId(17);
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p3_rel_throws(
    static fn () => $service->assign(55, 55, 'owns'),
    InvalidArgumentException::class,
    'self relationships fail before Party lookup'
);
esc_p3_rel_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'self relationship performs no data access');
esc_p3_rel_throws(
    static fn () => $service->assign(55, 70, 'customer'),
    InvalidArgumentException::class,
    'unsupported relationship code fails closed'
);
esc_p3_rel_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'unsupported relationship fails before data access');

$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'display_name' => 'Parent']],
    [[
        'id' => '9',
        'source_party_id' => '55',
        'target_party_id' => '70',
        'relationship_code' => 'parent_of',
        'status' => 'active',
    ]],
    [],
];
$listed = $service->relationshipsForParty(55);
esc_p3_rel_assert(count($listed['outgoing']) === 1 && $listed['incoming'] === [], 'relationship listing returns separate outgoing/incoming edges');
esc_p3_rel_assert(count($GLOBALS['sc_test_read_queries']) === 3, 'listing verifies Party ownership then queries both edge directions');
esc_p3_rel_assert(str_contains($GLOBALS['sc_test_read_queries'][0], 'WHERE id = 55 AND tenant_id = 17'), 'listing first verifies current-tenant Party ownership');
esc_p3_rel_assert(str_contains($GLOBALS['sc_test_read_queries'][1], "WHERE r.tenant_id = 17 AND r.source_party_id = 55 AND r.status = 'active'"), 'outgoing listing is tenant scoped');
esc_p3_rel_assert(str_contains($GLOBALS['sc_test_read_queries'][2], "WHERE r.tenant_id = 17 AND r.target_party_id = 55 AND r.status = 'active'"), 'incoming listing is tenant scoped');
esc_p3_rel_assert(str_contains($GLOBALS['sc_test_read_queries'][1], 'source_party.tenant_id = r.tenant_id') && str_contains($GLOBALS['sc_test_read_queries'][1], 'target_party.tenant_id = r.tenant_id'), 'relationship reads join both endpoints back to the same tenant');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'display_name' => 'Owner']],
    [['id' => '70', 'display_name' => 'Asset Party']],
];
$service->assign(55, 70, ' OWNS ', [
    'valid_from' => '2026-01-01',
    'valid_to' => '2026-12-31',
    'metadata' => ['source' => 'registry'],
]);
esc_p3_rel_assert(count($GLOBALS['sc_test_read_queries']) === 2, 'relationship assignment verifies both Party endpoints');
esc_p3_rel_assert(str_contains($GLOBALS['sc_test_read_queries'][0], 'WHERE id = 55 AND tenant_id = 17'), 'source Party verification is tenant scoped');
esc_p3_rel_assert(str_contains($GLOBALS['sc_test_read_queries'][1], 'WHERE id = 70 AND tenant_id = 17'), 'target Party verification is tenant scoped');
esc_p3_rel_assert(count($GLOBALS['sc_test_queries']) === 1, 'relationship assignment performs one atomic edge mutation');
$assignSql = $GLOBALS['sc_test_queries'][0];
esc_p3_rel_assert(str_contains($assignSql, 'INSERT INTO wp_safecontracts_party_relationships'), 'relationship assignment uses dedicated table');
esc_p3_rel_assert(str_contains($assignSql, "VALUES (17, 55, 70, 'owns', 'active'"), 'assignment derives tenant and normalized directional edge server-side');
esc_p3_rel_assert(str_contains($assignSql, "'2026-01-01'") && str_contains($assignSql, "'2026-12-31'"), 'validated effective date range is persisted');
esc_p3_rel_assert(str_contains($assignSql, 'source') && str_contains($assignSql, 'registry'), 'bounded relationship metadata is encoded before persistence');
esc_p3_rel_assert(str_contains($assignSql, 'ON DUPLICATE KEY UPDATE'), 'duplicate/re-activation relationship assignment is atomic');
esc_p3_rel_assert(str_contains($assignSql, "valid_from = IF(status = 'active', valid_from, VALUES(valid_from))"), 'already-active duplicate assignment preserves existing edge metadata');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'display_name' => 'Owner']],
    [['id' => '70', 'display_name' => 'Asset Party']],
];
$service->assign(55, 70, 'owns', [
    'valid_from' => '2026-01-01',
    'valid_to' => '2026-12-31',
    'metadata' => ['source' => 'registry'],
]);
esc_p3_rel_assert(($GLOBALS['sc_test_queries'][0] ?? '') === $assignSql, 'repeated identical directional assignment reaches the same atomic state transition');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'display_name' => 'A']],
    [['id' => '70', 'display_name' => 'B']],
];
$service->assign(70, 55, 'affiliated_with');
$symmetricSql = $GLOBALS['sc_test_queries'][0] ?? '';
esc_p3_rel_assert(str_contains($symmetricSql, "VALUES (17, 55, 70, 'affiliated_with', 'active'"), 'symmetric relationship canonicalizes endpoint order to prevent reverse duplicates');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'display_name' => 'Source']],
    [],
];
esc_p3_rel_throws(
    static fn () => $service->assign(55, 999, 'represents'),
    InvalidArgumentException::class,
    'foreign/missing target Party cannot receive a relationship edge'
);
esc_p3_rel_assert($GLOBALS['sc_test_queries'] === [], 'foreign target miss performs no relationship mutation');
esc_p3_rel_assert(count($GLOBALS['sc_test_read_queries']) === 2, 'both endpoints are checked before mutation');
esc_p3_rel_assert(str_contains($GLOBALS['sc_test_read_queries'][1], 'WHERE id = 999 AND tenant_id = 17'), 'target spoof attempt remains locked to current tenant');

TenantContextStore::reset();
TenantContextStore::context()->setTenantId(18);
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [[]];
esc_p3_rel_throws(
    static fn () => $service->assign(55, 70, 'parent_of'),
    InvalidArgumentException::class,
    'foreign source Party cannot initiate a relationship in another tenant'
);
esc_p3_rel_assert($GLOBALS['sc_test_queries'] === [], 'foreign source miss performs no relationship mutation');
esc_p3_rel_assert(str_contains($GLOBALS['sc_test_read_queries'][0] ?? '', 'WHERE id = 55 AND tenant_id = 18'), 'source spoof attempt remains locked to current tenant');

TenantContextStore::reset();
TenantContextStore::context()->setTenantId(17);
esc_p3_rel_throws(
    static fn () => $service->assign(55, 70, 'owns', ['valid_from' => '2026-13-01']),
    InvalidArgumentException::class,
    'invalid effective date is rejected'
);
esc_p3_rel_throws(
    static fn () => $service->assign(55, 70, 'owns', ['valid_from' => '2026-10-01', 'valid_to' => '2026-01-01']),
    InvalidArgumentException::class,
    'effective end date cannot precede start date'
);
esc_p3_rel_throws(
    static fn () => $service->assign(55, 70, 'owns', ['metadata' => 'not-an-array']),
    InvalidArgumentException::class,
    'relationship metadata must be array/object compatible'
);
esc_p3_rel_throws(
    static fn () => $service->assign(55, 70, 'owns', ['tenant_id' => 999]),
    InvalidArgumentException::class,
    'caller cannot provide relationship tenant ownership option'
);

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'display_name' => 'Owner']],
    [['id' => '70', 'display_name' => 'Asset Party']],
];
$service->revoke(55, 70, 'owns');
$revokeSql = $GLOBALS['sc_test_queries'][0] ?? '';
esc_p3_rel_assert(str_contains($revokeSql, 'UPDATE wp_safecontracts_party_relationships'), 'relationship revoke uses dedicated table');
esc_p3_rel_assert(str_contains($revokeSql, "WHERE tenant_id = 17 AND source_party_id = 55 AND target_party_id = 70 AND relationship_code = 'owns'"), 'relationship revoke includes tenant/source/target/type identity');
esc_p3_rel_assert(str_contains($revokeSql, "revoked_by = IF(status = 'active'"), 'revocation metadata changes only for active relationship');
esc_p3_rel_assert(str_contains($revokeSql, "status = 'inactive'"), 'relationship revoke is non-destructive');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'display_name' => 'A']],
    [['id' => '70', 'display_name' => 'B']],
];
$service->revoke(70, 55, 'affiliated_with');
$symmetricRevokeSql = $GLOBALS['sc_test_queries'][0] ?? '';
esc_p3_rel_assert(str_contains($symmetricRevokeSql, "WHERE tenant_id = 17 AND source_party_id = 55 AND target_party_id = 70 AND relationship_code = 'affiliated_with'"), 'symmetric revoke canonicalizes endpoint order consistently');

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = false;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p3_rel_throws(
    static fn () => $service->assign(55, 70, 'owns'),
    DomainException::class,
    'relationship mutations require tenant-aware MANAGE_REFERENCE_DATA'
);
esc_p3_rel_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'write capability denial occurs before data access');
$GLOBALS['sc_test_current_caps'][Capabilities::ACCESS] = false;
esc_p3_rel_throws(
    static fn () => $service->relationshipsForParty(55),
    DomainException::class,
    'relationship reads require tenant-aware ACCESS'
);

esc_p3_rel_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()'), 'relationship repository has explicit Enterprise-only boundary');
esc_p3_rel_assert(str_contains($repositorySource, 'requireTenantId()'), 'relationship repository never falls back to unscoped access');
esc_p3_rel_assert(! str_contains($repositorySource, 'DELETE FROM'), 'relationship revoke is non-destructive');
esc_p3_rel_assert(! str_contains($serviceSource, 'party_kind'), 'relationship service cannot mutate intrinsic Party kind');
esc_p3_rel_assert(! str_contains($serviceSource, 'PartyRole'), 'relationship service cannot mutate Party business roles');
esc_p3_rel_assert(! str_contains($serviceSource, 'safecontracts_customers'), 'relationship service has no legacy Customer mutation path');
esc_p3_rel_assert(! str_contains($migrationSource, 'safecontracts_customers'), 'relationship schema does not rewrite legacy customers');

CoreTenantEnforcement::disable();
TenantContextStore::reset();

fwrite(STDOUT, "Enterprise Party relationships P3-003 passed ({$assertions} assertions).\n");
