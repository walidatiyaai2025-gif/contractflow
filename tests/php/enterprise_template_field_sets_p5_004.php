<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\ContractTemplates\ContractTemplateService;
use SafeContracts\CustomFields\CustomFieldValuePolicy;
use SafeContracts\CustomFields\TemplateFieldSetService;
use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0031EnterpriseTemplateFieldSets;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p5_tfs_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p5_tfs_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p5_tfs_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ': ' . $error->getMessage() . ')');
        return;
    }
    esc_p5_tfs_assert(false, $message . ' (no exception)');
}

function esc_p5_tfs_actor(string $role = 'tenant_admin'): array
{
    return [[
        'id' => '1', 'tenant_id' => '17', 'user_id' => '42',
        'role_code' => $role, 'is_owner' => '0',
    ]];
}

function esc_p5_tfs_template(int $typeId = 31, string $status = 'active'): array
{
    return [[
        'id' => '41', 'uuid' => '22222222-2222-4222-8222-222222222222',
        'contract_type_id' => (string) $typeId, 'template_code' => 'construction.standard',
        'name' => 'Standard Construction Template', 'description' => 'Standard clauses',
        'status' => $status,
    ]];
}

function esc_p5_tfs_version(string $status = 'draft'): array
{
    return [[
        'id' => '51', 'template_id' => '41', 'version_no' => '4',
        'version_status' => $status, 'definition_json' => '{"sections":[]}', 'notes' => 'Draft',
        'created_by' => '42', 'updated_by' => '42',
        'published_by' => $status === 'published' ? '42' : null,
        'published_at' => $status === 'published' ? '2026-08-16 22:00:00' : null,
    ]];
}

function esc_p5_tfs_type(int $id = 31, string $status = 'active'): array
{
    return [[
        'id' => (string) $id, 'uuid' => '11111111-1111-4111-8111-111111111111',
        'type_code' => 'construction.main', 'name' => 'Construction Contract', 'status' => $status,
    ]];
}

function esc_p5_tfs_definition(int $id, int $typeId = 31, string $status = 'active', bool $required = true): array
{
    $isRegion = $id === 61;
    return [[
        'id' => (string) $id,
        'uuid' => sprintf('eeeeeeee-eeee-4eee-8eee-%012d', $id),
        'contract_type_id' => (string) $typeId,
        'field_code' => $isRegion ? 'project.region' : 'project.amount',
        'data_type' => $isRegion ? 'select' : 'decimal',
        'label' => $isRegion ? 'Project Region' : 'Project Amount',
        'help_text' => $isRegion ? 'Choose region' : 'Enter amount',
        'is_required' => $required ? '1' : '0',
        'status' => $status,
        'sort_order' => $isRegion ? '10' : '20',
        'options_json' => $isRegion ? '[{"value":"north","label":"North"},{"value":"south","label":"South"}]' : '',
        'validation_json' => $isRegion ? '' : '{"min":0,"max":1000000}',
        'created_by' => '42', 'updated_by' => '42',
    ]];
}

