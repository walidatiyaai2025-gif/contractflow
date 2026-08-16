<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\CustomFields\CustomFieldDefinitionPolicy;
use SafeContracts\CustomFields\CustomFieldDefinitionService;
use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0029EnterpriseCustomFieldDefinitions;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p5_field_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p5_field_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p5_field_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ')');
        return;
    }
    esc_p5_field_assert(false, $message . ' (no exception)');
}

function esc_p5_field_actor(string $role = 'tenant_admin'): array
{
    return [[
        'id' => '1',
        'tenant_id' => '17',
        'user_id' => '42',
        'role_code' => $role,
        'is_owner' => '0',
    ]];
}

function esc_p5_field_type(int $id = 31, string $status = 'active'): array
{
    return [['id' => (string) $id, 'status' => $status]];
}

function esc_p5_field_row(int $id = 61, string $dataType = 'select'): array
{
    return [[
        'id' => (string) $id,
        'uuid' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
        'contract_type_id' => '31',
        'field_code' => 'project.region',
        'data_type' => $dataType,
        'label' => 'Project Region',
        'help_text' => 'Choose a region',
        'is_required' => '1',
        'status' => 'active',
        'sort_order' => '10',
        'options_json' => $dataType === 'select' ? '[{"value":"north","label":"North"},{"value":"south","label":"South"}]' : '',
        'validation_json' => '',
        'created_by' => '42',
        'updated_by' => '42',
    ]];
}

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0029EnterpriseCustomFieldDefinitions.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/CustomFieldDefinitionRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/CustomFieldDefinitionService.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/CustomFieldDefinitionPolicy.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$contractMigration = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0004Contracts.php');
$templateMigration = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0027EnterpriseContractTemplates.php');
$bindingMigration = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0028EnterpriseContractConfigurationBindings.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0029EnterpriseCustomFieldDefinitions())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);
esc_p5_field_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_custom_field_definitions'), 'P5-001 creates dedicated definition table');
esc_p5_field_assert(str_contains($schema, 'tenant_id bigint(20) unsigned NOT NULL'), 'definition tenant ownership is mandatory');
esc_p5_field_assert(str_contains($schema, 'uuid char(36) NOT NULL'), 'definition has server-generated UUID identity');
esc_p5_field_assert(str_contains($schema, 'contract_type_id bigint(20) unsigned NOT NULL'), 'definition belongs to Contract Type');
esc_p5_field_assert(str_contains($schema, 'UNIQUE KEY tenant_type_code (tenant_id, contract_type_id, field_code)'), 'field code uniqueness is tenant+type local');
esc_p5_field_assert(str_contains($schema, 'KEY tenant_type_status_sort (tenant_id, contract_type_id, status, sort_order, id)'), 'field listing index is tenant-first');
esc_p5_field_assert(version_compare(Migrator::LATEST_VERSION, '1.28.0', '>='), 'P5-001 schema remains reachable after later migrations');
esc_p5_field_assert(str_contains($migratorSource, "'1.28.0' => Migration0029EnterpriseCustomFieldDefinitions::class"), 'P5-001 migration is registered at 1.28.0');
esc_p5_field_assert(! str_contains($schema, 'custom_field_values'), 'P5-001 does not create contract value storage');

esc_p5_field_assert(CustomFieldDefinitionPolicy::normalizeCode(' Project.Region ') === 'project.region', 'field code normalization is deterministic');
esc_p5_field_assert(CustomFieldDefinitionPolicy::normalizeDataType('SELECT') === 'select', 'data type normalization is deterministic');
esc_p5_field_assert(count(CustomFieldDefinitionPolicy::dataTypes()) === 9, 'data type allowlist is explicit and bounded');
esc_p5_field_throws(static fn () => CustomFieldDefinitionPolicy::normalizeDataType('php'), InvalidArgumentException::class, 'unsupported data type fails closed');
esc_p5_field_throws(static fn () => CustomFieldDefinitionPolicy::normalizeCode('bad/code'), InvalidArgumentException::class, 'unsupported field code characters fail closed');

