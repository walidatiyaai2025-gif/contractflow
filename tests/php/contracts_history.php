<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Contracts\ContractHistoryService;
use SafeContracts\Database\Migrator;
use SafeContracts\Roles\Capabilities;

$historyTests = 0;

function sc_history_assert(bool $condition, string $message): void
{
    global $historyTests;
    $historyTests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_history_expect(string $class, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        sc_history_assert($error instanceof $class, $message);
        return;
    }
    sc_history_assert(false, $message);
}

/** @return array<string, mixed> */
function sc_history_contract(array $overrides = []): array
{
    return array_merge([
        'id' => '501',
        'contract_number' => 'SC-501',
        'customer_id' => '7',
        'accountant_user_id' => '42',
        'status' => 'active',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'base_value' => '1000.0000',
        'notes' => 'History test',
        'is_archived' => '0',
    ], $overrides);
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_history_assert(is_callable($activate), 'plugin activation hook is available');
$activate();
sc_history_assert(Migrator::LATEST_VERSION === '1.5.0', 'contract history migration is current');
sc_history_assert(count($GLOBALS['sc_test_dbdelta']) === 8, 'contract history adds one append-only table');

$historySchema = $GLOBALS['sc_test_dbdelta'][7];
sc_history_assert(str_contains($historySchema, 'wp_safecontracts_contract_history'), 'history table uses WordPress prefix');
sc_history_assert(str_contains($historySchema, 'contract_id bigint(20) unsigned NOT NULL'), 'history rows belong to contracts');
sc_history_assert(str_contains($historySchema, 'action varchar(64) NOT NULL'), 'history action is persisted');
sc_history_assert(str_contains($historySchema, 'actor_user_id bigint(20) unsigned NULL'), 'history actor is traceable');
sc_history_assert(str_contains($historySchema, 'context_json longtext NULL'), 'history stores structured event context');
sc_history_assert(str_contains($historySchema, 'KEY contract_created (contract_id, created_at, id)'), 'contract timeline lookup is indexed');
sc_history_assert(str_contains($historySchema, 'KEY actor_created (actor_user_id, created_at, id)'), 'actor/time investigation lookup is indexed');

$queryCountBeforeBoot = count($GLOBALS['sc_test_queries']);
do_action('plugins_loaded');
sc_history_assert(count($GLOBALS['sc_test_queries']) === $queryCountBeforeBoot, 'runtime boot does not replay current migration');
sc_history_assert(isset($GLOBALS['sc_test_actions']['safecontracts_contract_created']), 'contract-created history listener registered');
sc_history_assert(isset($GLOBALS['sc_test_actions']['safecontracts_contract_status_changed']), 'status-change history listener registered');
sc_history_assert(isset($GLOBALS['sc_test_actions']['safecontracts_contract_customer_assigned']), 'customer-assignment history listener registered');

$beforeCreateHistory = count($GLOBALS['sc_test_queries']);
do_action('safecontracts_contract_created', 501, 42, 7, 77);
sc_history_assert(count($GLOBALS['sc_test_queries']) === $beforeCreateHistory + 1, 'contract create event appends one history row');
$createHistorySql = (string) end($GLOBALS['sc_test_queries']);
sc_history_assert(str_contains($createHistorySql, 'wp_safecontracts_contract_history'), 'history event writes dedicated table');
sc_history_assert(str_contains($createHistorySql, "'created'"), 'create action is recorded');
sc_history_assert(str_contains($createHistorySql, '"customer_id":7'), 'create context includes customer relation');
sc_history_assert(str_contains($createHistorySql, '"accountant_user_id":77'), 'create context includes responsible Accountant');

$beforeStatusHistory = count($GLOBALS['sc_test_queries']);
do_action('safecontracts_contract_status_changed', 501, 'draft', 'active', 42);
sc_history_assert(count($GLOBALS['sc_test_queries']) === $beforeStatusHistory + 1, 'status event appends one history row');
$statusHistorySql = (string) end($GLOBALS['sc_test_queries']);
sc_history_assert(str_contains($statusHistorySql, "'status_changed'"), 'status action is recorded');
sc_history_assert(str_contains($statusHistorySql, '"from":"draft"'), 'status history records previous state');
sc_history_assert(str_contains($statusHistorySql, '"to":"active"'), 'status history records target state');

$service = new ContractHistoryService();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [
    [sc_history_contract()],
    [[
        'id' => '10',
        'action' => 'status_changed',
        'actor_user_id' => '42',
        'context_json' => '{"from":"draft","to":"active"}',
        'created_at' => '2026-08-15 09:00:00',
    ]],
];
$history = $service->forContract(501, 25);
sc_history_assert(count($history) === 1, 'assigned Accountant can read own contract history');
sc_history_assert($history[0]['action'] === 'status_changed', 'history reader normalizes action');
sc_history_assert($history[0]['actor_user_id'] === 42, 'history reader normalizes actor ID');
sc_history_assert($history[0]['context']['to'] === 'active', 'history reader decodes structured context');
$historyReadSql = (string) end($GLOBALS['sc_test_read_queries']);
sc_history_assert(str_contains($historyReadSql, 'ORDER BY created_at DESC, id DESC'), 'history is returned newest-first');
sc_history_assert(str_contains($historyReadSql, 'LIMIT 25'), 'history reader uses bounded server-side limit');

$GLOBALS['sc_test_result_queue'] = [[sc_history_contract(['accountant_user_id' => '99'])]];
sc_history_expect(\DomainException::class, fn () => $service->forContract(501), 'Accountant cannot read out-of-scope contract history');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true];
$GLOBALS['sc_test_result_queue'] = [
    [sc_history_contract(['accountant_user_id' => '99'])],
    [],
];
sc_history_assert($service->forContract(501) === [], 'Manager all-data scope can read any accessible contract timeline');

$GLOBALS['sc_test_current_caps'] = [Capabilities::VIEW_ALL => true];
$GLOBALS['sc_test_result_queue'] = [[sc_history_contract()]];
sc_history_expect(\DomainException::class, fn () => $service->forContract(501), 'SafeContracts ACCESS capability is required for history reads');

sc_history_assert(! isset($GLOBALS['sc_test_fired_actions']['safecontracts_contract_history_failed']), 'normal contract history capture produces no failure event');

echo "SafeContracts contract history tests passed ({$historyTests} assertions).\n";
