<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use SafeContracts\Diagnostics\RuntimeInspector;
use SafeContracts\Roles\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Read-only mobile diagnostics endpoint.
 *
 * RuntimeInspector already stores only bounded, sanitized events. This route is
 * additionally protected by MANAGE_SYSTEM so non-admin mobile users cannot
 * enumerate diagnostic metadata.
 */
final class RuntimeDiagnosticsController
{
    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/diagnostics/runtime', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'index'],
            'permission_callback' => [self::class, 'canView'],
        ]);
    }

    public static function canView(): bool|WP_Error
    {
        if (current_user_can(Capabilities::MANAGE_SYSTEM)) {
            return true;
        }

        return ApiResponse::error(
            'safecontracts_diagnostics_forbidden',
            __('You do not have permission to view runtime diagnostics.', 'safecontracts'),
            403
        );
    }

    public static function index(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        unset($request);
        $access = self::canView();
        if ($access instanceof WP_Error) {
            return $access;
        }

        return ApiResponse::ok([
            'environment' => RuntimeInspector::environmentSnapshot(),
            'events' => RuntimeInspector::recent(),
            'retention_limit' => RuntimeInspector::MAX_EVENTS,
            'sanitized' => true,
        ], [
            'scope' => 'manage_system',
        ]);
    }
}
