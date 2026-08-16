<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\CustomFields\CustomFieldValuePolicy;
use SafeContracts\CustomFields\CustomFieldVisibilityPolicy;
use SafeContracts\CustomFields\CustomFieldVisibilityService;
use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0033EnterpriseCustomFieldVisibilityRules;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p5_vis_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p5_vis_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p5_vis_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ': ' . $error->getMessage() . ')');
        return;
    }
    esc_p5_vis_assert(false, $message . ' (no exception)');
}

function esc_p5_vis_actor(string $role = 'tenant_admin'): array
{
    return [[
        'id' => '1', 'tenant_id' => '17', 'user_id' => '42',
        'role_code' => $role, 'is_owner' => '0',
    ]];
}

function esc_p5_vis_definition(int $id, string $type, int $contractType = 31, string $status = 'active'): array
{
    $options = '';
    $validation = '';
    if ($type === 'multi_select') {
        $options = '[{"value":"north","label":"North"},{"value":"south","label":"South"},{"value":2,"label":"Two"}]';
        $validation = '{"min_items":1,"max_items":3}';
    } elseif ($type === 'select') {
        $options = '[{"value":"north","label":"North"},{"value":"south","label":"South"}]';
    } elseif ($type === 'decimal') {
        $validation = '{"min":0,"max":1000}';
    } elseif ($type === 'integer') {
        $validation = '{"min":0,"max":1000}';
    } elseif ($type === 'date') {
        $validation = '{"min":"2026-01-01","max":"2026-12-31"}';
    }
    return [[
        'id' => (string) $id,
        'uuid' => sprintf('eeeeeeee-eeee-4eee-8eee-%012d', $id),
        'contract_type_id' => (string) $contractType,
        'field_code' => 'field.' . $id,
        'data_type' => $type,
        'label' => 'Field ' . $id,
        'help_text' => '',
        'is_required' => '0',
        'status' => $status,
        'sort_order' => (string) $id,
        'options_json' => $options,
        'validation_json' => $validation,
        'created_by' => '42', 'updated_by' => '42',
    ]];
}

function esc_p5_vis_contract(int $accountant = 42): array
{
    return [[
        'id' => '71', 'accountant_user_id' => (string) $accountant,
        'status' => 'draft', 'is_archived' => '0',
    ]];
}

function esc_p5_vis_binding(int $typeId = 31): array
{
    return [['contract_id' => '71', 'contract_type_id' => (string) $typeId]];
}

function esc_p5_vis_rule(string $mode = 'all', ?string $hash = null): array
{
    $target = esc_p5_vis_definition(61, 'text')[0];
    return [[
        'id' => '501',
        'target_definition_id' => '61',
        'contract_type_id' => '31',
        'match_mode' => $mode,
        'target_field_code_snapshot' => $target['field_code'],
        'target_data_type_snapshot' => $target['data_type'],
        'target_config_hash' => $hash ?? CustomFieldValuePolicy::configurationHash($target),
        'created_by' => '42', 'updated_by' => '42',
    ]];
}

function esc_p5_vis_condition(int $position, int $sourceId, string $type, string $operator, ?string $operandJson): array
{
    $source = esc_p5_vis_definition($sourceId, $type)[0];
    return [
        'id' => (string) (600 + $position),
        'rule_id' => '501',
        'target_definition_id' => '61',
        'position_no' => (string) $position,
        'source_definition_id' => (string) $sourceId,
        'operator_code' => $operator,
        'operand_json' => $operandJson,
        'source_field_code_snapshot' => $source['field_code'],
        'source_data_type_snapshot' => $source['data_type'],
        'source_config_hash' => CustomFieldValuePolicy::configurationHash($source),
        'current_source_id' => (string) $sourceId,
        'current_contract_type_id' => '31',
        'current_field_code' => $source['field_code'],
        'current_data_type' => $source['data_type'],
        'current_status' => 'active',
        'current_options_json' => $source['options_json'],
        'current_validation_json' => $source['validation_json'],
    ];
}

