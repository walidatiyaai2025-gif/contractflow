<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\PublicSite\AppStorePages;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Settings\MobileConfiguration;
use Throwable;

final class MobileAdvertisingPage
{
    public const SLUG = 'safecontracts-mobile-advertising';
    public const SAVE_ACTION = 'safecontracts_save_mobile_advertising';

    public static function register(): void
    {
        add_submenu_page(
            AdminShell::SLUG,
            __('Mobile Advertising', 'safecontracts'),
            __('Mobile Advertising', 'safecontracts'),
            Capabilities::MANAGE_SYSTEM,
            self::SLUG,
            [self::class, 'render']
        );
    }

    public static function handleSave(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to manage mobile advertising.', 'safecontracts'));
        }
        check_admin_referer(self::SAVE_ACTION);

        $settings = new MobileConfiguration();
        $current = $settings->read();
        $status = 'saved';
        try {
            $settings->save([
                'support_text' => $current['support_text'],
                'default_page_size' => $current['default_page_size'],
                'excel_export_enabled' => $current['excel_export_enabled'],
                'push_notifications_enabled' => $current['push_notifications_enabled'],
                'collection_entry_enabled' => $current['collection_entry_enabled'],
                'ads_enabled' => isset($_POST['ads_enabled']),
                'ads_test_mode' => isset($_POST['ads_test_mode']),
                'ads_banner_enabled' => isset($_POST['ads_banner_enabled']),
                'ads_provider' => $_POST['ads_provider'] ?? MobileConfiguration::AD_PROVIDER_ADMOB,
                'ads_admob_banner_unit_id' => $_POST['ads_admob_banner_unit_id'] ?? '',
                'ads_applovin_sdk_key' => $_POST['ads_applovin_sdk_key'] ?? '',
                'ads_applovin_banner_unit_id' => $_POST['ads_applovin_banner_unit_id'] ?? '',
            ]);
        } catch (Throwable $error) {
            unset($error);
            $status = 'invalid';
        }

        wp_safe_redirect(add_query_arg([
            'page' => self::SLUG,
            'safecontracts_status' => $status,
        ], admin_url('admin.php')));
        exit;
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to manage mobile advertising.', 'safecontracts'));
        }

        $config = (new MobileConfiguration())->read();
        $links = AppStorePages::urls();
        $status = isset($_GET['safecontracts_status']) && is_scalar($_GET['safecontracts_status'])
            ? sanitize_key((string) $_GET['safecontracts_status'])
            : '';
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Mobile monetization', 'safecontracts'); ?></p>
                    <h1><?php echo esc_html__('Mobile Advertising', 'safecontracts'); ?></h1>
                    <p><?php echo esc_html__('Switch between Google AdMob and AppLovin MAX remotely. Advertising stays disabled by default and production identifiers remain server-managed.', 'safecontracts'); ?></p>
                </div>
            </div>
            <?php if ($status === 'saved') : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Mobile advertising configuration saved.', 'safecontracts'); ?></p></div><?php endif; ?>
            <?php if ($status === 'invalid') : ?><div class="notice notice-error is-dismissible"><p><?php echo esc_html__('Mobile advertising configuration is invalid. Review the provider identifiers.', 'safecontracts'); ?></p></div><?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">
                <?php wp_nonce_field(self::SAVE_ACTION); ?>
                <section class="safecontracts-admin-card safecontracts-settings-card">
                    <h2><?php echo esc_html__('Advertising providers', 'safecontracts'); ?></h2>
                    <label class="safecontracts-check-row"><input type="checkbox" name="ads_enabled" value="1" <?php checked($config['ads_enabled']); ?>><span><strong><?php echo esc_html__('Enable mobile advertising', 'safecontracts'); ?></strong></span></label>
                    <label class="safecontracts-check-row"><input type="checkbox" name="ads_test_mode" value="1" <?php checked($config['ads_test_mode']); ?>><span><strong><?php echo esc_html__('Test / QA mode', 'safecontracts'); ?></strong></span></label>
                    <label class="safecontracts-check-row"><input type="checkbox" name="ads_banner_enabled" value="1" <?php checked($config['ads_banner_enabled']); ?>><span><strong><?php echo esc_html__('Show banner ads', 'safecontracts'); ?></strong></span></label>
                    <p><label><?php echo esc_html__('Advertising provider', 'safecontracts'); ?><br>
                        <select name="ads_provider">
                            <option value="admob" <?php selected($config['ads_provider'], 'admob'); ?>><?php echo esc_html__('Google AdMob', 'safecontracts'); ?></option>
                            <option value="applovin" <?php selected($config['ads_provider'], 'applovin'); ?>><?php echo esc_html__('AppLovin MAX', 'safecontracts'); ?></option>
                        </select>
                    </label></p>
                    <p><label><?php echo esc_html__('AdMob banner Ad Unit ID', 'safecontracts'); ?><br><input class="regular-text code" type="text" name="ads_admob_banner_unit_id" maxlength="80" value="<?php echo esc_attr((string) $config['ads_admob_banner_unit_id']); ?>"></label></p>
                    <p><label><?php echo esc_html__('AppLovin SDK key', 'safecontracts'); ?><br><input class="large-text code" type="password" autocomplete="new-password" name="ads_applovin_sdk_key" maxlength="256" value="<?php echo esc_attr((string) $config['ads_applovin_sdk_key']); ?>"></label></p>
                    <p><label><?php echo esc_html__('AppLovin banner Ad Unit ID', 'safecontracts'); ?><br><input class="regular-text code" type="text" name="ads_applovin_banner_unit_id" maxlength="128" value="<?php echo esc_attr((string) $config['ads_applovin_banner_unit_id']); ?>"></label></p>
                    <p class="description"><?php echo esc_html__('The AdMob App ID remains part of the signed Android build. Never paste AppLovin management/API keys here; only the mobile SDK key is accepted.', 'safecontracts'); ?></p>
                </section>
                <?php submit_button(__('Save Mobile Advertising', 'safecontracts')); ?>
            </form>

            <section class="safecontracts-admin-card safecontracts-settings-card">
                <h2><?php echo esc_html__('Store compliance pages', 'safecontracts'); ?></h2>
                <p><?php echo esc_html__('Use these public URLs in Google Play Console and the advertising-provider privacy configuration.', 'safecontracts'); ?></p>
                <ul>
                    <li><strong><?php echo esc_html__('Privacy policy', 'safecontracts'); ?>:</strong> <a href="<?php echo esc_url($links['privacy']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($links['privacy']); ?></a></li>
                    <li><strong><?php echo esc_html__('Terms of use', 'safecontracts'); ?>:</strong> <a href="<?php echo esc_url($links['terms']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($links['terms']); ?></a></li>
                    <li><strong><?php echo esc_html__('Account deletion', 'safecontracts'); ?>:</strong> <a href="<?php echo esc_url($links['deletion']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($links['deletion']); ?></a></li>
                    <li><strong><?php echo esc_html__('Support', 'safecontracts'); ?>:</strong> <a href="<?php echo esc_url($links['support']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($links['support']); ?></a></li>
                </ul>
            </section>
        </div>
        <?php
    }
}
