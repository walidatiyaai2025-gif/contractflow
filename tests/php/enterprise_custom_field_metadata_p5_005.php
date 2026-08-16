<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\CustomFields\CustomFieldMetadataPolicy;
use SafeContracts\CustomFields\CustomFieldMetadataService;
use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0032EnterpriseCustomFieldMetadata;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p5_meta_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p5_meta_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p5_meta_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ': ' . $error->getMessage() . ')');
        return;
    }
    esc_p5_meta_assert(false, $message . ' (no exception)');
}

function esc_p5_meta_actor(string $role = 'tenant_admin'): array
{
    return [[
        'id' => '1', 'tenant_id' => '17', 'user_id' => '42',
        'role_code' => $role, 'is_owner' => '0',
    ]];
}

function esc_p5_meta_definition(int $id = 61, string $type = 'text', string $status = 'active'): array
{
    return [[
        'id' => (string) $id,
        'uuid' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
        'contract_type_id' => '31',
        'field_code' => 'project.field',
        'data_type' => $type,
        'label' => 'Project Field',
        'help_text' => 'Metadata field',
        'is_required' => '0',
        'status' => $status,
        'sort_order' => '10',
        'options_json' => '',
        'validation_json' => '',
        'created_by' => '42',
        'updated_by' => '42',
    ]];
}

function esc_p5_meta_row(string $type = 'text', array $overrides = []): array
{
    $row = [
        'id' => '91',
        'definition_id' => '61',
        'data_type_snapshot' => $type,
        'show_in_form' => '1',
        'show_in_summary' => '1',
        'show_in_mobile' => '1',
        'show_in_print' => '0',
        'filterable' => '1',
        'sortable' => '1',
        'groupable' => '0',
        'exportable' => '1',
        'dashboard_visible' => '0',
        'report_label' => 'Project Field Report',
        'report_data_class' => $type === 'decimal' ? 'measure' : 'text',
        'aggregation_policy' => $type === 'decimal' ? 'sum' : 'none',
        'created_by' => '42',
        'updated_by' => '42',
    ];
    return [array_replace($row, $overrides)];
}

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0032EnterpriseCustomFieldMetadata.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/CustomFieldMetadataPolicy.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/CustomFieldMetadataRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/CustomFieldMetadataService.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$valueMigration = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0030EnterpriseCustomFieldValues.php');
$templateFieldMigration = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0031EnterpriseTemplateFieldSets.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0032EnterpriseCustomFieldMetadata())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);
esc_p5_meta_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_custom_field_metadata'), 'P5-005 creates dedicated metadata table');
esc_p5_meta_assert(str_contains($schema, 'tenant_id bigint(20) unsigned NOT NULL'), 'metadata tenant ownership is mandatory');
esc_p5_meta_assert(str_contains($schema, 'definition_id bigint(20) unsigned NOT NULL'), 'metadata belongs to Dynamic Field definition');
esc_p5_meta_assert(str_contains($schema, 'data_type_snapshot varchar(30) NOT NULL'), 'metadata stores immutable data type snapshot');
esc_p5_meta_assert(str_contains($schema, 'show_in_form tinyint(1) NOT NULL DEFAULT 1'), 'form visibility default is explicit');
esc_p5_meta_assert(str_contains($schema, 'show_in_mobile tinyint(1) NOT NULL DEFAULT 1'), 'mobile visibility default is explicit');
esc_p5_meta_assert(str_contains($schema, 'filterable tinyint(1) NOT NULL DEFAULT 0'), 'filtering eligibility defaults closed');
esc_p5_meta_assert(str_contains($schema, 'exportable tinyint(1) NOT NULL DEFAULT 0'), 'export eligibility defaults closed');
esc_p5_meta_assert(str_contains($schema, 'report_data_class varchar(30) NOT NULL'), 'report data class is explicit');
esc_p5_meta_assert(str_contains($schema, 'aggregation_policy varchar(20) NOT NULL'), 'aggregation policy is explicit');
esc_p5_meta_assert(str_contains($schema, 'UNIQUE KEY tenant_definition (tenant_id, definition_id)'), 'one metadata row per tenant definition');
esc_p5_meta_assert(Migrator::LATEST_VERSION === '1.31.0', 'P5-005 is current schema version');
esc_p5_meta_assert(str_contains($migratorSource, "'1.31.0' => Migration0032EnterpriseCustomFieldMetadata::class"), 'P5-005 migration is registered');
esc_p5_meta_assert(! str_contains($migrationSource, 'ALTER TABLE'), 'P5-005 migration is additive');

