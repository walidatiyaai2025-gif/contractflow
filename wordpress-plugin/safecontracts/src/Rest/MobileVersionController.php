<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use SafeContracts\Settings\MobileConfiguration;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Non-secret public Android upgrade policy. Deliberately available before login.
 * Only a privileged WordPress admin can change the stored policy.
 */
final class MobileVersionController
{
    public const ANDROID_PACKAGE = 'com.safecontracts.safecontracts_mobile';

    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/mobile-version', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'show'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function show(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);
        $settings = (new MobileConfiguration())->read();
        $min = (int) $settings['force_update_min_android_build'];
        $latest = (int) $settings['force_update_latest_android_build'];
        $enabled = $settings['force_update_enabled'] === true
            && $min > 0
            && $latest >= $min;

        $response = ApiResponse::ok([
            'schema_version' => 1,
            'platform' => 'android',
            'enabled' => $enabled,
            'min_build' => $enabled ? $min : 0,
            'latest_build' => $latest,
            'store_url' => 'https://play.google.com/store/apps/details?id=' . self::ANDROID_PACKAGE,
            'message_ar' => $settings['force_update_message_ar'],
            'message_en' => $settings['force_update_message_en'],
        ]);
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        return $response;
    }
}
