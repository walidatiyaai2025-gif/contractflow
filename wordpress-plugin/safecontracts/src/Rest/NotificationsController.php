<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use InvalidArgumentException;
use SafeContracts\Notifications\DeliveryLogRepository;
use SafeContracts\Notifications\NotificationReadStateRepository;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class NotificationsController
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
    }

    public static function index(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $access = Permission::access();
        if ($access instanceof WP_Error) {
            return $access;
        }

        try {
            $params = ApiAbuseGuard::safeParams($request, ['page', 'per_page']);
            $page = self::boundedInt($params['page'] ?? 1, 1, 5, 'page');
            $perPage = self::boundedInt($params['per_page'] ?? 25, 1, 50, 'per_page');
            $userId = self::currentUserId();
            $repository = new DeliveryLogRepository();
            $readRepository = new NotificationReadStateRepository();
            $readSet = array_fill_keys($readRepository->idsForUser($userId), true);

            $rows = $repository->recentForUser(
                $userId,
                $perPage + 1,
                ($page - 1) * $perPage
            );
            $hasMore = count($rows) > $perPage;
            $rows = array_slice($rows, 0, $perPage);

            $items = array_map(
                static function (array $row) use ($readSet): array {
                    $id = (int) ($row['id'] ?? 0);
                    $paymentId = (int) ($row['payment_id'] ?? 0);
                    return [
                        'id' => $id,
                        'payment_id' => $paymentId,
                        'template_code' => (string) ($row['template_code'] ?? ''),
                        'scheduled_for' => (string) ($row['scheduled_for'] ?? ''),
                        'created_at' => (string) ($row['created_at'] ?? ''),
                        'is_read' => isset($readSet[$id]),
                        'deep_link' => $paymentId > 0
                            ? [
                                'destination' => 'payments',
                                'resource_id' => $paymentId,
                            ]
                            : null,
                    ];
                },
                $rows
            );

            return RequestGuard::response($items, [
                'page' => $page,
                'per_page' => $perPage,
                'returned' => count($items),
                'has_more' => $hasMore,
                'scope' => 'current_user',
            ]);
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_notifications_invalid');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_notifications_failed');
        }
    }

    public static function markRead(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $access = Permission::access();
        if ($access instanceof WP_Error) {
            return $access;
        }

        try {
            $params = ApiAbuseGuard::safeParams($request, ['id']);
            $id = self::boundedInt($params['id'] ?? 0, 1, PHP_INT_MAX, 'id');
            $userId = self::currentUserId();
            if (! (new DeliveryLogRepository())->hasSentForUser($id, $userId)) {
                return ApiResponse::notFound('Notification');
            }
            (new NotificationReadStateRepository())->markRead($userId, $id);
            return RequestGuard::response([
                'id' => $id,
                'is_read' => true,
            ]);
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_notification_read_invalid');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_notification_read_failed');
        }
    }

    private static function currentUserId(): int
    {
        $userId = get_current_user_id();
        if ($userId <= 0) {
            throw new InvalidArgumentException('Notification inbox requires an authenticated SafeContracts user.');
        }
        return $userId;
    }

    private static function boundedInt(mixed $value, int $minimum, int $maximum, string $field): int
    {
        $parsed = is_int($value) ? $value : (is_string($value) ? filter_var($value, FILTER_VALIDATE_INT) : false);
        if ($parsed === false || $parsed < $minimum || $parsed > $maximum) {
            throw new InvalidArgumentException("{$field} is outside the supported range.");
        }
        return (int) $parsed;
    }
}
