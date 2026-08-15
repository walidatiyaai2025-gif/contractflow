<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Contracts\ContractHistoryService;
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
        'notes' => 'Current contract notes',
        'is_archived' => '0',
    ], $overrides);
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE];
$activate();
do_action('plugins_loaded');

sc_history_assert(isset($GLOBALS['sc_test_actions']['safecontracts_contract_created']), 'history recorder subscribes to contract creation');
sc_history_assert(isset($GLOBALS['sc_test_actions']['safecontracts_contract_edited']), 'history recorder subscribes to contract edits');
sc_history_assert(isset($GLOBALS['sc_test_actions']['safecontracts_contract_status_changed']), 'history recorder subscribes to lifecycle changes');
sc_history_assert(isset($GLOBALS['sc_test_actions']['safecontracts_contract_adjustment_added']), 'history recorder subscribes to financial adjustments');
sc_history_assert(isset($GLOBALS['sc_test_actions']['safecontracts_contract_attachment_added']), 'history recorder subscribes to attachment changes');

$GLOBALS['sc_test_result_queue'] = [[sc_history_contract(['notes' => 'Revised notes'])]];
$GLOBALS['wpdb']->insert_id = 5001;
$queryCount = count($GLOBALS['sc_test_queries']);
do_action('safecontracts_contract_edited', 501, 42);
sc_history_assert(count($GLOBALS['sc_test_queries']) === $queryCount + 1, 'domain event appends one contract-history row');
$historyInsert = end($GLOBALS['sc_test_queries']);
sc_history_assert(str_contains((string) $historyInsert, 'wp_safecontracts_contract_history'), 'history append uses dedicated prefixed table');
sc_history_assert(str_contains((string) $historyInsert, "'edited'"), 'history append records normalized event type');
sc_history_assert(str_contains((string) $historyInsert, 'Revised notes'), 'history append stores the current contract snapshot');
sc_history_assert(str_contains((string) $historyInsert, '501'), 'history append is contract-scoped');

$service = new ContractHistoryService();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true];
$GLOBALS['sc_test_result_queue'] = [
    [sc_history_contract()],
    [[
        'id' => '5001',
        'contract_id' => '501',
        'event_type' => 'edited',
        'actor_user_id' => '42',
        'snapshot_json' => json_encode(sc_history_contract(), JSON_THROW_ON_ERROR),
        'created_at' => '2026-08-15 09:50:00',
    ]],
];
$history = $service->forContract(501, 1000);
sc_history_assert(count($history) === 1, 'Manager can read contract history');
sc_history_assert($history[0]['event_type'] === 'edited', 'history reader normalizes event type');
sc_history_assert($history[0]['actor_user_id'] === 42, 'history reader normalizes actor ID');
sc_history_assert($history[0]['snapshot']['contract_number'] === 'SC-501', 'history reader decodes state snapshot');
$historyReadSql = end($GLOBALS['sc_test_read_queries']);
sc_history_assert(str_contains((string) $historyReadSql, 'ORDER BY created_at DESC, id DESC'), 'history is returned newest first');
sc_history_assert(str_contains((string) $historyReadSql, 'LIMIT 500'), 'history read limit is bounded server-side');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [
    [sc_history_contract(['accountant_user_id' => '42'])],
    [],
];
sc_history_assert($service->forContract(501) === [], 'assigned Accountant can read own contract history');

$GLOBALS['sc_test_result_queue'] = [[sc_history_contract(['accountant_user_id' => '99'])]];
$readsBeforeScopeDenial = count($GLOBALS['sc_test_read_queries']);
sc_history_expect(DomainException::class, fn () => $service->forContract(501), 'Accountant cannot read another Accountant contract history');
sc_history_assert(count($GLOBALS['sc_test_read_queries']) === $readsBeforeScopeDenial + 1, 'scope denial reads contract ownership but not history rows');

$GLOBALS['sc_test_current_caps'] = [];
$readsBeforeAccessDenial = count($GLOBALS['sc_test_read_queries']);
sc_history_expect(DomainException::class, fn () => $service->forContract(501), 'history requires SafeContracts access capability');
sc_history_assert(count($GLOBALS['sc_test_read_queries']) === $readsBeforeAccessDenial, 'missing access is rejected before database reads');

$dbDeltaCount = count($GLOBALS['sc_test_dbdelta']);
$optionsBeforeDeactivate = $GLOBALS['sc_test_options'];
$deactivate = $GLOBALS['sc_test_deactivation_hooks'][SAFECONTRACTS_FILE];
$deactivate();
sc_history_assert(count($GLOBALS['sc_test_dbdelta']) === $dbDeltaCount, 'deactivation does not remove contract history schema');
sc_history_assert($GLOBALS['sc_test_options'] === $optionsBeforeDeactivate, 'deactivation preserves contract history migration state');

echo "SafeContracts contract history tests passed ({$historyTests} assertions).\n";
