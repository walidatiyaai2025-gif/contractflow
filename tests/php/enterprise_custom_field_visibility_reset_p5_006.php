<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\CustomFields\CustomFieldValuePolicy;
use SafeContracts\CustomFields\CustomFieldVisibilityService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p5_vis_reset_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p5_vis_reset_actor(): array
{
    return [[
        'id' => '1', 'tenant_id' => '17', 'user_id' => '42',
        'role_code' => 'tenant_admin', 'is_owner' => '0',
    ]];
}

function esc_p5_vis_reset_definition(int $id, string $type): array
{
    return [[
        'id' => (string) $id,
        'uuid' => sprintf('eeeeeeee-eeee-4eee-8eee-%012d', $id),
        'contract_type_id' => '31',
        'field_code' => 'field.' . $id,
        'data_type' => $type,
        'label' => 'Field ' . $id,
        'help_text' => '', 'is_required' => '0', 'status' => 'active', 'sort_order' => (string) $id,
        'options_json' => '',
        'validation_json' => $type === 'decimal' ? '{"min":0,"max":1000}' : '',
        'created_by' => '42', 'updated_by' => '42',
    ]];
}

function esc_p5_vis_reset_rule(): array
{
    $target = esc_p5_vis_reset_definition(61, 'text')[0];
    return [[
        'id' => '501', 'target_definition_id' => '61', 'contract_type_id' => '31', 'match_mode' => 'all',
        'target_field_code_snapshot' => $target['field_code'],
        'target_data_type_snapshot' => $target['data_type'],
        'target_config_hash' => CustomFieldValuePolicy::configurationHash($target),
        'created_by' => '42', 'updated_by' => '42',
    ]];
}

function esc_p5_vis_reset_condition(): array
{
    $source = esc_p5_vis_reset_definition(62, 'decimal')[0];
    return [[
        'id' => '601', 'rule_id' => '501', 'target_definition_id' => '61', 'position_no' => '1',
        'source_definition_id' => '62', 'operator_code' => 'gt', 'operand_json' => '"10"',
        'source_field_code_snapshot' => $source['field_code'], 'source_data_type_snapshot' => $source['data_type'],
        'source_config_hash' => CustomFieldValuePolicy::configurationHash($source),
        'current_source_id' => '62', 'current_contract_type_id' => '31',
        'current_field_code' => $source['field_code'], 'current_data_type' => $source['data_type'],
        'current_status' => 'active', 'current_options_json' => '', 'current_validation_json' => $source['validation_json'],
    ]];
}

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::MANAGE_REFERENCE_DATA => true];
update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(17);
$service = new CustomFieldVisibilityService();

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p5_vis_reset_actor(), esc_p5_vis_reset_definition(61, 'text'), esc_p5_vis_reset_rule(), esc_p5_vis_reset_condition(),
];
$configured = $service->getRule(61);
esc_p5_vis_reset_assert(($configured['configured'] ?? false) === true, 'configured visibility rule read is explicit');
esc_p5_vis_reset_assert(($configured['match_mode'] ?? '') === 'all', 'configured rule preserves match mode');
esc_p5_vis_reset_assert(count($configured['conditions'] ?? []) === 1, 'configured rule returns ordered conditions');
esc_p5_vis_reset_assert(($configured['conditions'][0]['source_definition_id'] ?? 0) === 62, 'configured rule preserves source identity');
esc_p5_vis_reset_assert(($configured['conditions'][0]['operator'] ?? '') === 'gt', 'configured rule preserves typed operator');
esc_p5_vis_reset_assert(($configured['conditions'][0]['operand'] ?? null) === '10', 'configured rule decodes canonical operand');
esc_p5_vis_reset_assert($GLOBALS['sc_test_queries'] === [], 'configured rule read performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p5_vis_reset_actor(), esc_p5_vis_reset_definition(61, 'text'), esc_p5_vis_reset_rule(),
    [['id' => '61']], [['id' => '501']],
];
$service->resetRule(61);
esc_p5_vis_reset_assert(($GLOBALS['sc_test_queries'][0] ?? '') === 'START TRANSACTION', 'reset starts transaction');
esc_p5_vis_reset_assert(str_contains((string) ($GLOBALS['sc_test_read_queries'][3] ?? ''), 'LIMIT 1 FOR UPDATE'), 'reset locks active target definition');
esc_p5_vis_reset_assert(str_contains((string) ($GLOBALS['sc_test_read_queries'][4] ?? ''), 'safecontracts_custom_field_visibility_rules') && str_contains((string) ($GLOBALS['sc_test_read_queries'][4] ?? ''), 'FOR UPDATE'), 'reset locks exact rule row');
esc_p5_vis_reset_assert(str_contains((string) ($GLOBALS['sc_test_queries'][1] ?? ''), 'DELETE FROM wp_safecontracts_custom_field_visibility_conditions'), 'reset removes conditions first');
esc_p5_vis_reset_assert(str_contains((string) ($GLOBALS['sc_test_queries'][2] ?? ''), 'DELETE FROM wp_safecontracts_custom_field_visibility_rules'), 'reset removes rule header after conditions');
esc_p5_vis_reset_assert(($GLOBALS['sc_test_queries'][3] ?? '') === 'COMMIT', 'reset commits atomically');
esc_p5_vis_reset_assert(! in_array('ROLLBACK', $GLOBALS['sc_test_queries'], true), 'valid reset does not roll back');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p5_vis_reset_actor(), esc_p5_vis_reset_definition(61, 'text'), []];
$service->resetRule(61);
esc_p5_vis_reset_assert($GLOBALS['sc_test_queries'] === [], 'reset absent rule is idempotent and storage-free');

CoreTenantEnforcement::disable();
TenantContextStore::reset();

echo "P5-006 Enterprise Dynamic Field visibility configured-read/reset checks passed ({$assertions} assertions).\n";
