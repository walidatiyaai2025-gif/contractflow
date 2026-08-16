<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$GLOBALS['sc_test_locale'] = 'en_US';
if (! function_exists('get_user_locale')) {
    function get_user_locale(): string
    {
        return (string) ($GLOBALS['sc_test_locale'] ?? 'en_US');
    }
}

require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\TranslationsPage;
use SafeContracts\Translations\AdminArabicDefaults;
use SafeContracts\Translations\TranslationCatalog;

$tests = 0;
function sc_i18n_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_i18n_source(string $relative): string
{
    $source = file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
    sc_i18n_assert(is_string($source), 'source exists: ' . $relative);
    return is_string($source) ? $source : '';
}

TranslationCatalog::register();
AdminArabicDefaults::register();

sc_i18n_assert(TranslationCatalog::text('Dashboard', 'en') === 'Dashboard', 'English default remains source text');
sc_i18n_assert(TranslationCatalog::text('Dashboard', 'ar') === 'لوحة التحكم', 'Arabic bundled default resolves');
sc_i18n_assert(AdminArabicDefaults::default('Firebase project') === 'مشروع Firebase', 'supplemental Arabic admin default resolves');
sc_i18n_assert(AdminArabicDefaults::default('Unknown source') === 'Unknown source', 'unknown supplemental key fails safe to source');

$catalog = TranslationCatalog::catalog();
sc_i18n_assert(isset($catalog['Dashboard']), 'catalog contains core dashboard label');
sc_i18n_assert(isset($catalog['Firebase project']), 'catalog discovers admin gettext strings');
sc_i18n_assert(isset($catalog['Payment #{id}']), 'catalog discovers dynamic mobile template sources');
sc_i18n_assert(in_array('wp-admin', $catalog['Firebase project']['surfaces'], true), 'admin source is tagged with wp-admin surface');

TranslationCatalog::saveRows([
    ['source' => 'Dashboard', 'en' => 'Operations Home', 'ar' => 'لوحة العمليات'],
    ['source' => 'Payment #{id}', 'en' => 'Instalment #{id}', 'ar' => 'دفعة رقم {id}'],
]);
sc_i18n_assert(TranslationCatalog::text('Dashboard', 'en') === 'Operations Home', 'English dashboard override wins');
sc_i18n_assert(TranslationCatalog::text('Dashboard', 'ar') === 'لوحة العمليات', 'Arabic dashboard override wins');
sc_i18n_assert(TranslationCatalog::text('Payment #{id}', 'ar') === 'دفعة رقم {id}', 'dynamic mobile template override is stored');

$beforeUnknown = TranslationCatalog::overrides();
TranslationCatalog::saveRows([
    ['source' => 'not-a-known-safecontracts-source', 'en' => 'bad', 'ar' => 'سيئ'],
]);
sc_i18n_assert(TranslationCatalog::overrides() === $beforeUnknown, 'unknown source cannot be injected into translation store');

TranslationCatalog::saveRows([
    ['source' => 'Dashboard', 'en' => '', 'ar' => ''],
]);
sc_i18n_assert(TranslationCatalog::text('Dashboard', 'en') === 'Dashboard', 'empty English override resets to default');
sc_i18n_assert(TranslationCatalog::text('Dashboard', 'ar') === 'لوحة التحكم', 'empty Arabic override resets to bundled default');

TranslationCatalog::saveRows([
    ['source' => 'Dashboard', 'en' => "bad\0value", 'ar' => str_repeat('ع', TranslationCatalog::MAX_TRANSLATION_LENGTH + 1)],
]);
sc_i18n_assert(TranslationCatalog::text('Dashboard', 'en') === 'Dashboard', 'NUL-containing translation is rejected');
sc_i18n_assert(TranslationCatalog::text('Dashboard', 'ar') === 'لوحة التحكم', 'oversized translation is rejected');

TranslationCatalog::saveRows([
    ['source' => 'Customers', 'en' => 'Accounts', 'ar' => 'الحسابات'],
]);
$mobile = TranslationCatalog::mobileOverrides();
sc_i18n_assert(($mobile['en']['Customers'] ?? '') === 'Accounts', 'mobile payload includes English override');
sc_i18n_assert(($mobile['ar']['Customers'] ?? '') === 'الحسابات', 'mobile payload includes Arabic override');
TranslationCatalog::reset('en');
sc_i18n_assert(! isset(TranslationCatalog::overrides()['en']['Customers']), 'single-language reset clears English only');
sc_i18n_assert((TranslationCatalog::overrides()['ar']['Customers'] ?? '') === 'الحسابات', 'single-language reset preserves Arabic');
TranslationCatalog::reset();
sc_i18n_assert(TranslationCatalog::overrides() === ['en' => [], 'ar' => []], 'full reset restores both override maps');

