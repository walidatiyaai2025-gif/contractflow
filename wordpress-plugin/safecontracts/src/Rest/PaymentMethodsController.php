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

    public static function active(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);

        return new WP_REST_Response([
            'data' => (new PaymentMethodRepository())->all(true),
            'meta' => [],
        ], 200);
    }

    public static function all(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);

        return new WP_REST_Response([
            'data' => (new PaymentMethodRepository())->all(false),
            'meta' => [],
        ], 200);
    }

    public static function save(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $input = $request->get_json_params();

        try {
            $saved = (new PaymentMethodRepository())->save([
                'code' => sanitize_key((string) ($input['code'] ?? '')),
                'name' => sanitize_text_field((string) ($input['name'] ?? '')),
                'sort_order' => (int) ($input['sort_order'] ?? 0),
                'is_active' => ! empty($input['is_active']),
            ]);
        } catch (InvalidArgumentException $error) {
            return new WP_Error(
                'safecontracts_invalid_payment_method',
                $error->getMessage(),
                ['status' => 422]
            );
        }

        return new WP_REST_Response([
            'data' => $saved,
            'meta' => [],
        ], 200);
    }

    public static function canManage(): bool|WP_Error
    {
        if (current_user_can(Capabilities::MANAGE_REFERENCE_DATA)) {
            return true;
        }

        return new WP_Error(
            'safecontracts_reference_data_forbidden',
            __('You do not have permission to manage SafeContracts reference data.', 'safecontracts'),
            ['status' => 403]
        );
    }
}