$options = CustomFieldDefinitionPolicy::encodeOptions('select', [
    ['value' => 'north', 'label' => 'North'],
    ['value' => 2, 'label' => 'Second'],
]);
esc_p5_field_assert(str_contains($options, 'north') && str_contains($options, 'Second'), 'select options encode declaratively');
esc_p5_field_throws(static fn () => CustomFieldDefinitionPolicy::encodeOptions('text', [['value' => 'x', 'label' => 'X']]), InvalidArgumentException::class, 'non-select options fail closed');
esc_p5_field_throws(static fn () => CustomFieldDefinitionPolicy::encodeOptions('select', []), InvalidArgumentException::class, 'select requires non-empty options');
esc_p5_field_throws(static fn () => CustomFieldDefinitionPolicy::encodeOptions('select', [
    ['value' => 'x', 'label' => 'X'],
    ['value' => 'x', 'label' => 'Duplicate'],
]), InvalidArgumentException::class, 'duplicate option values fail closed');
esc_p5_field_throws(static fn () => CustomFieldDefinitionPolicy::encodeOptions('select', [['value' => new stdClass(), 'label' => 'X']]), InvalidArgumentException::class, 'object option values fail closed');

esc_p5_field_assert(CustomFieldDefinitionPolicy::encodeValidation('text', ['min_length' => 2, 'max_length' => 50]) === '{"min_length":2,"max_length":50}', 'text length validation is declarative');
esc_p5_field_assert(CustomFieldDefinitionPolicy::encodeValidation('integer', ['min' => 1, 'max' => 100]) === '{"min":1,"max":100}', 'integer range validation is declarative');
esc_p5_field_throws(static fn () => CustomFieldDefinitionPolicy::encodeValidation('text', ['pattern' => '.*']), InvalidArgumentException::class, 'regex/pattern validation is not executable in foundation');
esc_p5_field_throws(static fn () => CustomFieldDefinitionPolicy::encodeValidation('boolean', ['min' => 1]), InvalidArgumentException::class, 'type-incompatible validation fails closed');
esc_p5_field_throws(static fn () => CustomFieldDefinitionPolicy::encodeValidation('integer', ['min' => 10, 'max' => 1]), InvalidArgumentException::class, 'reversed numeric range fails closed');
esc_p5_field_throws(static fn () => CustomFieldDefinitionPolicy::encodeValidation('multi_select', ['min_items' => 3, 'max_items' => 2]), InvalidArgumentException::class, 'reversed item-count range fails closed');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::MANAGE_REFERENCE_DATA => true,
];
$service = new CustomFieldDefinitionService();

CoreTenantEnforcement::disable();
TenantContextStore::reset();
esc_p5_field_throws(static fn () => $service->find(61), RuntimeException::class, 'definition access fails closed outside Enterprise enforcement');

