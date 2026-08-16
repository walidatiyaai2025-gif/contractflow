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

printf("SafeContracts FCM invalid-token recovery tests passed (%d assertions).\n", $tests);
