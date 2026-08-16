<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\NonCoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class EnterpriseRateLimitGuard
{
    public const LOGIN_IP_LIMIT = 10;
    public const LOGIN_IP_WINDOW = 300;
    public const LOGIN_USERNAME_LIMIT = 20;
    public const LOGIN_USERNAME_WINDOW = 900;
    public const AUTH_READ_LIMIT = 300;
    public const AUTH_READ_WINDOW = 60;
    public const AUTH_WRITE_LIMIT = 120;
    public const AUTH_WRITE_WINDOW = 60;
    public const ANONYMOUS_LIMIT = 60;
    public const ANONYMOUS_WINDOW = 60;

    public static function register(): void
    {
        // Priority 20 deliberately runs after TenantContextStore reset (1) and
        // CoreTenantRestGuard tenant locking (5) when a core tenant route is used.
        add_filter('rest_request_before_callbacks', [self::class, 'enforce'], 20, 3);
    }

    public static function enforce(mixed $response, mixed $handler, mixed $request): mixed
    {
        unset($handler);

        if ($response instanceof WP_Error || $response instanceof WP_REST_Response) {
            return $response;
        }
        if (! self::isEnabled()) {
            return $response;
        }
        if (! $request instanceof WP_REST_Request || ! method_exists($request, 'get_route')) {
            return $response;
        }

        $route = (string) $request->get_route();
        if (! self::isSafeContractsRoute($route) || $route === '/safecontracts/v1/health') {
            return $response;
        }

        $method = self::method($request);
        if ($method === 'OPTIONS') {
            return $response;
        }

        try {
            $store = new EnterpriseRateLimitStore();

            if ($route === '/safecontracts/v1/auth/login') {
                return self::enforceLogin($response, $request, $store, $route, $method);
            }

            $userId = get_current_user_id();
            if ($userId <= 0) {
                return self::consume(
                    $response,
                    $store,
                    'anonymous',
                    'ip:' . self::clientIp(),
                    self::ANONYMOUS_LIMIT,
                    self::ANONYMOUS_WINDOW,
                    $route,
                    $method
                );
            }

            $trafficClass = self::isWriteMethod($method) ? 'write' : 'read';
            $tenantId = TenantContextStore::context()->tenantId();
            $identity = 'user:' . $userId . '|tenant:' . ($tenantId === null ? 'none' : (string) $tenantId);

            if ($trafficClass === 'write') {
                return self::consume(
                    $response,
                    $store,
                    'authenticated_write',
                    $identity,
                    self::AUTH_WRITE_LIMIT,
                    self::AUTH_WRITE_WINDOW,
                    $route,
                    $method
                );
            }

            return self::consume(
                $response,
                $store,
                'authenticated_read',
                $identity,
                self::AUTH_READ_LIMIT,
                self::AUTH_READ_WINDOW,
                $route,
                $method
            );
        } catch (Throwable $error) {
            // The application authorization boundary remains authoritative. A
            // limiter-storage problem must not turn into a platform-wide outage.
            do_action('safecontracts_esc_rate_limit_storage_failed', get_class($error), $route);
            return $response;
        }
    }

    public static function isEnabled(): bool
    {
        return CoreTenantEnforcement::isEnabled() || NonCoreTenantEnforcement::isEnabled();
    }

    public static function isSafeContractsRoute(string $route): bool
    {
        return preg_match('#^/safecontracts/v1(?:/|$)#', $route) === 1;
    }

    public static function bucketDigest(string $scope, string $identity): string
    {
        $secret = self::hashSecret();
        return hash_hmac('sha256', $scope . '|' . $identity, $secret);
    }

    private static function enforceLogin(
        mixed $response,
        WP_REST_Request $request,
        EnterpriseRateLimitStore $store,
        string $route,
        string $method
    ): mixed {
        $ipResult = self::consume(
            $response,
            $store,
            'login_ip',
            'ip:' . self::clientIp(),
            self::LOGIN_IP_LIMIT,
            self::LOGIN_IP_WINDOW,
            $route,
            $method
        );
        if ($ipResult instanceof WP_Error) {
            return $ipResult;
        }

        $body = $request->get_json_params();
        $username = is_array($body) && isset($body['username']) && is_string($body['username'])
            ? strtolower(trim($body['username']))
            : '';
        $usernameIdentity = $username === '' ? '<empty>' : $username;

        return self::consume(
            $response,
            $store,
            'login_username',
            'username:' . $usernameIdentity,
            self::LOGIN_USERNAME_LIMIT,
            self::LOGIN_USERNAME_WINDOW,
            $route,
            $method
        );
    }

    private static function consume(
        mixed $response,
        EnterpriseRateLimitStore $store,
        string $scope,
        string $identity,
        int $defaultLimit,
        int $defaultWindow,
        string $route,
        string $method
    ): mixed {
        $policy = self::policy($scope, $defaultLimit, $defaultWindow, $route, $method);
        $bucketKey = self::bucketDigest($scope, $identity);
        $state = $store->hit($bucketKey, $policy['limit'], $policy['window_seconds']);

        if ($state['allowed']) {
            return $response;
        }

        // Cleanup is bounded and happens only during active throttling, avoiding a
        // cleanup query on every normal request while keeping expired rows removable.
        $store->pruneExpired(200);
        $retryAfter = max(1, (int) $state['retry_after']);
        do_action('safecontracts_esc_rate_limited', $scope, $route, $retryAfter);

        return ApiResponse::error(
            'safecontracts_esc_rate_limited',
            __('Too many requests. Try again later.', 'safecontracts'),
            429,
            ['retry_after' => $retryAfter]
        );
    }

    /** @return array{limit:int,window_seconds:int} */
    private static function policy(
        string $scope,
        int $defaultLimit,
        int $defaultWindow,
        string $route,
        string $method
    ): array {
        $policy = apply_filters(
            'safecontracts_esc_rate_limit_policy',
            ['limit' => $defaultLimit, 'window_seconds' => $defaultWindow],
            $scope,
            $route,
            $method
        );

        if (! is_array($policy)) {
            return ['limit' => $defaultLimit, 'window_seconds' => $defaultWindow];
        }

        $limit = isset($policy['limit']) && is_numeric($policy['limit'])
            ? (int) $policy['limit']
            : $defaultLimit;
        $window = isset($policy['window_seconds']) && is_numeric($policy['window_seconds'])
            ? (int) $policy['window_seconds']
            : $defaultWindow;

        return [
            'limit' => max(1, min(100000, $limit)),
            'window_seconds' => max(1, min(86400, $window)),
        ];
    }

    private static function method(WP_REST_Request $request): string
    {
        if (method_exists($request, 'get_method')) {
            $method = strtoupper((string) $request->get_method());
            if ($method !== '') {
                return $method;
            }
        }

        $method = isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD'])
            ? strtoupper($_SERVER['REQUEST_METHOD'])
            : 'GET';
        return $method === '' ? 'GET' : $method;
    }

    private static function isWriteMethod(string $method): bool
    {
        return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private static function clientIp(): string
    {
        $raw = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
            ? trim($_SERVER['REMOTE_ADDR'])
            : '';
        $ip = filter_var($raw, FILTER_VALIDATE_IP) !== false ? $raw : 'unknown';

        // Do not trust X-Forwarded-For here. Deployments behind a trusted reverse
        // proxy may supply a validated address through this server-side filter.
        $filtered = apply_filters('safecontracts_esc_rate_limit_client_ip', $ip);
        if (is_string($filtered)) {
            $filtered = trim($filtered);
            if ($filtered === 'unknown' || filter_var($filtered, FILTER_VALIDATE_IP) !== false) {
                return $filtered;
            }
        }

        return $ip;
    }

    private static function hashSecret(): string
    {
        if (function_exists('wp_salt')) {
            $salt = (string) wp_salt('auth');
            if ($salt !== '') {
                return $salt;
            }
        }
        if (defined('AUTH_SALT') && (string) AUTH_SALT !== '') {
            return (string) AUTH_SALT;
        }

        // WordPress production always provides salts; this deterministic fallback
        // keeps isolated test harnesses operational without storing raw identities.
        return home_url('/') . '|safecontracts-enterprise-rate-limit';
    }
}
