<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\CustomFields\CustomFieldCalculationPolicy;
use SafeContracts\CustomFields\CustomFieldCalculationRepository;
use SafeContracts\CustomFields\CustomFieldCalculationService;
use SafeContracts\CustomFields\CustomFieldValuePolicy;
use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0034EnterpriseCustomFieldCalculationRules;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p5_calc_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p5_calc_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p5_calc_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ': ' . $error->getMessage() . ')');
        return;
    }
    esc_p5_calc_assert(false, $message . ' (no exception)');
}

function esc_p5_calc_actor(string $role = 'tenant_admin'): array
{
    return [[
        'id' => '1', 'tenant_id' => '17', 'user_id' => '42',
        'role_code' => $role, 'is_owner' => '0',
    ]];
}

function esc_p5_calc_definition(int $id, string $type, int $contractType = 31, string $status = 'active'): array
{
    return [[
        'id' => (string) $id,
        'uuid' => sprintf('cccccccc-cccc-4ccc-8ccc-%012d', $id),
        'contract_type_id' => (string) $contractType,
        'field_code' => 'calc.' . $id,
        'data_type' => $type,
        'label' => 'Calc ' . $id,
        'help_text' => '',
        'is_required' => '0',
        'status' => $status,
        'sort_order' => (string) $id,
        'options_json' => '',
        'validation_json' => '',
        'created_by' => '42', 'updated_by' => '42',
    ]];
}

function esc_p5_calc_contract(int $accountant = 42): array
{
    return [[
        'id' => '71', 'accountant_user_id' => (string) $accountant,
        'status' => 'draft', 'is_archived' => '0',
    ]];
}

function esc_p5_calc_binding(int $typeId = 31): array
{
    return [['contract_id' => '71', 'contract_type_id' => (string) $typeId]];
}

function esc_p5_calc_rule(array $expression, string $targetType = 'decimal', ?string $hash = null): array
{
    $target = esc_p5_calc_definition(61, $targetType)[0];
    $normalized = CustomFieldCalculationPolicy::normalizeExpression($expression);
    return [[
        'id' => '501',
        'target_definition_id' => '61',
        'contract_type_id' => '31',
        'target_field_code_snapshot' => $target['field_code'],
        'target_data_type_snapshot' => $target['data_type'],
        'target_config_hash' => $hash ?? CustomFieldValuePolicy::configurationHash($target),
        'expression_json' => $normalized['expression_json'],
        'created_by' => '42', 'updated_by' => '42',
    ]];
}

function esc_p5_calc_dependency(int $position, int $sourceId, string $type, int $contractType = 31, string $status = 'active', ?string $hash = null): array
{
    $source = esc_p5_calc_definition($sourceId, $type, $contractType, $status)[0];
    return [
        'id' => (string) (600 + $position),
        'rule_id' => '501',
        'target_definition_id' => '61',
        'position_no' => (string) $position,
        'source_definition_id' => (string) $sourceId,
        'source_field_code_snapshot' => $source['field_code'],
        'source_data_type_snapshot' => $source['data_type'],
        'source_config_hash' => $hash ?? CustomFieldValuePolicy::configurationHash($source),
        'current_source_id' => (string) $sourceId,
        'current_contract_type_id' => (string) $contractType,
        'current_field_code' => $source['field_code'],
        'current_data_type' => $source['data_type'],
        'current_status' => $status,
        'current_options_json' => $source['options_json'],
        'current_validation_json' => $source['validation_json'],
    ];
}

function esc_p5_calc_value(int $definitionId, string $type, string $valueJson, int $isSet = 1, ?string $hash = null): array
{
    $definition = esc_p5_calc_definition($definitionId, $type)[0];
    return [
        'id' => (string) (700 + $definitionId),
        'contract_id' => '71',
        'definition_id' => (string) $definitionId,
        'is_set' => (string) $isSet,
        'value_json' => $isSet ? $valueJson : null,
        'data_type_snapshot' => $type,
        'definition_config_hash' => $hash ?? CustomFieldValuePolicy::configurationHash($definition),
    ];
}

