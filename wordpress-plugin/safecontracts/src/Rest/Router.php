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

        AuthController::register();
        MobileLandingController::register();

        foreach (['/me', '/session'] as $route) {
            register_rest_route(self::NAMESPACE, $route, [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'me'],
                'permission_callback' => [self::class, 'canAccess'],
            ]);
        }

        ProfileAvatarController::register();
        PaymentMethodsController::register();
        DataController::register();
        FinanceController::register();
        DashboardController::register();
        ContractMediaController::register();
        MobileConfigController::register();
        ReferenceDataController::register();
        ExcelExportController::register();
        NotificationsController::register();
        DevicesController::register();
        SuppliersController::register();
        CounterpartyContractsController::register();
        ContractMutationController::register();
        MobileMutationController::register();
        MobileCrudController::register();
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

    public static function me(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        unset($request);
        $access = self::canAccess();
        if ($access instanceof WP_Error) {
            return $access;
        }

        $capabilities = [];
        foreach (Capabilities::all() as $capability) {
            $capabilities[$capability] = current_user_can($capability);
        }

        $userId = get_current_user_id();
        $displayName = '';
        $email = '';
        $phone = '';
        $avatarUrl = null;
        if (function_exists('get_userdata')) {
            $user = get_userdata($userId);
            if (is_object($user)) {
                $displayName = trim((string) ($user->display_name ?? ''));
                if ($displayName === '') {
                    $displayName = trim((string) ($user->user_login ?? ''));
                }
                $email = trim((string) ($user->user_email ?? ''));
            }
        }
        if (function_exists('get_user_meta')) {
            foreach (['phone', 'billing_phone', 'mobile', 'mobile_phone'] as $phoneKey) {
                $value = trim((string) get_user_meta($userId, $phoneKey, true));
                if ($value !== '') {
                    $phone = $value;
                    break;
                }
            }
            $customAvatar = trim((string) get_user_meta($userId, 'safecontracts_mobile_avatar_url', true));
            if ($customAvatar !== '') {
                $avatarUrl = function_exists('esc_url_raw') ? esc_url_raw($customAvatar) : $customAvatar;
            }
        }
        if ($avatarUrl === null && function_exists('get_avatar_url')) {
            $resolvedAvatarUrl = get_avatar_url($userId, ['size' => 160]);
            if (is_string($resolvedAvatarUrl) && $resolvedAvatarUrl !== '') {
                $avatarUrl = function_exists('esc_url_raw')
                    ? esc_url_raw($resolvedAvatarUrl)
                    : $resolvedAvatarUrl;
            }
        }

        return ApiResponse::ok([
            'authenticated' => true,
            'user_id' => $userId,
            'display_name' => $displayName !== '' ? $displayName : null,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'avatar_url' => $avatarUrl,
            'scope' => AccessScope::current(),
            'capabilities' => $capabilities,
        ]);
    }

    public static function canAccess(): bool|WP_Error
    {
        return Permission::access();
    }
}