$textDefaults = CustomFieldMetadataPolicy::defaults('text');
esc_p5_meta_assert($textDefaults['show_in_form'] === true && $textDefaults['show_in_mobile'] === true, 'form/mobile defaults are deterministic');
esc_p5_meta_assert($textDefaults['show_in_summary'] === false && $textDefaults['show_in_print'] === false, 'secondary presentation defaults closed');
esc_p5_meta_assert($textDefaults['filterable'] === false && $textDefaults['sortable'] === false && $textDefaults['exportable'] === false, 'reporting defaults closed');
esc_p5_meta_assert($textDefaults['report_data_class'] === 'text' && $textDefaults['aggregation_policy'] === 'none', 'text report defaults are deterministic');
esc_p5_meta_assert(CustomFieldMetadataPolicy::defaults('decimal')['report_data_class'] === 'measure', 'numeric default report class is measure');
esc_p5_meta_assert(CustomFieldMetadataPolicy::defaults('date')['report_data_class'] === 'date', 'date default report class is date');
esc_p5_meta_assert(count(CustomFieldMetadataPolicy::reportDataClasses()) === 5, 'report class allowlist is bounded');
esc_p5_meta_assert(count(CustomFieldMetadataPolicy::aggregationPolicies()) === 5, 'aggregation allowlist is bounded');

$numeric = CustomFieldMetadataPolicy::normalize('decimal', [
    'show_in_summary' => true,
    'filterable' => true,
    'sortable' => true,
    'exportable' => true,
    'dashboard_visible' => true,
    'report_label' => ' Amount ',
    'report_data_class' => 'MEASURE',
    'aggregation_policy' => 'SUM',
]);
esc_p5_meta_assert($numeric['report_label'] === 'Amount' && $numeric['aggregation_policy'] === 'sum', 'numeric metadata normalizes deterministically');
esc_p5_meta_throws(static fn () => CustomFieldMetadataPolicy::normalize('text', ['report_data_class' => 'measure']), InvalidArgumentException::class, 'text cannot masquerade as measure');
esc_p5_meta_throws(static fn () => CustomFieldMetadataPolicy::normalize('date', ['aggregation_policy' => 'sum']), InvalidArgumentException::class, 'date cannot aggregate numerically');
esc_p5_meta_throws(static fn () => CustomFieldMetadataPolicy::normalize('decimal', ['report_data_class' => 'text']), InvalidArgumentException::class, 'numeric field rejects incompatible text class');
esc_p5_meta_throws(static fn () => CustomFieldMetadataPolicy::normalize('long_text', ['sortable' => true]), InvalidArgumentException::class, 'long text cannot claim sortable eligibility');
esc_p5_meta_throws(static fn () => CustomFieldMetadataPolicy::normalize('multi_select', ['groupable' => true]), InvalidArgumentException::class, 'multi-select cannot claim groupable eligibility');
esc_p5_meta_throws(static fn () => CustomFieldMetadataPolicy::normalize('text', ['show_in_form' => 1]), InvalidArgumentException::class, 'visibility flags require booleans');
esc_p5_meta_throws(static fn () => CustomFieldMetadataPolicy::normalize('text', ['report_label' => str_repeat('x', 192)]), InvalidArgumentException::class, 'report label is bounded');
esc_p5_meta_throws(static fn () => CustomFieldMetadataPolicy::normalize('text', ['expression' => 'value > 10']), InvalidArgumentException::class, 'executable/unknown metadata property fails closed');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::MANAGE_REFERENCE_DATA => true];
$service = new CustomFieldMetadataService();

