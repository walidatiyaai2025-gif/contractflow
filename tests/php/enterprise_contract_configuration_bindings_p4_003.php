<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\ContractTemplates\ContractConfigurationBindingService;
use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0028EnterpriseContractConfigurationBindings;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p4_bind_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p4_bind_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p4_bind_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ')');
        return;
    }
    esc_p4_bind_assert(false, $message . ' (no exception)');
}

function esc_p4_bind_contract(string $status = 'draft', ?int $accountant = 42, bool $archived = false): array
{
    return [[
        'id' => '71',
        'accountant_user_id' => $accountant === null ? null : (string) $accountant,
        'status' => $status,
        'is_archived' => $archived ? '1' : '0',
    ]];
}

function esc_p4_bind_type(int $id = 31, string $status = 'active'): array
{
    return [['id' => (string) $id, 'status' => $status]];
}

function esc_p4_bind_template(int $id = 41, int $typeId = 31, string $status = 'active'): array
{
    return [['id' => (string) $id, 'contract_type_id' => (string) $typeId, 'status' => $status]];
}

function esc_p4_bind_version(int $id = 51, string $status = 'published'): array
{
    return [['id' => (string) $id, 'template_id' => '41', 'version_no' => '4', 'version_status' => $status]];
}

function esc_p4_bind_binding(int $typeId = 31, ?int $templateId = 41, ?int $versionId = 51): array
{
    return [[
        'id' => '81',
        'contract_id' => '71',
        'contract_type_id' => (string) $typeId,
        'template_id' => $templateId === null ? null : (string) $templateId,
        'template_version_id' => $versionId === null ? null : (string) $versionId,
    ]];
}

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0028EnterpriseContractConfigurationBindings.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/ContractTemplates/ContractConfigurationBindingRepository.php');
$legacyContractMigration = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0004Contracts.php');
$contractStatusSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Contracts/ContractStatus.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0028EnterpriseContractConfigurationBindings())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);
esc_p4_bind_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_contract_configuration_bindings'), 'P4-003 creates separate binding table');
esc_p4_bind_assert(str_contains($schema, 'tenant_id bigint(20) unsigned NOT NULL'), 'binding requires tenant ownership');
esc_p4_bind_assert(str_contains($schema, 'UNIQUE KEY tenant_contract (tenant_id, contract_id)'), 'one binding row per tenant contract');
esc_p4_bind_assert(str_contains($schema, 'KEY tenant_type (tenant_id, contract_type_id, contract_id)'), 'type lookup index is tenant-first');
esc_p4_bind_assert(str_contains($schema, 'KEY tenant_template_version (tenant_id, template_id, template_version_id, contract_id)'), 'template version index is tenant-first');
esc_p4_bind_assert(str_contains($migratorSource, "'1.27.0' => Migration0028EnterpriseContractConfigurationBindings::class"), 'P4-003 migration registered at 1.27.0');
esc_p4_bind_assert(Migrator::LATEST_VERSION === '1.27.0', 'P4-003 is latest schema version');
esc_p4_bind_assert(! str_contains($legacyContractMigration, 'contract_type_id'), 'legacy contract foundation remains unchanged');
esc_p4_bind_assert(! str_contains($contractStatusSource, 'template'), 'legacy ContractStatus remains independent of templates');
esc_p4_bind_assert(str_contains($repositorySource, "c.status = 'draft' AND c.is_archived = 0"), 'binding persistence atomically rechecks editable draft state');
esc_p4_bind_assert(str_contains($repositorySource, 'INSERT INTO {$table}') && str_contains($repositorySource, 'FROM {$contracts} c'), 'binding persistence uses INSERT SELECT against the owned contract row');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::EDIT_CONTRACTS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::VIEW_ASSIGNED => true,
];
$service = new ContractConfigurationBindingService();

CoreTenantEnforcement::disable();
TenantContextStore::reset();
esc_p4_bind_throws(static fn () => $service->findForContract(71), RuntimeException::class, 'binding reads fail closed outside Enterprise enforcement');

update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
esc_p4_bind_throws(static fn () => $service->findForContract(71), RuntimeException::class, 'binding reads require locked tenant context');
TenantContextStore::context()->setTenantId(17);

