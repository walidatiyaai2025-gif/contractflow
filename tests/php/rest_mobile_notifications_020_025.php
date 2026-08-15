<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Rest\DevicesController;
use SafeContracts\Rest\NotificationsController;
use SafeContracts\Rest\Router;
use SafeContracts\Roles\Capabilities;

$GLOBALS['sc_test_user_meta'] = [];
if (! function_exists('get_user_meta')) {
    function get_user_meta(int $userId, string $key, bool $single = false): mixed
    {
        unset($single);
        return $GLOBALS['sc_test_user_meta'][$userId][$key] ?? '';
    }
}
if (! function_exists('update_user_meta')) {
    function update_user_meta(int $userId, string $key, mixed $value): bool
    {
        $GLOBALS['sc_test_user_meta'][$userId][$key] = $value;
        return true;
    }
}

$tests = 0;
function sc_p9n_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$GLOBALS['sc_test_current_caps'][Capabilities::ACCESS] = true;
$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ASSIGNED] = true;
Router::register();
sc_p9n_assert(isset($GLOBALS['sc_test_routes'][Router::NAMESPACE . '/notifications']), 'SC-P9-020 notifications route is registered');
sc_p9n_assert(isset($GLOBALS['sc_test_routes'][Router::NAMESPACE . '/notifications/(?P<id>\d+)/read']), 'SC-P9-020 notification read-state route is registered');
sc_p9n_assert(isset($GLOBALS['sc_test_routes'][Router::NAMESPACE . '/devices']), 'SC-P9-022 devices route is registered');

$notificationRow = [
    'id' => '91',
    'payment_id' => '21',
    'user_id' => '42',
    'template_code' => 'payment_due',
    'scheduled_for' => '2026-08-15 12:00:00',
    'created_at' => '2026-08-15 12:00:01',
    'device_token_id' => '777',
    'response_code' => '200',
    'error_code' => 'MUST_NOT_LEAK',
];

$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [[$notificationRow]];
$inbox = NotificationsController::index(new WP_REST_Request(['page' => '1', 'per_page' => '2']));
sc_p9n_assert($inbox instanceof WP_REST_Response && $inbox->status === 200, 'SC-P9-020 inbox returns canonical success response');
$item = $inbox->data['data'][0] ?? [];
sc_p9n_assert(($item['id'] ?? 0) === 91 && ($item['payment_id'] ?? 0) === 21, 'SC-P9-020 inbox preserves safe notification identifiers');
sc_p9n_assert(($item['is_read'] ?? true) === false, 'SC-P9-020 unread state starts from server-persisted user state');
sc_p9n_assert(($item['deep_link']['destination'] ?? '') === 'payments' && ($item['deep_link']['resource_id'] ?? 0) === 21, 'SC-P9-021 inbox emits allowlisted payment deep-link metadata');
sc_p9n_assert(! isset($item['device_token_id'], $item['response_code'], $item['error_code'], $item['user_id']), 'SC-P9-020 inbox excludes transport/internal fields');
sc_p9n_assert(($inbox->data['meta']['scope'] ?? '') === 'current_user' && ($inbox->data['meta']['page'] ?? 0) === 1, 'SC-P9-020 inbox metadata is current-user scoped and paged');
$query = implode("\n", $GLOBALS['sc_test_read_queries']);
sc_p9n_assert(str_contains($query, 'WHERE user_id = 42') && str_contains($query, "status = 'sent'"), 'SC-P9-020 repository query is pinned to current user and sent deliveries');

$GLOBALS['sc_test_result_queue'] = [[['id' => '91']]];
$markRead = NotificationsController::markRead(new WP_REST_Request(['id' => '91']));
sc_p9n_assert($markRead instanceof WP_REST_Response && ($markRead->data['data']['is_read'] ?? false) === true, 'SC-P9-020 mark-read persists only after current-user ownership verification');
$readIds = $GLOBALS['sc_test_user_meta'][42]['safecontracts_notification_read_ids'] ?? [];
sc_p9n_assert($readIds === [91], 'SC-P9-020 read state is persisted per user outside Firebase');
$ownershipQuery = $GLOBALS['sc_test_read_queries'][count($GLOBALS['sc_test_read_queries']) - 1] ?? '';
sc_p9n_assert(str_contains($ownershipQuery, 'id = 91') && str_contains($ownershipQuery, 'user_id = 42'), 'SC-P9-045 mark-read ownership query is pinned to notification and current user');

$GLOBALS['sc_test_result_queue'] = [[$notificationRow]];
$readInbox = NotificationsController::index(new WP_REST_Request(['page' => '1', 'per_page' => '2']));
sc_p9n_assert(($readInbox->data['data'][0]['is_read'] ?? false) === true, 'SC-P9-020 persisted read state survives inbox reload');

$GLOBALS['sc_test_result_queue'] = [[]];
$missingRead = NotificationsController::markRead(new WP_REST_Request(['id' => '999']));
sc_p9n_assert($missingRead instanceof WP_Error && ($missingRead->data['status'] ?? 0) === 404, 'SC-P9-045 mark-read rejects a notification outside current-user inbox');

$readsBefore = count($GLOBALS['sc_test_read_queries']);
$badPage = NotificationsController::index(new WP_REST_Request(['page' => '6']));
sc_p9n_assert($badPage instanceof WP_Error && ($badPage->data['status'] ?? 0) === 422, 'SC-P9-045 invalid inbox paging fails closed');
sc_p9n_assert(count($GLOBALS['sc_test_read_queries']) === $readsBefore, 'SC-P9-045 invalid paging fails before database read');

$GLOBALS['sc_test_result_queue'] = [[
    [
        'id' => '7',
        'platform' => 'android',
        'is_active' => '1',
        'last_seen_at' => '2026-08-15 12:00:00',
        'created_at' => '2026-08-01 09:00:00',
        'updated_at' => '2026-08-15 12:00:00',
        'token' => 'MUST_NOT_LEAK',
        'token_hash' => 'MUST_NOT_LEAK',
    ],
]];
$devices = DevicesController::index(new WP_REST_Request());
sc_p9n_assert($devices instanceof WP_REST_Response && $devices->status === 200, 'SC-P9-022 devices endpoint returns canonical success response');
$device = $devices->data['data'][0] ?? [];
sc_p9n_assert(($device['id'] ?? 0) === 7 && ($device['platform'] ?? '') === 'android' && ($device['is_active'] ?? false) === true, 'SC-P9-022 device state exposes safe operational metadata');
sc_p9n_assert(! isset($device['token'], $device['token_hash']), 'SC-P9-022 raw Firebase token material is excluded');
sc_p9n_assert(($devices->data['meta']['scope'] ?? '') === 'current_user', 'SC-P9-022 devices metadata is current-user scoped');

$GLOBALS['sc_test_current_caps'][Capabilities::ACCESS] = false;
$readsBefore = count($GLOBALS['sc_test_read_queries']);
$forbidden = NotificationsController::index(new WP_REST_Request());
sc_p9n_assert($forbidden instanceof WP_Error && ($forbidden->data['status'] ?? 0) === 403, 'SC-P9-045 unauthorized inbox access is forbidden');
sc_p9n_assert(count($GLOBALS['sc_test_read_queries']) === $readsBefore, 'SC-P9-045 forbidden inbox access performs no database read');

printf("SafeContracts P9 mobile notifications/profile REST SC-P9-020..025 passed (%d assertions).\n", $tests);
