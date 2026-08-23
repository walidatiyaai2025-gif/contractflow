<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Contracts\ContractService;
use SafeContracts\Contracts\ContractStatus;
use SafeContracts\Roles\Capabilities;

$workflowTests = 0;

function sc_workflow_assert(bool $condition, string $message): void
{
    global $workflowTests;
    $workflowTests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @return array<string, mixed> */
function sc_contract_row(array $overrides = []): array
{
    return array_merge([
        'id' => '501',
        'contract_number' => 'SC-501',
        'customer_id' => '7',
        'accountant_user_id' => '42',
        'status' => 'active',
        'base_value' => '1000.0000',
        'notes' => 'Initial notes',
        'is_archived' => '0',
    ], $overrides);
}

function sc_expect_exception(string $class, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        sc_workflow_assert($error instanceof $class, $message);
        return;
    }

    sc_workflow_assert(false, $message);
}

$service = new ContractService();

// Accountant create: customer must be active and an unassigned create auto-assigns the current Accountant.
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::CREATE_CONTRACTS => true,
    Capabilities::VIEW_ASSIGNED => true,
];
$GLOBALS['sc_test_result_queue'] = [[['id' => '7']]];
$GLOBALS['wpdb']->insert_id = 2001;
$queryCount = count($GLOBALS['sc_test_queries']);
$createdId = $service->create([
    'contract_number' => ' SC-2026-001 ',
    'customer_id' => 7,
    'base_value' => '1250.00',
    'notes' => "Accountant's contract",
]);
sc_workflow_assert($createdId === 2001, 'contract create returns the inserted contract ID');
sc_workflow_assert(count($GLOBALS['sc_test_queries']) === $queryCount + 1, 'contract create performs one mutation');
$createSql = end($GLOBALS['sc_test_queries']);
sc_workflow_assert(str_contains((string) $createSql, 'wp_safecontracts_contracts'), 'contract create uses the dedicated contracts table');
sc_workflow_assert(str_contains((string) $createSql, "'SC-2026-001'"), 'contract number is trimmed and prepared');
sc_workflow_assert(str_contains((string) $createSql, '7, 42'), 'Accountant-created contract is auto-assigned to the current Accountant');
sc_workflow_assert(str_contains((string) $createSql, "'active'") && str_contains((string) $createSql, "'1250.0000'"), 'new contracts start active with a positive base value');
sc_workflow_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_contract_created']), 'contract create emits a domain action');

$GLOBALS['sc_test_result_queue'] = [[['id' => '7']]];
$beforeZeroValue = count($GLOBALS['sc_test_queries']);
sc_expect_exception(InvalidArgumentException::class, fn () => $service->create([
    'contract_number' => 'SC-ZERO',
    'customer_id' => 7,
    'base_value' => '0',
]), 'contract create rejects zero base value');
sc_workflow_assert(count($GLOBALS['sc_test_queries']) === $beforeZeroValue, 'zero-value contract create does not mutate data');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$beforeDeniedCreate = count($GLOBALS['sc_test_queries']);
sc_expect_exception(DomainException::class, fn () => $service->create([
    'contract_number' => 'SC-DENIED',
    'customer_id' => 7,
    'base_value' => '100',
]), 'create workflow requires CREATE_CONTRACTS');
sc_workflow_assert(count($GLOBALS['sc_test_queries']) === $beforeDeniedCreate, 'denied create does not mutate data');

// Manager create can explicitly assign an eligible Accountant.
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::CREATE_CONTRACTS => true,
    Capabilities::EDIT_CONTRACTS => true,
    Capabilities::ASSIGN_CONTRACTS => true,
];
$GLOBALS['sc_test_user_caps'][77] = [
    Capabilities::ACCESS => true,
    Capabilities::CREATE_CONTRACTS => true,
    Capabilities::VIEW_ASSIGNED => true,
];
$GLOBALS['sc_test_result_queue'] = [[['id' => '8']]];
$GLOBALS['wpdb']->insert_id = 2002;
$managerCreatedId = $service->create([
    'contract_number' => 'SC-2026-002',
    'customer_id' => 8,
    'base_value' => '2000',
    'accountant_user_id' => 77,
]);
sc_workflow_assert($managerCreatedId === 2002, 'manager create returns inserted ID');
$managerCreateSql = end($GLOBALS['sc_test_queries']);
sc_workflow_assert(str_contains((string) $managerCreateSql, '8, 77'), 'manager can assign an eligible Accountant during create');

