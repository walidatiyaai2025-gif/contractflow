<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Notifications\DeviceTokenRepository;

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

printf("SafeContracts FCM device registration diagnostic tests passed (%d assertions).\n", $tests);
