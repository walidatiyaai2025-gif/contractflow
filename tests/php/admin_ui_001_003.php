<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\AdminShell;
use SafeContracts\Admin\LoginBranding;
use SafeContracts\Admin\NavigationCleanup;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Support\Brand;

$tests = 0;
function sc_p6a_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_p6a_expect(string $class, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        sc_p6a_assert($error instanceof $class, $message);
        return;
    }
    sc_p6a_assert(false, $message);
}

// Boot the plugin and confirm the P6 hooks are part of the real runtime path.
do_action('plugins_loaded');
sc_p6a_assert(isset($GLOBALS['sc_test_actions']['admin_menu']), 'P6 admin menu hooks register during plugin boot');
sc_p6a_assert(isset($GLOBALS['sc_test_actions']['admin_enqueue_scripts']), 'P6 admin stylesheet hook registers during plugin boot');
sc_p6a_assert(isset($GLOBALS['sc_test_actions']['login_enqueue_scripts']), 'P6 login branding hook registers during plugin boot');
sc_p6a_assert(isset($GLOBALS['sc_test_filters']['login_headerurl']) && isset($GLOBALS['sc_test_filters']['login_headertext']), 'P6 login branding filters register during plugin boot');

// SC-P6-001 — Safe Contracts admin shell.
$GLOBALS['sc_test_current_caps'] = [];
do_action('admin_menu');
$shell = $GLOBALS['sc_test_admin_pages'][AdminShell::SLUG] ?? null;
sc_p6a_assert(is_array($shell), 'SC-P6-001 top-level Safe Contracts admin page is registered');
sc_p6a_assert(($shell['capability'] ?? null) === Capabilities::ACCESS, 'SC-P6-001 shell visibility is capability-gated');
sc_p6a_assert(($shell['icon'] ?? null) === Brand::iconDataUri(), 'SC-P6-001 shell uses the approved Safe Contracts image identity');
sc_p6a_assert(($shell['page_title'] ?? null) === Brand::NAME && ($shell['menu_title'] ?? null) === Brand::NAME, 'SC-P6-001 shell uses the approved visible Safe Contracts name');
sc_p6a_assert(is_callable($shell['callback'] ?? null), 'SC-P6-001 shell callback is registered');

sc_p6a_expect(RuntimeException::class, [AdminShell::class, 'render'], 'SC-P6-001 shell render rejects users without Safe Contracts access');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
ob_start();
AdminShell::render();
$rendered = (string) ob_get_clean();
sc_p6a_assert(str_contains($rendered, 'Safe Contracts'), 'SC-P6-001 shell renders Safe Contracts identity');
sc_p6a_assert(str_contains($rendered, 'data:image/jpeg;base64,'), 'SC-P6-001 shell renders the supplied brand artwork');
sc_p6a_assert(str_contains($rendered, 'Server-side authorization'), 'SC-P6-001 shell communicates server-side authorization boundary');
sc_p6a_assert(! str_contains($rendered, '<table'), 'SC-P6-001 shell does not invent KPI/business data before later P6 tasks');

$_GET['page'] = 'safecontracts';
AdminShell::enqueueAssets();
$adminStyle = $GLOBALS['sc_test_enqueued_styles'][AdminShell::STYLE_HANDLE] ?? null;
sc_p6a_assert(is_array($adminStyle) && str_ends_with((string) ($adminStyle['src'] ?? ''), 'assets/admin/safecontracts-admin.css'), 'SC-P6-001 shell loads dedicated Safe Contracts admin identity stylesheet');
$_GET['page'] = 'safecontracts-payment-methods';
sc_p6a_assert(AdminShell::isSafeContractsPage(), 'SC-P6-001 existing Safe Contracts subpages inherit shell styling boundary');
$_GET['page'] = 'unrelated-plugin';
sc_p6a_assert(! AdminShell::isSafeContractsPage(), 'SC-P6-001 admin styling does not leak to unrelated WordPress pages');