function esc_p5_vis_value(int $definitionId, string $type, string $valueJson, int $isSet = 1, ?string $hash = null): array
{
    $definition = esc_p5_vis_definition($definitionId, $type)[0];
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

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0033EnterpriseCustomFieldVisibilityRules.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/CustomFieldVisibilityPolicy.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/CustomFieldVisibilityRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/CustomFieldVisibilityService.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$valueMigration = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0030EnterpriseCustomFieldValues.php');
$templateFieldMigration = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0031EnterpriseTemplateFieldSets.php');
$metadataMigration = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0032EnterpriseCustomFieldMetadata.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0033EnterpriseCustomFieldVisibilityRules())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);
esc_p5_vis_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_custom_field_visibility_rules'), 'P5-006 creates rule table');
esc_p5_vis_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_custom_field_visibility_conditions'), 'P5-006 creates condition table');
esc_p5_vis_assert(str_contains($schema, 'tenant_id bigint(20) unsigned NOT NULL'), 'visibility rows are tenant owned');
esc_p5_vis_assert(str_contains($schema, 'target_definition_id bigint(20) unsigned NOT NULL'), 'rule targets exact Dynamic Field');
esc_p5_vis_assert(str_contains($schema, 'source_definition_id bigint(20) unsigned NOT NULL'), 'condition references exact source Dynamic Field');
esc_p5_vis_assert(str_contains($schema, 'target_config_hash char(64) NOT NULL'), 'target configuration snapshot is persisted');
esc_p5_vis_assert(str_contains($schema, 'source_config_hash char(64) NOT NULL'), 'source configuration snapshot is persisted');
esc_p5_vis_assert(str_contains($schema, 'UNIQUE KEY tenant_target (tenant_id, target_definition_id)'), 'one visibility rule per tenant target');
esc_p5_vis_assert(str_contains($schema, 'UNIQUE KEY tenant_rule_position (tenant_id, rule_id, position_no)'), 'condition order is deterministic');
esc_p5_vis_assert(Migrator::LATEST_VERSION === '1.32.0', 'P5-006 is current schema version');
esc_p5_vis_assert(str_contains($migratorSource, "'1.32.0' => Migration0033EnterpriseCustomFieldVisibilityRules::class"), 'P5-006 migration is registered');
esc_p5_vis_assert(! str_contains($migrationSource, 'ALTER TABLE'), 'P5-006 migration is additive');

esc_p5_vis_assert(CustomFieldVisibilityPolicy::normalizeMatchMode(' ALL ') === 'all', 'match mode normalizes deterministically');
esc_p5_vis_assert(in_array('gt', CustomFieldVisibilityPolicy::operatorsFor('decimal'), true), 'numeric fields support ordered comparison');
esc_p5_vis_assert(in_array('contains', CustomFieldVisibilityPolicy::operatorsFor('multi_select'), true), 'multi-select supports typed contains');
esc_p5_vis_assert(! in_array('contains', CustomFieldVisibilityPolicy::operatorsFor('text'), true), 'text does not get arbitrary contains/regex semantics');
esc_p5_vis_assert(count(CustomFieldVisibilityPolicy::operatorsFor('boolean')) === 4, 'boolean operator surface is bounded');
esc_p5_vis_throws(static fn () => CustomFieldVisibilityPolicy::normalizeOperator('text', 'gt'), InvalidArgumentException::class, 'text ordered comparison fails closed');
esc_p5_vis_throws(static fn () => CustomFieldVisibilityPolicy::normalizeMatchMode('xor'), InvalidArgumentException::class, 'unsupported match mode fails closed');