update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
esc_p5_field_throws(static fn () => $service->find(61), RuntimeException::class, 'definition access requires locked tenant context');
TenantContextStore::context()->setTenantId(17);

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_field_actor(), esc_p5_field_type()];
$GLOBALS['wpdb']->insert_id = 0;
$definitionId = $service->create([
    'contract_type_id' => 31,
    'field_code' => ' Project.Region ',
    'data_type' => 'select',
    'label' => 'Project Region',
    'help_text' => 'Choose a region',
    'is_required' => true,
    'sort_order' => 10,
    'options' => [
        ['value' => 'north', 'label' => 'North'],
        ['value' => 'south', 'label' => 'South'],
    ],
]);
esc_p5_field_assert($definitionId > 0, 'definition create returns persisted identifier');
esc_p5_field_assert(str_contains($GLOBALS['sc_test_read_queries'][1] ?? '', 'WHERE id = 31 AND tenant_id = 17'), 'create verifies Contract Type in locked tenant');
$createSql = (string) (end($GLOBALS['sc_test_queries']) ?: '');
esc_p5_field_assert(str_contains($createSql, 'INSERT INTO wp_safecontracts_custom_field_definitions'), 'create writes dedicated definition table');
esc_p5_field_assert(str_contains($createSql, 'SELECT 17,') && str_contains($createSql, "ct.status = 'active'"), 'create atomically rechecks tenant and active Contract Type');
esc_p5_field_assert(str_contains($createSql, "'project.region', 'select', 'Project Region'"), 'create persists normalized immutable identity');
esc_p5_field_assert(str_contains($createSql, 'north') && str_contains($createSql, 'south'), 'create persists validated select options');
esc_p5_field_assert(preg_match("/'[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}'/", $createSql) === 1, 'definition UUID is generated server-side');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_field_actor(), []];
esc_p5_field_throws(static fn () => $service->create([
    'contract_type_id' => 999,
    'field_code' => 'foreign',
    'data_type' => 'text',
    'label' => 'Foreign',
]), InvalidArgumentException::class, 'foreign Contract Type cannot own a field definition');
esc_p5_field_assert($GLOBALS['sc_test_queries'] === [], 'foreign type rejection performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_field_actor(), esc_p5_field_type(31, 'inactive')];
esc_p5_field_throws(static fn () => $service->create([
    'contract_type_id' => 31,
    'field_code' => 'inactive',
    'data_type' => 'text',
    'label' => 'Inactive',
]), InvalidArgumentException::class, 'inactive Contract Type cannot author a field definition');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_field_actor(), esc_p5_field_type()];
esc_p5_field_throws(static fn () => $service->create([
    'contract_type_id' => 31,
    'field_code' => 'bad.options',
    'data_type' => 'text',
    'label' => 'Bad Options',
    'options' => [['value' => 'x', 'label' => 'X']],
]), InvalidArgumentException::class, 'non-select definition cannot smuggle options');
esc_p5_field_assert($GLOBALS['sc_test_queries'] === [], 'invalid options perform no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_field_actor(), esc_p5_field_type()];
esc_p5_field_throws(static fn () => $service->create([
    'contract_type_id' => 31,
    'field_code' => 'regions',
    'data_type' => 'multi_select',
    'label' => 'Regions',
    'options' => [['value' => 'north', 'label' => 'North'], ['value' => 'south', 'label' => 'South']],
    'validation' => ['min_items' => 3],
]), InvalidArgumentException::class, 'multi-select item bounds cannot exceed option count');
esc_p5_field_assert($GLOBALS['sc_test_queries'] === [], 'impossible multi-select validation performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_field_actor()];
esc_p5_field_throws(static fn () => $service->update(61, ['field_code' => 'changed']), InvalidArgumentException::class, 'field_code is immutable after creation');
esc_p5_field_assert($GLOBALS['sc_test_queries'] === [], 'immutable code rejection performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_field_actor()];
esc_p5_field_throws(static fn () => $service->update(61, ['data_type' => 'text']), InvalidArgumentException::class, 'data_type is immutable after creation');
esc_p5_field_assert($GLOBALS['sc_test_queries'] === [], 'immutable data type rejection performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_field_actor()];
esc_p5_field_throws(static fn () => $service->update(61, ['contract_type_id' => 32]), InvalidArgumentException::class, 'Contract Type binding is immutable after definition creation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_field_actor(), esc_p5_field_row(), esc_p5_field_type()];
$service->update(61, [
    'label' => 'Region',
    'help_text' => 'Updated help',
    'is_required' => false,
    'sort_order' => 20,
    'options' => [['value' => 'north', 'label' => 'North'], ['value' => 'east', 'label' => 'East']],
]);
$updateSql = (string) (end($GLOBALS['sc_test_queries']) ?: '');
esc_p5_field_assert(str_contains($updateSql, 'UPDATE wp_safecontracts_custom_field_definitions d SET label ='), 'update changes declarative/display configuration only');
esc_p5_field_assert(str_contains($updateSql, 'd.id = 61 AND d.tenant_id = 17 AND d.contract_type_id = 31'), 'update is tenant+type scoped');
esc_p5_field_assert(str_contains($updateSql, "ct.status = 'active'"), 'update atomically rechecks active Contract Type');
$updateSetClause = explode(' WHERE ', $updateSql, 2)[0] ?? $updateSql;
esc_p5_field_assert(! str_contains($updateSetClause, 'field_code =') && ! str_contains($updateSetClause, 'data_type =') && ! str_contains($updateSetClause, 'contract_type_id ='), 'update SET cannot change immutable identity/type binding');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_field_actor(), esc_p5_field_type(), []];
$service->search(31, 'region', 'active', 500, -10);
$searchSql = (string) (end($GLOBALS['sc_test_read_queries']) ?: '');
esc_p5_field_assert(str_contains($searchSql, 'WHERE tenant_id = 17 AND contract_type_id = 31 AND status ='), 'search is tenant/type/status scoped');
esc_p5_field_assert(str_contains($searchSql, 'LIMIT 100 OFFSET 0'), 'search pagination is bounded');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_field_actor(), esc_p5_field_row()];
$service->deactivate(61);
$deactivateSql = (string) (end($GLOBALS['sc_test_queries']) ?: '');
esc_p5_field_assert(str_contains($deactivateSql, "SET status = 'inactive'"), 'definition deactivation is non-destructive');
esc_p5_field_assert(str_contains($deactivateSql, 'WHERE id = 61 AND tenant_id = 17'), 'deactivation is tenant-scoped');
esc_p5_field_assert(! str_contains($deactivateSql, 'DELETE FROM'), 'historical definition is preserved');

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = false;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p5_field_throws(static fn () => $service->create([
    'contract_type_id' => 31, 'field_code' => 'denied', 'data_type' => 'text', 'label' => 'Denied'
]), DomainException::class, 'definition mutation requires MANAGE_REFERENCE_DATA');
esc_p5_field_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'global mutation denial occurs before data access');

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = true;
$GLOBALS['sc_test_result_queue'] = [esc_p5_field_actor('viewer')];
esc_p5_field_throws(static fn () => $service->create([
    'contract_type_id' => 31, 'field_code' => 'denied', 'data_type' => 'text', 'label' => 'Denied'
]), DomainException::class, 'tenant viewer cannot bypass definition mutation ceiling');

esc_p5_field_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()') && str_contains($repositorySource, 'requireTenantId()'), 'repository has no unscoped tenant fallback');
esc_p5_field_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'service enforces tenant-role capability ceiling');
esc_p5_field_assert(! str_contains($migrationSource, 'safecontracts_contracts'), 'P5-001 migration does not alter contracts');
esc_p5_field_assert(! str_contains($migrationSource, 'safecontracts_contract_templates'), 'P5-001 migration does not alter templates');
esc_p5_field_assert(! str_contains($migrationSource, 'safecontracts_contract_configuration_bindings'), 'P5-001 migration does not alter P4 bindings');
esc_p5_field_assert(! str_contains($contractMigration, 'custom_field'), 'legacy contract migration remains unchanged');
esc_p5_field_assert(! str_contains($templateMigration, 'custom_field'), 'P4 template migration remains unchanged');
esc_p5_field_assert(! str_contains($bindingMigration, 'custom_field'), 'P4 binding migration remains unchanged');
esc_p5_field_assert(! str_contains($policySource, 'eval(') && ! str_contains($policySource, 'preg_replace_callback'), 'definition policy contains no executable expression engine');

echo "P5-001 Enterprise Custom Field definition checks passed ({$assertions} assertions).\n";