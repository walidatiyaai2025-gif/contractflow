<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Admin\DashboardFilters;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class RequestGuard
{
    /** @return array<string,mixed> */
    public static function params(WP_REST_Request $request): array
    {
        return ApiRequest::params($request);
    }

    /**
     * Backward-compatible normalized filter helper for internal/admin callers.
     * REST dashboard callbacks must use strictDashboardFilters().
     *
     * @return array<string,mixed>
     */
    public static function dashboardFilters(WP_REST_Request $request): array
    {
        return DashboardFilters::normalize(self::params($request));
    }

    /** @return array<string,mixed> */
    public static function strictDashboardFilters(WP_REST_Request $request): array
    {
        ApiAbuseGuard::safeParams($request, [
            'customer_id', 'counterparty_type', 'counterparty_id', 'financial_direction', 'currency_code',
            'contract_id', 'accountant_user_id', 'status', 'due_from', 'due_to',
        ]);
        return ApiRequest::filters($request);
    }

    public static function response(mixed $data, array $meta = [], int $status = 200): WP_REST_Response
    {
        return ApiResponse::ok($data, $meta, $status);
    }

    public static function forbidden(string $code, string $message): WP_Error
    {
        return ApiResponse::error($code, $message, 403);
    }

    public static function invalid(Throwable $error, string $code = 'safecontracts_invalid_request'): WP_Error
    {
        $message = $error instanceof InvalidArgumentException
            ? $error->getMessage()
            : __('The SafeContracts request is invalid.', 'safecontracts');
        return ApiResponse::error($code, $message, 422);
    }

    public static function domain(Throwable $error, string $code = 'safecontracts_request_forbidden'): WP_Error
    {
        $message = $error instanceof DomainException
            ? $error->getMessage()
            : __('The SafeContracts request is not allowed.', 'safecontracts');
        return ApiResponse::error($code, $message, 403);
    }

    public static function failure(Throwable $error, string $code = 'safecontracts_request_failed'): WP_Error
    {
        unset($error);
        return ApiResponse::error(
            $code,
            __('The SafeContracts request could not be completed.', 'safecontracts'),
            500
        );
    }
}
