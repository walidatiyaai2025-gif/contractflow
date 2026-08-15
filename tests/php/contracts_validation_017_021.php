<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Contracts\ContractService;
use SafeContracts\Roles\Capabilities;

$validationTests = 0;

function sc_v_assert(bool $condition, string $message): void
{
    global $validationTests;
    $validationTests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_v_expect(string $class, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        sc_v_assert($error instanceof $class, $message);
        return;
    }

    sc_v_assert(false, $message);
}

/** @return array<string, mixed> */
function sc_v_contract(array $overrides = []): array
{
    return array_merge([
        'id' => '501',
        'contract_number' => 'SC-501',
        'customer_id' => '7',
        'accountant_user_id' => '42',
        'status' => 'draft',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'base_value' => '1000.0000',
        'notes' => 'Validation contract',
        'is_archived' => '0',
    ], $overrides);
}

$service = new ContractService();

// SC-P2-017 — Accountant assignment validation.
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::ASSIGN_CONTRACTS => true,
];
$GLOBALS['sc_test_user_caps'][77] = [
    Capabilities::ACCESS => true,
    Capabilities::CREATE_CONTRACTS => true,
    Capabilities::VIEW_ASSIGNED => true,
];
$GLOBALS['sc_test_result_queue'] = [[sc_v_contract()]];
$service->assignAccountant(501, 77);
$assignmentSql = (string) end($GLOBALS['sc_test_queries']);
sc_v_assert(str_contains($assignmentSql, 'accountant_user_id = 77'), 'SC-P2-017 eligible Accountant assignment is persisted');
sc_v_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_contract_accountant_assigned']), 'SC-P2-017 assignment emits the domain event');

$GLOBALS['sc_test_result_queue'] = [[sc_v_contract()]];
$service->assignAccountant(501, null);
sc_v_assert(str_contains((string) end($GLOBALS['sc_test_queries']), 'accountant_user_id = NULL'), 'SC-P2-017 controlled unassignment is supported');

$GLOBALS['sc_test_result_queue'] = [[sc_v_contract()]];
$beforeInvalidAssignee = count($GLOBALS['sc_test_queries']);
sc_v_expect(InvalidArgumentException::class, fn () => $service->assignAccountant(501, 88), 'SC-P2-017 rejects users who are not eligible Accountants');
sc_v_assert(count($GLOBALS['sc_test_queries']) === $beforeInvalidAssignee, 'SC-P2-017 invalid assignee causes no mutation');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ASSIGNED => true,
    Capabilities::ASSIGN_CONTRACTS => true,
];
$GLOBALS['sc_test_result_queue'] = [[sc_v_contract(['accountant_user_id' => '99'])]];
$beforeOutOfScopeAssignment = count($GLOBALS['sc_test_queries']);
sc_v_expect(DomainException::class, fn () => $service->assignAccountant(501, 77), 'SC-P2-017 assignment cannot bypass Accountant data scope');
sc_v_assert(count($GLOBALS['sc_test_queries']) === $beforeOutOfScopeAssignment, 'SC-P2-017 out-of-scope assignment causes no mutation');

// SC-P2-018 — Contract status lifecycle validation.
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::EDIT_CONTRACTS => true,
];
$GLOBALS['sc_test_result_queue'] = [[sc_v_contract(['status' => 'draft'])]];
$service->changeStatus(501, 'active');
sc_v_assert(str_contains((string) end($GLOBALS['sc_test_queries']), "status = 'active'"), 'SC-P2-018 draft can transition to active');

$GLOBALS['sc_test_result_queue'] = [[sc_v_contract(['status' => 'active'])]];
$service->changeStatus(501, 'completed');
sc_v_assert(str_contains((string) end($GLOBALS['sc_test_queries']), "status = 'completed'"), 'SC-P2-018 active can transition to completed');

