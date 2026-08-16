<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\ContractTemplates\ContractTemplatePolicy;
use SafeContracts\ContractTemplates\ContractTemplateService;
use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0027EnterpriseContractTemplates;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p4_tpl_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p4_tpl_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p4_tpl_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ')');
        return;
    }
    esc_p4_tpl_assert(false, $message . ' (no exception)');
}

function esc_p4_tpl_actor(string $role = 'tenant_admin'): array
{
    return [[
        'id' => '1',
        'tenant_id' => '17',
        'user_id' => '42',
        'role_code' => $role,
        'is_owner' => '0',
    ]];
}

function esc_p4_tpl_type(int $id = 31, string $status = 'active'): array
{
    return [[
        'id' => (string) $id,
        'uuid' => '11111111-1111-4111-8111-111111111111',
        'type_code' => 'construction.main',
        'name' => 'Construction Contract',
        'status' => $status,
    ]];
}

function esc_p4_tpl_template(int $id = 41, string $status = 'active', int $typeId = 31): array
{
    return [[
        'id' => (string) $id,
        'uuid' => '22222222-2222-4222-8222-222222222222',
        'contract_type_id' => (string) $typeId,
        'template_code' => 'construction.standard',
        'name' => 'Standard Construction Template',
        'description' => 'Standard clauses',
        'status' => $status,
    ]];
}

function esc_p4_tpl_version(int $id = 51, int $versionNo = 1, string $status = 'draft'): array
{
    return [[
        'id' => (string) $id,
        'template_id' => '41',
        'version_no' => (string) $versionNo,
        'version_status' => $status,
        'definition_json' => '{"sections":[{"code":"scope","title":"Scope"}]}',
        'notes' => 'Draft notes',
        'created_by' => '42',
        'updated_by' => '42',
        'published_by' => $status === 'published' ? '42' : null,
        'published_at' => $status === 'published' ? '2026-08-16 20:00:00' : null,
    ]];
}

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0027EnterpriseContractTemplates.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/ContractTemplates/ContractTemplateRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/ContractTemplates/ContractTemplateService.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/ContractTemplates/ContractTemplatePolicy.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$contractMigrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0004Contracts.php');
$contractServiceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Contracts/ContractService.php');
$statusSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Contracts/ContractStatus.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0027EnterpriseContractTemplates())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);
esc_p4_tpl_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_contract_templates'), 'P4-002 creates template identity table');
esc_p4_tpl_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_contract_template_versions'), 'P4-002 creates template version table');
esc_p4_tpl_assert(substr_count($schema, 'tenant_id bigint(20) unsigned NOT NULL') >= 2, 'template identity and versions both require tenant ownership');
esc_p4_tpl_assert(str_contains($schema, 'contract_type_id bigint(20) unsigned NOT NULL'), 'template identity belongs to a Contract Type');
esc_p4_tpl_assert(str_contains($schema, 'UNIQUE KEY tenant_code (tenant_id, template_code)'), 'template code is tenant-local unique');
esc_p4_tpl_assert(str_contains($schema, 'UNIQUE KEY tenant_template_version (tenant_id, template_id, version_no)'), 'template version number is DB-unique per tenant/template');
esc_p4_tpl_assert(str_contains($schema, 'KEY tenant_template_status (tenant_id, template_id, version_status, version_no, id)'), 'version listing index is tenant-first');
esc_p4_tpl_assert(version_compare(Migrator::LATEST_VERSION, '1.26.0', '>='), 'P4-002 schema remains reachable after future migrations');
esc_p4_tpl_assert(str_contains($migratorSource, "'1.26.0' => Migration0027EnterpriseContractTemplates::class"), 'P4-002 migration is registered specifically at schema 1.26.0');

esc_p4_tpl_assert(ContractTemplatePolicy::normalizeCode(' Construction.Standard ') === 'construction.standard', 'template code normalization is deterministic');
esc_p4_tpl_assert(ContractTemplatePolicy::encodeDefinition(['sections' => [['code' => 'scope']]]) === '{"sections":[{"code":"scope"}]}', 'structured definition encodes deterministically');
esc_p4_tpl_throws(static fn () => ContractTemplatePolicy::encodeDefinition('raw-code'), InvalidArgumentException::class, 'template definition rejects non-array payload');
esc_p4_tpl_throws(static fn () => ContractTemplatePolicy::encodeDefinition(['bad' => new stdClass()]), InvalidArgumentException::class, 'template definition rejects object payloads');
esc_p4_tpl_throws(static fn () => ContractTemplatePolicy::encodeDefinition(['bad' => INF]), InvalidArgumentException::class, 'template definition rejects non-finite numeric payloads');
esc_p4_tpl_throws(static fn () => ContractTemplatePolicy::encodeDefinition(['blob' => str_repeat('x', ContractTemplatePolicy::MAX_DEFINITION_BYTES + 1)]), InvalidArgumentException::class, 'template definition encoded size is bounded');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::MANAGE_REFERENCE_DATA => true,
];
$service = new ContractTemplateService();