$decimal = esc_p5_vis_definition(62, 'decimal')[0];
esc_p5_vis_assert(CustomFieldVisibilityPolicy::canonicalizeOperand($decimal, 'gt', true, '010.500') === '"10.5"', 'decimal operand uses canonical P5 value semantics');
esc_p5_vis_throws(static fn () => CustomFieldVisibilityPolicy::canonicalizeOperand($decimal, 'gt', false, null), InvalidArgumentException::class, 'comparison requires operand');
esc_p5_vis_throws(static fn () => CustomFieldVisibilityPolicy::canonicalizeOperand($decimal, 'is_set', true, 1), InvalidArgumentException::class, 'set-state operator rejects operand');
$multi = esc_p5_vis_definition(63, 'multi_select')[0];
esc_p5_vis_assert(CustomFieldVisibilityPolicy::canonicalizeOperand($multi, 'contains', true, 'north') === '"north"', 'contains operand is typed against configured select options');
esc_p5_vis_throws(static fn () => CustomFieldVisibilityPolicy::canonicalizeOperand($multi, 'contains', true, 'east'), InvalidArgumentException::class, 'contains rejects unconfigured option');
esc_p5_vis_assert(CustomFieldVisibilityPolicy::evaluate($decimal, 'gt', false, null, '"10"') === false, 'missing values do not satisfy ordered comparison');
esc_p5_vis_assert(CustomFieldVisibilityPolicy::evaluate($decimal, 'is_not_set', false, null, null) === true, 'missing value has explicit is_not_set semantics');
esc_p5_vis_assert(CustomFieldVisibilityPolicy::evaluate($decimal, 'neq', false, null, '"10"') === false, 'missing value is not implicitly neq');
esc_p5_vis_assert(CustomFieldVisibilityPolicy::evaluate($decimal, 'gt', true, '"15"', '"10.5"') === true, 'decimal comparison avoids binary-float conversion');
esc_p5_vis_assert(CustomFieldVisibilityPolicy::evaluate($multi, 'contains', true, '["south","north"]', '"north"') === true, 'multi-select contains uses typed scalar membership');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::MANAGE_REFERENCE_DATA => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::VIEW_ASSIGNED => true,
];
$service = new CustomFieldVisibilityService();

CoreTenantEnforcement::disable();
TenantContextStore::reset();
esc_p5_vis_throws(static fn () => $service->getRule(61), RuntimeException::class, 'visibility access fails closed outside Enterprise enforcement');
update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
esc_p5_vis_throws(static fn () => $service->getRule(61), RuntimeException::class, 'visibility access requires locked tenant');
TenantContextStore::context()->setTenantId(17);

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_vis_actor(), esc_p5_vis_definition(61, 'text'), []];
$unconfigured = $service->getRule(61);
esc_p5_vis_assert(($unconfigured['configured'] ?? true) === false, 'missing rule is explicit unconfigured state');
esc_p5_vis_assert($GLOBALS['sc_test_queries'] === [], 'rule read performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p5_vis_actor(),
    esc_p5_vis_definition(61, 'text'),
    esc_p5_vis_definition(62, 'decimal'),
    esc_p5_vis_definition(63, 'multi_select'),
    [],
    [['id' => '61'], ['id' => '62'], ['id' => '63']],
];
$GLOBALS['wpdb']->insert_id = 0;
$service->replaceRule(61, 'all', [
    ['source_definition_id' => 62, 'operator' => 'gt', 'operand' => '10.5'],
    ['source_definition_id' => 63, 'operator' => 'contains', 'operand' => 'north'],
]);
esc_p5_vis_assert(($GLOBALS['sc_test_queries'][0] ?? '') === 'START TRANSACTION', 'rule replacement starts transaction');
$lockSql = (string) ($GLOBALS['sc_test_read_queries'][5] ?? '');
esc_p5_vis_assert(str_contains($lockSql, 'ORDER BY id ASC FOR UPDATE'), 'target/source definitions lock in deterministic order');
esc_p5_vis_assert(str_contains($lockSql, 'tenant_id = 17 AND contract_type_id = 31') && str_contains($lockSql, "status = 'active'"), 'definition lock is tenant/type/active scoped');
$ruleSql = (string) ($GLOBALS['sc_test_queries'][1] ?? '');
esc_p5_vis_assert(str_contains($ruleSql, 'INSERT INTO wp_safecontracts_custom_field_visibility_rules'), 'rule header persists in dedicated table');
esc_p5_vis_assert(str_contains($ruleSql, 'id = LAST_INSERT_ID(id)'), 'rule upsert returns stable ID on duplicate update');
esc_p5_vis_assert(str_contains($ruleSql, "d.field_code = 'field.61' AND d.data_type = 'text'"), 'rule header revalidates target identity/type');
$deleteSql = (string) ($GLOBALS['sc_test_queries'][2] ?? '');
esc_p5_vis_assert(str_contains($deleteSql, 'DELETE FROM wp_safecontracts_custom_field_visibility_conditions'), 'replacement removes only old dedicated conditions inside transaction');
$condition1 = (string) ($GLOBALS['sc_test_queries'][3] ?? '');
$condition2 = (string) ($GLOBALS['sc_test_queries'][4] ?? '');
esc_p5_vis_assert(str_contains($condition1, "'gt'") && str_contains($condition1, '"10.5"'), 'numeric condition persists canonical operand');
esc_p5_vis_assert(str_contains($condition2, "'contains'") && str_contains($condition2, '"north"'), 'multi-select condition persists canonical option operand');
esc_p5_vis_assert(str_contains($condition1, "d.status = 'active'") && str_contains($condition1, 'd.contract_type_id = 31'), 'condition insert atomically revalidates active same-Type source');
esc_p5_vis_assert(($GLOBALS['sc_test_queries'][5] ?? '') === 'COMMIT', 'valid rule replacement commits atomically');
esc_p5_vis_assert(! in_array('ROLLBACK', $GLOBALS['sc_test_queries'], true), 'valid replacement does not roll back');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_vis_actor(), esc_p5_vis_definition(61, 'text')];
esc_p5_vis_throws(static fn () => $service->replaceRule(61, 'all', [
    ['source_definition_id' => 61, 'operator' => 'eq', 'operand' => 'x'],
]), InvalidArgumentException::class, 'self-dependency fails closed');
esc_p5_vis_assert($GLOBALS['sc_test_queries'] === [], 'self-dependency performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_vis_actor(), esc_p5_vis_definition(61, 'text'), esc_p5_vis_definition(62, 'decimal', 32)];
esc_p5_vis_throws(static fn () => $service->replaceRule(61, 'all', [
    ['source_definition_id' => 62, 'operator' => 'gt', 'operand' => 10],
]), InvalidArgumentException::class, 'wrong-Contract-Type source fails closed');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_vis_actor(), esc_p5_vis_definition(61, 'text'), esc_p5_vis_definition(62, 'decimal', 31, 'inactive')];
esc_p5_vis_throws(static fn () => $service->replaceRule(61, 'all', [
    ['source_definition_id' => 62, 'operator' => 'gt', 'operand' => 10],
]), InvalidArgumentException::class, 'inactive source fails closed');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_vis_actor(), esc_p5_vis_definition(61, 'text'), esc_p5_vis_definition(62, 'decimal')];
esc_p5_vis_throws(static fn () => $service->replaceRule(61, 'all', [
    ['source_definition_id' => 62, 'operator' => 'gt', 'operand' => 10],
    ['source_definition_id' => 62, 'operator' => 'gt', 'operand' => '10.0'],
]), InvalidArgumentException::class, 'semantically duplicate canonical conditions fail closed');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p5_vis_actor(), esc_p5_vis_definition(61, 'text'), esc_p5_vis_definition(62, 'decimal'),
    [['target_definition_id' => '62', 'source_definition_id' => '61']],
];
esc_p5_vis_throws(static fn () => $service->replaceRule(61, 'all', [
    ['source_definition_id' => 62, 'operator' => 'gt', 'operand' => 10],
]), InvalidArgumentException::class, 'indirect dependency cycle fails closed');
esc_p5_vis_assert($GLOBALS['sc_test_queries'] === [], 'cycle rejection occurs before transaction');

