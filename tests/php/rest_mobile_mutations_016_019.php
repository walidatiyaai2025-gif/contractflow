<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Rest\MobileMutationController;
use SafeContracts\Rest\Router;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p9m2_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

SafeContracts\Plugin::instance()->boot();
Router::register();

$prefix = Router::NAMESPACE;
$paymentRoute = $prefix . '/payments/(?P<id>\\d+)/expected-date';
$collectionRoute = $prefix . '/collections/record';
$followUpRoute = $prefix . '/payments/(?P<payment_id>\\d+)/followups/record';

sc_p9m2_assert(isset($GLOBALS['sc_test_routes'][$paymentRoute]), 'SC-P9-016 expected-date route is registered');
sc_p9m2_assert(isset($GLOBALS['sc_test_routes'][$collectionRoute]), 'SC-P9-017 collection route is registered');
sc_p9m2_assert(isset($GLOBALS['sc_test_routes'][$followUpRoute]), 'SC-P9-019 follow-up route is registered');
sc_p9m2_assert(($GLOBALS['sc_test_routes'][$paymentRoute]['methods'] ?? '') === 'PATCH', 'SC-P9-016 expected-date mutation is PATCH-only');
sc_p9m2_assert(($GLOBALS['sc_test_routes'][$collectionRoute]['methods'] ?? '') === WP_REST_Server::CREATABLE, 'SC-P9-017 collection mutation is POST-only');
sc_p9m2_assert(($GLOBALS['sc_test_routes'][$followUpRoute]['methods'] ?? '') === WP_REST_Server::CREATABLE, 'SC-P9-019 follow-up mutation is POST-only');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ASSIGNED => true,
];
sc_p9m2_assert(MobileMutationController::canManagePayments() instanceof WP_Error, 'SC-P9-016 missing payment capability is forbidden');
sc_p9m2_assert(MobileMutationController::canManageCollections() instanceof WP_Error, 'SC-P9-017 missing collection capability is forbidden');
sc_p9m2_assert(MobileMutationController::canManageFollowUps() instanceof WP_Error, 'SC-P9-019 missing follow-up capability is forbidden');

$deniedCollection = MobileMutationController::recordCollection(new WP_REST_Request([
    'payment_id' => '21',
    'amount' => '10.0000',
    'collection_date' => '2026-08-15',
    'payment_method_id' => '4',
]));
sc_p9m2_assert($deniedCollection instanceof WP_Error && ($deniedCollection->data['status'] ?? 0) === 403, 'SC-P9-017 callback rechecks capability before mutation');

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_PAYMENTS] = true;
$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_COLLECTIONS] = true;
$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_FOLLOWUPS] = true;
sc_p9m2_assert(MobileMutationController::canManagePayments() === true, 'SC-P9-016 scoped payment capability permits mutation boundary');
sc_p9m2_assert(MobileMutationController::canManageCollections() === true, 'SC-P9-017 scoped collection capability permits mutation boundary');
sc_p9m2_assert(MobileMutationController::canManageFollowUps() === true, 'SC-P9-019 scoped follow-up capability permits mutation boundary');

$missingExpected = MobileMutationController::editPaymentExpectedDate(new WP_REST_Request(['id' => '21']));
sc_p9m2_assert($missingExpected instanceof WP_Error && ($missingExpected->data['status'] ?? 0) === 422, 'SC-P9-016 expected_payment_date key is explicit and required');

$invalidExpected = MobileMutationController::editPaymentExpectedDate(new WP_REST_Request([
    'id' => '21',
    'expected_payment_date' => '2026-02-30',
]));
sc_p9m2_assert($invalidExpected instanceof WP_Error && ($invalidExpected->data['status'] ?? 0) === 422, 'SC-P9-016 invalid expected date fails before domain mutation');

$unknownPaymentField = MobileMutationController::editPaymentExpectedDate(new WP_REST_Request([
    'id' => '21',
    'expected_payment_date' => '2026-08-20',
    'due_date' => '2099-01-01',
]));
sc_p9m2_assert($unknownPaymentField instanceof WP_Error && ($unknownPaymentField->data['status'] ?? 0) === 422, 'SC-P9-016 contractual due_date cannot enter light-edit body');

$missingMethod = MobileMutationController::recordCollection(new WP_REST_Request([
    'payment_id' => '21',
    'amount' => '10.0000',
    'collection_date' => '2026-08-15',
]));
sc_p9m2_assert($missingMethod instanceof WP_Error && ($missingMethod->data['status'] ?? 0) === 422, 'SC-P9-017 payment method is mandatory');

