<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use InvalidArgumentException;
use SafeContracts\ReferenceData\PaymentMethodRepository;
use SafeContracts\Roles\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class PaymentMethodsController
{
    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/payment-methods', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'active'],
            'permission_callback' => [Router::class, 'canAccess'],
        ]);

        register_rest_route(Router::NAMESPACE, '/admin/payment-methods', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'all'],
                'permission_callback' => [self::class, 'canManage'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'save'],
                'permission_callback' => [self::class, 'canManage'],
            ],
        ]);
    }

    public static function active(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        unset($request);
        $access = Router::canAccess();
        if ($access instanceof WP_Error) {
            return $access;
        }
        return ApiResponse::ok((new PaymentMethodRepository())->all(true));
    }

    public static function all(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        unset($request);
        $permission = self::canManage();
        if ($permission instanceof WP_Error) {
            return $permission;
        }
        return ApiResponse::ok((new PaymentMethodRepository())->all(false));
    }

    public static function save(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $permission = self::canManage();
        if ($permission instanceof WP_Error) {
            return $permission;
        }

        $input = $request->get_json_params();

        try {
            $saved = (new PaymentMethodRepository())->save([
                'code' => sanitize_key((string) ($input['code'] ?? '')),
                'name' => sanitize_text_field((string) ($input['name'] ?? '')),
                'display_order' => (int) ($input['display_order'] ?? 0),
                'is_active' => ! empty($input['is_active']),
            ]);
        } catch (InvalidArgumentException $error) {
            return ApiResponse::error(
                'safecontracts_invalid_payment_method',
                $error->getMessage(),
                422
            );
        }

        return ApiResponse::ok($saved);
    }

    public static function canManage(): bool|WP_Error
    {
        return Permission::capability(Capabilities::MANAGE_REFERENCE_DATA, 'safecontracts_reference_data_forbidden');
    }
}