$GLOBALS['sc_test_result_queue'] = [[sc_v_contract(['status' => 'completed'])]];
$beforeTerminalTransition = count($GLOBALS['sc_test_queries']);
sc_v_expect(DomainException::class, fn () => $service->changeStatus(501, 'active'), 'SC-P2-018 completed is terminal');
sc_v_assert(count($GLOBALS['sc_test_queries']) === $beforeTerminalTransition, 'SC-P2-018 terminal transition causes no mutation');

$GLOBALS['sc_test_result_queue'] = [[sc_v_contract(['status' => 'cancelled'])]];
sc_v_expect(DomainException::class, fn () => $service->changeStatus(501, 'active'), 'SC-P2-018 cancelled is terminal');

$GLOBALS['sc_test_result_queue'] = [[sc_v_contract(['status' => 'active'])]];
sc_v_expect(InvalidArgumentException::class, fn () => $service->changeStatus(501, 'unknown'), 'SC-P2-018 unknown lifecycle state is rejected');

$GLOBALS['sc_test_result_queue'] = [[sc_v_contract(['status' => 'active', 'is_archived' => '1'])]];
sc_v_expect(DomainException::class, fn () => $service->changeStatus(501, 'completed'), 'SC-P2-018 archived contract lifecycle is frozen');

// SC-P2-019 — Contract date validation.
$GLOBALS['sc_test_result_queue'] = [[sc_v_contract()]];
$service->updateDates(501, '2026-02-01', '2026-11-30');
$dateSql = (string) end($GLOBALS['sc_test_queries']);
sc_v_assert(str_contains($dateSql, "start_date = '2026-02-01'"), 'SC-P2-019 valid start date is persisted');
sc_v_assert(str_contains($dateSql, "end_date = '2026-11-30'"), 'SC-P2-019 valid end date is persisted');

$GLOBALS['sc_test_result_queue'] = [[sc_v_contract()]];
$service->updateDates(501, null, '');
$clearDateSql = (string) end($GLOBALS['sc_test_queries']);
sc_v_assert(str_contains($clearDateSql, 'start_date = NULL'), 'SC-P2-019 start date can be cleared explicitly');
sc_v_assert(str_contains($clearDateSql, 'end_date = NULL'), 'SC-P2-019 end date can be cleared explicitly');

$GLOBALS['sc_test_result_queue'] = [[sc_v_contract()]];
$beforeInvalidDate = count($GLOBALS['sc_test_queries']);
sc_v_expect(InvalidArgumentException::class, fn () => $service->updateDates(501, '2026-02-30', '2026-03-01'), 'SC-P2-019 invalid calendar date is rejected');
sc_v_assert(count($GLOBALS['sc_test_queries']) === $beforeInvalidDate, 'SC-P2-019 invalid date causes no mutation');

$GLOBALS['sc_test_result_queue'] = [[sc_v_contract()]];
$beforeReversedDate = count($GLOBALS['sc_test_queries']);
sc_v_expect(InvalidArgumentException::class, fn () => $service->updateDates(501, '2026-05-01', '2026-04-30'), 'SC-P2-019 end date before start date is rejected');
sc_v_assert(count($GLOBALS['sc_test_queries']) === $beforeReversedDate, 'SC-P2-019 reversed date range causes no mutation');

$GLOBALS['sc_test_result_queue'] = [[sc_v_contract(['is_archived' => '1'])]];
$beforeArchivedDate = count($GLOBALS['sc_test_queries']);
sc_v_expect(DomainException::class, fn () => $service->updateDates(501, '2026-02-01', '2026-11-30'), 'SC-P2-019 archived contract dates are immutable');
sc_v_assert(count($GLOBALS['sc_test_queries']) === $beforeArchivedDate, 'SC-P2-019 archived date update causes no mutation');

