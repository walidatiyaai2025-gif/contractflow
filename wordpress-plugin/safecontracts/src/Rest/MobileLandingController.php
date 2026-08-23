<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use SafeContracts\Settings\MobileLandingContent;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class MobileLandingController
{
    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/mobile-landing', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'show'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function show(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);
        $response = ApiResponse::ok((new MobileLandingContent())->read());
        $response->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
        return $response;
    }
}
