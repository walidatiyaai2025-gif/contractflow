<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\CustomFields\CustomFieldValuePolicy;
use SafeContracts\CustomFields\CustomFieldValueService;
use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0030EnterpriseCustomFieldValues;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p5_value_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p5_value_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p5_value_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ': ' . $error->getMessage() . ')');
        return;
    }
    esc_p5_value_assert(false, $message . ' (no exception)');
}

function esc_p5_value_actor(string $role = 'tenant_admin'): array
{
    return [[
        'id' => '1',
        'tenant_id' => '17',
        'user_id' => '42',
        'role_code' => $role,
        'is_owner' => '0',
    ]];
}

function esc_p5_value_contract(string $status = 'draft', ?int $accountant = 42, bool $archived = false): array
{
    return [[
        'id' => '71',
        'accountant_user_id' => $accountant === null ? null : (string) $accountant,
        'status' => $status,
        'is_archived' => $archived ? '1' : '0',
    ]];
}

function esc_p5_value_binding(int $typeId = 31): array
{
    return [['contract_id' => '71', 'contract_type_id' => (string) $typeId]];
}

function esc_p5_value_definition(string $type = 'text', int $typeId = 31, string $status = 'active', array $overrides = []): array
{
    $options = '';
    $validation = '';
    if ($type === 'select' || $type === 'multi_select') {
        $options = '[{"value":"north","label":"North"},{"value":"south","label":"South"},{"value":2,"label":"Two"}]';
    }
    if ($type === 'text') {
        $validation = '{"min_length":2,"max_length":20}';
    } elseif ($type === 'integer' || $type === 'decimal') {
        $validation = '{"min":1,"max":100}';
    } elseif ($type === 'date') {
        $validation = '{"min":"2026-01-01","max":"2026-12-31"}';
    } elseif ($type === 'datetime') {
        $validation = '{"min":"2026-01-01T00:00Z","max":"2026-12-31T23:59Z"}';
    } elseif ($type === 'multi_select') {
        $validation = '{"min_items":1,"max_items":2}';
    }
    $row = [
        'id' => '61',
        'contract_type_id' => (string) $typeId,
        'field_code' => 'project.field',
        'data_type' => $type,
        'label' => 'Project Field',
        'is_required' => '1',
        'status' => $status,
        'sort_order' => '10',
        'options_json' => $options,
        'validation_json' => $validation,
        'updated_at' => '2026-08-16 21:00:00',
    ];
    return [array_replace($row, $overrides)];
}

function esc_p5_value_row(string $json = '"hello"', string $type = 'text', string $hash = 'hash', int $isSet = 1): array
{
    return [[
        'id' => '81',
        'contract_id' => '71',
        'definition_id' => '61',
        'is_set' => (string) $isSet,
        'value_json' => $isSet ? $json : null,
        'data_type_snapshot' => $type,
        'definition_config_hash' => $hash,
        'created_by' => '42',
        'updated_by' => '42',
    ]];
}

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0030EnterpriseCustomFieldValues.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/CustomFieldValueRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/CustomFieldValueService.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/CustomFieldValuePolicy.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$contractMigration = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0004Contracts.php');
$bindingMigration = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0028EnterpriseContractConfigurationBindings.php');
$definitionMigration = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0029EnterpriseCustomFieldDefinitions.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0030EnterpriseCustomFieldValues())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);
esc_p5_value_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_custom_field_values'), 'P5-002 creates dedicated value table');
esc_p5_value_assert(str_contains($schema, 'tenant_id bigint(20) unsigned NOT NULL'), 'value tenant ownership is mandatory');
esc_p5_value_assert(str_contains($schema, 'contract_id bigint(20) unsigned NOT NULL'), 'value belongs to a contract');
esc_p5_value_assert(str_contains($schema, 'definition_id bigint(20) unsigned NOT NULL'), 'value belongs to a definition');
esc_p5_value_assert(str_contains($schema, 'is_set tinyint(1) NOT NULL DEFAULT 1'), 'clear state is non-destructive');
esc_p5_value_assert(str_contains($schema, 'data_type_snapshot varchar(30) NOT NULL'), 'value keeps data type snapshot');
esc_p5_value_assert(str_contains($schema, 'definition_config_hash char(64) NOT NULL'), 'value keeps definition configuration hash');
esc_p5_value_assert(str_contains($schema, 'UNIQUE KEY tenant_contract_definition (tenant_id, contract_id, definition_id)'), 'one current value row per tenant contract definition');
esc_p5_value_assert(str_contains($schema, 'KEY tenant_contract_set (tenant_id, contract_id, is_set, definition_id)'), 'contract value listing index is tenant-first');
esc_p5_value_assert(version_compare(Migrator::LATEST_VERSION, '1.29.0', '>='), 'P5-002 schema version remains reachable after later migrations');
esc_p5_value_assert(str_contains($migratorSource, "'1.29.0' => Migration0030EnterpriseCustomFieldValues::class"), 'P5-002 migration is registered at 1.29.0');

