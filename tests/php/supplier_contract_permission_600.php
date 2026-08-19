<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Contracts\CounterpartyContractService;
use SafeContracts\Diagnostics\RuntimeInspector;
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

// #600: a supplier exposed by the UI under VIEW_ALL must remain valid at save time.
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

// Reference-data managers have the same supplier-read contract.
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

// CREATE_CONTRACTS alone must not grant supplier visibility/use.
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

$source = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Contracts/CounterpartyContractService.php');
sc_600_assert(substr_count($source, '$this->requireSupplierReadAccess(') === 2, 'create and assign share the supplier-read policy helper');
foreach (['VIEW_SUPPLIERS', 'MANAGE_SUPPLIERS', 'VIEW_ALL', 'MANAGE_REFERENCE_DATA'] as $capabilityName) {
    sc_600_assert(str_contains($source, 'Capabilities::' . $capabilityName), 'supplier-read policy includes ' . $capabilityName);
}

// #602: Runtime Inspector must redact secrets recursively before persistence.
$sanitized = RuntimeInspector::sanitizeContext([
    'counterparty_id' => 3101,
    'contract_number' => 'SUP-TRACE-1',
    'password' => 'never-store-me',
    'api_token' => 'never-store-token',
    'nested' => [
        'nonce' => 'never-store-nonce',
        'authorization' => 'Bearer secret',
        'safe_value' => 'visible',
    ],
]);
sc_600_assert(($sanitized['counterparty_id'] ?? null) === 3101, 'runtime inspector preserves non-sensitive diagnostic context');
sc_600_assert(($sanitized['password'] ?? '') === '[redacted]' && ($sanitized['api_token'] ?? '') === '[redacted]', 'runtime inspector redacts top-level credentials and tokens');
sc_600_assert(($sanitized['nested']['nonce'] ?? '') === '[redacted]' && ($sanitized['nested']['authorization'] ?? '') === '[redacted]', 'runtime inspector redacts nested auth material');
sc_600_assert(($sanitized['nested']['safe_value'] ?? '') === 'visible', 'runtime inspector retains safe nested context');

// Use an invalid zero counterparty ID so the failure stage is deterministic and does
// not depend on the mock database result queue. The service must capture the exact
// breadcrumb before rethrowing the original validation error.
RuntimeInspector::clear();
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::CREATE_CONTRACTS => true,
    Capabilities::VIEW_ALL => true,
];
try {
    $service->create([
        'contract_number' => 'SUP-STAGE-6201',
        'counterparty_type' => 'supplier',
        'counterparty_id' => 0,
        'currency_code' => 'KWD',
    ]);
    sc_600_assert(false, 'invalid supplier ID must fail for runtime stage regression');
} catch (Throwable $error) {
    sc_600_assert($error instanceof \InvalidArgumentException, 'runtime stage regression preserves the original validation exception');
}
$latest = RuntimeInspector::recent()[0] ?? [];
sc_600_assert(str_starts_with((string) ($latest['id'] ?? ''), 'SC-'), 'runtime inspector emits a correlation ID');
sc_600_assert(($latest['operation'] ?? '') === 'contract.create', 'runtime inspector records the failing operation');
sc_600_assert(($latest['stage'] ?? '') === 'contract.create.counterparty.active', 'runtime inspector identifies the exact contract-create stage');
sc_600_assert(($latest['context']['counterparty_id'] ?? null) === 0, 'runtime event preserves the selected invalid counterparty ID safely');

// Retention is intentionally bounded.
RuntimeInspector::clear();
for ($index = 0; $index < RuntimeInspector::MAX_EVENTS + 5; $index++) {
    RuntimeInspector::begin('retention.test', ['index' => $index]);
    RuntimeInspector::stage('forced.failure');
    RuntimeInspector::capture(new \RuntimeException('bounded retention test'));
    RuntimeInspector::finish();
}
sc_600_assert(count(RuntimeInspector::recent()) === RuntimeInspector::MAX_EVENTS, 'runtime inspector retains only the bounded event limit');
RuntimeInspector::clear();

// Legacy handlers that only expose safecontracts_status still get a correlation ID.
$_REQUEST['action'] = 'safecontracts_legacy_failure';
$fallbackLocation = RuntimeInspector::captureFailedRedirect('https://example.test/wp-admin/admin.php?page=safecontracts-payments&safecontracts_status=delete_failed');
sc_600_assert(str_contains($fallbackLocation, 'safecontracts_runtime_id=SC-'), 'generic SafeContracts failure redirects receive a runtime correlation ID');
$fallback = RuntimeInspector::recent()[0] ?? [];
sc_600_assert(($fallback['stage'] ?? '') === 'admin.redirect.status', 'generic failure redirect records the fallback runtime stage');
RuntimeInspector::clear();
unset($_REQUEST['action']);

$pluginSource = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Plugin.php');
$runtimeSource = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Diagnostics/RuntimeInspector.php');
sc_600_assert(str_contains($pluginSource, 'RuntimeInspector::register()'), 'runtime inspector is registered during plugin boot');
sc_600_assert(str_contains($pluginSource, 'RuntimeInspectorPage::class'), 'runtime inspector admin page is registered');
sc_600_assert(str_contains($runtimeSource, 'captureFailedRedirect'), 'runtime inspector covers generic SafeContracts failure redirects');

fwrite(STDOUT, "Alkenzy supplier contract permission + runtime inspector #600/#602 passed ({$assertions} assertions).\n");