function esc_p5_tfs_snapshot(int $id, int $position, ?int $override = null): array
{
    $definition = esc_p5_tfs_definition($id)[0];
    return [
        'id' => (string) (900 + $position), 'template_id' => '41', 'template_version_id' => '51',
        'definition_id' => (string) $id, 'position_no' => (string) $position,
        'field_code_snapshot' => $definition['field_code'], 'data_type_snapshot' => $definition['data_type'],
        'label_snapshot' => $definition['label'], 'help_text_snapshot' => $definition['help_text'],
        'definition_required_snapshot' => $definition['is_required'],
        'required_override' => $override === null ? null : (string) $override,
        'options_json_snapshot' => $definition['options_json'],
        'validation_json_snapshot' => $definition['validation_json'],
        'definition_config_hash' => CustomFieldValuePolicy::configurationHash($definition),
        'created_by' => '42', 'updated_by' => '42',
    ];
}

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0031EnterpriseTemplateFieldSets.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/TemplateFieldSetRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/TemplateFieldSetService.php');
$templateRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/ContractTemplates/ContractTemplateRepository.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0031EnterpriseTemplateFieldSets())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);
esc_p5_tfs_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_contract_template_version_fields'), 'P5-004 creates dedicated snapshot table');
esc_p5_tfs_assert(str_contains($schema, 'tenant_id bigint(20) unsigned NOT NULL'), 'snapshot tenant ownership is mandatory');
esc_p5_tfs_assert(str_contains($schema, 'template_version_id bigint(20) unsigned NOT NULL'), 'snapshot belongs to exact Template Version');
esc_p5_tfs_assert(str_contains($schema, 'definition_id bigint(20) unsigned NOT NULL'), 'snapshot records definition identity');
esc_p5_tfs_assert(str_contains($schema, 'position_no int(10) unsigned NOT NULL'), 'snapshot stores deterministic order');
esc_p5_tfs_assert(str_contains($schema, 'field_code_snapshot varchar(100) NOT NULL'), 'field code is snapshotted');
esc_p5_tfs_assert(str_contains($schema, 'data_type_snapshot varchar(30) NOT NULL'), 'data type is snapshotted');
esc_p5_tfs_assert(str_contains($schema, 'required_override tinyint(1) NULL'), 'required override is nullable');
esc_p5_tfs_assert(str_contains($schema, 'definition_config_hash char(64) NOT NULL'), 'configuration hash is snapshotted');
esc_p5_tfs_assert(str_contains($schema, 'UNIQUE KEY tenant_version_definition (tenant_id, template_version_id, definition_id)'), 'definition uniqueness is tenant/version scoped');
esc_p5_tfs_assert(str_contains($schema, 'UNIQUE KEY tenant_version_position (tenant_id, template_version_id, position_no)'), 'position uniqueness is tenant/version scoped');
esc_p5_tfs_assert(Migrator::LATEST_VERSION === '1.30.0', 'P5-004 is current schema version');
esc_p5_tfs_assert(str_contains($migratorSource, "'1.30.0' => Migration0031EnterpriseTemplateFieldSets::class"), 'P5-004 migration is registered');
esc_p5_tfs_assert(! str_contains($migrationSource, 'ALTER TABLE'), 'P5-004 migration is additive');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::MANAGE_REFERENCE_DATA => true];
$service = new TemplateFieldSetService();

