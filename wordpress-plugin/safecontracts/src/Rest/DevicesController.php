<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Notifications\DeviceTokenRepository;
use SafeContracts\Notifications\DeviceTokenService;
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
        register_rest_route(Router::NAMESPACE, '/devices/register', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'registerDevice'],
            'permission_callback' => [Router::class, 'canAccess'],
        ]);
        register_rest_route(Router::NAMESPACE, '/devices/revoke', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'revokeDevice'],
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

    public static function registerDevice(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $access = Permission::access();
        if ($access instanceof WP_Error) {
            return $access;
        }

        try {
            $body = self::body($request, ['token', 'platform']);
            if (! array_key_exists('token', $body) || ! array_key_exists('platform', $body)) {
                throw new InvalidArgumentException('Device token and platform are required.');
            }
            (new DeviceTokenService())->register($body['token'], $body['platform']);
            return RequestGuard::response([
                'registered' => true,
                'platform' => strtolower(trim((string) $body['platform'])),
            ], [], 201);
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_device_register_invalid');
        } catch (DomainException $error) {
            return RequestGuard::domain($error, 'safecontracts_device_register_forbidden');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_device_register_failed');
        }
    }

    public static function revokeDevice(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $access = Permission::access();
        if ($access instanceof WP_Error) {
            return $access;
        }

        try {
            $body = self::body($request, ['token']);
            if (! array_key_exists('token', $body)) {
                throw new InvalidArgumentException('Device token is required.');
            }
            (new DeviceTokenService())->revoke($body['token']);
            return RequestGuard::response(['revoked' => true]);
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_device_revoke_invalid');
        } catch (DomainException $error) {
            return RequestGuard::domain($error, 'safecontracts_device_revoke_forbidden');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_device_revoke_failed');
        }
    }

    /** @param list<string> $allowed @return array<string,mixed> */
    private static function body(WP_REST_Request $request, array $allowed): array
    {
        $body = $request->get_json_params();
        if (! is_array($body)) {
            throw new InvalidArgumentException('SafeContracts device requests require a JSON object body.');
        }
        foreach (array_keys($body) as $field) {
            if (! is_string($field) || ! in_array($field, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported SafeContracts device field.');
            }
        }
        return $body;
    }
}
