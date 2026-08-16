<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\CustomFields\CustomFieldValuePolicy;
use SafeContracts\CustomFields\CustomFieldValidationService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p5_ready_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p5_ready_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p5_ready_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ': ' . $error->getMessage() . ')');
        return;
    }
    esc_p5_ready_assert(false, $message . ' (no exception)');
}

function esc_p5_ready_actor(string $role = 'tenant_admin'): array
{
    return [[
        'id' => '1',
        'tenant_id' => '17',
        'user_id' => '42',
        'role_code' => $role,
        'is_owner' => '0',
    ]];
}

function esc_p5_ready_contract(?int $accountant = 42): array
{
    return [[
        'id' => '71',
        'accountant_user_id' => $accountant === null ? null : (string) $accountant,
        'status' => 'draft',
        'is_archived' => '0',
    ]];
}

function esc_p5_ready_binding(int $typeId = 31): array
{
    return [['contract_id' => '71', 'contract_type_id' => (string) $typeId]];
}

function esc_p5_ready_definition(int $id = 61, string $type = 'select', bool $required = true): array
{
    return [
        'id' => (string) $id,
        'contract_type_id' => '31',
        'field_code' => 'project.field.' . $id,
        'data_type' => $type,
        'label' => 'Project Field ' . $id,
        'is_required' => $required ? '1' : '0',
        'status' => 'active',
        'sort_order' => (string) $id,
        'options_json' => $type === 'select' ? '[{"value":"north","label":"North"},{"value":"south","label":"South"}]' : '',
        'validation_json' => $type === 'integer' ? '{"min":1,"max":100}' : '',
    ];
}

function esc_p5_ready_joined_value(
    int $id = 61,
    string $type = 'select',
    string $valueJson = '"north"',
    ?string $hash = null,
    string $status = 'active',
    int $currentTypeId = 31,
    bool $definitionExists = true,
    ?string $snapshotType = null
): array {
    $definition = esc_p5_ready_definition($id, $type, false);
    $definition['contract_type_id'] = (string) $currentTypeId;
    $definition['status'] = $status;
    $hash ??= CustomFieldValuePolicy::configurationHash($definition);

    return [
        'id' => (string) (1000 + $id),
        'contract_id' => '71',
        'definition_id' => (string) $id,
        'value_json' => $valueJson,
        'data_type_snapshot' => $snapshotType ?? $type,
        'definition_config_hash' => $hash,
        'current_definition_id' => $definitionExists ? (string) $id : null,
        'current_contract_type_id' => $definitionExists ? (string) $currentTypeId : null,
        'field_code' => $definitionExists ? $definition['field_code'] : null,
        'data_type' => $definitionExists ? $type : null,
        'label' => $definitionExists ? $definition['label'] : null,
        'is_required' => $definitionExists ? '0' : null,
        'definition_status' => $definitionExists ? $status : null,
        'sort_order' => $definitionExists ? (string) $id : null,
        'options_json' => $definitionExists ? $definition['options_json'] : null,
        'validation_json' => $definitionExists ? $definition['validation_json'] : null,
    ];
}

function esc_p5_ready_has_issue(array $result, string $code, ?string $severity = null): bool
{
    foreach ($result['issues'] ?? [] as $issue) {
        if (($issue['code'] ?? '') === $code && ($severity === null || ($issue['severity'] ?? '') === $severity)) {
            return true;
        }
    }
    return false;
}

$root = dirname(__DIR__, 2);
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/CustomFieldValidationRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/CustomFieldValidationService.php');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::VIEW_ASSIGNED => true,
];
$service = new CustomFieldValidationService();

CoreTenantEnforcement::disable();
TenantContextStore::reset();
esc_p5_ready_throws(static fn () => $service->validateContract(71), RuntimeException::class, 'readiness fails closed outside Enterprise enforcement');

