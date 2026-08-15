<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use SafeContracts\Auth\MobileBearerAuthentication;
use SafeContracts\Auth\MobileSessionStore;
use SafeContracts\Roles\Capabilities;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class AuthController
{
    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/auth/login', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'login'],
            'permission_callback' => [self::class, 'allowLogin'],
        ]);
        register_rest_route(Router::NAMESPACE, '/auth/logout', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'logout'],
            'permission_callback' => [Router::class, 'canAccess'],
        ]);
    }

    public static function allowLogin(): bool
    {
        return true;
    }

    public static function login(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! is_ssl()) {
            return ApiResponse::error(
                'safecontracts_https_required',
                __('SafeContracts mobile login requires HTTPS.', 'safecontracts'),
                400
            );
        }

        try {
            $body = $request->get_json_params();
            if (! is_array($body)) {
                return self::invalidCredentials();
            }
            $allowed = ['username', 'password'];
            foreach (array_keys($body) as $field) {
                if (! is_string($field) || ! in_array($field, $allowed, true)) {
                    return self::invalidCredentials();
                }
            }

            $username = isset($body['username']) && is_string($body['username'])
                ? trim($body['username'])
                : '';
            $password = isset($body['password']) && is_string($body['password'])
                ? $body['password']
                : '';
            if ($username === '' || strlen($username) > 254 || $password === '' || strlen($password) > 4096) {
                return self::invalidCredentials();
            }

            $user = wp_authenticate($username, $password);
            if (is_wp_error($user) || ! is_object($user) || (int) ($user->ID ?? 0) <= 0) {
                return self::invalidCredentials();
            }

            $hasAccess = user_can($user, Capabilities::ACCESS);
            $hasScope = user_can($user, Capabilities::VIEW_ALL) || user_can($user, Capabilities::VIEW_ASSIGNED);
            if (! $hasAccess || ! $hasScope) {
                return ApiResponse::error(
                    'safecontracts_mobile_access_forbidden',
                    __('This account is not authorized for SafeContracts mobile access.', 'safecontracts'),
                    403
                );
            }

            $issued = (new MobileSessionStore())->issue((int) $user->ID);
            $response = ApiResponse::ok([
                'token' => $issued['token'],
                'token_type' => 'Bearer',
                'expires_at' => gmdate(DATE_ATOM, $issued['expires_at']),
                'user_id' => (int) $user->ID,
            ], [], 201);
            self::noStore($response);
            do_action('safecontracts_mobile_login_succeeded', (int) $user->ID);
            return $response;
        } catch (Throwable $error) {
            do_action('safecontracts_mobile_login_failed', get_class($error));
            return ApiResponse::error(
                'safecontracts_mobile_login_failed',
                __('SafeContracts login could not be completed.', 'safecontracts'),
                500
            );
        }
    }

    public static function logout(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        unset($request);
        $access = Permission::access();
        if ($access instanceof WP_Error) {
            return $access;
        }

        $token = MobileBearerAuthentication::bearerToken();
        if ($token !== null) {
            (new MobileSessionStore())->revoke($token);
        }
        $response = ApiResponse::ok(['logged_out' => true]);
        self::noStore($response);
        do_action('safecontracts_mobile_logout', get_current_user_id());
        return $response;
    }

    private static function invalidCredentials(): WP_Error
    {
        do_action('safecontracts_mobile_login_rejected');
        return ApiResponse::error(
            'safecontracts_invalid_credentials',
            __('Invalid username or password.', 'safecontracts'),
            401
        );
    }

    private static function noStore(WP_REST_Response $response): void
    {
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
    }
}