$GLOBALS['sc_test_result_queue'] = [[['id' => '8']]];
$beforeBadAssignee = count($GLOBALS['sc_test_queries']);
sc_expect_exception(InvalidArgumentException::class, fn () => $service->create([
    'contract_number' => 'SC-BAD-ASSIGNEE',
    'customer_id' => 8,
    'base_value' => '300',
    'accountant_user_id' => 88,
]), 'create rejects users who are not eligible Accountants');
sc_workflow_assert(count($GLOBALS['sc_test_queries']) === $beforeBadAssignee, 'invalid Accountant create does not mutate contract data');

// Contract editing is capability-controlled and still data-scope controlled.
$GLOBALS['sc_test_result_queue'] = [[sc_contract_row()]];
$service->edit(501, ['contract_number' => 'SC-501-R1', 'notes' => 'Revised']);
$editSql = end($GLOBALS['sc_test_queries']);
sc_workflow_assert(str_starts_with(ltrim((string) $editSql), 'UPDATE wp_safecontracts_contracts'), 'authorized contract edit performs UPDATE');
sc_workflow_assert(str_contains((string) $editSql, "'SC-501-R1'"), 'contract edit persists the changed contract number');
sc_workflow_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_contract_edited']), 'contract edit emits a domain action');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true];
$beforeNoEditCapability = count($GLOBALS['sc_test_queries']);
sc_expect_exception(DomainException::class, fn () => $service->edit(501, ['notes' => 'No']), 'contract edit requires EDIT_CONTRACTS');
sc_workflow_assert(count($GLOBALS['sc_test_queries']) === $beforeNoEditCapability, 'missing edit capability causes no mutation');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ASSIGNED => true,
    Capabilities::EDIT_CONTRACTS => true,
];
$GLOBALS['sc_test_result_queue'] = [[sc_contract_row(['accountant_user_id' => '99'])]];
$beforeOutOfScopeEdit = count($GLOBALS['sc_test_queries']);
sc_expect_exception(DomainException::class, fn () => $service->edit(501, ['notes' => 'Out of scope']), 'granted edit capability cannot bypass assigned scope');
sc_workflow_assert(count($GLOBALS['sc_test_queries']) === $beforeOutOfScopeEdit, 'out-of-scope edit causes no mutation');

$GLOBALS['sc_test_result_queue'] = [[sc_contract_row(['accountant_user_id' => '42'])]];
$service->edit(501, ['notes' => 'Scoped edit']);
sc_workflow_assert(str_contains((string) end($GLOBALS['sc_test_queries']), "'Scoped edit'"), 'Accountant with explicit edit capability can edit own assigned contract');

// Customer assignment requires assignment capability, existing scope and an active customer.
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::ASSIGN_CONTRACTS => true,
];
$GLOBALS['sc_test_result_queue'] = [[sc_contract_row()], [['id' => '11']]];
$service->assignCustomer(501, 11);
$customerSql = end($GLOBALS['sc_test_queries']);
sc_workflow_assert(str_contains((string) $customerSql, 'SET customer_id = 11'), 'customer assignment updates the contract relation');
sc_workflow_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_contract_customer_assigned']), 'customer assignment emits a domain action');

$GLOBALS['sc_test_result_queue'] = [[sc_contract_row()], []];
$beforeInactiveCustomer = count($GLOBALS['sc_test_queries']);
sc_expect_exception(InvalidArgumentException::class, fn () => $service->assignCustomer(501, 12), 'customer assignment rejects inactive or missing customers');
sc_workflow_assert(count($GLOBALS['sc_test_queries']) === $beforeInactiveCustomer, 'invalid customer assignment does not mutate data');