function esc_p5_calc_binary_tree(int $levels): array
{
    if ($levels <= 1) {
        return ['kind' => 'constant', 'value' => '1'];
    }
    return [
        'kind' => 'add',
        'children' => [esc_p5_calc_binary_tree($levels - 1), esc_p5_calc_binary_tree($levels - 1)],
    ];
}

final class ESC_P5_Calc_Failing_Wpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 0;
    public array $queries = [];
    public array $reads = [];

    public function prepare(string $query, mixed ...$args): string
    {
        $prepared = array_map(
            static fn (mixed $value): mixed => is_int($value) ? $value : "'" . addslashes((string) $value) . "'",
            $args
        );
        return vsprintf($query, $prepared);
    }

    public function query(string $sql): int|false
    {
        $this->queries[] = $sql;
        $trimmed = ltrim($sql);
        if (str_starts_with($trimmed, 'INSERT INTO wp_safecontracts_custom_field_calculation_rules')) {
            $this->insert_id = 2001;
            return 1;
        }
        if (str_starts_with($trimmed, 'INSERT INTO wp_safecontracts_custom_field_calculation_dependencies')) {
            return 0;
        }
        return 1;
    }

    public function get_results(string $sql, mixed $output = null): array
    {
        unset($output);
        $this->reads[] = $sql;
        if (str_contains($sql, 'FOR UPDATE') && str_contains($sql, 'safecontracts_custom_field_definitions')) {
            return [['id' => '61'], ['id' => '62']];
        }
        return [];
    }
}

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0034EnterpriseCustomFieldCalculationRules.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/CustomFieldCalculationPolicy.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/CustomFieldCalculationRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/CustomFieldCalculationService.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');
$valueSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/CustomFieldValueRepository.php');
$templateSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/TemplateFieldSetRepository.php');
$metadataSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/CustomFieldMetadataRepository.php');
$visibilitySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/CustomFieldVisibilityRepository.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0034EnterpriseCustomFieldCalculationRules())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);
esc_p5_calc_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_custom_field_calculation_rules'), 'P5-007 creates calculation rule table');
esc_p5_calc_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_custom_field_calculation_dependencies'), 'P5-007 creates calculation dependency table');
esc_p5_calc_assert(str_contains($schema, 'tenant_id bigint(20) unsigned NOT NULL'), 'calculation rows are tenant owned');
esc_p5_calc_assert(str_contains($schema, 'expression_json longtext NOT NULL'), 'canonical AST JSON has dedicated storage');
esc_p5_calc_assert(str_contains($schema, 'target_config_hash char(64) NOT NULL'), 'target configuration snapshot is persisted');
esc_p5_calc_assert(str_contains($schema, 'source_config_hash char(64) NOT NULL'), 'source configuration snapshot is persisted');
esc_p5_calc_assert(str_contains($schema, 'UNIQUE KEY tenant_target (tenant_id, target_definition_id)'), 'one calculation rule exists per tenant target');
esc_p5_calc_assert(str_contains($schema, 'UNIQUE KEY tenant_rule_source (tenant_id, rule_id, source_definition_id)'), 'dependencies are unique per rule/source');
esc_p5_calc_assert(str_contains($schema, 'UNIQUE KEY tenant_rule_position (tenant_id, rule_id, position_no)'), 'dependency snapshot ordering is deterministic');
esc_p5_calc_assert(Migrator::LATEST_VERSION === '1.33.0', 'P5-007 is current schema version');
esc_p5_calc_assert(str_contains($migratorSource, "'1.33.0' => Migration0034EnterpriseCustomFieldCalculationRules::class"), 'P5-007 migration is registered');
esc_p5_calc_assert(! str_contains($migrationSource, 'ALTER TABLE'), 'P5-007 migration is additive');

