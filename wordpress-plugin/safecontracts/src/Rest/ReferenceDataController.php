<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use SafeContracts\ReferenceData\PaymentMethodRepository;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class ReferenceDataController
{
    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/reference-data', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'show'],
            'permission_callback' => [Router::class, 'canAccess'],
        ]);
    }

    public static function show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        unset($request);
        try {
            $methods = array_map(
                static fn (array $method): array => [
                    'id' => (int) $method['id'],
                    'code' => (string) $method['code'],
                    'name' => (string) $method['name'],
                    'display_order' => (int) $method['display_order'],
                ],
                (new PaymentMethodRepository())->all(true)
            );
            return RequestGuard::response(['payment_methods' => $methods]);
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_reference_data_failed');
        }
    }
}
