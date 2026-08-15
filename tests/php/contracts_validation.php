<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Contracts\ContractService;
use SafeContracts\Database\Migrator;
use SafeContracts\Roles\Capabilities;

$validationTests = 0;

function sc_val_assert(bool $condition, string $message): void
{
    global $validationTests;
    $validationTests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_val_expect(string $class, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        sc_val_assert($error instanceof $class, $message);
        return;
    }
    sc_val_assert(false, $message);
}

/** @return array<string, mixed> */
function sc_val_contract(array $overrides = []): array
{
    return array_merge([
        'id' => '501',
        'contract_number' => 'SC-501',
        'customer_id' => '7',
        'accountant_user_id' => '42',
        'status' => 'draft',
        'start_date' => null,
        'end_date' => null,
        'base_value' => '0.0000',
        'notes' => '',
        'is_archived' => '0',
    ], $overrides);
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_val_assert(is_callable($activate), 'plugin activation hook is available');
$activate();

// SC-P2-013 — Contract data model validation.
sc_val_assert(Migrator::LATEST_VERSION === '1.5.0', 'contract data model is on the current migration baseline');
$contractSchema = $GLOBALS['sc_test_dbdelta'][3];
sc_val_assert(str_contains($contractSchema, 'UNIQUE KEY contract_number (contract_number)'), 'contract number uniqueness remains enforced');
sc_val_assert(str_contains($contractSchema, 'customer_id bigint(20) unsigned NOT NULL'), 'customer relation remains required');
sc_val_assert(str_contains($contractSchema, 'accountant_user_id bigint(20) unsigned NULL'), 'responsible Accountant relation remains explicitly nullable for controlled drafts');
sc_val_assert(str_contains($contractSchema, 'base_value decimal(20,4) NOT NULL DEFAULT 0.0000'), 'contract money remains fixed-point DECIMAL(20,4)');
sc_val_assert(str_contains($contractSchema, 'KEY accountant_status (accountant_user_id, status, is_archived)'), 'assigned-scope query index remains present');

$service = new ContractService();

// SC-P2-014 — Contract create workflow validation.
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::CREATE_CONTRACTS => true,
    Capabilities::VIEW_ASSIGNED => true,
];
$GLOBALS['sc_test_result_queue'] = [[['id' => '7']]];
$GLOBALS['wpdb']->insert_id = 7101;
$createdId = $service->create([
    'contract_number' => ' SC-VAL-001 ',
    'customer_id' => 7,
]);
sc_val_assert($createdId === 7101, 'authorized Accountant create returns inserted contract ID');
$createSql = (string) end($GLOBALS['sc_test_queries']);
sc_val_assert(str_contains($createSql, "'SC-VAL-001'"), 'create workflow normalizes contract number');
sc_val_assert(str_contains($createSql, '7, 42'), 'Accountant-created contract remains auto-assigned to current Accountant');
sc_val_assert(str_contains($createSql, "'draft'"), 'new contract starts in draft state');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$beforeDeniedCreate = count($GLOBALS['sc_test_queries']);
sc_val_expect(\DomainException::class, fn () => $service->create([
    'contract_number' => 'SC-VAL-DENIED',
    'customer_id' => 7,
]), 'create requires explicit CREATE_CONTRACTS capability');
sc_val_assert(count($GLOBALS['sc_test_queries']) === $beforeDeniedCreate, 'denied create performs no mutation');

// SC-P2-015 — Contract edit capability validation.
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::EDIT_CONTRACTS => true,
];
$GLOBALS['sc_test_result_queue'] = [[sc_val_contract()]];
$service->edit(501, ['contract_number' => 'SC-501-R2', 'notes' => 'Validated edit']);
$editSql = (string) end($GLOBALS['sc_test_queries']);
sc_val_assert(str_contains($editSql, "'SC-501-R2'"), 'edit capability permits bounded contract edit');
sc_val_assert(str_contains($editSql, "'Validated edit'"), 'edit workflow persists notes under same capability boundary');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true];
$beforeDeniedEdit = count($GLOBALS['sc_test_queries']);
sc_val_expect(\DomainException::class, fn () => $service->edit(501, ['notes' => 'Denied']), 'edit is denied when EDIT_CONTRACTS is absent');
sc_val_assert(count($GLOBALS['sc_test_queries']) === $beforeDeniedEdit, 'denied edit performs no mutation');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ASSIGNED => true,
    Capabilities::EDIT_CONTRACTS => true,
];
$GLOBALS['sc_test_result_queue'] = [[sc_val_contract(['accountant_user_id' => '99'])]];
sc_val_expect(\DomainException::class, fn () => $service->edit(501, ['notes' => 'Out of scope']), 'edit capability cannot bypass assigned Accountant scope');

// SC-P2-016 — Customer assignment validation.
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::ASSIGN_CONTRACTS => true,
];
$GLOBALS['sc_test_result_queue'] = [[sc_val_contract()], [['id' => '11']]];
$service->assignCustomer(501, 11);
$customerSql = (string) end($GLOBALS['sc_test_queries']);
sc_val_assert(str_contains($customerSql, 'SET customer_id = 11'), 'authorized customer assignment updates contract relation');
sc_val_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_contract_customer_assigned']), 'customer assignment emits domain event for history/audit integration');

$GLOBALS['sc_test_result_queue'] = [[sc_val_contract()], []];
$beforeInactiveCustomer = count($GLOBALS['sc_test_queries']);
sc_val_expect(\InvalidArgumentException::class, fn () => $service->assignCustomer(501, 12), 'customer assignment rejects missing or inactive customer');
sc_val_assert(count($GLOBALS['sc_test_queries']) === $beforeInactiveCustomer, 'invalid customer assignment performs no mutation');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ASSIGNED => true,
    Capabilities::ASSIGN_CONTRACTS => true,
];
$GLOBALS['sc_test_result_queue'] = [[sc_val_contract(['accountant_user_id' => '99'])]];
sc_val_expect(\DomainException::class, fn () => $service->assignCustomer(501, 11), 'assignment capability cannot bypass assigned contract scope');

echo "SafeContracts contract validation tests passed ({$validationTests} assertions).\n";