$normalized = CustomFieldCalculationPolicy::normalizeExpression([
    'kind' => ' ADD ',
    'children' => [
        ['kind' => 'field', 'definition_id' => '00062'],
        ['kind' => 'constant', 'value' => '0010.5000'],
        ['kind' => 'field', 'definition_id' => 62],
    ],
]);
esc_p5_calc_assert($normalized['dependencies'] === [62], 'duplicate field references extract one canonical dependency');
esc_p5_calc_assert($normalized['ast']['kind'] === 'add', 'AST kind normalizes to allowlisted canonical form');
esc_p5_calc_assert(($normalized['ast']['children'][0]['definition_id'] ?? 0) === 62, 'source IDs canonicalize to positive integers');
esc_p5_calc_assert(($normalized['ast']['children'][1]['value'] ?? '') === '10.5', 'constants canonicalize as plain decimals');
esc_p5_calc_assert($normalized['expression_json'] === '{"kind":"add","children":[{"kind":"field","definition_id":62},{"kind":"constant","value":"10.5"},{"kind":"field","definition_id":62}]}', 'canonical AST JSON is deterministic');

esc_p5_calc_throws(static fn () => CustomFieldCalculationPolicy::normalizeExpression(['kind' => 'divide', 'children' => []]), InvalidArgumentException::class, 'divide is unsupported and fails closed');
esc_p5_calc_throws(static fn () => CustomFieldCalculationPolicy::normalizeExpression(['kind' => 'constant', 'value' => '1', 'code' => 'php']), InvalidArgumentException::class, 'extra executable-like AST properties fail closed');
esc_p5_calc_throws(static fn () => CustomFieldCalculationPolicy::normalizeExpression(['kind' => 'constant', 'value' => 0.1]), InvalidArgumentException::class, 'binary floating point constants are rejected');
esc_p5_calc_throws(static fn () => CustomFieldCalculationPolicy::canonicalNumber('1e3'), InvalidArgumentException::class, 'scientific notation is rejected');
esc_p5_calc_throws(static fn () => CustomFieldCalculationPolicy::canonicalNumber('0.1234567890123'), InvalidArgumentException::class, 'fractional scale above 12 fails closed');
esc_p5_calc_throws(static fn () => CustomFieldCalculationPolicy::canonicalNumber(str_repeat('9', 39)), InvalidArgumentException::class, 'precision above 38 digits fails closed');
esc_p5_calc_throws(static fn () => CustomFieldCalculationPolicy::normalizeExpression(['kind' => 'add', 'children' => [['kind' => 'constant', 'value' => 1]]]), InvalidArgumentException::class, 'add lower arity is bounded');
esc_p5_calc_throws(static fn () => CustomFieldCalculationPolicy::normalizeExpression(['kind' => 'multiply', 'children' => array_fill(0, 9, ['kind' => 'constant', 'value' => 1])]), InvalidArgumentException::class, 'multiply upper arity is bounded');
esc_p5_calc_throws(static fn () => CustomFieldCalculationPolicy::normalizeExpression(['kind' => 'subtract', 'children' => [['kind' => 'constant', 'value' => 1]]]), InvalidArgumentException::class, 'subtract requires exactly two children');
esc_p5_calc_throws(static fn () => CustomFieldCalculationPolicy::normalizeExpression(['kind' => 'negate', 'children' => []]), InvalidArgumentException::class, 'negate requires exactly one child');

$deep = ['kind' => 'constant', 'value' => '1'];
for ($i = 0; $i < 16; $i++) {
    $deep = ['kind' => 'negate', 'children' => [$deep]];
}
esc_p5_calc_throws(static fn () => CustomFieldCalculationPolicy::normalizeExpression($deep), InvalidArgumentException::class, 'AST depth above 16 fails closed');
esc_p5_calc_throws(static fn () => CustomFieldCalculationPolicy::normalizeExpression(esc_p5_calc_binary_tree(8)), InvalidArgumentException::class, 'AST node count above 128 fails closed');