$text = esc_p5_value_definition('text')[0];
$canon = CustomFieldValuePolicy::canonicalize($text, ' hello ');
esc_p5_value_assert($canon['value'] === 'hello' && $canon['value_json'] === '"hello"', 'text value is trimmed and canonical JSON');
esc_p5_value_throws(static fn () => CustomFieldValuePolicy::canonicalize($text, 'x'), InvalidArgumentException::class, 'text min length is enforced');
esc_p5_value_throws(static fn () => CustomFieldValuePolicy::canonicalize($text, str_repeat('x', 21)), InvalidArgumentException::class, 'text max length is enforced');

$integer = esc_p5_value_definition('integer')[0];
esc_p5_value_assert(CustomFieldValuePolicy::canonicalize($integer, '010')['value'] === 10, 'integer numeric string canonicalizes to integer');
esc_p5_value_throws(static fn () => CustomFieldValuePolicy::canonicalize($integer, '1.5'), InvalidArgumentException::class, 'integer rejects decimal input');
esc_p5_value_throws(static fn () => CustomFieldValuePolicy::canonicalize($integer, 101), InvalidArgumentException::class, 'integer maximum is enforced');

$decimal = esc_p5_value_definition('decimal')[0];
esc_p5_value_assert(CustomFieldValuePolicy::canonicalize($decimal, '001.2300')['value'] === '1.23', 'decimal is stored as canonical decimal string');
esc_p5_value_throws(static fn () => CustomFieldValuePolicy::canonicalize($decimal, '0.9'), InvalidArgumentException::class, 'decimal minimum is enforced');

$boolean = esc_p5_value_definition('boolean')[0];
esc_p5_value_assert(CustomFieldValuePolicy::canonicalize($boolean, true)['value_json'] === 'true', 'boolean is canonical JSON boolean');
esc_p5_value_throws(static fn () => CustomFieldValuePolicy::canonicalize($boolean, 1), InvalidArgumentException::class, 'boolean rejects numeric truthy values');

$date = esc_p5_value_definition('date')[0];
esc_p5_value_assert(CustomFieldValuePolicy::canonicalize($date, '2026-08-17')['value'] === '2026-08-17', 'date value is canonical');
esc_p5_value_throws(static fn () => CustomFieldValuePolicy::canonicalize($date, '2026-02-30'), InvalidArgumentException::class, 'invalid calendar date fails closed');
esc_p5_value_throws(static fn () => CustomFieldValuePolicy::canonicalize($date, '2027-01-01'), InvalidArgumentException::class, 'date maximum is enforced');

$datetime = esc_p5_value_definition('datetime')[0];
esc_p5_value_assert(CustomFieldValuePolicy::canonicalize($datetime, '2026-08-17T03:00:00+03:00')['value'] === '2026-08-17T00:00:00Z', 'datetime canonicalizes to UTC');

