<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Rest\MutationController;
use SafeContracts\Rest\Router;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p9m_assert(bool $ok, string $message): void
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
$contractRoute = $prefix . '/contracts/(?P<id>\\d+)/light';
$paymentRoute = $prefix . '/payments/(?P<id>\\d+)/expected-date';
$collectionRoute = $prefix . '/collections/record';
$followUpRoute = $prefix . '/payments/(?P<payment_id>\\d+)/followups/record';

sc_p9m_assert(isset($GLOBALS['sc_test_routes'][$contractRoute]), 'SC-P9-013 contract light-edit route is registered');
sc_p9m_assert(isset($GLOBALS['sc_test_routes'][$paymentRoute]), 'SC-P9-016 payment expected-date route is registered');
sc_p9m_assert(isset($GLOBALS['sc_test_routes'][$collectionRoute]), 'SC-P9-017 collection record route is registered');
sc_p9m_assert(isset($GLOBALS['sc_test_routes'][$followUpRoute]), 'SC-P9-019 follow-up record route is registered');
sc_p9m_assert(($GLOBALS['sc_test_routes'][$contractRoute]['methods'] ?? '') === 'PATCH', 'SC-P9-013 contract mutation is PATCH-only');
sc_p9m_assert(($GLOBALS['sc_test_routes'][$paymentRoute]['methods'] ?? '') === 'PATCH', 'SC-P9-016 payment mutation is PATCH-only');
sc_p9m_assert(($GLOBALS['sc_test_routes'][$collectionRoute]['methods'] ?? '') === WP_REST_Server::CREATABLE, 'SC-P9-017 collection mutation is POST-only');
sc_p9m_assert(($GLOBALS['sc_test_routes'][$followUpRoute]['methods'] ?? '') === WP_REST_Server::CREATABLE, 'SC-P9-019 follow-up mutation is POST-only');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
sc_p9m_assert(MutationController::canEditContracts() instanceof WP_Error, 'SC-P9-013 missing edit capability is forbidden');
sc_p9m_assert(MutationController::canManagePayments() instanceof WP_Error, 'SC-P9-016 missing payment capability is forbidden');
sc_p9m_assert(MutationController::canManageCollections() instanceof WP_Error, 'SC-P9-017 missing collection capability is forbidden');
sc_p9m_assert(MutationController::canManageFollowUps() instanceof WP_Error, 'SC-P9-019 missing follow-up capability is forbidden');

$deniedFollowUp = MutationController::recordFollowUp(new WP_REST_Request(['payment_id' => '21']));
sc_p9m_assert($deniedFollowUp instanceof WP_Error && ($deniedFollowUp->data['status'] ?? 0) === 403, 'SC-P9-019 direct callback rechecks capability before parsing');

$GLOBALS['sc_test_current_caps'][Capabilities::EDIT_CONTRACTS] = true;
$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_PAYMENTS] = true;
$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_COLLECTIONS] = true;
$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_FOLLOWUPS] = true;
sc_p9m_assert(MutationController::canEditContracts() === true, 'SC-P9-013 capability + scoped access permits contract edit');
sc_p9m_assert(MutationController::canManagePayments() === true, 'SC-P9-016 capability + scoped access permits payment edit');
sc_p9m_assert(MutationController::canManageCollections() === true, 'SC-P9-017 capability + scoped access permits collection entry');
sc_p9m_assert(MutationController::canManageFollowUps() === true, 'SC-P9-019 capability + scoped access permits follow-up entry');

$badContract = MutationController::editContract(new WP_REST_Request([
    'id' => '11',
    'start_date' => '2026-01-01',
]));
sc_p9m_assert($badContract instanceof WP_Error && ($badContract->data['status'] ?? 0) === 422, 'SC-P9-013 partial date edits fail closed');

$badDateContract = MutationController::editContract(new WP_REST_Request([
    'id' => '11',
    'start_date' => '2026-13-01',
    'end_date' => '2026-12-31',
]));
sc_p9m_assert($badDateContract instanceof WP_Error && ($badDateContract->data['status'] ?? 0) === 422, 'SC-P9-013 invalid dates fail before domain mutation');

$unknownContract = MutationController::editContract(new WP_REST_Request([
    'id' => '11',
    'base_value' => '999.0000',
]));
sc_p9m_assert($unknownContract instanceof WP_Error && ($unknownContract->data['status'] ?? 0) === 422, 'SC-P9-013 financial fields cannot enter light edit');

$badPayment = MutationController::editPaymentExpectedDate(new WP_REST_Request(['id' => '21']));
sc_p9m_assert($badPayment instanceof WP_Error && ($badPayment->data['status'] ?? 0) === 422, 'SC-P9-016 expected_payment_date is explicit and required');

$badCollection = MutationController::recordCollection(new WP_REST_Request([
    'payment_id' => '21',
    'amount' => '10.0000',
    'collection_date' => '2026-08-15',
]));
sc_p9m_assert($badCollection instanceof WP_Error && ($badCollection->data['status'] ?? 0) === 422, 'SC-P9-017 payment method is mandatory');

$badFollowUp = MutationController::recordFollowUp(new WP_REST_Request([
    'payment_id' => '21',
    'operation' => 'promise',
    'note' => 'Customer called',
]));
sc_p9m_assert($badFollowUp instanceof WP_Error && ($badFollowUp->data['status'] ?? 0) === 422, 'SC-P9-019 promise requires promised date');

$source = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Rest/MutationController.php') ?: '';
sc_p9m_assert(! str_contains($source, '$wpdb'), 'SC-P9-013/016/017/019 mutation boundary contains no direct SQL');
sc_p9m_assert(str_contains($source, 'new ContractService()'), 'SC-P9-013 delegates to ContractService');
sc_p9m_assert(str_contains($source, 'new PaymentService()'), 'SC-P9-016 delegates to PaymentService');
sc_p9m_assert(str_contains($source, 'new CollectionService()'), 'SC-P9-017 delegates to CollectionService');
sc_p9m_assert(str_contains($source, 'new FollowUpService()'), 'SC-P9-019 delegates to FollowUpService');
sc_p9m_assert(str_contains($source, '409'), 'SC-P9-013/016/017/019 surface archived domain conflicts distinctly');

fwrite(STDOUT, "SafeContracts P9 mobile mutation checks passed ({$tests}).\n");
