<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use SafeContracts\PublicSite\AppStorePages;
use SafeContracts\Settings\GeneralSettings;
use SafeContracts\Settings\MobileConfiguration;
use SafeContracts\Translations\TranslationCatalog;
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
            $storeUrls = AppStorePages::urls();
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
                'ads' => [
                    'enabled' => $config['ads_enabled'],
                    'test_mode' => $config['ads_test_mode'],
                    'banner_enabled' => $config['ads_banner_enabled'],
                    'provider' => $config['ads_provider'],
                    // Legacy key retained for older mobile builds.
                    'banner_ad_unit_id' => $config['ads_admob_banner_unit_id'],
                    'admob_banner_ad_unit_id' => $config['ads_admob_banner_unit_id'],
                    'applovin_sdk_key' => $config['ads_applovin_sdk_key'],
                    'applovin_banner_ad_unit_id' => $config['ads_applovin_banner_unit_id'],
                    'privacy_policy_url' => $storeUrls['privacy'],
                    'terms_url' => $storeUrls['terms'],
                ],
                'store_links' => [
                    'privacy_policy' => $storeUrls['privacy'],
                    'terms' => $storeUrls['terms'],
                    'account_deletion' => $storeUrls['deletion'],
                    'support' => $storeUrls['support'],
                ],
                'translation_overrides' => TranslationCatalog::mobileOverrides(),
            ]);
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_mobile_config_failed');
        }
    }
}
