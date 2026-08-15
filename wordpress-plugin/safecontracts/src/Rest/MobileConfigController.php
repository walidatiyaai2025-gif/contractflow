<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use SafeContracts\Settings\GeneralSettings;
use SafeContracts\Settings\MobileConfiguration;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class MobileConfigController
{
    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/mobile-config', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'show'],
            'permission_callback' => [Router::class, 'canAccess'],
        ]);
    }

    public static function show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        unset($request);
        try {
            $config = (new MobileConfiguration())->read();
            $general = (new GeneralSettings())->read();
            return RequestGuard::response([
                'support_text' => $config['support_text'],
                'default_page_size' => $config['default_page_size'],
                'currency' => [
                    'code' => $general['currency_code'],
                    'symbol' => $general['currency_symbol'],
                ],
                'features' => [
                    'excel_export' => $config['excel_export_enabled'],
                    'push_notifications' => $config['push_notifications_enabled'],
                    'collection_entry' => $config['collection_entry_enabled'],
                ],
            ]);
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_mobile_config_failed');
        }
    }
}