// SC-P2-020 — Financial line-item validation.
$GLOBALS['wpdb']->insert_id = 6001;
$GLOBALS['sc_test_result_queue'] = [[sc_v_contract()]];
$itemId = $service->addFinancialItem(501, 'Campaign production', '250.125', 15);
sc_v_assert($itemId === 6001, 'SC-P2-020 financial line returns its inserted ID');
$itemSql = (string) end($GLOBALS['sc_test_queries']);
sc_v_assert(str_contains($itemSql, "'250.1250'"), 'SC-P2-020 financial amount uses exact four-decimal normalization');
sc_v_assert(str_contains($itemSql, ', 15,'), 'SC-P2-020 display order is persisted');

$GLOBALS['sc_test_result_queue'] = [[sc_v_contract()]];
$beforeNegativeLine = count($GLOBALS['sc_test_queries']);
sc_v_expect(InvalidArgumentException::class, fn () => $service->addFinancialItem(501, 'Invalid negative', '-1.00'), 'SC-P2-020 negative line amount is rejected');
sc_v_assert(count($GLOBALS['sc_test_queries']) === $beforeNegativeLine, 'SC-P2-020 negative line causes no mutation');

$GLOBALS['sc_test_result_queue'] = [[sc_v_contract(['is_archived' => '1'])]];
$beforeArchivedLine = count($GLOBALS['sc_test_queries']);
sc_v_expect(DomainException::class, fn () => $service->addFinancialItem(501, 'Archived mutation', '1.00'), 'SC-P2-020 archived contract financial lines are immutable');
sc_v_assert(count($GLOBALS['sc_test_queries']) === $beforeArchivedLine, 'SC-P2-020 archived financial line causes no mutation');

// SC-P2-021 — Additions and discounts validation.
$GLOBALS['wpdb']->insert_id = 7001;
$GLOBALS['sc_test_result_queue'] = [[sc_v_contract()]];
$additionId = $service->addAdjustment(501, 'ADDITION', 'Extra media', '50.50', 10);
sc_v_assert($additionId === 7001, 'SC-P2-021 addition returns its inserted ID');
sc_v_assert(str_contains((string) end($GLOBALS['sc_test_queries']), "'addition'"), 'SC-P2-021 addition type is normalized');

$GLOBALS['wpdb']->insert_id = 7002;
$GLOBALS['sc_test_result_queue'] = [[sc_v_contract()]];
$discountId = $service->addAdjustment(501, 'discount', 'Commercial discount', '25.125', 20);
sc_v_assert($discountId === 7002, 'SC-P2-021 discount returns its inserted ID');
$discountSql = (string) end($GLOBALS['sc_test_queries']);
sc_v_assert(str_contains($discountSql, "'discount'"), 'SC-P2-021 discount type is persisted');
sc_v_assert(str_contains($discountSql, "'25.1250'"), 'SC-P2-021 discount amount uses exact four-decimal normalization');

$GLOBALS['sc_test_result_queue'] = [[sc_v_contract()]];
$beforeInvalidType = count($GLOBALS['sc_test_queries']);
sc_v_expect(InvalidArgumentException::class, fn () => $service->addAdjustment(501, 'fee', 'Unsupported', '1.00'), 'SC-P2-021 unsupported adjustment type is rejected');
sc_v_assert(count($GLOBALS['sc_test_queries']) === $beforeInvalidType, 'SC-P2-021 unsupported adjustment causes no mutation');

$GLOBALS['sc_test_result_queue'] = [[sc_v_contract(['is_archived' => '1'])]];
$beforeArchivedAdjustment = count($GLOBALS['sc_test_queries']);
sc_v_expect(DomainException::class, fn () => $service->addAdjustment(501, 'addition', 'Archived mutation', '1.00'), 'SC-P2-021 archived contract adjustments are immutable');
sc_v_assert(count($GLOBALS['sc_test_queries']) === $beforeArchivedAdjustment, 'SC-P2-021 archived adjustment causes no mutation');

echo "SafeContracts P2 validation SC-P2-017..021 passed ({$validationTests} assertions).\n";