$tooMany = array_fill(0, CustomFieldVisibilityPolicy::MAX_CONDITIONS + 1, ['source_definition_id' => 62, 'operator' => 'is_set']);
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_vis_actor(), esc_p5_vis_definition(61, 'text')];
esc_p5_vis_throws(static fn () => $service->replaceRule(61, 'all', $tooMany), InvalidArgumentException::class, 'condition count is bounded');
esc_p5_vis_assert($GLOBALS['sc_test_queries'] === [], 'oversized rule performs no mutation');

$conditionDecimal = esc_p5_vis_condition(1, 62, 'decimal', 'gt', '"10.5"');
$conditionMulti = esc_p5_vis_condition(2, 63, 'multi_select', 'contains', '"north"');
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p5_vis_actor(), esc_p5_vis_contract(), esc_p5_vis_binding(), esc_p5_vis_definition(61, 'text'), esc_p5_vis_rule('all'),
    [$conditionDecimal, $conditionMulti],
    [esc_p5_vis_value(62, 'decimal', '"15"'), esc_p5_vis_value(63, 'multi_select', '["north"]')],
];
$evaluation = $service->evaluate(71, 61);
esc_p5_vis_assert(($evaluation['valid'] ?? false) === true && ($evaluation['conditional_visible'] ?? false) === true, 'all-mode evaluation matches when every typed condition matches');
esc_p5_vis_assert(($evaluation['status'] ?? '') === 'matched' && ($evaluation['evaluated_conditions'] ?? 0) === 2, 'evaluation reports deterministic matched summary');
esc_p5_vis_assert(count($evaluation['conditions'] ?? []) === 2, 'evaluation returns bounded condition diagnostics');
esc_p5_vis_assert($GLOBALS['sc_test_queries'] === [], 'visibility evaluator performs zero writes');
$valueRead = (string) (end($GLOBALS['sc_test_read_queries']) ?: '');
esc_p5_vis_assert(str_contains($valueRead, 'contract_id = 71 AND tenant_id = 17') && str_contains($valueRead, 'definition_id IN'), 'evaluation value read is contract+tenant+bounded-source scoped');