CoreTenantEnforcement::disable();
TenantContextStore::reset();
esc_p5_meta_throws(static fn () => $service->get(61), RuntimeException::class, 'metadata access fails closed outside Enterprise enforcement');
update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
esc_p5_meta_throws(static fn () => $service->get(61), RuntimeException::class, 'metadata access requires locked tenant context');
TenantContextStore::context()->setTenantId(17);

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_meta_actor(), esc_p5_meta_definition(), []];
$defaults = $service->get(61);
esc_p5_meta_assert(($defaults['is_default'] ?? false) === true, 'missing metadata resolves to explicit defaults');
esc_p5_meta_assert(($defaults['show_in_form'] ?? false) === true && ($defaults['filterable'] ?? true) === false, 'resolved defaults are conservative');
esc_p5_meta_assert($GLOBALS['sc_test_queries'] === [], 'default read performs no mutation');
esc_p5_meta_assert(str_contains($GLOBALS['sc_test_read_queries'][1] ?? '', 'WHERE id = 61 AND tenant_id = 17'), 'definition read is tenant scoped');
esc_p5_meta_assert(str_contains($GLOBALS['sc_test_read_queries'][2] ?? '', 'WHERE tenant_id = 17 AND definition_id = 61'), 'metadata read is tenant scoped');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_meta_actor(), esc_p5_meta_definition(), []];
$noOp = $service->upsert(61, []);
esc_p5_meta_assert(($noOp['is_default'] ?? false) === true, 'absent exact-default upsert remains default');
esc_p5_meta_assert($GLOBALS['sc_test_queries'] === [], 'exact default upsert is storage-free idempotent');

