<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use WP_Error;
use WP_REST_Response;

final class ApiResponse
{
    public static function ok(mixed $data, array $meta = [], int $status = 200): WP_REST_Response
    {
        return new WP_REST_Response([
            'data' => $data,
            'meta' => array_merge([
                'api_version' => Router::API_VERSION,
            ], $meta),
        ], $status);
    }

    public static function error(string $code, string $message, int $status, array $details = []): WP_Error
    {
        $data = [
            'status' => $status,
            'api_version' => Router::API_VERSION,
        ];
        if ($details !== []) {
            $data['details'] = $details;
        }

        return new WP_Error($code, $message, $data);
    }

    public static function notFound(string $resource): WP_Error
    {
        return self::error(
            'safecontracts_not_found',
            sprintf(__('%s was not found.', 'safecontracts'), $resource),
            404
        );
    }
}