$dependencyGroups = [];
$dependencyId = 100;
for ($group = 0; $group < 3; $group++) {
    $children = [];
    for ($item = 0; $item < 11; $item++) {
        $children[] = ['kind' => 'field', 'definition_id' => $dependencyId++];
    }
    $dependencyGroups[] = ['kind' => 'add', 'children' => $children];
}
esc_p5_calc_throws(static fn () => CustomFieldCalculationPolicy::normalizeExpression(['kind' => 'add', 'children' => $dependencyGroups]), InvalidArgumentException::class, 'unique dependency count above 32 fails closed');

esc_p5_calc_assert(CustomFieldCalculationPolicy::evaluate(['kind' => 'add', 'children' => [
    ['kind' => 'constant', 'value' => '0.1'], ['kind' => 'constant', 'value' => '0.2'],
]], []) === '0.3', 'decimal addition is exact without binary floating point');
esc_p5_calc_assert(CustomFieldCalculationPolicy::evaluate(['kind' => 'subtract', 'children' => [
    ['kind' => 'constant', 'value' => '5'], ['kind' => 'constant', 'value' => '7.25'],
]], []) === '-2.25', 'decimal subtraction is exact');
esc_p5_calc_assert(CustomFieldCalculationPolicy::evaluate(['kind' => 'multiply', 'children' => [
    ['kind' => 'constant', 'value' => '1.2'], ['kind' => 'constant', 'value' => '-3.4'],
]], []) === '-4.08', 'decimal multiplication is exact');
esc_p5_calc_assert(CustomFieldCalculationPolicy::evaluate(['kind' => 'negate', 'children' => [
    ['kind' => 'field', 'definition_id' => 62],
]], [62 => '12.50']) === '-12.5', 'field references evaluate from canonical supplied values');
esc_p5_calc_throws(static fn () => CustomFieldCalculationPolicy::evaluate(['kind' => 'field', 'definition_id' => 62], []), InvalidArgumentException::class, 'missing source never becomes implicit zero');
esc_p5_calc_throws(static fn () => CustomFieldCalculationPolicy::evaluate(['kind' => 'add', 'children' => [
    ['kind' => 'constant', 'value' => str_repeat('9', 38)], ['kind' => 'constant', 'value' => '1'],
]], []), InvalidArgumentException::class, 'result precision overflow fails closed');
esc_p5_calc_throws(static fn () => CustomFieldCalculationPolicy::evaluate(['kind' => 'multiply', 'children' => [
    ['kind' => 'constant', 'value' => '0.0000001'], ['kind' => 'constant', 'value' => '0.0000001'],
]], []), InvalidArgumentException::class, 'result scale overflow fails closed');
esc_p5_calc_assert(CustomFieldCalculationPolicy::numericSourceValue('integer', 7) === '7', 'integer P5 source converts to calculation decimal exactly');
esc_p5_calc_assert(CustomFieldCalculationPolicy::numericSourceValue('decimal', '7.500') === '7.5', 'decimal P5 source canonicalizes without float conversion');
esc_p5_calc_throws(static fn () => CustomFieldCalculationPolicy::numericSourceValue('text', '7'), InvalidArgumentException::class, 'non-numeric source type fails closed');
esc_p5_calc_assert(CustomFieldCalculationPolicy::isIntegral('-12') && ! CustomFieldCalculationPolicy::isIntegral('12.5'), 'integral-result contract is explicit');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::MANAGE_REFERENCE_DATA => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::VIEW_ASSIGNED => true,
];
$service = new CustomFieldCalculationService();

CoreTenantEnforcement::disable();
TenantContextStore::reset();
esc_p5_calc_throws(static fn () => $service->getRule(61), RuntimeException::class, 'calculation access fails closed outside Enterprise enforcement');
update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
esc_p5_calc_throws(static fn () => $service->getRule(61), RuntimeException::class, 'calculation access requires locked tenant');
TenantContextStore::context()->setTenantId(17);