CoreTenantEnforcement::disable();
TenantContextStore::reset();
esc_p4_tpl_throws(static fn () => $service->findTemplate(41), RuntimeException::class, 'template access fails closed outside Enterprise core enforcement');

update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
esc_p4_tpl_throws(static fn () => $service->findTemplate(41), RuntimeException::class, 'template access requires locked tenant context');
TenantContextStore::context()->setTenantId(17);

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_tpl_actor(), esc_p4_tpl_type(31, 'active')];
$GLOBALS['wpdb']->insert_id = 0;
$templateId = $service->createTemplate([
    'contract_type_id' => 31,
    'template_code' => ' Construction.Standard ',
    'name' => 'Standard Construction Template',
    'description' => 'Standard clauses',
]);
esc_p4_tpl_assert($templateId > 0, 'template create returns persisted identifier');
$createSql = end($GLOBALS['sc_test_queries']);
$createSql = is_string($createSql) ? $createSql : '';
esc_p4_tpl_assert(str_contains($GLOBALS['sc_test_read_queries'][1] ?? '', 'WHERE id = 31 AND tenant_id = 17'), 'template creation verifies Contract Type in locked tenant');
esc_p4_tpl_assert(str_contains($createSql, 'INSERT INTO wp_safecontracts_contract_templates'), 'template create writes only identity table');
esc_p4_tpl_assert(str_contains($createSql, "VALUES (17,"), 'template tenant comes from locked server context');
esc_p4_tpl_assert(str_contains($createSql, "31, 'construction.standard', 'Standard Construction Template'"), 'template stores verified Contract Type and normalized immutable code');
esc_p4_tpl_assert(preg_match("/'[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}'/", $createSql) === 1, 'template UUID is generated server-side');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_tpl_actor(), []];
esc_p4_tpl_throws(static fn () => $service->createTemplate(['contract_type_id' => 999, 'template_code' => 'foreign', 'name' => 'Foreign']), InvalidArgumentException::class, 'foreign Contract Type cannot own a template');
esc_p4_tpl_assert($GLOBALS['sc_test_queries'] === [], 'foreign Contract Type rejection performs no mutation');
esc_p4_tpl_assert(str_contains($GLOBALS['sc_test_read_queries'][1] ?? '', 'WHERE id = 999 AND tenant_id = 17'), 'foreign Contract Type lookup is tenant-scoped');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_tpl_actor(), esc_p4_tpl_type(31, 'inactive')];
esc_p4_tpl_throws(static fn () => $service->createTemplate(['contract_type_id' => 31, 'template_code' => 'inactive', 'name' => 'Inactive']), InvalidArgumentException::class, 'inactive Contract Type cannot author a new template');
esc_p4_tpl_assert($GLOBALS['sc_test_queries'] === [], 'inactive Contract Type rejection performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_tpl_actor()];
esc_p4_tpl_throws(static fn () => $service->createTemplate(['tenant_id' => 999, 'contract_type_id' => 31, 'template_code' => 'x', 'name' => 'X']), InvalidArgumentException::class, 'caller cannot supply template tenant ownership');
esc_p4_tpl_assert($GLOBALS['sc_test_queries'] === [], 'template tenant spoof fails before mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_tpl_actor()];
esc_p4_tpl_throws(static fn () => $service->updateTemplate(41, ['template_code' => 'changed']), InvalidArgumentException::class, 'template code is immutable after creation');
esc_p4_tpl_assert($GLOBALS['sc_test_queries'] === [], 'immutable template code rejection performs no mutation');
esc_p4_tpl_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'immutable template code rejected before template lookup');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_tpl_actor()];
esc_p4_tpl_throws(static fn () => $service->updateTemplate(41, ['contract_type_id' => 32]), InvalidArgumentException::class, 'template Contract Type binding is immutable in foundation');
esc_p4_tpl_assert($GLOBALS['sc_test_queries'] === [], 'immutable template type rejection performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_tpl_actor(), esc_p4_tpl_template(41, 'active', 31), esc_p4_tpl_type(31, 'active'), [['max_version' => '3']]];
$GLOBALS['wpdb']->insert_id = 0;
$versionId = $service->createDraftVersion(41, ['sections' => [['code' => 'scope', 'title' => 'Scope']]], 'Version four');
esc_p4_tpl_assert($versionId > 0, 'draft version create returns persisted identifier');
esc_p4_tpl_assert(str_contains($GLOBALS['sc_test_read_queries'][1] ?? '', 'WHERE id = 41 AND tenant_id = 17'), 'draft creation verifies template in locked tenant');
esc_p4_tpl_assert(str_contains($GLOBALS['sc_test_read_queries'][2] ?? '', 'WHERE id = 31 AND tenant_id = 17'), 'draft creation verifies template Contract Type remains current-tenant and active');
esc_p4_tpl_assert(str_contains($GLOBALS['sc_test_read_queries'][3] ?? '', 'MAX(version_no)'), 'draft version number is calculated server-side');
$draftSql = end($GLOBALS['sc_test_queries']);
$draftSql = is_string($draftSql) ? $draftSql : '';
esc_p4_tpl_assert(str_contains($draftSql, "VALUES (17, 41, 4, 'draft'"), 'server controls monotonically increasing version number');
esc_p4_tpl_assert(str_contains($draftSql, 'sections') && str_contains($draftSql, 'scope'), 'draft stores structured encoded definition');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_tpl_actor(), []];
esc_p4_tpl_throws(static fn () => $service->createDraftVersion(999, ['sections' => []]), InvalidArgumentException::class, 'foreign Template ID cannot create a draft');
esc_p4_tpl_assert($GLOBALS['sc_test_queries'] === [], 'foreign Template draft rejection performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_tpl_actor(), esc_p4_tpl_template(41), esc_p4_tpl_type(31), esc_p4_tpl_version(51, 4, 'draft')];
$service->updateDraftVersion(41, 51, ['sections' => [['code' => 'scope'], ['code' => 'payment']]], 'Edited draft');
$editSql = end($GLOBALS['sc_test_queries']);
$editSql = is_string($editSql) ? $editSql : '';
esc_p4_tpl_assert(str_contains($editSql, 'UPDATE wp_safecontracts_contract_template_versions SET definition_json ='), 'draft definition can be edited');
esc_p4_tpl_assert(str_contains($editSql, "WHERE id = 51 AND template_id = 41 AND tenant_id = 17 AND version_status = 'draft'"), 'draft edit has tenant+template+draft predicate');
esc_p4_tpl_assert(! str_contains($editSql, 'version_no ='), 'draft edit cannot alter server-controlled version number');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_tpl_actor(), esc_p4_tpl_template(41), esc_p4_tpl_type(31), esc_p4_tpl_version(51, 4, 'published')];
esc_p4_tpl_throws(static fn () => $service->updateDraftVersion(41, 51, ['sections' => []]), InvalidArgumentException::class, 'published version definition is immutable');
esc_p4_tpl_assert($GLOBALS['sc_test_queries'] === [], 'published version edit rejection performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_tpl_actor(), esc_p4_tpl_template(41), esc_p4_tpl_type(31), esc_p4_tpl_version(51, 4, 'draft')];
$service->publishVersion(41, 51);
$publishSql = end($GLOBALS['sc_test_queries']);
$publishSql = is_string($publishSql) ? $publishSql : '';
esc_p4_tpl_assert(str_contains($publishSql, "SET version_status = 'published'"), 'publish transitions only a draft version');
esc_p4_tpl_assert(str_contains($publishSql, "WHERE id = 51 AND template_id = 41 AND tenant_id = 17 AND version_status = 'draft'"), 'publish is tenant/template scoped and draft-only');
esc_p4_tpl_assert(! str_contains($publishSql, 'definition_json ='), 'publish never rewrites the immutable definition snapshot');
esc_p4_tpl_assert(! str_contains($publishSql, 'notes ='), 'publish never rewrites version notes/content');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_tpl_actor(), esc_p4_tpl_template(41), esc_p4_tpl_type(31), esc_p4_tpl_version(51, 4, 'published')];
esc_p4_tpl_throws(static fn () => $service->publishVersion(41, 51), InvalidArgumentException::class, 'published version cannot be published/unpublished again through foundation service');
esc_p4_tpl_assert($GLOBALS['sc_test_queries'] === [], 'republish rejection performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_tpl_actor(), esc_p4_tpl_template(41), []];
esc_p4_tpl_throws(static fn () => $service->findVersion(41, 999), InvalidArgumentException::class, 'foreign version ID is not accepted as another template version');
esc_p4_tpl_assert(str_contains($GLOBALS['sc_test_read_queries'][2] ?? '', 'WHERE id = 999 AND template_id = 41 AND tenant_id = 17'), 'version lookup binds ID to tenant and template');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_tpl_actor(), esc_p4_tpl_template(41), [esc_p4_tpl_version(51, 4, 'published')[0]]];
$versions = $service->listVersions(41, 500, -1);
esc_p4_tpl_assert(count($versions) === 1, 'version history remains readable after publication');
$versionListSql = end($GLOBALS['sc_test_read_queries']);
$versionListSql = is_string($versionListSql) ? $versionListSql : '';
esc_p4_tpl_assert(str_contains($versionListSql, 'WHERE tenant_id = 17 AND template_id = 41'), 'version history is tenant/template scoped');
esc_p4_tpl_assert(str_contains($versionListSql, 'LIMIT 100 OFFSET 0'), 'version history pagination is bounded');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_tpl_actor(), esc_p4_tpl_template(41)];
$service->deactivateTemplate(41);
$deactivateSql = end($GLOBALS['sc_test_queries']);
$deactivateSql = is_string($deactivateSql) ? $deactivateSql : '';
esc_p4_tpl_assert(str_contains($deactivateSql, "SET status = 'inactive'"), 'template deactivate is non-destructive');
esc_p4_tpl_assert(str_contains($deactivateSql, 'WHERE id = 41 AND tenant_id = 17'), 'template deactivate is tenant-scoped');
esc_p4_tpl_assert(str_contains($deactivateSql, "status <> 'inactive'"), 'template deactivate is idempotent');

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = false;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p4_tpl_throws(static fn () => $service->createDraftVersion(41, []), DomainException::class, 'template version mutation requires MANAGE_REFERENCE_DATA global ceiling');
esc_p4_tpl_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'global mutation denial occurs before data access');

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = true;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_tpl_actor('viewer')];
esc_p4_tpl_throws(static fn () => $service->createDraftVersion(41, []), DomainException::class, 'tenant viewer cannot bypass template authoring ceiling');
esc_p4_tpl_assert(count($GLOBALS['sc_test_read_queries']) === 1 && $GLOBALS['sc_test_queries'] === [], 'tenant-role mutation denial performs only authorization lookup');