$GLOBALS['sc_test_result_queue'] = [
    esc_p5_vis_actor(), esc_p5_vis_contract(), esc_p5_vis_binding(), esc_p5_vis_definition(61, 'text'), esc_p5_vis_rule('all'),
    [$conditionDecimal, $conditionMulti],
    [esc_p5_vis_value(62, 'decimal', '"5"'), esc_p5_vis_value(63, 'multi_select', '["north"]')],
];
$notMatched = $service->evaluate(71, 61);
esc_p5_vis_assert(($notMatched['conditional_visible'] ?? true) === false && ($notMatched['status'] ?? '') === 'not_matched', 'all-mode fails when one condition is false');

$GLOBALS['sc_test_result_queue'] = [
    esc_p5_vis_actor(), esc_p5_vis_contract(), esc_p5_vis_binding(), esc_p5_vis_definition(61, 'text'), esc_p5_vis_rule('any'),
    [$conditionDecimal, $conditionMulti],
    [esc_p5_vis_value(62, 'decimal', '"5"'), esc_p5_vis_value(63, 'multi_select', '["north"]')],
];
$anyMatched = $service->evaluate(71, 61);
esc_p5_vis_assert(($anyMatched['conditional_visible'] ?? false) === true && ($anyMatched['match_mode'] ?? '') === 'any', 'any-mode matches when at least one condition is true');

$isNotSet = esc_p5_vis_condition(1, 62, 'decimal', 'is_not_set', null);
$GLOBALS['sc_test_result_queue'] = [
    esc_p5_vis_actor(), esc_p5_vis_contract(), esc_p5_vis_binding(), esc_p5_vis_definition(61, 'text'), esc_p5_vis_rule('all'),
    [$isNotSet], [],
];
$missingMatched = $service->evaluate(71, 61);
esc_p5_vis_assert(($missingMatched['conditional_visible'] ?? false) === true, 'missing source value satisfies explicit is_not_set');

$GLOBALS['sc_test_result_queue'] = [
    esc_p5_vis_actor(), esc_p5_vis_contract(), esc_p5_vis_binding(), esc_p5_vis_definition(61, 'text'), esc_p5_vis_rule('all', str_repeat('0', 64)),
];
$staleTarget = $service->evaluate(71, 61);
esc_p5_vis_assert(($staleTarget['valid'] ?? true) === false && ($staleTarget['status'] ?? '') === 'stale_rule', 'stale target configuration fails visibility closed');
esc_p5_vis_assert(($staleTarget['conditional_visible'] ?? true) === false, 'stale target cannot become visible');

$staleSource = $conditionDecimal;
$staleSource['source_config_hash'] = str_repeat('0', 64);
$GLOBALS['sc_test_result_queue'] = [
    esc_p5_vis_actor(), esc_p5_vis_contract(), esc_p5_vis_binding(), esc_p5_vis_definition(61, 'text'), esc_p5_vis_rule('all'),
    [$staleSource],
];
$staleRule = $service->evaluate(71, 61);
esc_p5_vis_assert(($staleRule['valid'] ?? true) === false && ($staleRule['status'] ?? '') === 'stale_rule', 'stale source definition configuration fails closed');

$GLOBALS['sc_test_result_queue'] = [
    esc_p5_vis_actor(), esc_p5_vis_contract(), esc_p5_vis_binding(), esc_p5_vis_definition(61, 'text'), esc_p5_vis_rule('all'),
    [$conditionDecimal], [esc_p5_vis_value(62, 'decimal', '"15"', 1, str_repeat('0', 64))],
];
$staleValue = $service->evaluate(71, 61);
esc_p5_vis_assert(($staleValue['valid'] ?? true) === false && ($staleValue['status'] ?? '') === 'stale_value', 'value validated under stale source config fails visibility closed');

