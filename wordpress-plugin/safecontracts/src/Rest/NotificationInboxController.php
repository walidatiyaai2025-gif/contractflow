<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use InvalidArgumentException;
use SafeContracts\Notifications\NotificationInboxService;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class NotificationInboxController
{
    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/notifications', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'index'],
            'permission_callback' => [Router::class, 'canAccess'],
        ]);
        register_rest_route(Router::NAMESPACE, '/notifications/(?P<id>\d+)/read', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'markRead'],
            'permission_callback' => [Router::class, 'canAccess'],
        ]);
        register_rest_route(Router::NAMESPACE, '/device-status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'deviceStatus'],
            'permission_callback' => [Router::class, 'canAccess'],
        ]);
    }

    public static function index(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $access = Router::canAccess();
        if ($access instanceof WP_Error) {
            return $access;
        }
        try {
            ApiAbuseGuard::safeParams($request, ['page', 'per_page']);
            $pagination = ApiRequest::pagination($request);
            $window = min(500, $pagination['page'] * $pagination['per_page']);
            $rows = (new NotificationInboxService())->inbox(get_current_user_id(), $window);
            $offset = ($pagination['page'] - 1) * $pagination['per_page'];
            $items = array_slice($rows, $offset, $pagination['per_page']);
            $unread = count(array_filter($rows, static fn (array $row): bool => ! ($row['is_read'] ?? false)));

            return ApiResponse::ok($items, [
                'page' => $pagination['page'],
                'per_page' => $pagination['per_page'],
                'returned' => count($items),
                'unread_in_bounded_read' => $unread,
                'has_more' => ($offset + count($items)) < count($rows),
            ]);
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_notifications_invalid');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_notifications_failed');
        }
    }

    public static function markRead(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $access = Router::canAccess();
        if ($access instanceof WP_Error) {
            return $access;
        }
        try {
            ApiAbuseGuard::safeParams($request, ['id']);
            $id = ApiRequest::routeId($request);
            if (! (new NotificationInboxService())->markRead($id, get_current_user_id())) {
                return ApiResponse::notFound('Notification');
            }
            return ApiResponse::ok(['notification_id' => $id, 'is_read' => true]);
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_notification_read_invalid');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_notification_read_failed');
        }
    }

    public static function deviceStatus(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $access = Router::canAccess();
        if ($access instanceof WP_Error) {
            return $access;
        }
        try {
            ApiAbuseGuard::safeParams($request, []);
            return ApiResponse::ok(
                (new NotificationInboxService())->deviceStatus(get_current_user_id())
            );
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_device_status_invalid');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_device_status_failed');
        }
    }
}
