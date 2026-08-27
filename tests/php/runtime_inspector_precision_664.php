<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Diagnostics\RuntimeInspector;
use SafeContracts\Roles\Capabilities;

$assertions = 0;

function sc_664_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

RuntimeInspector::clear();
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::MANAGE_SYSTEM => true,
    Capabilities::MANAGE_PAYMENTS => true,
    Capabilities::MANAGE_COLLECTIONS => true,
    Capabilities::MANAGE_NOTIFICATIONS => true,
];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_REQUEST = [
    'action' => 'safecontracts_record_collection_admin',
    'payment_id' => '7001',
    'payment_method_id' => '0',
    'amount' => '125.5000',
    'collection_date' => '2026-08-27',
    'password' => 'must-not-be-captured',
    'details' => 'must-not-be-captured',
];

RuntimeInspector::begin('settlement.record', [
    'payment_id' => 7001,
    'payment_method_id' => 0,
    'amount' => '125.5000',
]);
RuntimeInspector::stage('settlement.record.payment_method.active');
$runtimeId = RuntimeInspector::capture(
    new InvalidArgumentException('Settlement payment method must be an active SafeContracts payment method.')
);
RuntimeInspector::finish();

$redirect = RuntimeInspector::captureFailedRedirect(
    'https://example.test/wp-admin/admin.php?page=safecontracts-collections&safecontracts_status=invalid'
);
$events = RuntimeInspector::recent();
$event = $events[0] ?? [];

sc_664_assert(count($events) === 1, 'exact captured exception is not replaced by a generic fallback event');
sc_664_assert(str_contains($redirect, 'safecontracts_runtime_id=' . $runtimeId), 'redirect remains linked to the exact captured correlation ID');
sc_664_assert(($event['schema_version'] ?? 0) === RuntimeInspector::EVENT_SCHEMA_VERSION, 'runtime event records the diagnostic schema version');
sc_664_assert(($event['operation'] ?? '') === 'settlement.record', 'runtime event records the exact settlement operation');
sc_664_assert(($event['stage'] ?? '') === 'settlement.record.payment_method.active', 'runtime event records the exact failing stage');
sc_664_assert(($event['classification'] ?? '') === 'validation', 'invalid argument failure is classified deterministically as validation');
sc_664_assert(($event['root_cause'] ?? '') === 'Settlement payment method must be an active SafeContracts payment method.', 'runtime event records the exact root cause');
sc_664_assert(($event['exception_class'] ?? '') === InvalidArgumentException::class, 'runtime event records the exact exception class');
sc_664_assert(($event['source']['file'] ?? '') === basename(__FILE__), 'runtime event records the source file without exposing a filesystem path');
sc_664_assert((int) ($event['source']['line'] ?? 0) > 0, 'runtime event records the source line');
sc_664_assert(($event['request']['input']['payment_id'] ?? '') === '7001', 'safe request snapshot retains payment ID');
sc_664_assert(($event['request']['input']['payment_method_id'] ?? '') === '0', 'safe request snapshot retains payment method ID');
sc_664_assert(($event['request']['input']['amount'] ?? '') === '125.5000', 'safe request snapshot retains the submitted amount');
sc_664_assert(! array_key_exists('password', (array) ($event['request']['input'] ?? [])), 'safe request snapshot never captures passwords');
sc_664_assert(! array_key_exists('details', (array) ($event['request']['input'] ?? [])), 'safe request snapshot never captures free-form details');
sc_664_assert(($event['capabilities'][Capabilities::MANAGE_COLLECTIONS] ?? false) === true, 'runtime event captures collection permission state');
sc_664_assert(($event['capabilities'][Capabilities::MANAGE_NOTIFICATIONS] ?? false) === true, 'runtime event captures capabilities beyond the old abbreviated list');

$collectionSource = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Collections/CollectionService.php');
$deletionSource = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Deletion/SafeDeletionService.php');
foreach ([
    'settlement.record.payment.lock',
    'settlement.record.payment_method.active',
    'settlement.record.ledger.integrity',
    'settlement.record.payment_capacity',
    'settlement.record.contract_capacity',
    'settlement.record.database.insert',
    'settlement.record.payment.update',
    'settlement.record.transaction.rollback',
] as $stage) {
    sc_664_assert(str_contains($collectionSource, $stage), 'settlement tracing contains stage ' . $stage);
}
foreach ([
    'payment.archive.state',
    'payment.archive.collection_history.blocked',
    'payment.archive.database.update',
    'collection.archive.load',
    'collection.archive.ledger.integrity',
    'collection.archive.payment.reconcile',
    'collection.archive.transaction.rollback',
] as $stage) {
    sc_664_assert(str_contains($deletionSource, $stage), 'deletion tracing contains stage ' . $stage);
}

RuntimeInspector::clear();
unset($_REQUEST, $_SERVER['REQUEST_METHOD']);

fwrite(STDOUT, "Runtime Inspector precision #664 passed ({$assertions} assertions).\n");
