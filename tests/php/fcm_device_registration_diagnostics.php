<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Notifications\DeviceTokenRepository;
use SafeContracts\Notifications\DeviceTokenService;
use SafeContracts\Rest\DevicesController;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_fcm_diag_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$repository = new DeviceTokenRepository();

$GLOBALS['sc_test_result_queue'][] = [
    ['user_id' => '42', 'device_count' => '2'],
    ['user_id' => '77', 'device_count' => '3'],
];
$diagnostics = $repository->activeDiagnostics(42);
sc_fcm_diag_assert($diagnostics['current_user_active_devices'] === 2, 'Current-user active-device count is reported');
sc_fcm_diag_assert($diagnostics['active_devices'] === 5, 'System active-device count is reported without selecting tokens');
sc_fcm_diag_assert($diagnostics['active_users'] === 2, 'System active-user count is reported');
sc_fcm_diag_assert($diagnostics['truncated'] === false, 'Small diagnostic result is not truncated');

$lastRead = end($GLOBALS['sc_test_read_queries']);
sc_fcm_diag_assert(is_string($lastRead) && str_contains($lastRead, 'COUNT(*) AS device_count'), 'Diagnostics query aggregates device counts');
sc_fcm_diag_assert(is_string($lastRead) && ! preg_match('/SELECT\s+[^\n]*\btoken\b/i', $lastRead), 'Diagnostics query does not select raw FCM token material');

$GLOBALS['sc_test_result_queue'][] = [
    ['user_id' => '77', 'device_count' => '1'],
];
$otherUserOnly = $repository->activeDiagnostics(42);
sc_fcm_diag_assert($otherUserOnly['current_user_active_devices'] === 0, 'Diagnostics distinguish no device for current WordPress user');
sc_fcm_diag_assert($otherUserOnly['active_devices'] === 1, 'Diagnostics still report active device for another user');

$page = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Admin/FirebaseSettingsPage.php');
sc_fcm_diag_assert(str_contains($page, "'test_push_other_user_device'"), 'Firebase admin has explicit user-mismatch diagnostic status');
sc_fcm_diag_assert(str_contains($page, 'Compare the WordPress user ID'), 'Firebase admin tells operator how to compare account identity safely');
sc_fcm_diag_assert(str_contains($page, 'No FCM token or bearer credential is displayed here.'), 'Firebase admin explicitly preserves secret-free diagnostics');

// ESC-P0-002F: token mutation endpoints must fail before persistence unless the
// caller explicitly binds the request to the Enterprise Android application.
$GLOBALS['sc_test_current_caps'][Capabilities::ACCESS] = true;
$token = 'esc-fcm-token-123456789012345678901234567890';
$safeApplicationId = 'com.safecontracts.safecontracts_mobile';

$writesBefore = count($GLOBALS['sc_test_queries']);
$missingIdentity = DevicesController::registerDevice(new WP_REST_Request([
    'token' => $token,
    'platform' => 'android',
]));
sc_fcm_diag_assert($missingIdentity instanceof WP_Error && ($missingIdentity->data['status'] ?? 0) === 422, 'FCM register rejects a missing application identity');
sc_fcm_diag_assert(count($GLOBALS['sc_test_queries']) === $writesBefore, 'Missing application identity fails before database mutation');

$wrongIdentity = DevicesController::registerDevice(new WP_REST_Request([
    'token' => $token,
    'platform' => 'android',
    'application_id' => $safeApplicationId,
]));
sc_fcm_diag_assert($wrongIdentity instanceof WP_Error && ($wrongIdentity->data['status'] ?? 0) === 422, 'FCM register rejects the Safe Contract application identity');
sc_fcm_diag_assert(count($GLOBALS['sc_test_queries']) === $writesBefore, 'Wrong application identity fails before database mutation');

$registered = DevicesController::registerDevice(new WP_REST_Request([
    'token' => $token,
    'platform' => 'android',
    'application_id' => DeviceTokenService::APPLICATION_ID,
]));
sc_fcm_diag_assert($registered instanceof WP_REST_Response && $registered->status === 201, 'FCM register accepts the exact ESC application identity');
sc_fcm_diag_assert(($registered->data['data']['application_id'] ?? '') === DeviceTokenService::APPLICATION_ID, 'FCM register response preserves normalized ESC identity');
sc_fcm_diag_assert(count($GLOBALS['sc_test_queries']) === $writesBefore + 1, 'Exact ESC identity reaches device-token persistence once');

$writesBeforeRevoke = count($GLOBALS['sc_test_queries']);
$wrongRevoke = DevicesController::revokeDevice(new WP_REST_Request([
    'token' => $token,
    'application_id' => $safeApplicationId,
]));
sc_fcm_diag_assert($wrongRevoke instanceof WP_Error && ($wrongRevoke->data['status'] ?? 0) === 422, 'FCM revoke rejects the Safe Contract application identity');
sc_fcm_diag_assert(count($GLOBALS['sc_test_queries']) === $writesBeforeRevoke, 'Wrong revoke identity fails before database mutation');

$revoked = DevicesController::revokeDevice(new WP_REST_Request([
    'token' => $token,
    'application_id' => DeviceTokenService::APPLICATION_ID,
]));
sc_fcm_diag_assert($revoked instanceof WP_REST_Response && $revoked->status === 200, 'FCM revoke accepts the exact ESC application identity');
sc_fcm_diag_assert(($revoked->data['data']['application_id'] ?? '') === DeviceTokenService::APPLICATION_ID, 'FCM revoke response preserves normalized ESC identity');
sc_fcm_diag_assert(count($GLOBALS['sc_test_queries']) === $writesBeforeRevoke + 1, 'Exact ESC revoke identity reaches token revocation once');

$registeredActions = $GLOBALS['sc_test_fired_actions']['safecontracts_device_token_registered'] ?? [];
$revokedActions = $GLOBALS['sc_test_fired_actions']['safecontracts_device_token_revoked'] ?? [];
sc_fcm_diag_assert(($registeredActions[count($registeredActions) - 1][3] ?? '') === DeviceTokenService::APPLICATION_ID, 'FCM registration audit hook carries ESC application identity');
sc_fcm_diag_assert(($revokedActions[count($revokedActions) - 1][2] ?? '') === DeviceTokenService::APPLICATION_ID, 'FCM revoke audit hook carries ESC application identity');

printf("SafeContracts FCM device registration diagnostic tests passed (%d assertions).\n", $tests);