$customInput = [
    'show_in_summary' => true,
    'filterable' => true,
    'sortable' => true,
    'exportable' => true,
    'report_label' => 'Project Field Report',
];
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_meta_actor(), esc_p5_meta_definition(), []];
$stored = $service->upsert(61, $customInput);
esc_p5_meta_assert(($stored['is_default'] ?? true) === false, 'non-default metadata persists');
esc_p5_meta_assert(count($GLOBALS['sc_test_queries']) === 1, 'non-default metadata performs one mutation');
$upsertSql = (string) ($GLOBALS['sc_test_queries'][0] ?? '');
esc_p5_meta_assert(str_contains($upsertSql, 'INSERT INTO wp_safecontracts_custom_field_metadata'), 'metadata writes dedicated table');
esc_p5_meta_assert(str_contains($upsertSql, 'FROM wp_safecontracts_custom_field_definitions d'), 'metadata write derives from live definition');
esc_p5_meta_assert(str_contains($upsertSql, "d.id = 61 AND d.tenant_id = 17 AND d.status = 'active' AND d.data_type = 'text'"), 'upsert atomically revalidates tenant/status/type');
esc_p5_meta_assert(str_contains($upsertSql, 'ON DUPLICATE KEY UPDATE'), 'metadata persistence is atomic upsert');
esc_p5_meta_assert(str_contains($upsertSql, "'Project Field Report'"), 'validated report label persists');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_meta_actor(), esc_p5_meta_definition(), esc_p5_meta_row()];
$existing = $service->get(61);
esc_p5_meta_assert(($existing['is_default'] ?? true) === false && ($existing['show_in_summary'] ?? false) === true, 'stored metadata hydrates typed flags');
esc_p5_meta_assert(($existing['report_label'] ?? '') === 'Project Field Report', 'stored report label hydrates');
esc_p5_meta_assert($GLOBALS['sc_test_queries'] === [], 'stored read remains read-only');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_meta_actor(), esc_p5_meta_definition(), esc_p5_meta_row()];
$service->upsert(61, $customInput);
esc_p5_meta_assert($GLOBALS['sc_test_queries'] === [], 'exact stored metadata upsert is idempotent');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_meta_actor(), []];
esc_p5_meta_throws(static fn () => $service->upsert(999, ['filterable' => true]), InvalidArgumentException::class, 'foreign definition fails closed');
esc_p5_meta_assert($GLOBALS['sc_test_queries'] === [], 'foreign definition rejection performs no write');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_meta_actor(), esc_p5_meta_definition(61, 'text', 'inactive')];
esc_p5_meta_throws(static fn () => $service->upsert(61, ['filterable' => true]), InvalidArgumentException::class, 'inactive definition cannot mutate metadata');
esc_p5_meta_assert($GLOBALS['sc_test_queries'] === [], 'inactive definition rejection performs no write');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_meta_actor(), esc_p5_meta_definition(61, 'decimal'), []];
$service->upsert(61, ['report_data_class' => 'measure', 'aggregation_policy' => 'sum']);
$numericSql = (string) ($GLOBALS['sc_test_queries'][0] ?? '');
esc_p5_meta_assert(str_contains($numericSql, "d.data_type = 'decimal'"), 'numeric metadata guard locks decimal type');
esc_p5_meta_assert(str_contains($numericSql, "'measure', 'sum'"), 'numeric aggregation metadata remains declarative');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_meta_actor(), esc_p5_meta_definition(), esc_p5_meta_row()];
$reset = $service->reset(61);
esc_p5_meta_assert(($reset['is_default'] ?? false) === true, 'reset returns deterministic defaults');
esc_p5_meta_assert(count($GLOBALS['sc_test_queries']) === 1, 'reset existing metadata performs one mutation');
$resetSql = (string) ($GLOBALS['sc_test_queries'][0] ?? '');
esc_p5_meta_assert(str_contains($resetSql, 'DELETE m FROM wp_safecontracts_custom_field_metadata m'), 'reset deletes only metadata override');
esc_p5_meta_assert(str_contains($resetSql, 'INNER JOIN wp_safecontracts_custom_field_definitions d'), 'reset atomically revalidates definition');
esc_p5_meta_assert(str_contains($resetSql, "m.tenant_id = 17 AND m.definition_id = 61 AND d.status = 'active' AND d.data_type = 'text'"), 'reset is tenant/status/type guarded');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_meta_actor(), esc_p5_meta_definition(), []];
$service->reset(61);
esc_p5_meta_assert($GLOBALS['sc_test_queries'] === [], 'reset absent metadata is idempotent');

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = false;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p5_meta_throws(static fn () => $service->upsert(61, ['filterable' => true]), DomainException::class, 'mutation requires MANAGE_REFERENCE_DATA');
esc_p5_meta_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'global capability denial occurs before data access');
$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = true;
$GLOBALS['sc_test_result_queue'] = [esc_p5_meta_actor('viewer')];
esc_p5_meta_throws(static fn () => $service->upsert(61, ['filterable' => true]), DomainException::class, 'tenant viewer cannot bypass metadata mutation ceiling');

esc_p5_meta_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()') && str_contains($repositorySource, 'requireTenantId()'), 'metadata repository has no unscoped tenant fallback');
esc_p5_meta_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'metadata service enforces tenant-role ceiling');
esc_p5_meta_assert(! str_contains($serviceSource, '$wpdb') && ! str_contains($serviceSource, '->query('), 'metadata service delegates persistence and executes no report/database query');
esc_p5_meta_assert(! str_contains($migrationSource, 'safecontracts_contracts'), 'P5-005 schema does not alter contracts');
esc_p5_meta_assert(! str_contains($migrationSource, 'safecontracts_custom_field_values'), 'P5-005 schema does not alter P5 values');
esc_p5_meta_assert(! str_contains($migrationSource, 'safecontracts_contract_template_version_fields'), 'P5-005 schema does not alter P5-004 snapshots');
esc_p5_meta_assert(! str_contains($valueMigration, 'custom_field_metadata'), 'P5-002 migration remains unchanged');
esc_p5_meta_assert(! str_contains($templateFieldMigration, 'custom_field_metadata'), 'P5-004 migration remains unchanged');
esc_p5_meta_assert(! str_contains($policySource, 'eval(') && ! str_contains($policySource, 'exec('), 'metadata policy introduces no executable engine');

CoreTenantEnforcement::disable();
TenantContextStore::reset();

echo "P5-005 Enterprise Dynamic Field metadata checks passed ({$assertions} assertions).\n";