$GLOBALS['sc_test_result_queue'] = [esc_p5_vis_actor(), esc_p5_vis_contract(), esc_p5_vis_binding(), esc_p5_vis_definition(61, 'text'), []];
$noRuleEval = $service->evaluate(71, 61);
esc_p5_vis_assert(($noRuleEval['configured'] ?? true) === false && ($noRuleEval['conditional_visible'] ?? false) === true, 'unconfigured condition result is neutral true for later static-surface composition');

$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ALL] = false;
$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ASSIGNED] = true;
$GLOBALS['sc_test_result_queue'] = [esc_p5_vis_actor(), esc_p5_vis_contract(99)];
esc_p5_vis_throws(static fn () => $service->evaluate(71, 61), DomainException::class, 'assigned-scope user cannot evaluate another accountant contract');
$GLOBALS['sc_test_result_queue'] = [esc_p5_vis_actor(), esc_p5_vis_contract(42), esc_p5_vis_binding(), esc_p5_vis_definition(61, 'text'), []];
esc_p5_vis_assert(($service->evaluate(71, 61)['valid'] ?? false) === true, 'assigned-scope user can evaluate own contract');
$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ALL] = true;

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = false;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p5_vis_throws(static fn () => $service->replaceRule(61, 'all', [['source_definition_id' => 62, 'operator' => 'is_set']]), DomainException::class, 'visibility mutation requires MANAGE_REFERENCE_DATA');
esc_p5_vis_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'global mutation denial occurs before data access');
$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = true;
$GLOBALS['sc_test_result_queue'] = [esc_p5_vis_actor('viewer')];
esc_p5_vis_throws(static fn () => $service->replaceRule(61, 'all', [['source_definition_id' => 62, 'operator' => 'is_set']]), DomainException::class, 'tenant viewer cannot bypass visibility mutation ceiling');

esc_p5_vis_assert(str_contains($repositorySource, 'START TRANSACTION') && str_contains($repositorySource, 'ORDER BY id ASC FOR UPDATE') && str_contains($repositorySource, 'ROLLBACK'), 'visibility replacement is transactionally lock protected');
esc_p5_vis_assert(str_contains($repositorySource, '$wpdb->insert_id = 0') && str_contains($repositorySource, 'LAST_INSERT_ID(id)'), 'rule header distinguishes stale zero-row target write from duplicate no-change');
esc_p5_vis_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()') && str_contains($repositorySource, 'requireTenantId()'), 'visibility repository has no unscoped tenant fallback');
esc_p5_vis_assert(str_contains($serviceSource, 'MAX_GRAPH_EDGES + 1') && str_contains($serviceSource, 'MAX_GRAPH_NODES'), 'cycle detection is bounded and overflow-aware');
esc_p5_vis_assert(str_contains($serviceSource, 'assertContractScope') && str_contains($serviceSource, 'VIEW_ASSIGNED'), 'evaluator preserves contract data scope');
esc_p5_vis_assert(! str_contains($policySource, 'eval(') && ! str_contains($policySource, 'exec(') && ! str_contains($policySource, 'preg_match'), 'visibility policy has no executable/regex expression surface');
esc_p5_vis_assert(! str_contains($migrationSource, 'safecontracts_custom_field_values'), 'P5-006 schema does not alter P5 values');
esc_p5_vis_assert(! str_contains($migrationSource, 'safecontracts_contract_template_version_fields'), 'P5-006 schema does not alter P5-004 snapshots');
esc_p5_vis_assert(! str_contains($migrationSource, 'safecontracts_custom_field_metadata'), 'P5-006 schema does not alter P5-005 static metadata');
esc_p5_vis_assert(! str_contains($valueMigration, 'visibility_rules'), 'P5-002 migration remains unchanged');
esc_p5_vis_assert(! str_contains($templateFieldMigration, 'visibility_rules'), 'P5-004 migration remains unchanged');
esc_p5_vis_assert(! str_contains($metadataMigration, 'visibility_rules'), 'P5-005 migration remains unchanged');

CoreTenantEnforcement::disable();
TenantContextStore::reset();

echo "P5-006 Enterprise Dynamic Field visibility checks passed ({$assertions} assertions).\n";
