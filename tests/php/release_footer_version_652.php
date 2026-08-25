<?php

declare(strict_types=1);

$GLOBALS['sc_652_locale'] = 'en_US';

function get_user_locale(): string
{
    return (string) ($GLOBALS['sc_652_locale'] ?? 'en_US');
}

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\AdminShell;

$tests = 0;

$assert = static function (bool $condition, string $message) use (&$tests): void {
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$_GET['page'] = 'dashboard';
$assert(AdminShell::footerText('WordPress footer') === 'WordPress footer', 'unrelated WordPress pages retain their existing footer text');
$assert(AdminShell::footerVersion('WordPress 7.1') === 'WordPress 7.1', 'unrelated WordPress pages retain their existing WordPress version');

$_GET['page'] = 'safecontracts';
$assert(AdminShell::footerText('WordPress footer') === 'ALKENZY ADV — Approved release', 'SafeContracts footer identifies the approved Alkenzy ADV release');
$assert(AdminShell::footerVersion('WordPress 7.1') === 'Version 0.3.6', 'SafeContracts footer renders the canonical plugin version');

$GLOBALS['sc_652_locale'] = 'ar_KW';
$assert(AdminShell::footerText('WordPress footer') === 'ALKENZY ADV — النسخة المعتمدة', 'approved footer label is Arabic on Arabic WordPress profiles');
$assert(AdminShell::footerVersion('WordPress 7.1') === 'الإصدار 0.3.6', 'approved footer version is Arabic on Arabic WordPress profiles');

$shell = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Admin/AdminShell.php');
$assert(str_contains($shell, "add_filter('admin_footer_text'") && str_contains($shell, "add_filter('update_footer'"), 'AdminShell registers both WordPress footer filters');

echo "ALKENZY ADV release footer/version regression passed ({$tests} assertions).\n";
