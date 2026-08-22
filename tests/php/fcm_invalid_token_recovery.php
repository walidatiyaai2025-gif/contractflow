<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Notifications\FirebasePushTransport;

$tests = 0;
function sc_fcm_invalid_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$reflection = new ReflectionClass(FirebasePushTransport::class);
$transport = $reflection->newInstanceWithoutConstructor();
$errorCode = $reflection->getMethod('firebaseErrorCode');
$errorCode->setAccessible(true);
$buildRequest = $reflection->getMethod('buildRequest');
$buildRequest->setAccessible(true);

$notificationOnly = $buildRequest->invoke($transport, 'TEST_DEVICE_TOKEN', [
    'title' => 'SafeContracts',
    'body' => 'Firebase test notification delivered successfully.',
    'data' => [],
]);
sc_fcm_invalid_assert(
    ! array_key_exists('data', $notificationOnly['message']),
    'Notification-only FCM request omits empty custom data instead of emitting an invalid JSON array'
);
$notificationOnlyJson = json_encode($notificationOnly, JSON_THROW_ON_ERROR);
sc_fcm_invalid_assert(
    ! str_contains($notificationOnlyJson, '"data":[]'),
    'FCM request JSON never serializes an empty data map as data:[]'
);

$withData = $buildRequest->invoke($transport, 'TEST_DEVICE_TOKEN', [
    'title' => 'SafeContracts',
    'body' => 'Payment reminder',
    'data' => [
        'payment_id' => 7001,
        'remaining_amount' => '125.5000',
        'optional' => null,
    ],
]);
sc_fcm_invalid_assert(
    ($withData['message']['data'] ?? null) === [
        'payment_id' => '7001',
        'remaining_amount' => '125.5000',
        'optional' => '',
    ],
    'Non-empty FCM custom data remains a string-to-string map'
);
$withDataJson = json_encode($withData, JSON_THROW_ON_ERROR);
sc_fcm_invalid_assert(
    str_contains($withDataJson, '"data":{"payment_id":"7001"'),
    'Non-empty FCM custom data serializes as a JSON object/map'
);

$invalidTokenBody = json_encode([
    'error' => [
        'code' => 400,
        'message' => 'The registration token is not a valid FCM registration token',
        'status' => 'INVALID_ARGUMENT',
        'details' => [[
            '@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError',
            'errorCode' => 'INVALID_ARGUMENT',
        ]],
    ],
], JSON_THROW_ON_ERROR);
sc_fcm_invalid_assert(
    $errorCode->invoke($transport, 400, $invalidTokenBody) === 'firebase_token_not_found',
    'FCM INVALID_ARGUMENT with an invalid registration token is classified as a rejected device token'
);

$invalidTokenFieldBody = json_encode([
    'error' => [
        'code' => 400,
        'message' => 'Request contains an invalid argument.',
        'status' => 'INVALID_ARGUMENT',
        'details' => [[
            '@type' => 'type.googleapis.com/google.rpc.BadRequest',
            'fieldViolations' => [[
                'field' => 'message.token',
                'description' => 'Registration token is invalid.',
            ]],
        ]],
    ],
], JSON_THROW_ON_ERROR);
sc_fcm_invalid_assert(
    $errorCode->invoke($transport, 400, $invalidTokenFieldBody) === 'firebase_token_not_found',
    'FCM token field violations are classified as rejected device tokens without storing raw token material'
);

$invalidPayloadBody = json_encode([
    'error' => [
        'code' => 400,
        'message' => "Invalid value at 'message.data[0].value' (TYPE_STRING), 12",
        'status' => 'INVALID_ARGUMENT',
        'details' => [[
            '@type' => 'type.googleapis.com/google.rpc.BadRequest',
            'fieldViolations' => [[
                'field' => 'message.data[0].value',
                'description' => 'Invalid data payload value.',
            ]],
        ]],
    ],
], JSON_THROW_ON_ERROR);
sc_fcm_invalid_assert(
    $errorCode->invoke($transport, 400, $invalidPayloadBody) === 'firebase_invalid_argument',
    'Non-token INVALID_ARGUMENT responses remain payload/request failures and are not deactivated as devices'
);

$fanoutSource = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Notifications/FirebaseTestNotificationService.php');
sc_fcm_invalid_assert(
    str_contains($fanoutSource, "\$errorCode === 'firebase_token_not_found'"),
    'Rejected invalid registrations follow the existing owner-scoped device deactivation path'
);

$pushDeliverySource = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Notifications/PushDeliveryService.php');
sc_fcm_invalid_assert(
    str_contains($pushDeliverySource, "\$errorCode === 'firebase_token_not_found'")
        && str_contains($pushDeliverySource, 'deactivateOwnedById')
        && str_contains($pushDeliverySource, "'retryable' => \$retryableFailures > 0"),
    'Scheduled production push retires rejected FCM tokens and does not classify them as retryable failures'
);

$directDeliverySource = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Notifications/DirectNotificationService.php');
sc_fcm_invalid_assert(
    str_contains($directDeliverySource, "\$errorCode === 'firebase_token_not_found'")
        && str_contains($directDeliverySource, 'deactivateOwnedById'),
    'Direct admin push also retires rejected FCM tokens instead of leaving them active'
);

printf("SafeContracts FCM request/recovery tests passed (%d assertions).\n", $tests);
