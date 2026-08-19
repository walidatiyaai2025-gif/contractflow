<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Contracts\CounterpartyContractService;
use SafeContracts\Roles\Capabilities;

$assertions = 0;

function sc_600_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_600_expect_domain(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        sc_600_assert($error instanceof \DomainException, $message . ' (' . get_class($error) . ')');
        return;
    }
    sc_600_assert(false, $message . ' (no exception)');
}

$service = new CounterpartyContractService();

// The Contracts UI and SupplierService both treat VIEW_ALL as valid supplier
// read access. A supplier shown in the dropdown must therefore remain a valid
// counterparty when the same user also has CREATE_CONTRACTS.
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::CREATE_CONTRACTS => true,
    Capabilities::VIEW_ALL => true,
];
$GLOBALS['sc_test_result_queue'] = [[['id' => '3101']]];
$GLOBALS['wpdb']->insert_id = 6101;
$viewAllId = $service->create([
    'contract_number' => 'SUP-VIEW-ALL-6101',
    'counterparty_type' => 'supplier',
    'counterparty_id' => 3101,
    'currency_code' => 'KWD',
]);
sc_600_assert($viewAllId === 6101, 'VIEW_ALL user can save the supplier contract exposed by the UI');
$viewAllSql = (string) end($GLOBALS['sc_test_queries']);
sc_600_assert(str_contains($viewAllSql, "'supplier', 3101, 'payable', 'KWD'"), 'VIEW_ALL supplier contract is persisted as Accounts Payable');

// Reference-data managers are also allowed to browse suppliers throughout the
// existing admin/runtime contract, so creation must not reject the selection.
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::CREATE_CONTRACTS => true,
    Capabilities::MANAGE_REFERENCE_DATA => true,
];
$GLOBALS['sc_test_result_queue'] = [[['id' => '3102']]];
$GLOBALS['wpdb']->insert_id = 6102;
$referenceManagerId = $service->create([
    'contract_number' => 'SUP-REF-6102',
    'counterparty_type' => 'supplier',
    'counterparty_id' => 3102,
    'currency_code' => 'EGP',
]);
sc_600_assert($referenceManagerId === 6102, 'reference-data manager can save a visible supplier contract');
$referenceSql = (string) end($GLOBALS['sc_test_queries']);
sc_600_assert(str_contains($referenceSql, "'supplier', 3102, 'payable', 'EGP'"), 'reference-data supplier contract remains Accounts Payable');

// CREATE_CONTRACTS alone must not grant supplier visibility/use. The fix aligns
// contracts with the existing supplier-read contract; it does not bypass it.
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::CREATE_CONTRACTS => true,
];
$GLOBALS['sc_test_result_queue'] = [[['id' => '3103']]];
sc_600_expect_domain(
    fn () => $service->create([
        'contract_number' => 'SUP-DENIED-6103',
        'counterparty_type' => 'supplier',
        'counterparty_id' => 3103,
        'currency_code' => 'KWD',
    ]),
    'supplier contract still fails closed without supplier-read permission'
);

// Both create and reassign must use one canonical policy helper so they cannot
// drift apart again.
$source = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Contracts/CounterpartyContractService.php');
sc_600_assert(substr_count($source, '$this->requireSupplierReadAccess(') === 2, 'create and assign share the supplier-read policy helper');
foreach (['VIEW_SUPPLIERS', 'MANAGE_SUPPLIERS', 'VIEW_ALL', 'MANAGE_REFERENCE_DATA'] as $capabilityName) {
    sc_600_assert(str_contains($source, 'Capabilities::' . $capabilityName), 'supplier-read policy includes ' . $capabilityName);
}

fwrite(STDOUT, "Alkenzy supplier contract permission #600 passed ({$assertions} assertions).\n");