$expression = [
    'kind' => 'add',
    'children' => [
        ['kind' => 'field', 'definition_id' => 62],
        ['kind' => 'multiply', 'children' => [
            ['kind' => 'field', 'definition_id' => 63],
            ['kind' => 'constant', 'value' => '2.5'],
        ]],
    ],
];

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p5_calc_actor(),
    esc_p5_calc_definition(61, 'decimal'),
    esc_p5_calc_definition(62, 'integer'),
    esc_p5_calc_definition(63, 'decimal'),
    [],
    [['id' => '61'], ['id' => '62'], ['id' => '63']],
];
$GLOBALS['wpdb']->insert_id = 0;
$service->replaceRule(61, $expression);
esc_p5_calc_assert(($GLOBALS['sc_test_queries'][0] ?? '') === 'START TRANSACTION', 'calculation replacement starts transaction');
$lockSql = (string) ($GLOBALS['sc_test_read_queries'][5] ?? '');
esc_p5_calc_assert(str_contains($lockSql, 'ORDER BY id ASC FOR UPDATE'), 'target/source definitions lock deterministically');
esc_p5_calc_assert(str_contains($lockSql, "status = 'active'") && str_contains($lockSql, "data_type IN ('integer','decimal')"), 'definition lock is active numeric same-Type scoped');
$ruleSql = (string) ($GLOBALS['sc_test_queries'][1] ?? '');
esc_p5_calc_assert(str_contains($ruleSql, 'INSERT INTO wp_safecontracts_custom_field_calculation_rules'), 'calculation rule persists in dedicated table');
esc_p5_calc_assert(str_contains($ruleSql, 'id = LAST_INSERT_ID(id)'), 'calculation rule upsert returns stable existing ID');
$expectedExpressionSql = addslashes(CustomFieldCalculationPolicy::normalizeExpression($expression)['expression_json']);
esc_p5_calc_assert(str_contains($ruleSql, $expectedExpressionSql), 'canonical AST JSON is persisted');
$deleteSql = (string) ($GLOBALS['sc_test_queries'][2] ?? '');
esc_p5_calc_assert(str_contains($deleteSql, 'DELETE FROM wp_safecontracts_custom_field_calculation_dependencies'), 'replacement removes old dedicated dependencies inside transaction');
esc_p5_calc_assert(str_contains((string) ($GLOBALS['sc_test_queries'][3] ?? ''), "d.data_type IN ('integer','decimal')"), 'source dependency insert atomically revalidates numeric type');
esc_p5_calc_assert(($GLOBALS['sc_test_queries'][5] ?? '') === 'COMMIT', 'valid calculation replacement commits atomically');
esc_p5_calc_assert(! in_array('ROLLBACK', $GLOBALS['sc_test_queries'], true), 'valid calculation replacement does not roll back');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_calc_actor(), esc_p5_calc_definition(61, 'decimal')];
esc_p5_calc_throws(static fn () => $service->replaceRule(61, ['kind' => 'field', 'definition_id' => 61]), InvalidArgumentException::class, 'self calculation dependency fails closed before mutation');
esc_p5_calc_assert($GLOBALS['sc_test_queries'] === [], 'self dependency performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_calc_actor(), esc_p5_calc_definition(61, 'decimal'), esc_p5_calc_definition(62, 'text')];
esc_p5_calc_throws(static fn () => $service->replaceRule(61, ['kind' => 'field', 'definition_id' => 62]), InvalidArgumentException::class, 'non-numeric source fails closed');

$GLOBALS['sc_test_result_queue'] = [esc_p5_calc_actor(), esc_p5_calc_definition(61, 'text')];
esc_p5_calc_throws(static fn () => $service->replaceRule(61, ['kind' => 'constant', 'value' => '1']), InvalidArgumentException::class, 'non-numeric target fails closed');

$GLOBALS['sc_test_result_queue'] = [esc_p5_calc_actor(), esc_p5_calc_definition(61, 'decimal'), esc_p5_calc_definition(62, 'integer', 32)];
esc_p5_calc_throws(static fn () => $service->replaceRule(61, ['kind' => 'field', 'definition_id' => 62]), InvalidArgumentException::class, 'wrong-Contract-Type source fails closed');

$GLOBALS['sc_test_result_queue'] = [esc_p5_calc_actor(), esc_p5_calc_definition(61, 'decimal'), esc_p5_calc_definition(62, 'integer', 31, 'inactive')];
esc_p5_calc_throws(static fn () => $service->replaceRule(61, ['kind' => 'field', 'definition_id' => 62]), InvalidArgumentException::class, 'inactive source fails closed');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p5_calc_actor(), esc_p5_calc_definition(61, 'decimal'), esc_p5_calc_definition(62, 'integer'),
    [['target_definition_id' => '62', 'source_definition_id' => '61']],
];
esc_p5_calc_throws(static fn () => $service->replaceRule(61, ['kind' => 'field', 'definition_id' => 62]), InvalidArgumentException::class, 'indirect calculation dependency cycle fails closed');
esc_p5_calc_assert($GLOBALS['sc_test_queries'] === [], 'cycle rejection occurs before transaction');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_calc_actor(), []];
esc_p5_calc_throws(static fn () => $service->replaceRule(999999, ['kind' => 'constant', 'value' => '1']), InvalidArgumentException::class, 'foreign/missing target identity fails current-tenant lookup');
$tenantLookup = (string) ($GLOBALS['sc_test_read_queries'][1] ?? '');
esc_p5_calc_assert(str_contains($tenantLookup, 'tenant_id = 17'), 'definition identity is never authorization and lookup is tenant scoped');

