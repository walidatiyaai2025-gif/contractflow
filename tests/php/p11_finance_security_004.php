<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Finance\FinanceReadFilters;
use SafeContracts\Rest\FinanceController;
use SafeContracts\Roles\Capabilities;

$p11FinanceSecurityTests = 0;

function sc_p11fs_assert(bool $condition, string $message): void
{
    global $p11FinanceSecurityTests;
    $p11FinanceSecurityTests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_p11fs_expect(string $class, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        sc_p11fs_assert($error instanceof $class, $message);
        return;
    }
    sc_p11fs_assert(false, $message);
}

sc_p11fs_expect(InvalidArgumentException::class, fn () => FinanceReadFilters::strict(['direction' => 'garbage']), 'invalid direction fails closed');
sc_p11fs_expect(InvalidArgumentException::class, fn () => FinanceReadFilters::strict(['direction' => 'payable', 'financial_direction' => 'receivable']), 'conflicting direction aliases fail closed');
sc_p11fs_expect(InvalidArgumentException::class, fn () => FinanceReadFilters::strict(['currency_code' => 'KW']), 'invalid currency fails closed');
sc_p11fs_expect(InvalidArgumentException::class, fn () => FinanceReadFilters::strict(['counterparty_type' => 'vendor']), 'invalid counterparty type fails closed');
sc_p11fs_expect(InvalidArgumentException::class, fn () => FinanceReadFilters::strict(['customer_id' => '9', 'counterparty_type' => 'supplier']), 'Customer selector cannot be combined with Supplier type');
sc_p11fs_expect(InvalidArgumentException::class, fn () => FinanceReadFilters::strict(['customer_id' => '9', 'supplier_id' => '55']), 'Customer and Supplier selectors cannot be combined');
sc_p11fs_expect(InvalidArgumentException::class, fn () => FinanceReadFilters::strict(['due_from' => '2026-09-01', 'due_to' => '2026-08-01']), 'reversed due range fails closed');
sc_p11fs_expect(InvalidArgumentException::class, fn () => FinanceReadFilters::strict(['due_from' => '2026-02-31']), 'invalid due date fails closed');
sc_p11fs_expect(InvalidArgumentException::class, fn () => FinanceReadFilters::strict(['limit' => '501']), 'excessive finance limit fails closed');
sc_p11fs_expect(InvalidArgumentException::class, fn () => FinanceReadFilters::strict(['accountant_user_id' => ['42']]), 'array type confusion fails closed');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true];
$beforeReads = count($GLOBALS['sc_test_read_queries']);
$directDenied = FinanceController::overview(new WP_REST_Request());
sc_p11fs_assert($directDenied instanceof WP_Error && ($directDenied->data['status'] ?? 0) === 403, 'direct finance callback enforces finance capability defense-in-depth');
sc_p11fs_assert(count($GLOBALS['sc_test_read_queries']) === $beforeReads, 'direct finance denial performs no data read');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::VIEW_FINANCE => true,
];
$beforeReads = count($GLOBALS['sc_test_read_queries']);
$invalidDirection = FinanceController::overview(new WP_REST_Request(['direction' => 'garbage']));
sc_p11fs_assert($invalidDirection instanceof WP_Error && ($invalidDirection->data['status'] ?? 0) === 422, 'invalid REST direction returns versioned 422');
sc_p11fs_assert(count($GLOBALS['sc_test_read_queries']) === $beforeReads, 'invalid REST direction fails before finance reads');

$beforeReads = count($GLOBALS['sc_test_read_queries']);
$unknownFilter = FinanceController::overview(new WP_REST_Request(['include_secret' => '1']));
sc_p11fs_assert($unknownFilter instanceof WP_Error && ($unknownFilter->data['status'] ?? 0) === 422, 'unknown finance REST filter is rejected');
sc_p11fs_assert(count($GLOBALS['sc_test_read_queries']) === $beforeReads, 'unknown finance REST filter performs no read');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ASSIGNED => true,
    Capabilities::VIEW_FINANCE => true,
];
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '101',
    'contract_id' => '9',
    'financial_direction' => 'payable',
    'currency_code' => 'KWD',
    'counterparty_type' => 'supplier',
    'counterparty_id' => '55',
    'counterparty_name' => 'Supplier Co',
]]];
$beforeReads = count($GLOBALS['sc_test_read_queries']);
$assigned = FinanceController::obligations(new WP_REST_Request(['accountant_user_id' => '999', 'direction' => 'payable']));
$assignedSql = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $beforeReads));
sc_p11fs_assert($assigned instanceof WP_REST_Response && count($assigned->data['data'] ?? []) === 1, 'assigned finance user can read their scoped work queue');
sc_p11fs_assert(str_contains($assignedSql, 'c.accountant_user_id = 42'), 'assigned scope locks finance query to current accountant');
sc_p11fs_assert(! str_contains($assignedSql, 'c.accountant_user_id = 999'), 'forged accountant filter cannot widen assigned finance scope');

fwrite(STDOUT, "P11 finance security tests passed ({$p11FinanceSecurityTests} assertions).\n");
