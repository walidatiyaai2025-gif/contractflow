<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Rest\MobileOperationsController;
use SafeContracts\Roles\Capabilities;

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$readsBefore = count($GLOBALS['sc_test_read_queries']);
$writesBefore = count($GLOBALS['sc_test_queries']);

$result = MobileOperationsController::editPayment(new WP_REST_Request([
    'id' => '21',
    'expected_payment_date' => '2026-08-20',
]));

if (! ($result instanceof WP_Error) || ($result->data['status'] ?? 0) !== 403) {
    fwrite(STDERR, "FAIL: payment mutation must return 403 without manage capability.\n");
    exit(1);
}
if (count($GLOBALS['sc_test_read_queries']) !== $readsBefore || count($GLOBALS['sc_test_queries']) !== $writesBefore) {
    fwrite(STDERR, "FAIL: capability denial must happen before data access.\n");
    exit(1);
}

fwrite(STDOUT, "SafeContracts P9 mutation permission ordering passed (2 assertions).\n");