$select = esc_p5_value_definition('select')[0];
esc_p5_value_assert(CustomFieldValuePolicy::canonicalize($select, 2)['value'] === 2, 'select preserves configured scalar type');
esc_p5_value_throws(static fn () => CustomFieldValuePolicy::canonicalize($select, '2'), InvalidArgumentException::class, 'select comparison is type-strict');
esc_p5_value_throws(static fn () => CustomFieldValuePolicy::canonicalize($select, 'east'), InvalidArgumentException::class, 'unknown select option fails closed');

$multi = esc_p5_value_definition('multi_select')[0];
esc_p5_value_assert(CustomFieldValuePolicy::canonicalize($multi, ['north', 2])['value'] === ['north', 2], 'multi-select canonicalizes typed option list');
esc_p5_value_throws(static fn () => CustomFieldValuePolicy::canonicalize($multi, ['north', 'north']), InvalidArgumentException::class, 'multi-select duplicate values fail closed');
esc_p5_value_throws(static fn () => CustomFieldValuePolicy::canonicalize($multi, []), InvalidArgumentException::class, 'multi-select minimum item count is enforced');
esc_p5_value_throws(static fn () => CustomFieldValuePolicy::canonicalize($multi, ['north', 'south', 2]), InvalidArgumentException::class, 'multi-select maximum item count is enforced');

esc_p5_value_assert(strlen(CustomFieldValuePolicy::configurationHash($select)) === 64, 'definition configuration hash is SHA-256 sized');
esc_p5_value_assert(CustomFieldValuePolicy::decodeStored('[1,true,"x"]') === [1, true, 'x'], 'stored value JSON decodes safely');
esc_p5_value_throws(static fn () => CustomFieldValuePolicy::decodeStored('{bad'), InvalidArgumentException::class, 'invalid stored JSON fails closed');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::EDIT_CONTRACTS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::VIEW_ASSIGNED => true,
];
$service = new CustomFieldValueService();

CoreTenantEnforcement::disable();
TenantContextStore::reset();
esc_p5_value_throws(static fn () => $service->get(71, 61), RuntimeException::class, 'value access fails closed outside Enterprise enforcement');

update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
esc_p5_value_throws(static fn () => $service->get(71, 61), RuntimeException::class, 'value access requires locked tenant context');
TenantContextStore::context()->setTenantId(17);

$definition = esc_p5_value_definition('select')[0];
$definitionHash = CustomFieldValuePolicy::configurationHash($definition);
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_value_actor(), esc_p5_value_contract(), esc_p5_value_binding(), [$definition], []];
$service->set(71, 61, 'north');
esc_p5_value_assert(count($GLOBALS['sc_test_queries']) === 1, 'valid value set performs one mutation');
$setSql = (string) ($GLOBALS['sc_test_queries'][0] ?? '');
esc_p5_value_assert(str_contains($setSql, 'INSERT INTO wp_safecontracts_custom_field_values'), 'value set writes only dedicated value table');
esc_p5_value_assert(str_contains($setSql, 'INNER JOIN wp_safecontracts_contract_configuration_bindings b'), 'value set atomically requires P4 binding');
esc_p5_value_assert(str_contains($setSql, 'd.contract_type_id = b.contract_type_id') && str_contains($setSql, "d.status = 'active'"), 'value set atomically requires active definition matching bound type');
esc_p5_value_assert(str_contains($setSql, "c.status = 'draft' AND c.is_archived = 0"), 'value set atomically requires editable draft');
esc_p5_value_assert(str_contains($setSql, "d.field_code = 'project.field' AND d.data_type = 'select'"), 'value set revalidates immutable definition identity');
esc_p5_value_assert(str_contains($setSql, 'COALESCE(d.options_json') && str_contains($setSql, 'COALESCE(d.validation_json'), 'value set revalidates exact options/validation configuration');
esc_p5_value_assert(str_contains($setSql, $definitionHash), 'value set stores configuration hash');
esc_p5_value_assert(str_contains($setSql, 'ON DUPLICATE KEY UPDATE is_set = 1'), 'value set uses atomic upsert');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_value_actor(), esc_p5_value_contract(), esc_p5_value_binding(), [$definition], esc_p5_value_row('"north"', 'select', $definitionHash)];
$service->set(71, 61, 'north');
esc_p5_value_assert($GLOBALS['sc_test_queries'] === [], 'exact value/config set is idempotent without write');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_value_actor(), []];
esc_p5_value_throws(static fn () => $service->set(999, 61, 'north'), InvalidArgumentException::class, 'foreign contract cannot receive values');
esc_p5_value_assert($GLOBALS['sc_test_queries'] === [], 'foreign contract rejection performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_value_actor(), esc_p5_value_contract(), []];
esc_p5_value_throws(static fn () => $service->set(71, 61, 'north'), InvalidArgumentException::class, 'contract without P4 binding cannot receive values');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_value_actor(), esc_p5_value_contract(), esc_p5_value_binding(32), [$definition]];
esc_p5_value_throws(static fn () => $service->set(71, 61, 'north'), InvalidArgumentException::class, 'definition from another Contract Type cannot be used');