// Accountant assignment requires an eligible Accountant; unassignment is allowed for controlled workflow handling.
$GLOBALS['sc_test_result_queue'] = [[sc_contract_row()]];
$service->assignAccountant(501, 77);
$accountantSql = end($GLOBALS['sc_test_queries']);
sc_workflow_assert(str_contains((string) $accountantSql, 'SET accountant_user_id = 77'), 'Accountant assignment persists the responsible user');
sc_workflow_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_contract_accountant_assigned']), 'Accountant assignment emits a domain action');

$GLOBALS['sc_test_result_queue'] = [[sc_contract_row()]];
$beforeInvalidAccountant = count($GLOBALS['sc_test_queries']);
sc_expect_exception(InvalidArgumentException::class, fn () => $service->assignAccountant(501, 88), 'assignment rejects non-Accountant users');
sc_workflow_assert(count($GLOBALS['sc_test_queries']) === $beforeInvalidAccountant, 'invalid Accountant assignment causes no mutation');

$GLOBALS['sc_test_result_queue'] = [[sc_contract_row()]];
$service->assignAccountant(501, null);
sc_workflow_assert(str_contains((string) end($GLOBALS['sc_test_queries']), 'accountant_user_id = NULL'), 'authorized workflow can return a contract to unassigned state');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ASSIGNED => true,
    Capabilities::ASSIGN_CONTRACTS => true,
];
$GLOBALS['sc_test_result_queue'] = [[sc_contract_row(['accountant_user_id' => '99'])]];
$beforeOutOfScopeAssign = count($GLOBALS['sc_test_queries']);
sc_expect_exception(DomainException::class, fn () => $service->assignAccountant(501, 77), 'assignment capability cannot bypass assigned data scope');
sc_workflow_assert(count($GLOBALS['sc_test_queries']) === $beforeOutOfScopeAssign, 'out-of-scope assignment causes no mutation');

// Lifecycle transitions remain explicit for existing records; completed/cancelled are terminal and archived rows are frozen.
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::EDIT_CONTRACTS => true,
];
$GLOBALS['sc_test_result_queue'] = [[sc_contract_row(['status' => 'draft'])]];
$service->changeStatus(501, 'ACTIVE');
$statusSql = end($GLOBALS['sc_test_queries']);
sc_workflow_assert(str_contains((string) $statusSql, "SET status = 'active'"), 'legacy draft contract can transition to active');
sc_workflow_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_contract_status_changed']), 'status transition emits a domain action');
sc_workflow_assert(ContractStatus::all() === ['draft', 'active', 'completed', 'cancelled'], 'contract lifecycle exposes the controlled status set');

$GLOBALS['sc_test_result_queue'] = [[sc_contract_row(['status' => 'completed'])]];
$beforeInvalidTransition = count($GLOBALS['sc_test_queries']);
sc_expect_exception(DomainException::class, fn () => $service->changeStatus(501, 'active'), 'completed contract cannot transition back to active');
sc_workflow_assert(count($GLOBALS['sc_test_queries']) === $beforeInvalidTransition, 'invalid lifecycle transition causes no mutation');

$GLOBALS['sc_test_result_queue'] = [[sc_contract_row(['status' => 'active', 'is_archived' => '1'])]];
$beforeArchivedTransition = count($GLOBALS['sc_test_queries']);
sc_expect_exception(DomainException::class, fn () => $service->changeStatus(501, 'completed'), 'archived contract lifecycle is frozen');
sc_workflow_assert(count($GLOBALS['sc_test_queries']) === $beforeArchivedTransition, 'archived lifecycle attempt causes no mutation');

$GLOBALS['sc_test_result_queue'] = [[sc_contract_row(['status' => 'active'])]];
$beforeUnknownStatus = count($GLOBALS['sc_test_queries']);
sc_expect_exception(InvalidArgumentException::class, fn () => $service->changeStatus(501, 'unknown'), 'unknown contract status is rejected');
sc_workflow_assert(count($GLOBALS['sc_test_queries']) === $beforeUnknownStatus, 'unknown status causes no mutation');

echo "SafeContracts contract workflow tests passed ({$workflowTests} assertions).\n";