update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
esc_p5_ready_throws(static fn () => $service->validateContract(71), RuntimeException::class, 'readiness requires locked tenant context');
TenantContextStore::context()->setTenantId(17);

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$definition = esc_p5_ready_definition();
$value = esc_p5_ready_joined_value();
$GLOBALS['sc_test_result_queue'] = [esc_p5_ready_actor(), esc_p5_ready_contract(), esc_p5_ready_binding(), [$definition], [$value]];
$result = $service->validateContract(71);
esc_p5_ready_assert($result['ready'] === true, 'valid current values produce ready=true');
esc_p5_ready_assert($result['error_count'] === 0 && $result['warning_count'] === 0, 'valid current values produce no issues');
esc_p5_ready_assert($result['definition_count'] === 1 && $result['set_value_count'] === 1, 'readiness reports scanned counts');
esc_p5_ready_assert($GLOBALS['sc_test_queries'] === [], 'readiness engine performs no database writes');
esc_p5_ready_assert(str_contains($GLOBALS['sc_test_read_queries'][1] ?? '', 'WHERE id = 71 AND tenant_id = 17'), 'contract lookup is tenant-scoped');
esc_p5_ready_assert(str_contains($GLOBALS['sc_test_read_queries'][2] ?? '', 'WHERE contract_id = 71 AND tenant_id = 17'), 'binding lookup is tenant-scoped');
esc_p5_ready_assert(str_contains($GLOBALS['sc_test_read_queries'][3] ?? '', "contract_type_id = 31 AND tenant_id = 17 AND status = 'active'"), 'definition scan is tenant/type/active scoped');
esc_p5_ready_assert(str_contains($GLOBALS['sc_test_read_queries'][4] ?? '', 'LEFT JOIN wp_safecontracts_custom_field_definitions d') && str_contains($GLOBALS['sc_test_read_queries'][4] ?? '', 'v.tenant_id = 17'), 'set value scan is tenant-scoped and preserves missing definitions');

$GLOBALS['sc_test_result_queue'] = [esc_p5_ready_actor(), esc_p5_ready_contract(), esc_p5_ready_binding(), [$definition], []];
$result = $service->validateContract(71);
esc_p5_ready_assert($result['ready'] === false && $result['error_count'] === 1, 'missing required field makes contract not ready');
esc_p5_ready_assert(esc_p5_ready_has_issue($result, 'missing_required', 'error'), 'missing required issue is explicit');

$stale = esc_p5_ready_joined_value(hash: str_repeat('0', 64));
$GLOBALS['sc_test_result_queue'] = [esc_p5_ready_actor(), esc_p5_ready_contract(), esc_p5_ready_binding(), [$definition], [$stale]];
$result = $service->validateContract(71);
esc_p5_ready_assert($result['ready'] === true && $result['warning_count'] === 1, 'stale but still valid configuration is warning-only');
esc_p5_ready_assert(esc_p5_ready_has_issue($result, 'stale_configuration', 'warning'), 'stale configuration warning is visible');

$invalidJson = esc_p5_ready_joined_value(valueJson: '{bad');
$GLOBALS['sc_test_result_queue'] = [esc_p5_ready_actor(), esc_p5_ready_contract(), esc_p5_ready_binding(), [$definition], [$invalidJson]];
$result = $service->validateContract(71);
esc_p5_ready_assert($result['ready'] === false && esc_p5_ready_has_issue($result, 'invalid_value', 'error'), 'invalid stored JSON makes contract not ready');

$invalidOption = esc_p5_ready_joined_value(valueJson: '"east"');
$GLOBALS['sc_test_result_queue'] = [esc_p5_ready_actor(), esc_p5_ready_contract(), esc_p5_ready_binding(), [$definition], [$invalidOption]];
$result = $service->validateContract(71);
esc_p5_ready_assert(esc_p5_ready_has_issue($result, 'invalid_value', 'error'), 'stored value invalid under current options is an error');

$integerDefinition = esc_p5_ready_definition(62, 'integer', false);
$noncanonical = esc_p5_ready_joined_value(62, 'integer', '"010"');
$GLOBALS['sc_test_result_queue'] = [esc_p5_ready_actor(), esc_p5_ready_contract(), esc_p5_ready_binding(), [$integerDefinition], [$noncanonical]];
$result = $service->validateContract(71);
esc_p5_ready_assert($result['ready'] === true && esc_p5_ready_has_issue($result, 'noncanonical_value', 'warning'), 'valid noncanonical stored value is warning-only');

$typeMismatch = esc_p5_ready_joined_value(snapshotType: 'text');
$GLOBALS['sc_test_result_queue'] = [esc_p5_ready_actor(), esc_p5_ready_contract(), esc_p5_ready_binding(), [$definition], [$typeMismatch]];
$result = $service->validateContract(71);
esc_p5_ready_assert($result['ready'] === false && esc_p5_ready_has_issue($result, 'type_snapshot_mismatch', 'error'), 'type snapshot mismatch is a readiness error');

$missingDefinition = esc_p5_ready_joined_value(definitionExists: false);
$GLOBALS['sc_test_result_queue'] = [esc_p5_ready_actor(), esc_p5_ready_contract(), esc_p5_ready_binding(), [], [$missingDefinition]];
$result = $service->validateContract(71);
esc_p5_ready_assert($result['ready'] === false && esc_p5_ready_has_issue($result, 'orphan_value', 'error'), 'value referencing missing definition is orphan error');

