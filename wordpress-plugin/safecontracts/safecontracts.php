<?php
/**
 * Plugin Name: Safe Contracts
 * Plugin URI: https://github.com/walidatiyaai2025-gif/contractflow
 * Description: Contract receivables tracking backend and administration foundation for Safe Contracts.
 * Version: 0.3.24
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: Safe Contracts Team
 * Text Domain: safecontracts
 */

if (! defined('ABSPATH')) {
    exit;
}

define('SAFECONTRACTS_VERSION', '0.3.24');
define('SAFECONTRACTS_FILE', __FILE__);
define('SAFECONTRACTS_DIR', plugin_dir_path(__FILE__));
define('SAFECONTRACTS_PATH', SAFECONTRACTS_DIR);
define('SAFECONTRACTS_URL', plugin_dir_url(__FILE__));

require_once SAFECONTRACTS_DIR . 'src/Support/Autoloader.php';

\SafeContracts\Support\Autoloader::register();
require_once SAFECONTRACTS_DIR . 'src/Admin/MoneyPresentationFunctions.php';
\SafeContracts\Translations\PluginRedesignArabicDefaults::register();
\SafeContracts\Translations\CompleteArabicDefaults::register();
\SafeContracts\Translations\FeatureArabicDefaults::register();
\SafeContracts\Translations\PremiumPolishArabicDefaults::register();
\SafeContracts\Translations\MobileAdvertisingArabicDefaults::register();
\SafeContracts\Translations\ArabicRuntimeSafety::register();
\SafeContracts\PublicSite\AppStorePages::register();
\SafeContracts\Diagnostics\DatabaseDiagnosticExport::register();

register_activation_hook(SAFECONTRACTS_FILE, [\SafeContracts\Lifecycle\Activator::class, 'activate']);
register_deactivation_hook(SAFECONTRACTS_FILE, [\SafeContracts\Lifecycle\Deactivator::class, 'deactivate']);

add_action('admin_menu', [\SafeContracts\Admin\PlayReviewAccount::class, 'registerPage'], 35);
add_action('admin_post_' . \SafeContracts\Admin\PlayReviewAccount::CREATE_ACTION, [\SafeContracts\Admin\PlayReviewAccount::class, 'handleCreate']);
add_action('admin_post_' . \SafeContracts\Admin\PlayReviewAccount::DISABLE_ACTION, [\SafeContracts\Admin\PlayReviewAccount::class, 'handleDisable']);
add_action('admin_menu', [\SafeContracts\Admin\MobileAdvertisingPage::class, 'register'], 36);
add_action('admin_post_' . \SafeContracts\Admin\MobileAdvertisingPage::SAVE_ACTION, [\SafeContracts\Admin\MobileAdvertisingPage::class, 'handleSave']);
add_action('admin_menu', [\SafeContracts\Admin\NotificationSoundSettingsPage::class, 'register'], 37);
add_action('admin_post_' . \SafeContracts\Admin\NotificationSoundSettingsPage::SAVE_ACTION, [\SafeContracts\Admin\NotificationSoundSettingsPage::class, 'handleSave']);

add_filter('gettext', static function (string $translation, string $text, string $domain): string {
    if ($domain !== 'safecontracts') {
        return $translation;
    }

    return str_replace('SafeContracts', 'Safe Contracts', $translation);
}, 10, 3);

add_action('plugins_loaded', static function (): void {
    \SafeContracts\Plugin::instance()->boot();
    \SafeContracts\Admin\AdminPremiumDashboardEnhancements::register();
    \SafeContracts\Admin\AdminFinancePremiumEnhancements::register();
    \SafeContracts\Admin\EmailSettingsPage::register();
    \SafeContracts\Admin\NotificationCenterPage::registerInboxActions();
});
