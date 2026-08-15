<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use InvalidArgumentException;
use SafeContracts\Notifications\DeviceTokenRepository;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class DevicesController
{
    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/devices', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'index'],
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
            ApiAbuseGuard::safeParams($request, []);
            $userId = get_current_user_id();
            if ($userId <= 0) {
                return RequestGuard::forbidden(
                    'safecontracts_devices_forbidden',
                    __('Device state requires an authenticated SafeContracts user.', 'safecontracts')
                );
            }

            $items = (new DeviceTokenRepository())->safeForUser($userId);
            return RequestGuard::response($items, [
                'returned' => count($items),
                'scope' => 'current_user',
            ]);
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_devices_invalid');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_devices_failed');
        }
    }
}