$dep62 = esc_p5_calc_dependency(1, 62, 'integer');
$dep63 = esc_p5_calc_dependency(2, 63, 'decimal');
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p5_calc_actor(), esc_p5_calc_contract(), esc_p5_calc_actor(), esc_p5_calc_binding(), esc_p5_calc_definition(61, 'decimal'),
    esc_p5_calc_rule($expression), [$dep62, $dep63],
    [esc_p5_calc_value(62, 'integer', '5'), esc_p5_calc_value(63, 'decimal', '"2"')],
];
$evaluation = $service->evaluate(71, 61);
esc_p5_calc_assert(($evaluation['valid'] ?? false) === true && ($evaluation['status'] ?? '') === 'calculated', 'valid typed expression evaluates successfully');
esc_p5_calc_assert(($evaluation['result'] ?? null) === '10', 'mixed integer/decimal source arithmetic is deterministic');
esc_p5_calc_assert(($evaluation['dependencies'] ?? []) === [62, 63], 'evaluation reports canonical dependency IDs');
esc_p5_calc_assert($GLOBALS['sc_test_queries'] === [], 'calculation evaluator performs zero writes');
$valueRead = (string) (end($GLOBALS['sc_test_read_queries']) ?: '');
esc_p5_calc_assert(str_contains($valueRead, 'contract_id = 71 AND tenant_id = 17') && str_contains($valueRead, 'definition_id IN'), 'calculation value read is contract+tenant+bounded-source scoped');

$GLOBALS['sc_test_result_queue'] = [
    esc_p5_calc_actor(), esc_p5_calc_contract(), esc_p5_calc_actor(), esc_p5_calc_binding(), esc_p5_calc_definition(61, 'decimal'),
    esc_p5_calc_rule($expression), [$dep62, $dep63], [esc_p5_calc_value(62, 'integer', '5')],
];
$missing = $service->evaluate(71, 61);
esc_p5_calc_assert(($missing['valid'] ?? true) === false && ($missing['status'] ?? '') === 'missing_source', 'missing calculation source fails deterministically instead of becoming zero');

$GLOBALS['sc_test_result_queue'] = [
    esc_p5_calc_actor(), esc_p5_calc_contract(), esc_p5_calc_actor(), esc_p5_calc_binding(), esc_p5_calc_definition(61, 'decimal'),
    esc_p5_calc_rule($expression), [$dep62, $dep63],
    [esc_p5_calc_value(62, 'integer', '5', 1, str_repeat('0', 64)), esc_p5_calc_value(63, 'decimal', '"2"')],
];
$staleValue = $service->evaluate(71, 61);
esc_p5_calc_assert(($staleValue['status'] ?? '') === 'stale_value', 'stale calculation source value snapshot fails closed');