$inactive = esc_p5_value_definition('select', 31, 'inactive')[0];
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_value_actor(), esc_p5_value_contract(), esc_p5_value_binding(), [$inactive]];
esc_p5_value_throws(static fn () => $service->set(71, 61, 'north'), InvalidArgumentException::class, 'inactive definition cannot receive new value');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_value_actor(), esc_p5_value_contract('active')];
esc_p5_value_throws(static fn () => $service->set(71, 61, 'north'), DomainException::class, 'post-draft contract cannot mutate values');
esc_p5_value_assert($GLOBALS['sc_test_queries'] === [], 'post-draft mutation denial performs no write');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_value_actor(), esc_p5_value_contract('draft', 42, true)];
esc_p5_value_throws(static fn () => $service->set(71, 61, 'north'), DomainException::class, 'archived draft cannot mutate values');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_value_actor(), esc_p5_value_contract(), esc_p5_value_binding(), [$definition], esc_p5_value_row('"north"', 'select', $definitionHash)];
$service->clear(71, 61);
esc_p5_value_assert(count($GLOBALS['sc_test_queries']) === 1, 'clear performs one non-destructive mutation');
$clearSql = (string) ($GLOBALS['sc_test_queries'][0] ?? '');
esc_p5_value_assert(str_contains($clearSql, 'UPDATE wp_safecontracts_custom_field_values v'), 'clear updates dedicated value row');
esc_p5_value_assert(str_contains($clearSql, 'v.is_set = 0, v.value_json = NULL'), 'clear is non-destructive');
esc_p5_value_assert(str_contains($clearSql, 'EXISTS (') && str_contains($clearSql, "c.status = 'draft' AND c.is_archived = 0"), 'clear atomically revalidates editable contract');
esc_p5_value_assert(str_contains($clearSql, "d.status = 'active'") && str_contains($clearSql, 'd.contract_type_id = b.contract_type_id'), 'clear atomically revalidates definition/binding');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_value_actor(), esc_p5_value_contract(), esc_p5_value_binding(), [$definition], []];
$service->clear(71, 61);
esc_p5_value_assert($GLOBALS['sc_test_queries'] === [], 'clear of absent value is idempotent');

$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_value_actor(), esc_p5_value_contract('active'), esc_p5_value_row('"north"', 'select', $definitionHash)];
$historical = $service->get(71, 61);
esc_p5_value_assert(is_array($historical) && ($historical['value'] ?? null) === 'north', 'historical value remains readable after contract leaves draft');
esc_p5_value_assert(count($GLOBALS['sc_test_read_queries']) === 3, 'historical read does not require current definition/binding validation');