$inactiveDefinition = esc_p5_ready_joined_value(status: 'inactive');
$GLOBALS['sc_test_result_queue'] = [esc_p5_ready_actor(), esc_p5_ready_contract(), esc_p5_ready_binding(), [], [$inactiveDefinition]];
$result = $service->validateContract(71);
esc_p5_ready_assert($result['ready'] === true && esc_p5_ready_has_issue($result, 'orphan_value', 'warning'), 'inactive historical definition value is warning-only and preserved');

$otherType = esc_p5_ready_joined_value(currentTypeId: 32);
$GLOBALS['sc_test_result_queue'] = [esc_p5_ready_actor(), esc_p5_ready_contract(), esc_p5_ready_binding(), [], [$otherType]];
$result = $service->validateContract(71);
esc_p5_ready_assert($result['ready'] === false && esc_p5_ready_has_issue($result, 'orphan_value', 'error'), 'other-Type value is orphan error');

$GLOBALS['sc_test_result_queue'] = [esc_p5_ready_actor(), esc_p5_ready_contract(), []];
esc_p5_ready_throws(static fn () => $service->validateContract(71), InvalidArgumentException::class, 'contract without P4 type binding cannot be validated');

$GLOBALS['sc_test_result_queue'] = [esc_p5_ready_actor(), []];
esc_p5_ready_throws(static fn () => $service->validateContract(999), InvalidArgumentException::class, 'foreign contract cannot be validated');

$manyDefinitions = [];
for ($i = 1; $i <= 501; $i++) {
    $manyDefinitions[] = esc_p5_ready_definition(1000 + $i, 'text', false);
}
$GLOBALS['sc_test_result_queue'] = [esc_p5_ready_actor(), esc_p5_ready_contract(), esc_p5_ready_binding(), $manyDefinitions, []];
$result = $service->validateContract(71);
esc_p5_ready_assert($result['ready'] === false && esc_p5_ready_has_issue($result, 'validation_limit_exceeded', 'error'), 'definition overflow fails closed instead of silently truncating readiness');
esc_p5_ready_assert($result['definition_count'] === 500, 'definition overflow report remains bounded to 500 processed rows');

$manyValues = [];
for ($i = 1; $i <= 501; $i++) {
    $manyValues[] = esc_p5_ready_joined_value(2000 + $i, 'select', '"north"');
}
$GLOBALS['sc_test_result_queue'] = [esc_p5_ready_actor(), esc_p5_ready_contract(), esc_p5_ready_binding(), [], $manyValues];
$result = $service->validateContract(71);
esc_p5_ready_assert($result['ready'] === false && esc_p5_ready_has_issue($result, 'validation_limit_exceeded', 'error'), 'value overflow fails closed instead of silently truncating readiness');
esc_p5_ready_assert($result['set_value_count'] === 500, 'value overflow report remains bounded to 500 processed rows');

$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ALL] = false;
$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ASSIGNED] = true;
$GLOBALS['sc_test_result_queue'] = [esc_p5_ready_actor(), esc_p5_ready_contract(99)];
esc_p5_ready_throws(static fn () => $service->validateContract(71), DomainException::class, 'assigned-scope user cannot validate another accountant contract');
$GLOBALS['sc_test_result_queue'] = [esc_p5_ready_actor(), esc_p5_ready_contract(42), esc_p5_ready_binding(), [], []];
esc_p5_ready_assert($service->validateContract(71)['ready'] === true, 'assigned-scope user can validate own assigned contract');

$GLOBALS['sc_test_current_caps'][Capabilities::ACCESS] = false;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p5_ready_throws(static fn () => $service->validateContract(71), DomainException::class, 'readiness requires ACCESS');
esc_p5_ready_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'global ACCESS denial happens before data access');

$GLOBALS['sc_test_current_caps'][Capabilities::ACCESS] = true;
$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ALL] = true;
$GLOBALS['sc_test_result_queue'] = [esc_p5_ready_actor('viewer'), esc_p5_ready_contract(), esc_p5_ready_binding(), [], []];
$result = $service->validateContract(71);
esc_p5_ready_assert(is_array($result), 'tenant viewer with ACCESS may perform read-only readiness validation');

esc_p5_ready_assert(str_contains($repositorySource, 'MAX_SCAN_ROWS = 501'), 'repository supports one-row overflow detection beyond the 500 validation bound');
esc_p5_ready_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()') && str_contains($repositorySource, 'requireTenantId()'), 'validation repository has no unscoped tenant fallback');
esc_p5_ready_assert(str_contains($serviceSource, "'stale_configuration', 'warning'") && str_contains($serviceSource, "'invalid_value', 'error'"), 'readiness severity policy is explicit');
esc_p5_ready_assert(! str_contains($serviceSource, '->query(') && ! str_contains($repositorySource, '->query('), 'readiness engine has no database mutation path');

echo "P5-003 Enterprise Dynamic Field readiness checks passed ({$assertions} assertions).\n";