$GLOBALS['sc_test_result_queue'] = [
    esc_p5_calc_actor(), esc_p5_calc_contract(), esc_p5_calc_actor(), esc_p5_calc_binding(), esc_p5_calc_definition(61, 'decimal'),
    esc_p5_calc_rule($expression), [$dep62, $dep63],
    [esc_p5_calc_value(62, 'integer', '5'), esc_p5_calc_value(63, 'decimal', '"02.000"')],
];
$noncanonical = $service->evaluate(71, 61);
esc_p5_calc_assert(($noncanonical['status'] ?? '') === 'invalid_source', 'noncanonical stored P5 source value fails closed');

$GLOBALS['sc_test_result_queue'] = [
    esc_p5_calc_actor(), esc_p5_calc_contract(), esc_p5_calc_actor(), esc_p5_calc_binding(), esc_p5_calc_definition(61, 'decimal'),
    esc_p5_calc_rule($expression, 'decimal', str_repeat('0', 64)),
];
$staleRule = $service->evaluate(71, 61);
esc_p5_calc_assert(($staleRule['status'] ?? '') === 'stale_rule', 'stale target calculation rule fails closed');

$fractionExpression = ['kind' => 'constant', 'value' => '1.5'];
$GLOBALS['sc_test_result_queue'] = [
    esc_p5_calc_actor(), esc_p5_calc_contract(), esc_p5_calc_actor(), esc_p5_calc_binding(), esc_p5_calc_definition(61, 'integer'),
    esc_p5_calc_rule($fractionExpression, 'integer'), [],
];
$fractional = $service->evaluate(71, 61);
esc_p5_calc_assert(($fractional['status'] ?? '') === 'fractional_result' && ($fractional['result'] ?? 'x') === null, 'integer target rejects fractional calculated result');

$scaleExpression = ['kind' => 'multiply', 'children' => [
    ['kind' => 'constant', 'value' => '0.0000001'], ['kind' => 'constant', 'value' => '0.0000001'],
]];
$GLOBALS['sc_test_result_queue'] = [
    esc_p5_calc_actor(), esc_p5_calc_contract(), esc_p5_calc_actor(), esc_p5_calc_binding(), esc_p5_calc_definition(61, 'decimal'),
    esc_p5_calc_rule($scaleExpression), [],
];
$scaleFailure = $service->evaluate(71, 61);
esc_p5_calc_assert(($scaleFailure['status'] ?? '') === 'calculation_error', 'runtime scale overflow returns explicit calculation error');

$badStoredRule = esc_p5_calc_rule(['kind' => 'constant', 'value' => '1']);
$badStoredRule[0]['expression_json'] = '{"kind":"divide","children":[]}';
$GLOBALS['sc_test_result_queue'] = [
    esc_p5_calc_actor(), esc_p5_calc_contract(), esc_p5_calc_actor(), esc_p5_calc_binding(), esc_p5_calc_definition(61, 'decimal'),
    $badStoredRule,
];
$invalidRule = $service->evaluate(71, 61);
esc_p5_calc_assert(($invalidRule['status'] ?? '') === 'invalid_rule', 'unsupported stored operator fails closed during evaluation');

$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ALL] = false;
$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ASSIGNED] = true;
$GLOBALS['sc_test_result_queue'] = [esc_p5_calc_actor(), esc_p5_calc_contract(99), esc_p5_calc_actor()];
esc_p5_calc_throws(static fn () => $service->evaluate(71, 61), DomainException::class, 'assigned-scope user cannot calculate another accountant contract');
$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ALL] = true;

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = false;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p5_calc_throws(static fn () => $service->replaceRule(61, ['kind' => 'constant', 'value' => '1']), DomainException::class, 'calculation mutation requires MANAGE_REFERENCE_DATA');
esc_p5_calc_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'global calculation mutation denial occurs before data access');
$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = true;
$GLOBALS['sc_test_result_queue'] = [esc_p5_calc_actor('viewer')];
esc_p5_calc_throws(static fn () => $service->replaceRule(61, ['kind' => 'constant', 'value' => '1']), DomainException::class, 'tenant viewer cannot bypass calculation mutation ceiling');