$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_bind_contract(), esc_p4_bind_binding()];
$binding = $service->findForContract(71);
esc_p4_bind_assert(is_array($binding) && (int) ($binding['template_version_id'] ?? 0) === 51, 'historical binding is readable');
esc_p4_bind_assert(str_contains($GLOBALS['sc_test_read_queries'][0] ?? '', 'WHERE id = 71 AND tenant_id = 17'), 'contract read is tenant-scoped');
esc_p4_bind_assert(str_contains($GLOBALS['sc_test_read_queries'][1] ?? '', 'WHERE contract_id = 71 AND tenant_id = 17'), 'binding read is tenant-scoped');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_bind_contract(), esc_p4_bind_type(), esc_p4_bind_template(), esc_p4_bind_version(), []];
$service->bind(71, 31, 41, 51);
esc_p4_bind_assert(count($GLOBALS['sc_test_queries']) === 1, 'valid published template binding performs one mutation');
$sql = (string) ($GLOBALS['sc_test_queries'][0] ?? '');
esc_p4_bind_assert(str_contains($sql, 'INSERT INTO wp_safecontracts_contract_configuration_bindings'), 'binding writes only separate Enterprise table');
esc_p4_bind_assert(str_contains($sql, 'SELECT 17, c.id, 31, 41, 51'), 'binding ownership and references are server-validated in INSERT SELECT');
esc_p4_bind_assert(str_contains($sql, "WHERE c.id = 71 AND c.tenant_id = 17 AND c.status = 'draft' AND c.is_archived = 0"), 'binding mutation atomically rejects a concurrent lifecycle/archive change');
esc_p4_bind_assert(str_contains($sql, 'ON DUPLICATE KEY UPDATE'), 'draft binding replacement is atomic');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_bind_contract(), esc_p4_bind_type(), esc_p4_bind_template(), esc_p4_bind_version(), esc_p4_bind_binding()];
$service->bind(71, 31, 41, 51);
esc_p4_bind_assert($GLOBALS['sc_test_queries'] === [], 'exact existing binding is idempotent without mutation');

$GLOBALS['sc_test_queries'] = [];
esc_p4_bind_throws(static fn () => $service->bind(71, 31, 41, null), InvalidArgumentException::class, 'template and version must be supplied together');
esc_p4_bind_assert($GLOBALS['sc_test_queries'] === [], 'one-sided template input fails before mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [[]];
esc_p4_bind_throws(static fn () => $service->bind(999, 31), InvalidArgumentException::class, 'foreign contract cannot be configured');
esc_p4_bind_assert($GLOBALS['sc_test_queries'] === [], 'foreign contract rejection performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_bind_contract(), []];
esc_p4_bind_throws(static fn () => $service->bind(71, 999), InvalidArgumentException::class, 'foreign Contract Type cannot be bound');
esc_p4_bind_assert($GLOBALS['sc_test_queries'] === [], 'foreign Contract Type rejection performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_bind_contract(), esc_p4_bind_type(31, 'inactive')];
esc_p4_bind_throws(static fn () => $service->bind(71, 31), InvalidArgumentException::class, 'inactive Contract Type cannot be newly bound');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_bind_contract(), esc_p4_bind_type(), []];
esc_p4_bind_throws(static fn () => $service->bind(71, 31, 999, 51), InvalidArgumentException::class, 'foreign Template cannot be bound');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_bind_contract(), esc_p4_bind_type(), esc_p4_bind_template(41, 32), esc_p4_bind_version()];
esc_p4_bind_throws(static fn () => $service->bind(71, 31, 41, 51), InvalidArgumentException::class, 'Template must belong to selected Contract Type');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_bind_contract(), esc_p4_bind_type(), esc_p4_bind_template(), []];
esc_p4_bind_throws(static fn () => $service->bind(71, 31, 41, 999), InvalidArgumentException::class, 'foreign Template Version cannot be bound');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_bind_contract(), esc_p4_bind_type(), esc_p4_bind_template(), esc_p4_bind_version(51, 'draft')];
esc_p4_bind_throws(static fn () => $service->bind(71, 31, 41, 51), InvalidArgumentException::class, 'draft Template Version cannot be bound');
esc_p4_bind_assert($GLOBALS['sc_test_queries'] === [], 'draft version rejection performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_bind_contract('active')];
esc_p4_bind_throws(static fn () => $service->bind(71, 31), DomainException::class, 'binding is immutable after contract leaves draft');
esc_p4_bind_assert($GLOBALS['sc_test_queries'] === [], 'post-draft rebind rejection performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_bind_contract('draft', 42, true)];
esc_p4_bind_throws(static fn () => $service->bind(71, 31), DomainException::class, 'archived draft cannot be rebound');

$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ALL] = false;
$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ASSIGNED] = true;
$GLOBALS['sc_test_result_queue'] = [esc_p4_bind_contract('draft', 99)];
esc_p4_bind_throws(static fn () => $service->findForContract(71), DomainException::class, 'assigned-scope user cannot read another accountant contract');
$GLOBALS['sc_test_result_queue'] = [esc_p4_bind_contract('draft', 42), esc_p4_bind_binding()];
esc_p4_bind_assert(is_array($service->findForContract(71)), 'assigned-scope user can read own assigned contract binding');

$GLOBALS['sc_test_current_caps'][Capabilities::EDIT_CONTRACTS] = false;
$GLOBALS['sc_test_queries'] = [];
esc_p4_bind_throws(static fn () => $service->bind(71, 31), DomainException::class, 'binding mutation requires EDIT_CONTRACTS');
esc_p4_bind_assert($GLOBALS['sc_test_queries'] === [], 'capability denial performs no mutation');

esc_p4_bind_assert(! str_contains($migrationSource, 'ALTER TABLE'), 'P4-003 remains additive and does not alter legacy contract table');

echo "P4-003 Enterprise contract configuration binding checks passed ({$assertions} assertions).\n";