$arrayAmount = MobileMutationController::recordCollection(new WP_REST_Request([
    'payment_id' => '21',
    'amount' => ['10.0000'],
    'collection_date' => '2026-08-15',
    'payment_method_id' => '4',
]));
sc_p9m2_assert($arrayAmount instanceof WP_Error && ($arrayAmount->data['status'] ?? 0) === 422, 'SC-P9-017 structured amount input fails closed');

$unknownCollectionField = MobileMutationController::recordCollection(new WP_REST_Request([
    'payment_id' => '21',
    'amount' => '10.0000',
    'collection_date' => '2026-08-15',
    'payment_method_id' => '4',
    'remaining_amount' => '0.0000',
]));
sc_p9m2_assert($unknownCollectionField instanceof WP_Error && ($unknownCollectionField->data['status'] ?? 0) === 422, 'SC-P9-017 client cannot submit calculated remaining balance');

$missingPromiseDate = MobileMutationController::recordFollowUp(new WP_REST_Request([
    'payment_id' => '21',
    'operation' => 'promise',
    'note' => 'Customer committed',
]));
sc_p9m2_assert($missingPromiseDate instanceof WP_Error && ($missingPromiseDate->data['status'] ?? 0) === 422, 'SC-P9-019 promise requires promised_date');

$ambiguousPromise = MobileMutationController::recordFollowUp(new WP_REST_Request([
    'payment_id' => '21',
    'operation' => 'promise',
    'promised_date' => '2026-08-20',
    'deferred_until' => '2026-08-21',
]));
sc_p9m2_assert($ambiguousPromise instanceof WP_Error && ($ambiguousPromise->data['status'] ?? 0) === 422, 'SC-P9-019 promise rejects deferred date pollution');

$unknownOperation = MobileMutationController::recordFollowUp(new WP_REST_Request([
    'payment_id' => '21',
    'operation' => 'unknown',
]));
sc_p9m2_assert($unknownOperation instanceof WP_Error && ($unknownOperation->data['status'] ?? 0) === 422, 'SC-P9-019 unknown operation fails closed instead of becoming a note');

$datedNote = MobileMutationController::recordFollowUp(new WP_REST_Request([
    'payment_id' => '21',
    'operation' => 'note',
    'note' => 'Called customer',
    'promised_date' => '2026-08-20',
]));
sc_p9m2_assert($datedNote instanceof WP_Error && ($datedNote->data['status'] ?? 0) === 422, 'SC-P9-019 note cannot smuggle operational date state');

$source = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Rest/MobileMutationController.php') ?: '';
sc_p9m2_assert(! str_contains($source, '$wpdb'), 'SC-P9-016/017/019 mutation boundary contains no direct SQL');
sc_p9m2_assert(str_contains($source, 'new PaymentService()'), 'SC-P9-016 delegates to PaymentService');
sc_p9m2_assert(str_contains($source, "\$payment['due_date']"), 'SC-P9-016 preserves contractual due date while editing expected date');
sc_p9m2_assert(str_contains($source, 'new CollectionService()'), 'SC-P9-017 delegates financial settlement to CollectionService');
sc_p9m2_assert(str_contains($source, 'new FollowUpService()'), 'SC-P9-019 delegates operational workflow to FollowUpService');
sc_p9m2_assert(! str_contains($source, 'remaining_amount'), 'SC-P9-017 mutation controller never calculates or accepts remaining balance');

$referenceSource = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Rest/ReferenceDataController.php') ?: '';
sc_p9m2_assert(str_contains($referenceSource, 'all(true)'), 'SC-P9-018 lookup exposes active backend payment methods only');
sc_p9m2_assert(str_contains($referenceSource, "'id' =>") && str_contains($referenceSource, "'name' =>"), 'SC-P9-018 lookup exposes stable payment method IDs and names');

$dataSource = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Rest/DataController.php') ?: '';
sc_p9m2_assert(str_contains($dataSource, "'/followups' => 'followUps'"), 'SC-P9-019 scoped follow-up queue endpoint remains registered');
sc_p9m2_assert(str_contains($dataSource, "'/payments/(?P<payment_id>\\d+)/followups'"), 'SC-P9-019 protected payment history endpoint remains registered');

fwrite(STDOUT, "SafeContracts P9 mobile mutation checks SC-P9-016..019 passed ({$tests} assertions).\n");
