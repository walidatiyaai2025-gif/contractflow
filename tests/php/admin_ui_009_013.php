<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\AdminReadRepository;
use SafeContracts\Admin\AdminShell;
use SafeContracts\Admin\CollectionsPage;
use SafeContracts\Admin\FollowUpsPage;
use SafeContracts\Admin\NotificationsPage;
use SafeContracts\Admin\ReportsPage;
use SafeContracts\Admin\UsersRolesPage;
use SafeContracts\Notifications\DeliveryLogRepository;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p6ops_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

SafeContracts\Plugin::instance()->boot();
$read = new AdminReadRepository();

// SC-P6-009 — collection ledger read model is scoped and joins authoritative entities.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true];
$GLOBALS['sc_test_result_queue'] = [[['id' => '1', 'payment_id' => '20', 'amount' => '125.0000', 'collection_date' => '2026-08-15', 'payment_method_name' => 'Bank Transfer', 'customer_name' => 'Acme', 'contract_number' => 'SC-20']]];
$before = count($GLOBALS['sc_test_read_queries']);
$collections = $read->collections(['customer_id' => 4, 'contract_id' => 8, 'accountant_user_id' => 17]);
$collectionQuery = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p6ops_assert(count($collections) === 1, 'SC-P6-009 collection ledger returns scoped rows');
sc_p6ops_assert(str_contains($collectionQuery, 'safecontracts_payment_collections') && str_contains($collectionQuery, 'safecontracts_payment_methods'), 'SC-P6-009 ledger joins collection and payment-method sources');
sc_p6ops_assert(str_contains($collectionQuery, 'c.customer_id = 4') && str_contains($collectionQuery, 'c.id = 8') && str_contains($collectionQuery, 'c.accountant_user_id = 17'), 'SC-P6-009 manager collection filters apply server-side');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [[]];
$before = count($GLOBALS['sc_test_read_queries']);
$read->collections(['accountant_user_id' => 999]);
$assignedCollectionQuery = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p6ops_assert(str_contains($assignedCollectionQuery, 'c.accountant_user_id = 42') && ! str_contains($assignedCollectionQuery, 'accountant_user_id = 999'), 'SC-P6-009 assigned collection scope cannot be widened');

$collectionSource = file_get_contents((string) (new ReflectionClass(CollectionsPage::class))->getFileName()) ?: '';
sc_p6ops_assert(str_contains($collectionSource, 'CollectionService'), 'SC-P6-009 collection mutation delegates to CollectionService');
sc_p6ops_assert(str_contains($collectionSource, 'PaymentMethodRepository')->__toString ?? false, '');