$GLOBALS['sc_test_current_caps'][Capabilities::ACCESS] = false;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p4_tpl_throws(static fn () => $service->findTemplate(41), DomainException::class, 'template reads require ACCESS global ceiling');
esc_p4_tpl_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'template read denial occurs before data access');

esc_p4_tpl_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()'), 'template repository has explicit Enterprise-only boundary');
esc_p4_tpl_assert(str_contains($repositorySource, 'requireTenantId()'), 'template repository has no unscoped tenant fallback');
esc_p4_tpl_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'template service enforces tenant-role capability ceiling');
esc_p4_tpl_assert(str_contains($policySource, 'MAX_DEFINITION_BYTES = 100000'), 'definition payload has explicit byte limit');
esc_p4_tpl_assert(! str_contains($repositorySource, 'DELETE FROM'), 'template foundation has no destructive version/template delete path');
esc_p4_tpl_assert(! str_contains(strtolower($serviceSource), 'unpublish'), 'template foundation exposes no unpublish path');
esc_p4_tpl_assert(! str_contains($migrationSource, 'safecontracts_contracts'), 'P4-002 migration does not alter existing contracts');
esc_p4_tpl_assert(! str_contains($repositorySource, 'safecontracts_contracts'), 'template repository cannot mutate existing contracts');
esc_p4_tpl_assert(! str_contains($contractMigrationSource, 'contract_type'), 'legacy contract schema remains unchanged in P4-002');
esc_p4_tpl_assert(! str_contains($contractServiceSource, 'ContractTemplate'), 'legacy ContractService remains unbound to templates');
esc_p4_tpl_assert(str_contains($statusSource, "self::DRAFT => [self::ACTIVE, self::CANCELLED]"), 'inherited ContractStatus lifecycle remains intact');

CoreTenantEnforcement::disable();
TenantContextStore::reset();

fwrite(STDOUT, "Enterprise Contract Templates P4-002 passed ({$assertions} assertions).\n");