$originalWpdb = $GLOBALS['wpdb'];
$failingWpdb = new ESC_P5_Calc_Failing_Wpdb();
$GLOBALS['wpdb'] = $failingWpdb;
$repository = new CustomFieldCalculationRepository();
$target = esc_p5_calc_definition(61, 'decimal')[0];
$source = esc_p5_calc_definition(62, 'integer')[0];
$atomicExpression = CustomFieldCalculationPolicy::normalizeExpression(['kind' => 'field', 'definition_id' => 62]);
esc_p5_calc_throws(static fn () => $repository->replaceRule($target, $atomicExpression['expression_json'], [$source], 42), RuntimeException::class, 'concurrent dependency drift aborts replacement');
esc_p5_calc_assert(in_array('ROLLBACK', $failingWpdb->queries, true), 'failed calculation replacement rolls back');
esc_p5_calc_assert(! in_array('COMMIT', $failingWpdb->queries, true), 'failed calculation replacement never commits partial state');
$GLOBALS['wpdb'] = $originalWpdb;

esc_p5_calc_assert(str_contains($repositorySource, 'START TRANSACTION') && str_contains($repositorySource, 'ORDER BY id ASC FOR UPDATE') && str_contains($repositorySource, 'ROLLBACK'), 'calculation replacement is transactionally lock protected');
esc_p5_calc_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()') && str_contains($repositorySource, 'requireTenantId()'), 'calculation repository has no unscoped tenant fallback');
esc_p5_calc_assert(str_contains($serviceSource, 'MAX_GRAPH_EDGES + 1') && str_contains($serviceSource, 'MAX_GRAPH_NODES'), 'calculation cycle traversal is bounded');
esc_p5_calc_assert(str_contains($serviceSource, 'assertContractScope') && str_contains($serviceSource, 'VIEW_ASSIGNED'), 'calculation evaluator preserves contract data scope');
esc_p5_calc_assert(! str_contains($policySource, 'eval(') && ! str_contains($policySource, 'floatval(') && ! str_contains($policySource, 'is_float('), 'calculation policy contains no executable or binary-float conversion surface');
esc_p5_calc_assert(! str_contains($policySource, "'divide'") && ! str_contains($policySource, 'preg_match_all'), 'calculation operator surface excludes divide/expression parsing');
esc_p5_calc_assert(! str_contains($migrationSource, 'safecontracts_custom_field_values'), 'P5-007 schema does not rewrite P5-002 values');
esc_p5_calc_assert(! str_contains($migrationSource, 'safecontracts_contract_template_version_fields'), 'P5-007 schema does not rewrite P5-004 snapshots');
esc_p5_calc_assert(! str_contains($migrationSource, 'safecontracts_custom_field_metadata'), 'P5-007 schema does not rewrite P5-005 metadata');
esc_p5_calc_assert(! str_contains($migrationSource, 'safecontracts_custom_field_visibility'), 'P5-007 schema does not rewrite P5-006 visibility');
esc_p5_calc_assert(! str_contains($repositorySource, 'UPDATE wp_safecontracts_custom_field_values') && ! str_contains($repositorySource, 'INSERT INTO wp_safecontracts_custom_field_values'), 'calculation repository has no P5 value materialization path');
esc_p5_calc_assert(str_contains($valueSource, 'safecontracts_custom_field_values') && str_contains($templateSource, 'safecontracts_contract_template_version_fields') && str_contains($metadataSource, 'safecontracts_custom_field_metadata') && str_contains($visibilitySource, 'safecontracts_custom_field_visibility_rules'), 'existing P5 domains remain separate and intact');
esc_p5_calc_assert(str_contains($gateSource, 'enterprise_custom_field_calculations_p5_007.php'), 'P5-007 regression is explicitly wired into ESC backend Gate');

CoreTenantEnforcement::disable();
TenantContextStore::reset();

echo "P5-007 Enterprise Dynamic Field calculation checks passed ({$assertions} assertions).\n";
