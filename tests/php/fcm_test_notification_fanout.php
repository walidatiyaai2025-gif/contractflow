<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Notifications\DeviceTokenRepository;
use SafeContracts\Notifications\FirebaseTestNotificationService;
use SafeContracts\Notifications\PushTransport;

$tests = 0;
function sc_fcm_fanout_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

final class SC_Fcm_Fanout_Transport implements PushTransport
{
    /** @var list<string> */
    public array $calls = [];

    /** @var list<array{success:bool,status_code?:int,error_code?:string|null}> */
    public array $results = [];

    public function send(string $token, array $payload): array
    {
        $this->calls[] = $token;
        sc_fcm_fanout_assert(($payload['title'] ?? '') === 'SafeContracts', 'Fanout keeps the SafeContracts test notification title');
        if ($this->results !== []) {
            return array_shift($this->results);
        }
        return ['success' => true, 'status_code' => 200, 'error_code' => null];
    }
}

$repository = new DeviceTokenRepository();

// Two active devices for the same WordPress user must both receive the test push.
$transport = new SC_Fcm_Fanout_Transport();
$service = new FirebaseTestNotificationService($repository, $transport);
$GLOBALS['sc_test_result_queue'] = [[
    ['id' => '11', 'user_id' => '42', 'token' => 'FANOUT_DEVICE_A', 'platform' => 'android'],
    ['id' => '12', 'user_id' => '42', 'token' => 'FANOUT_DEVICE_B', 'platform' => 'android'],
]];
$result = $service->sendForUser(42);
sc_fcm_fanout_assert($result['status'] === 'ok', 'Two successful device sends return ok');
sc_fcm_fanout_assert($result['attempted'] === 2 && $result['succeeded'] === 2 && $result['failed'] === 0, 'Both active user devices are attempted and succeed');
sc_fcm_fanout_assert($transport->calls === ['FANOUT_DEVICE_A', 'FANOUT_DEVICE_B'], 'Fanout sends to every usable device instead of only the latest row');

// A blank token row must not hide a second valid device for the same user.
$transport = new SC_Fcm_Fanout_Transport();
$service = new FirebaseTestNotificationService($repository, $transport);
$GLOBALS['sc_test_result_queue'] = [[
    ['id' => '21', 'user_id' => '42', 'token' => '   ', 'platform' => 'android'],
    ['id' => '22', 'user_id' => '42', 'token' => 'FANOUT_VALID_AFTER_BLANK', 'platform' => 'android'],
]];
$result = $service->sendForUser(42);
sc_fcm_fanout_assert($result['status'] === 'ok' && $result['attempted'] === 1, 'Blank device token does not suppress another usable device');
sc_fcm_fanout_assert($transport->calls === ['FANOUT_VALID_AFTER_BLANK'], 'Only usable tokens are sent');

// One rejected token must not stop delivery to the remaining devices.
$transport = new SC_Fcm_Fanout_Transport();
$transport->results = [
    ['success' => false, 'status_code' => 404, 'error_code' => 'firebase_token_not_found'],
    ['success' => true, 'status_code' => 200, 'error_code' => null],
];
$service = new FirebaseTestNotificationService($repository, $transport);
$GLOBALS['sc_test_result_queue'] = [[
    ['id' => '31', 'user_id' => '42', 'token' => 'FANOUT_STALE_DEVICE', 'platform' => 'android'],
    ['id' => '32', 'user_id' => '42', 'token' => 'FANOUT_HEALTHY_DEVICE', 'platform' => 'android'],
]];
$beforeWrites = count($GLOBALS['sc_test_queries']);
$result = $service->sendForUser(42);
sc_fcm_fanout_assert($result['status'] === 'partial', 'Mixed device delivery returns partial instead of stopping after the first failure');
sc_fcm_fanout_assert($result['attempted'] === 2 && $result['succeeded'] === 1 && $result['failed'] === 1, 'Partial result reports both device attempts');
sc_fcm_fanout_assert($result['deactivated'] === 1, 'Firebase unregistered device is deactivated');
sc_fcm_fanout_assert($transport->calls === ['FANOUT_STALE_DEVICE', 'FANOUT_HEALTHY_DEVICE'], 'A failed first device does not prevent sending to the second device');
$deactivationSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeWrites));
sc_fcm_fanout_assert(str_contains($deactivationSql, 'user_id = 42') && str_contains($deactivationSql, 'id = 31'), 'Rejected device deactivation is scoped by authenticated owner and device ID');
sc_fcm_fanout_assert(! str_contains($deactivationSql, 'FANOUT_STALE_DEVICE'), 'Rejected raw FCM token is never copied into deactivation SQL');

// If only another user has active devices, report identity mismatch rather than a generic no-device result.
$transport = new SC_Fcm_Fanout_Transport();
$service = new FirebaseTestNotificationService($repository, $transport);
$GLOBALS['sc_test_result_queue'] = [
    [],
    [['user_id' => '77', 'device_count' => '1']],
];
$result = $service->sendForUser(42);
sc_fcm_fanout_assert($result['status'] === 'other_user_device' && $result['attempted'] === 0, 'Other-user-only registrations are distinguished safely');
sc_fcm_fanout_assert($transport->calls === [], 'No push is sent to devices owned by another WordPress user');

// Current-user active rows with no usable token get a dedicated recovery state.
$transport = new SC_Fcm_Fanout_Transport();
$service = new FirebaseTestNotificationService($repository, $transport);
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '41', 'user_id' => '42', 'token' => '', 'platform' => 'android']],
    [['user_id' => '42', 'device_count' => '1']],
];
$result = $service->sendForUser(42);
sc_fcm_fanout_assert($result['status'] === 'no_usable_token', 'Current-user rows without usable token are not mislabeled as another-user devices');

// Total transport failure still attempts every usable device and preserves safe error codes.
$transport = new SC_Fcm_Fanout_Transport();
$transport->results = [
    ['success' => false, 'status_code' => 403, 'error_code' => 'firebase_permission_denied'],
    ['success' => false, 'status_code' => 403, 'error_code' => 'firebase_permission_denied'],
];
$service = new FirebaseTestNotificationService($repository, $transport);
$GLOBALS['sc_test_result_queue'] = [[
    ['id' => '51', 'user_id' => '42', 'token' => 'FANOUT_FAIL_A', 'platform' => 'android'],
    ['id' => '52', 'user_id' => '42', 'token' => 'FANOUT_FAIL_B', 'platform' => 'android'],
]];
$result = $service->sendForUser(42);
sc_fcm_fanout_assert($result['status'] === 'failed' && $result['attempted'] === 2 && $result['failed'] === 2, 'Total failure still attempts every usable device');
sc_fcm_fanout_assert($result['error_codes'] === ['firebase_permission_denied'], 'Duplicate transport errors are reduced to a safe diagnostic code');

$page = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Admin/FirebaseSettingsPage.php');
sc_fcm_fanout_assert(str_contains($page, 'targets every active device'), 'Firebase admin explains all-device test fanout');
sc_fcm_fanout_assert(str_contains($page, "'test_push_partial'"), 'Firebase admin exposes partial-delivery feedback');
sc_fcm_fanout_assert(str_contains($page, "'test_push_no_usable_token'"), 'Firebase admin exposes current-user unusable-token recovery feedback');
sc_fcm_fanout_assert(! str_contains($page, 'latest active device'), 'Firebase admin no longer claims only the latest device is targeted');

printf("SafeContracts FCM test notification fanout tests passed (%d assertions).\n", $tests);
