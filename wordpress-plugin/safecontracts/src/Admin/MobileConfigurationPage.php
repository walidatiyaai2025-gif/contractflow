<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\PublicSite\AppStorePages;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Settings\MobileConfiguration;
use Throwable;

final class MobileConfigurationPage
{
    public const SLUG = 'safecontracts-mobile-configuration';
    public const SAVE_ACTION = 'safecontracts_save_mobile_configuration';

    public static function register(): void
    {
        add_submenu_page(AdminShell::SLUG, __('Mobile Configuration', 'safecontracts'), __('Mobile Configuration', 'safecontracts'), Capabilities::MANAGE_SYSTEM, self::SLUG, [self::class, 'render']);
    }

    public static function handleSave(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to manage mobile configuration.', 'safecontracts'));
        }
        check_admin_referer(self::SAVE_ACTION);
        $status = 'saved';
        try {
            (new MobileConfiguration())->save([
                'support_text' => $_POST['support_text'] ?? '',
                'default_page_size' => $_POST['default_page_size'] ?? 25,
                'excel_export_enabled' => isset($_POST['excel_export_enabled']),
                'push_notifications_enabled' => isset($_POST['push_notifications_enabled']),
                'collection_entry_enabled' => isset($_POST['collection_entry_enabled']),
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
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'safecontracts_status' => $status], admin_url('admin.php')));
        exit;
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to manage mobile configuration.', 'safecontracts'));
        }
        $config = (new MobileConfiguration())->read();
        $storeUrls = AppStorePages::urls();
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Mobile bootstrap', 'safecontracts'); ?></p><h1><?php echo esc_html__('Mobile Configuration', 'safecontracts'); ?></h1></div></div>
            <section class="safecontracts-admin-card safecontracts-settings-card">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>
                    <p><label><?php echo esc_html__('Support / footer text', 'safecontracts'); ?><textarea class="widefat" name="support_text" maxlength="500" rows="4"><?php echo esc_html($config['support_text']); ?></textarea></label></p>
                    <p><label><?php echo esc_html__('Default mobile page size', 'safecontracts'); ?><input type="number" min="10" max="200" name="default_page_size" value="<?php echo esc_attr((string) $config['default_page_size']); ?>"></label></p>
                    <fieldset><legend><?php echo esc_html__('Feature availability', 'safecontracts'); ?></legend>
                        <label class="safecontracts-check-row"><input type="checkbox" name="excel_export_enabled" value="1" <?php checked($config['excel_export_enabled']); ?>><?php echo esc_html__('Excel export', 'safecontracts'); ?></label>
                        <label class="safecontracts-check-row"><input type="checkbox" name="push_notifications_enabled" value="1" <?php checked($config['push_notifications_enabled']); ?>><?php echo esc_html__('Push notifications', 'safecontracts'); ?></label>
                        <label class="safecontracts-check-row"><input type="checkbox" name="collection_entry_enabled" value="1" <?php checked($config['collection_entry_enabled']); ?>><?php echo esc_html__('Collection entry', 'safecontracts'); ?></label>
                    </fieldset>

                    <hr>
                    <fieldset>
                        <legend><strong><?php echo esc_html__('Advertising providers', 'safecontracts'); ?></strong></legend>
                        <p class="description"><?php echo esc_html__('Ads are disabled by default. Choose the active provider here; switching providers takes effect from remote configuration without publishing a new app build.', 'safecontracts'); ?></p>
                        <label class="safecontracts-check-row"><input type="checkbox" name="ads_enabled" value="1" <?php checked($config['ads_enabled']); ?>><?php echo esc_html__('Enable mobile advertising', 'safecontracts'); ?></label>
                        <label class="safecontracts-check-row"><input type="checkbox" name="ads_test_mode" value="1" <?php checked($config['ads_test_mode']); ?>><?php echo esc_html__('Test / QA mode', 'safecontracts'); ?></label>
                        <label class="safecontracts-check-row"><input type="checkbox" name="ads_banner_enabled" value="1" <?php checked($config['ads_banner_enabled']); ?>><?php echo esc_html__('Show banner ads', 'safecontracts'); ?></label>
                        <p>
                            <label><?php echo esc_html__('Advertising provider', 'safecontracts'); ?>
                                <select name="ads_provider">
                                    <option value="admob" <?php selected($config['ads_provider'], MobileConfiguration::AD_PROVIDER_ADMOB); ?>><?php echo esc_html__('Google AdMob', 'safecontracts'); ?></option>
                                    <option value="applovin" <?php selected($config['ads_provider'], MobileConfiguration::AD_PROVIDER_APPLOVIN); ?>><?php echo esc_html__('AppLovin MAX', 'safecontracts'); ?></option>
                                </select>
                            </label>
                        </p>
                        <p class="description"><strong><?php echo esc_html__('If AdMob is suspended or intentionally disabled, select AppLovin MAX and save. The app will stop requesting AdMob ads and use AppLovin on the next configuration refresh/app start.', 'safecontracts'); ?></strong></p>

                        <h3><?php echo esc_html__('Google AdMob', 'safecontracts'); ?></h3>
                        <p>
                            <label><?php echo esc_html__('AdMob banner Ad Unit ID', 'safecontracts'); ?>
                                <input class="regular-text code" type="text" name="ads_admob_banner_unit_id" placeholder="ca-app-pub-XXXXXXXXXXXXXXXX/YYYYYYYYYY" value="<?php echo esc_attr($config['ads_admob_banner_unit_id']); ?>">
                            </label>
                        </p>
                        <p class="description"><?php echo esc_html__('The production AdMob App ID is already embedded in the signed Android build. The Banner Ad Unit ID below remains editable from WordPress at runtime.', 'safecontracts'); ?></p>

                        <h3><?php echo esc_html__('AppLovin MAX', 'safecontracts'); ?></h3>
                        <p>
                            <label><?php echo esc_html__('AppLovin SDK key', 'safecontracts'); ?>
                                <input class="large-text code" type="text" name="ads_applovin_sdk_key" autocomplete="off" value="<?php echo esc_attr($config['ads_applovin_sdk_key']); ?>">
                            </label>
                        </p>
                        <p>
                            <label><?php echo esc_html__('AppLovin banner Ad Unit ID', 'safecontracts'); ?>
                                <input class="regular-text code" type="text" name="ads_applovin_banner_unit_id" value="<?php echo esc_attr($config['ads_applovin_banner_unit_id']); ?>">
                            </label>
                        </p>
                        <p class="description"><?php echo esc_html__('For AppLovin QA, add the test device GAID in MAX > Mediation > Manage > Test Mode. AppLovin does not provide a universal public banner test unit like AdMob.', 'safecontracts'); ?></p>
                        <p class="description"><?php echo esc_html__('The AdMob App ID is fixed in the signed Android build. AppLovin uses only the SDK key here; never paste an AppLovin Management Key, API Key, or Ad Review Key into this page.', 'safecontracts'); ?></p>
                    </fieldset>
                    <?php submit_button(__('Save Mobile Configuration', 'safecontracts')); ?>
                </form>
            </section>

            <section class="safecontracts-admin-card safecontracts-settings-card" style="margin-top:18px">
                <h2><?php echo esc_html__('Store compliance pages', 'safecontracts'); ?></h2>
                <p class="description"><?php echo esc_html__('Use these public URLs in Google Play Console, AdMob/AppLovin privacy configuration, and the app listing.', 'safecontracts'); ?></p>
                <table class="widefat striped"><tbody>
                    <tr><th><?php echo esc_html__('Privacy policy', 'safecontracts'); ?></th><td><a target="_blank" rel="noopener" href="<?php echo esc_url($storeUrls['privacy']); ?>"><?php echo esc_html($storeUrls['privacy']); ?></a></td></tr>
                    <tr><th><?php echo esc_html__('Account deletion', 'safecontracts'); ?></th><td><a target="_blank" rel="noopener" href="<?php echo esc_url($storeUrls['deletion']); ?>"><?php echo esc_html($storeUrls['deletion']); ?></a></td></tr>
                    <tr><th><?php echo esc_html__('Support', 'safecontracts'); ?></th><td><a target="_blank" rel="noopener" href="<?php echo esc_url($storeUrls['support']); ?>"><?php echo esc_html($storeUrls['support']); ?></a></td></tr>
                    <tr><th><?php echo esc_html__('Terms of use', 'safecontracts'); ?></th><td><a target="_blank" rel="noopener" href="<?php echo esc_url($storeUrls['terms']); ?>"><?php echo esc_html($storeUrls['terms']); ?></a></td></tr>
                </tbody></table>

                <h3><?php echo esc_html__('AdMob setup checklist', 'safecontracts'); ?></h3>
                <ol dir="rtl">
                    <li>افتح AdMob وسجّل تطبيق Android باسم Alkenzy ADV.</li>
                    <li>استخدم Package ID نفسه الموجود في نسخة التطبيق الموقعة.</li>
                    <li>تم تثبيت App ID ‏ca-app-pub-3218037275900725~7401372044 داخل نسخة أندرويد الموقعة؛ لا تحتاج GitHub secret له.</li>
                    <li>تم ضبط Banner Ad Unit ID الافتراضي على ca-app-pub-3218037275900725/8818395498 ويمكن تغييره من الحقل الموجود بالأعلى.</li>
                    <li>أثناء الاختبار اترك Test / QA mode مفعلاً؛ لا تضغط على إعلانات إنتاج حقيقية من أجهزة الاختبار.</li>
                    <li>أكمل Payments وIdentity/verification وPrivacy &amp; messaging داخل AdMob قبل التحويل للإنتاج.</li>
                </ol>

                <h3><?php echo esc_html__('AppLovin setup checklist', 'safecontracts'); ?></h3>
                <ol dir="rtl">
                    <li>أنشئ تطبيق Android داخل AppLovin MAX بنفس Package ID الخاص بـ Alkenzy ADV.</li>
                    <li>من Account &gt; General &gt; Keys انسخ SDK Key فقط إلى الحقل الموجود بالأعلى.</li>
                    <li>من MAX أنشئ Banner Ad Unit وانسخ Ad Unit ID إلى الحقل الموجود بالأعلى.</li>
                    <li>للاختبار أضف GAID الخاص بجهازك من MAX &gt; Mediation &gt; Manage &gt; Test Mode.</li>
                    <li>لا تضع Management Key أو API Key أو Ad Review Key داخل WordPress أو التطبيق.</li>
                    <li>لو AdMob توقف: اختر AppLovin MAX من Advertising provider ثم Save Mobile Configuration؛ لا تحتاج إصدار تطبيق جديد.</li>
                </ol>
            </section>
        </div>
        <?php
    }
}
