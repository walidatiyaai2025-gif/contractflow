<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use SafeContracts\Roles\AccessScope;
use SafeContracts\Roles\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class Router
{
    public const NAMESPACE = 'safecontracts/v1';
    public const API_VERSION = 'v1';

    public static function register(): void
    {
        register_rest_route(self::NAMESPACE, '/health', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'health'],
            'permission_callback' => '__return_true',
        ]);

        foreach (['/me', '/session'] as $route) {
            register_rest_route(self::NAMESPACE, $route, [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'me'],
                'permission_callback' => [self::class, 'canAccess'],
            ]);
        }

        PaymentMethodsController::register();
        DataController::register();
    }

    public static function health(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);

        return ApiResponse::ok([
            'service' => 'SafeContracts',
            'plugin_version' => SAFECONTRACTS_VERSION,
            'api_version' => self::API_VERSION,
            'status' => 'ok',
        ]);
    }

    public static function me(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);

        $capabilities = [];
        foreach (Capabilities::all() as $capability) {
            $capabilities[$capability] = current_user_can($capability);
        }

        return ApiResponse::ok([
            'authenticated' => get_current_user_id() > 0,
            'user_id' => get_current_user_id(),
            'scope' => AccessScope::current(),
            'capabilities' => $capabilities,
        ]);
    }

    public static function canAccess(): bool|WP_Error
    {
        if (get_current_user_id() > 0 && AccessScope::canAccess()) {
            return true;
        }

        return ApiResponse::error(
            'safecontracts_forbidden',
            __('You do not have access to SafeContracts.', 'safecontracts'),
            403
        );
    }
}