$GLOBALS['sc_test_result_queue'] = [esc_p5_value_actor(), esc_p5_value_contract(), [
    esc_p5_value_row('"north"', 'select', $definitionHash)[0],
    esc_p5_value_row('10', 'integer', 'hash2')[0],
]];
$list = $service->list(71, 999, -1);
esc_p5_value_assert(count($list) === 2 && ($list[1]['value'] ?? null) === 10, 'value listing hydrates canonical stored JSON');
$lastRead = (string) (end($GLOBALS['sc_test_read_queries']) ?: '');
esc_p5_value_assert(str_contains($lastRead, 'LIMIT 500 OFFSET 0'), 'value listing pagination is bounded');

$GLOBALS['sc_test_result_queue'] = [esc_p5_value_actor(), esc_p5_value_contract(), esc_p5_value_binding(), [
    ['id' => '62', 'field_code' => 'project.code', 'data_type' => 'text', 'label' => 'Project Code', 'sort_order' => '1'],
]];
$missing = $service->missingRequired(71, 999);
esc_p5_value_assert(count($missing) === 1 && ($missing[0]['field_code'] ?? '') === 'project.code', 'required completeness returns missing active required definitions');
$missingSql = (string) (end($GLOBALS['sc_test_read_queries']) ?: '');
esc_p5_value_assert(str_contains($missingSql, "d.status = 'active' AND d.is_required = 1"), 'required completeness uses active required definitions');
esc_p5_value_assert(str_contains($missingSql, 'v.is_set = 1') && str_contains($missingSql, 'v.id IS NULL'), 'required completeness treats cleared/absent values as missing');
esc_p5_value_assert(str_contains($missingSql, 'LIMIT 500'), 'required completeness is bounded');

$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ALL] = false;
$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ASSIGNED] = true;
$GLOBALS['sc_test_result_queue'] = [esc_p5_value_actor(), esc_p5_value_contract('draft', 99)];
esc_p5_value_throws(static fn () => $service->get(71, 61), DomainException::class, 'assigned-scope user cannot read another accountant contract');
$GLOBALS['sc_test_result_queue'] = [esc_p5_value_actor(), esc_p5_value_contract('draft', 42), esc_p5_value_row('"north"', 'select', $definitionHash)];
esc_p5_value_assert(($service->get(71, 61)['value'] ?? null) === 'north', 'assigned-scope user can read own assigned contract value');

$GLOBALS['sc_test_current_caps'][Capabilities::EDIT_CONTRACTS] = false;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p5_value_throws(static fn () => $service->set(71, 61, 'north'), DomainException::class, 'value mutation requires EDIT_CONTRACTS');
esc_p5_value_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'global mutation denial occurs before data access');

$GLOBALS['sc_test_current_caps'][Capabilities::EDIT_CONTRACTS] = true;
$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ALL] = true;
$GLOBALS['sc_test_result_queue'] = [esc_p5_value_actor('viewer')];
esc_p5_value_throws(static fn () => $service->set(71, 61, 'north'), DomainException::class, 'tenant viewer cannot bypass value mutation ceiling');

esc_p5_value_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()') && str_contains($repositorySource, 'requireTenantId()'), 'value repository has no unscoped tenant fallback');
esc_p5_value_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'value service enforces tenant-role capability ceiling');
esc_p5_value_assert(! str_contains($migrationSource, 'ALTER TABLE'), 'P5-002 migration is additive');
esc_p5_value_assert(! str_contains($contractMigration, 'custom_field_values'), 'legacy contract migration remains unchanged');
esc_p5_value_assert(! str_contains($bindingMigration, 'custom_field_values'), 'P4 binding migration remains unchanged');
esc_p5_value_assert(! str_contains($definitionMigration, 'custom_field_values'), 'P5 definition migration remains unchanged');
esc_p5_value_assert(! str_contains($policySource, 'eval(') && ! str_contains($policySource, 'exec('), 'value policy contains no executable expression engine');

echo "P5-002 Enterprise Dynamic Field value checks passed ({$assertions} assertions).\n";
