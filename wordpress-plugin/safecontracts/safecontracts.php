<?php
/**
 * Plugin Name: Safe Contracts
 * Plugin URI: https://github.com/walidatiyaai2025-gif/contractflow
 * Description: Contract receivables tracking backend and administration foundation for Safe Contracts.
 * Version: 0.3.10
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: Safe Contracts Team
 * Text Domain: safecontracts
 */

if (! defined('ABSPATH')) {
    exit;
}

define('SAFECONTRACTS_VERSION', '0.3.10');
define('SAFECONTRACTS_FILE', __FILE__);
define('SAFECONTRACTS_DIR', plugin_dir_path(__FILE__));
// Canonical path alias used by translation source discovery. Keep it equal to
// SAFECONTRACTS_DIR; it does not expose a URL or change WordPress locale state.
define('SAFECONTRACTS_PATH', SAFECONTRACTS_DIR);
define('SAFECONTRACTS_URL', plugin_dir_url(__FILE__));

require_once SAFECONTRACTS_DIR . 'src/Support/Autoloader.php';

\SafeContracts\Support\Autoloader::register();
// Namespace-level legacy money helpers are functions, not classes, so they
// cannot be loaded through the PSR-style autoloader. Load them explicitly so
// every older Admin number_format(..., 2) presentation follows the centralized
// no-redundant-decimals rule without changing DECIMAL storage or arithmetic.
require_once SAFECONTRACTS_DIR . 'src/Admin/MoneyPresentationFunctions.php';
\SafeContracts\Translations\PluginRedesignArabicDefaults::register();
\SafeContracts\Translations\CompleteArabicDefaults::register();
\SafeContracts\Translations\FeatureArabicDefaults::register();
\SafeContracts\Translations\PremiumPolishArabicDefaults::register();
\SafeContracts\Translations\ArabicRuntimeSafety::register();

register_activation_hook(SAFECONTRACTS_FILE, [\SafeContracts\Lifecycle\Activator::class, 'activate']);
register_deactivation_hook(SAFECONTRACTS_FILE, [\SafeContracts\Lifecycle\Deactivator::class, 'deactivate']);

// Keep technical slugs/namespaces backward-compatible while normalizing every
// Safe Contracts gettext surface shown by the plugin to the approved name.
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
    // Email delivery configuration is intentionally a dedicated admin page,
    // separate from the operational Notification Center.
    \SafeContracts\Admin\EmailSettingsPage::register();
    \SafeContracts\Admin\NotificationCenterPage::registerInboxActions();
});