CoreTenantEnforcement::disable();
TenantContextStore::reset();
esc_p5_tfs_throws(static fn () => $service->list(41, 51), RuntimeException::class, 'field-set access fails closed outside Enterprise enforcement');
update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
esc_p5_tfs_throws(static fn () => $service->list(41, 51), RuntimeException::class, 'field-set access requires locked tenant');
TenantContextStore::context()->setTenantId(17);

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p5_tfs_actor(), esc_p5_tfs_template(), esc_p5_tfs_version(),
    esc_p5_tfs_definition(61), esc_p5_tfs_definition(62), [['id' => '51']],
];
$service->replace(41, 51, [
    ['definition_id' => 61],
    ['definition_id' => 62, 'required_override' => false],
]);
esc_p5_tfs_assert(count($GLOBALS['sc_test_read_queries']) === 6, 'replace validates actor/template/version/definitions and lock');
esc_p5_tfs_assert(str_contains($GLOBALS['sc_test_read_queries'][3] ?? '', 'WHERE id = 61 AND tenant_id = 17'), 'first definition lookup is tenant scoped');
esc_p5_tfs_assert(str_contains($GLOBALS['sc_test_read_queries'][4] ?? '', 'WHERE id = 62 AND tenant_id = 17'), 'second definition lookup is tenant scoped');
$lockSql = (string) ($GLOBALS['sc_test_read_queries'][5] ?? '');
esc_p5_tfs_assert(str_contains($lockSql, 'FOR UPDATE'), 'replace locks Template Version');
esc_p5_tfs_assert(str_contains($lockSql, "v.version_status = 'draft'") && str_contains($lockSql, "t.status = 'active'") && str_contains($lockSql, "ct.status = 'active'"), 'lock revalidates editable Template/Type state');
esc_p5_tfs_assert(($GLOBALS['sc_test_queries'][0] ?? '') === 'START TRANSACTION', 'replace starts transaction');
$deleteSql = (string) ($GLOBALS['sc_test_queries'][1] ?? '');
esc_p5_tfs_assert(str_contains($deleteSql, 'DELETE FROM wp_safecontracts_contract_template_version_fields'), 'replace clears only dedicated snapshots');
esc_p5_tfs_assert(str_contains($deleteSql, 'tenant_id = 17 AND template_id = 41 AND template_version_id = 51'), 'delete is tenant/template/version scoped');
$insert1 = (string) ($GLOBALS['sc_test_queries'][2] ?? '');
$insert2 = (string) ($GLOBALS['sc_test_queries'][3] ?? '');
esc_p5_tfs_assert(str_contains($insert1, 'INSERT INTO wp_safecontracts_contract_template_version_fields'), 'first snapshot persists');
esc_p5_tfs_assert(str_contains($insert2, 'INSERT INTO wp_safecontracts_contract_template_version_fields'), 'second snapshot persists');
esc_p5_tfs_assert(str_contains($insert1, "d.status = 'active'") && str_contains($insert1, 'd.contract_type_id = 31'), 'snapshot write rechecks active same-Type definition');
esc_p5_tfs_assert(str_contains($insert1, "d.field_code = 'project.region'") && str_contains($insert1, "d.data_type = 'select'"), 'snapshot write rechecks field identity/type');
esc_p5_tfs_assert(str_contains($insert1, "d.label = 'Project Region'") && str_contains($insert1, "COALESCE(d.help_text, '') = 'Choose region'"), 'snapshot write rechecks presentation');
esc_p5_tfs_assert(str_contains($insert1, 'COALESCE(d.options_json') && str_contains($insert1, 'COALESCE(d.validation_json'), 'snapshot write rechecks declarative config');
esc_p5_tfs_assert(($GLOBALS['sc_test_queries'][4] ?? '') === 'COMMIT', 'replace commits after all snapshots');
esc_p5_tfs_assert(! in_array('ROLLBACK', $GLOBALS['sc_test_queries'], true), 'valid replacement does not roll back');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_tfs_actor(), esc_p5_tfs_template(), esc_p5_tfs_version(), esc_p5_tfs_definition(61)];
esc_p5_tfs_throws(static fn () => $service->replace(41, 51, [['definition_id' => 61], ['definition_id' => 61]]), InvalidArgumentException::class, 'duplicate definitions fail closed');
esc_p5_tfs_assert($GLOBALS['sc_test_queries'] === [], 'duplicate rejection performs no write');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_tfs_actor(), esc_p5_tfs_template(), esc_p5_tfs_version(), esc_p5_tfs_definition(61, 32)];
esc_p5_tfs_throws(static fn () => $service->replace(41, 51, [['definition_id' => 61]]), InvalidArgumentException::class, 'other-Type definition fails closed');
esc_p5_tfs_assert($GLOBALS['sc_test_queries'] === [], 'other-Type rejection performs no write');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_tfs_actor(), esc_p5_tfs_template(), esc_p5_tfs_version(), esc_p5_tfs_definition(61, 31, 'inactive')];
esc_p5_tfs_throws(static fn () => $service->replace(41, 51, [['definition_id' => 61]]), InvalidArgumentException::class, 'inactive definition fails closed');
esc_p5_tfs_assert($GLOBALS['sc_test_queries'] === [], 'inactive definition rejection performs no write');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_tfs_actor(), esc_p5_tfs_template(), esc_p5_tfs_version('published')];
esc_p5_tfs_throws(static fn () => $service->replace(41, 51, []), InvalidArgumentException::class, 'published field set is immutable');
esc_p5_tfs_assert($GLOBALS['sc_test_queries'] === [], 'post-publish mutation performs no write');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_tfs_actor(), esc_p5_tfs_template(), esc_p5_tfs_version(), [['id' => '51']]];
$service->replace(41, 51, []);
esc_p5_tfs_assert(($GLOBALS['sc_test_queries'][0] ?? '') === 'START TRANSACTION', 'explicit empty set remains lock-protected');
esc_p5_tfs_assert(str_contains((string) ($GLOBALS['sc_test_queries'][1] ?? ''), 'DELETE FROM wp_safecontracts_contract_template_version_fields'), 'empty field set clears draft snapshots');
esc_p5_tfs_assert(($GLOBALS['sc_test_queries'][2] ?? '') === 'COMMIT', 'empty field set commits atomically');
esc_p5_tfs_assert(! array_filter($GLOBALS['sc_test_queries'], static fn ($q) => is_string($q) && str_contains($q, 'INSERT INTO wp_safecontracts_contract_template_version_fields')), 'empty field set does not inherit/synthesize definitions');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p5_tfs_actor(), esc_p5_tfs_template(), esc_p5_tfs_version('published'),
    [esc_p5_tfs_snapshot(61, 1), esc_p5_tfs_snapshot(62, 2, 0)],
];
$historical = $service->list(41, 51);
esc_p5_tfs_assert(count($historical) === 2, 'published snapshots remain historically readable');
esc_p5_tfs_assert(($historical[0]['effective_required'] ?? false) === true, 'base required snapshot hydrates');
esc_p5_tfs_assert(($historical[1]['required_override'] ?? null) === false && ($historical[1]['effective_required'] ?? true) === false, 'required override hydrates historically');
esc_p5_tfs_assert($GLOBALS['sc_test_queries'] === [], 'historical read is read-only');

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = false;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p5_tfs_throws(static fn () => $service->replace(41, 51, []), DomainException::class, 'mutation requires MANAGE_REFERENCE_DATA');
esc_p5_tfs_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'capability denial occurs before data access');
$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = true;