$GLOBALS['sc_test_locale'] = 'ar';
sc_i18n_assert(AdminArabicDefaults::filterGettext('Firebase project', 'Firebase project', 'safecontracts') === 'مشروع Firebase', 'Arabic supplemental gettext fallback works');
$GLOBALS['sc_test_locale'] = 'en_US';
sc_i18n_assert(AdminArabicDefaults::filterGettext('Firebase project', 'Firebase project', 'safecontracts') === 'Firebase project', 'supplemental filter does not alter English');

$plugin = sc_i18n_source('wordpress-plugin/safecontracts/src/Plugin.php');
sc_i18n_assert(str_contains($plugin, 'TranslationsPage::class'), 'plugin wires translation dashboard page');
sc_i18n_assert(str_contains($plugin, 'TranslationCatalog::register()'), 'plugin registers primary translation filter');
sc_i18n_assert(str_contains($plugin, 'AdminArabicDefaults::register()'), 'plugin registers Arabic defaults filter');
sc_i18n_assert(str_contains($plugin, "TranslationsPage::SAVE_ACTION"), 'plugin wires translation save endpoint');

$translationsPage = sc_i18n_source('wordpress-plugin/safecontracts/src/Admin/TranslationsPage.php');
sc_i18n_assert(str_contains($translationsPage, 'Capabilities::MANAGE_SYSTEM'), 'translation editor is system-admin capability gated');
sc_i18n_assert(str_contains($translationsPage, 'check_admin_referer(self::SAVE_ACTION)'), 'translation writes require nonce');
sc_i18n_assert(str_contains($translationsPage, "translation_mode' =>" ) === false, 'translation mode is not blindly persisted as an option');

$mobileConfig = sc_i18n_source('wordpress-plugin/safecontracts/src/Rest/MobileConfigController.php');
sc_i18n_assert(str_contains($mobileConfig, "'translation_overrides' => TranslationCatalog::mobileOverrides()"), 'mobile config exposes safe translation overrides');

$mobileRuntime = sc_i18n_source('mobile/lib/core/localization/runtime_translation_overrides.dart');
$mobileL10n = sc_i18n_source('mobile/lib/core/localization/safecontracts_localizations.dart');
sc_i18n_assert(str_contains($mobileRuntime, 'maxEntriesPerLanguage = 1000'), 'mobile override parser is bounded');
sc_i18n_assert(str_contains($mobileL10n, 'SafeContractsRuntimeTranslations.lookup'), 'mobile localization prefers runtime dashboard override');
sc_i18n_assert(str_contains($mobileL10n, "'Payment #{id}'"), 'dynamic mobile payment label is template-localized');

$themeOverlay = sc_i18n_source('wordpress-theme/safecontracts-onepage/inc/translation-overrides.php');
sc_i18n_assert(str_contains($themeOverlay, "option_safecontracts_home_content"), 'public theme consumes shared translation override store');
sc_i18n_assert(! str_contains($themeOverlay, "add_filter( 'locale'"), 'theme overlay never filters WordPress locale');
sc_i18n_assert(! str_contains($themeOverlay, 'switch_to_locale'), 'theme overlay never switches WordPress locale');

$translationSources = $plugin
    . sc_i18n_source('wordpress-plugin/safecontracts/src/Translations/TranslationCatalog.php')
    . sc_i18n_source('wordpress-plugin/safecontracts/src/Translations/AdminArabicDefaults.php')
    . $translationsPage;
foreach (["add_filter('locale'", "add_filter('determine_locale'", "add_filter('pre_determine_locale'", "add_filter('plugin_locale'", 'switch_to_locale(', "update_user_meta("] as $forbidden) {
    sc_i18n_assert(! str_contains($translationSources, $forbidden), 'translation implementation does not mutate WordPress locale: ' . $forbidden);
}

sc_i18n_assert(TranslationsPage::SLUG === 'safecontracts-translations', 'stable translations page slug');

fwrite(STDOUT, "SafeContracts translation registry/editor #404 passed ({$tests} assertions).\n");
