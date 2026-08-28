<?php

declare(strict_types=1);

$tests = 0;

function sc_diag_assert(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
$source = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Diagnostics/DatabaseDiagnosticExport.php');
$bootstrap = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/safecontracts.php');

sc_diag_assert(str_contains($bootstrap, 'DatabaseDiagnosticExport::register()'), 'database diagnostic export is registered at plugin bootstrap');
sc_diag_assert(str_contains($source, "public const ACTION = 'safecontracts_database_diagnostic_export'"), 'diagnostic export has a stable admin-post action');
sc_diag_assert(str_contains($source, "add_action('admin_post_' . self::ACTION"), 'diagnostic export is wired through admin-post');
sc_diag_assert(str_contains($source, 'current_user_can(Capabilities::MANAGE_NOTIFICATIONS)'), 'diagnostic export requires notification-management capability');
sc_diag_assert(str_contains($source, 'check_admin_referer(self::ACTION)'), 'diagnostic export is nonce protected');
sc_diag_assert(str_contains($source, "'safecontracts-notification-schedule'"), 'diagnostic export control is shown on the notification schedule page');
sc_diag_assert(str_contains($source, 'تنزيل نسخة تشخيصية من قاعدة البيانات'), 'Arabic database diagnostic button is present');
sc_diag_assert(str_contains($source, "'safecontracts-database-diagnostic-v1'"), 'diagnostic payload has an explicit versioned format');
sc_diag_assert(str_contains($source, "'notification_focus' => self::notificationFocus"), 'export includes a supplier/payment notification focus view');
sc_diag_assert(str_contains($source, "COALESCE(NULLIF(p.financial_direction, ''), NULLIF(c.financial_direction, '')) AS resolved_direction"), 'supplier payment focus exposes stored and resolved financial directions');
sc_diag_assert(str_contains($source, "safecontracts_scheduled_payments") && str_contains($source, "safecontracts_contracts") && str_contains($source, "safecontracts_suppliers"), 'supplier payment diagnostic joins the key business tables');
sc_diag_assert(str_contains($source, "'tables' => self::exportTables") && str_contains($source, "'options' => self::exportOptions"), 'export includes all SafeContracts tables and SafeContracts options');
sc_diag_assert(str_contains($source, "'safecontracts_'"), 'table/option export is scoped to SafeContracts data');
sc_diag_assert(str_contains($source, 'isSensitiveKey') && str_contains($source, "'[REDACTED]'"), 'diagnostic export redacts secret fields');
sc_diag_assert(str_contains($source, 'password|passwd|secret|token|private[_-]?key|api[_-]?key|credential|service[_-]?account'), 'secret redaction covers passwords tokens credentials and private/API keys');
sc_diag_assert(str_contains($source, "Content-Disposition: attachment; filename=\"safecontracts-diagnostic-db-"), 'diagnostic data downloads as a file');
sc_diag_assert(str_contains($bootstrap, 'Version: 0.3.25') && str_contains($bootstrap, "SAFECONTRACTS_VERSION', '0.3.25'"), 'diagnostic export remains available in forward plugin release 0.3.25');

echo "SafeContracts database diagnostic export regression passed ({$tests} assertions).\n";