$tooMany = array_fill(0, TemplateFieldSetService::MAX_FIELDS + 1, ['definition_id' => 61]);
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_tfs_actor(), esc_p5_tfs_template(), esc_p5_tfs_version()];
esc_p5_tfs_throws(static fn () => $service->replace(41, 51, $tooMany), InvalidArgumentException::class, 'Template field-set count is bounded');
esc_p5_tfs_assert($GLOBALS['sc_test_queries'] === [], 'oversized field set performs no write');

$templateService = new ContractTemplateService();
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_tfs_actor(), esc_p5_tfs_template(), esc_p5_tfs_type(), esc_p5_tfs_version()];
$templateService->publishVersion(41, 51);
$publishSql = (string) (end($GLOBALS['sc_test_queries']) ?: '');
esc_p5_tfs_assert(str_contains($publishSql, "SET version_status = 'published'"), 'P4 publish transition contract remains intact');
esc_p5_tfs_assert(str_contains($publishSql, "WHERE id = 51 AND template_id = 41 AND tenant_id = 17 AND version_status = 'draft'"), 'publish remains exact tenant/template/draft scoped');
esc_p5_tfs_assert(str_contains($publishSql, 'wp_safecontracts_contract_template_version_fields'), 'publish checks P5-004 snapshots');
esc_p5_tfs_assert(str_contains($publishSql, 'NOT EXISTS'), 'publish uses fail-closed stale snapshot guard');
esc_p5_tfs_assert(str_contains($publishSql, 'LEFT JOIN wp_safecontracts_custom_field_definitions'), 'publish compares current definitions');
esc_p5_tfs_assert(str_contains($publishSql, "d.status <> 'active'") && str_contains($publishSql, 'd.contract_type_id <> t.contract_type_id'), 'publish rejects unavailable/wrong-Type definitions');
esc_p5_tfs_assert(str_contains($publishSql, 'd.field_code <> f.field_code_snapshot') && str_contains($publishSql, 'd.data_type <> f.data_type_snapshot'), 'publish revalidates identity/type');
esc_p5_tfs_assert(str_contains($publishSql, 'd.label <> f.label_snapshot') && str_contains($publishSql, "COALESCE(d.help_text, '') <> COALESCE(f.help_text_snapshot, '')"), 'publish revalidates presentation');
esc_p5_tfs_assert(str_contains($publishSql, 'd.is_required <> f.definition_required_snapshot'), 'publish revalidates required baseline');
esc_p5_tfs_assert(str_contains($publishSql, "COALESCE(d.options_json, '') <> COALESCE(f.options_json_snapshot, '')"), 'publish revalidates options');
esc_p5_tfs_assert(str_contains($publishSql, "COALESCE(d.validation_json, '') <> COALESCE(f.validation_json_snapshot, '')"), 'publish revalidates validation metadata');
esc_p5_tfs_assert(! str_contains($publishSql, 'definition_json ='), 'publish never rewrites Template content');

esc_p5_tfs_assert(str_contains($repositorySource, 'START TRANSACTION') && str_contains($repositorySource, 'FOR UPDATE') && str_contains($repositorySource, 'ROLLBACK'), 'replacement is transactional and lock protected');
esc_p5_tfs_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()') && str_contains($repositorySource, 'requireTenantId()'), 'repository has no unscoped tenant fallback');
esc_p5_tfs_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'service enforces tenant-role ceiling');
esc_p5_tfs_assert(str_contains($serviceSource, 'MAX_FIELDS = 200'), 'field-set size bound is explicit');
esc_p5_tfs_assert(str_contains($templateRepositorySource, 'NOT EXISTS') && str_contains($templateRepositorySource, 'definition_config_hash') === false, 'publish validates source snapshot fields without trusting hash alone');
esc_p5_tfs_assert(! str_contains($serviceSource, 'eval(') && ! str_contains($serviceSource, 'exec('), 'P5-004 introduces no executable expression engine');

CoreTenantEnforcement::disable();
TenantContextStore::reset();

echo "P5-004 Enterprise Template Dynamic Field snapshot checks passed ({$assertions} assertions).\n";