$adminCss = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/assets/admin/safecontracts-admin.css');
sc_p6a_assert(is_string($adminCss) && str_contains($adminCss, '--safecontracts-navy') && str_contains($adminCss, '--safecontracts-green'), 'SC-P6-001 admin stylesheet carries navy/green Safe Contracts identity tokens');
sc_p6a_assert(str_contains((string) $adminCss, '[dir="rtl"]') && str_contains((string) $adminCss, '@media (max-width: 782px)'), 'SC-P6-001 admin identity includes RTL and responsive behavior');

// SC-P6-002 — Login branding.
$GLOBALS['sc_test_enqueued_styles'] = [];
LoginBranding::enqueueAssets();
$loginStyle = $GLOBALS['sc_test_enqueued_styles'][LoginBranding::STYLE_HANDLE] ?? null;
sc_p6a_assert(is_array($loginStyle) && str_ends_with((string) ($loginStyle['src'] ?? ''), 'assets/admin/safecontracts-login.css'), 'SC-P6-002 login branding loads isolated stylesheet');
sc_p6a_assert(LoginBranding::headerUrl('https://wordpress.org') === 'https://example.test/', 'SC-P6-002 login logo link stays on site rather than WordPress branding');
sc_p6a_assert(str_contains(LoginBranding::headerText('WordPress'), Brand::NAME), 'SC-P6-002 login header text uses Safe Contracts identity');
$loginCss = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/assets/admin/safecontracts-login.css');
sc_p6a_assert(is_string($loginCss) && str_contains($loginCss, 'body.login') && str_contains($loginCss, 'background-size: cover'), 'SC-P6-002 login CSS hosts the supplied Safe Contracts image identity');
sc_p6a_assert(! str_contains((string) $loginCss, 'content: "SC"'), 'SC-P6-002 legacy SC lettermark is removed from login presentation');
sc_p6a_assert(! str_contains((string) $loginCss, 'display: none') || str_contains((string) $loginCss, '#nav'), 'SC-P6-002 login branding does not hide authentication controls');

// SC-P6-003 — Admin navigation cleanup.
$GLOBALS['sc_test_removed_admin_menus'] = [];
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
NavigationCleanup::cleanup();
$removed = $GLOBALS['sc_test_removed_admin_menus'];
sc_p6a_assert($removed === NavigationCleanup::defaultHiddenMenus(), 'SC-P6-003 operational users receive deterministic native-menu cleanup');
sc_p6a_assert(! in_array(AdminShell::SLUG, $removed, true), 'SC-P6-003 Safe Contracts entry point is never removed');
sc_p6a_assert(in_array('index.php', $removed, true) && in_array('edit.php', $removed, true) && in_array('options-general.php', $removed, true), 'SC-P6-003 irrelevant dashboard/content/settings menus are hidden for operational roles');

$GLOBALS['sc_test_removed_admin_menus'] = [];
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::MANAGE_SYSTEM => true];
NavigationCleanup::cleanup();
sc_p6a_assert($GLOBALS['sc_test_removed_admin_menus'] === [], 'SC-P6-003 Safe Contracts system administrator is exempt from menu cleanup');

$GLOBALS['sc_test_removed_admin_menus'] = [];
$GLOBALS['sc_test_current_caps'] = [];
NavigationCleanup::cleanup();
sc_p6a_assert($GLOBALS['sc_test_removed_admin_menus'] === [], 'SC-P6-003 non-Safe Contracts users are not modified by Safe Contracts navigation cleanup');

add_filter('safecontracts_hidden_admin_menus', static fn (mixed $menus): array => ['index.php', AdminShell::SLUG]);
$GLOBALS['sc_test_removed_admin_menus'] = [];
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
NavigationCleanup::cleanup();
sc_p6a_assert($GLOBALS['sc_test_removed_admin_menus'] === ['index.php'], 'SC-P6-003 filtered cleanup remains extensible but refuses to remove Safe Contracts shell');
sc_p6a_assert(current_user_can(Capabilities::ACCESS), 'SC-P6-003 menu cleanup never mutates the underlying capability authorization state');

echo "Safe Contracts P6 admin UI SC-P6-001..003 passed ({$tests} assertions).\n";
