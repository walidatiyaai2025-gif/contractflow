<?php

declare(strict_types=1);

namespace SafeContracts\Diagnostics;

use SafeContracts\Roles\Capabilities;

final class DatabaseDiagnosticExport
{
    public const ACTION = 'safecontracts_database_diagnostic_export';

    public static function register(): void
    {
        add_action('admin_post_' . self::ACTION, [self::class, 'handleExport']);
        add_action('admin_notices', [self::class, 'renderControl'], 10);
    }

    public static function renderControl(): void
    {
        $page = isset($_GET['page']) && is_scalar($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
        if ($page !== 'safecontracts-notification-schedule' || ! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            return;
        }
        ?>
        <div class="notice notice-warning safecontracts-database-diagnostic-export" style="padding:14px 16px;display:flex;gap:16px;align-items:center;justify-content:space-between;flex-wrap:wrap;">
            <div style="max-width:760px;">
                <strong><?php echo esc_html(self::adminText('Database diagnostic copy', 'نسخة تشخيصية من قاعدة البيانات')); ?></strong>
                <p style="margin:4px 0 0;">
                    <?php echo esc_html(self::adminText(
                        'Downloads SafeContracts tables, schema and SafeContracts options as JSON so notification/payment data can be inspected. Passwords, tokens, secrets, API keys and private keys are redacted automatically. WordPress users and unrelated WordPress tables are not exported.',
                        'تنزّل جداول SafeContracts والـschema وإعدادات SafeContracts في ملف JSON لفحص بيانات الدفعات والإشعارات. يتم إخفاء كلمات السر والتوكنات والأسرار ومفاتيح API والمفاتيح الخاصة تلقائياً، ولا يتم تصدير مستخدمي WordPress أو الجداول غير التابعة للنظام.'
                    )); ?>
                </p>
            </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                <?php wp_nonce_field(self::ACTION); ?>
                <button type="submit" class="button button-secondary"><?php echo esc_html(self::adminText('Download database diagnostic copy', 'تنزيل نسخة تشخيصية من قاعدة البيانات')); ?></button>
            </form>
        </div>
        <?php
    }

    public static function handleExport(): void
    {
        if (! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            wp_die(self::adminText(
                'You do not have permission to export SafeContracts diagnostic data.',
                'ليس لديك صلاحية لتصدير بيانات SafeContracts التشخيصية.'
            ));
        }

        check_admin_referer(self::ACTION);

        global $wpdb;
        if (! is_object($wpdb)) {
            wp_die(self::adminText('Database connection is unavailable.', 'اتصال قاعدة البيانات غير متاح.'));
        }

        $payload = [
            'format' => 'safecontracts-database-diagnostic-v1',
            'generated_at_utc' => gmdate('c'),
            'plugin_version' => defined('SAFECONTRACTS_VERSION') ? (string) SAFECONTRACTS_VERSION : '',
            'database_version' => (string) get_option('safecontracts_db_version', ''),
            'scope' => 'SafeContracts tables and SafeContracts options only',
            'redaction' => 'password/token/secret/private-key/api-key/credential/service-account fields are replaced with [REDACTED]',
            'notification_focus' => self::notificationFocus($wpdb),
            'tables' => self::exportTables($wpdb),
            'options' => self::exportOptions($wpdb),
        ];

        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($json)) {
            wp_die(self::adminText('Could not encode the diagnostic export.', 'تعذر تجهيز ملف التشخيص.'));
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="safecontracts-diagnostic-db-' . gmdate('Ymd-His') . '.json"');
        echo $json;
        exit;
    }

    /** @return array<int,array<string,mixed>> */
    private static function exportTables(object $wpdb): array
    {
        $prefix = (string) $wpdb->prefix . 'safecontracts_';
        $like = method_exists($wpdb, 'esc_like') ? $wpdb->esc_like($prefix) . '%' : $prefix . '%';
        $prepared = method_exists($wpdb, 'prepare') ? $wpdb->prepare('SHOW TABLES LIKE %s', $like) : "SHOW TABLES LIKE '" . addslashes($like) . "'";
        $names = method_exists($wpdb, 'get_col') ? $wpdb->get_col($prepared) : [];
        if (! is_array($names)) {
            $names = [];
        }
        sort($names, SORT_STRING);

        $tables = [];
        foreach ($names as $tableName) {
            $table = (string) $tableName;
            if (! str_starts_with($table, $prefix) || preg_match('/[^A-Za-z0-9_]/', $table)) {
                continue;
            }

            $quoted = '`' . $table . '`';
            $columns = method_exists($wpdb, 'get_results') ? $wpdb->get_results("SHOW COLUMNS FROM {$quoted}", ARRAY_A) : [];
            $rows = method_exists($wpdb, 'get_results') ? $wpdb->get_results("SELECT * FROM {$quoted}", ARRAY_A) : [];
            $columns = is_array($columns) ? $columns : [];
            $rows = is_array($rows) ? $rows : [];

            $redactedRows = [];
            foreach ($rows as $row) {
                $redactedRows[] = self::redactRow(is_array($row) ? $row : []);
            }

            $tables[] = [
                'name' => $table,
                'row_count' => count($redactedRows),
                'schema' => $columns,
                'rows' => $redactedRows,
            ];
        }

        return $tables;
    }

    /** @return array<int,array<string,mixed>> */
    private static function exportOptions(object $wpdb): array
    {
        $optionsTable = (string) $wpdb->options;
        if ($optionsTable === '' || preg_match('/[^A-Za-z0-9_]/', $optionsTable)) {
            return [];
        }
        $like = method_exists($wpdb, 'esc_like') ? $wpdb->esc_like('safecontracts_') . '%' : 'safecontracts_%';
        $sql = method_exists($wpdb, 'prepare')
            ? $wpdb->prepare("SELECT option_name, option_value, autoload FROM `{$optionsTable}` WHERE option_name LIKE %s ORDER BY option_name ASC", $like)
            : "SELECT option_name, option_value, autoload FROM `{$optionsTable}` WHERE option_name LIKE 'safecontracts_%' ORDER BY option_name ASC";
        $rows = method_exists($wpdb, 'get_results') ? $wpdb->get_results($sql, ARRAY_A) : [];
        if (! is_array($rows)) {
            return [];
        }

        $options = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = (string) ($row['option_name'] ?? '');
            $raw = $row['option_value'] ?? '';
            $value = function_exists('maybe_unserialize') ? maybe_unserialize($raw) : $raw;
            $options[] = [
                'option_name' => $name,
                'option_value' => self::redactValue($value, $name),
                'autoload' => (string) ($row['autoload'] ?? ''),
            ];
        }
        return $options;
    }

    /** @return array<string,mixed> */
    private static function notificationFocus(object $wpdb): array
    {
        $prefix = (string) $wpdb->prefix;
        foreach (['safecontracts_scheduled_payments', 'safecontracts_contracts', 'safecontracts_suppliers'] as $suffix) {
            $table = $prefix . $suffix;
            if (preg_match('/[^A-Za-z0-9_]/', $table)) {
                return [];
            }
        }

        $payments = '`' . $prefix . 'safecontracts_scheduled_payments`';
        $contracts = '`' . $prefix . 'safecontracts_contracts`';
        $suppliers = '`' . $prefix . 'safecontracts_suppliers`';
        $sql = "SELECT
                    p.id AS payment_id,
                    p.contract_id,
                    p.financial_direction AS payment_direction,
                    c.financial_direction AS contract_direction,
                    COALESCE(NULLIF(p.financial_direction, ''), NULLIF(c.financial_direction, '')) AS resolved_direction,
                    c.counterparty_type,
                    c.counterparty_id,
                    su.name AS supplier_name,
                    p.due_date,
                    p.expected_payment_date,
                    p.original_amount,
                    p.paid_amount,
                    p.remaining_amount,
                    p.status AS payment_status,
                    p.is_archived AS payment_archived,
                    c.status AS contract_status,
                    c.is_archived AS contract_archived
                FROM {$payments} p
                LEFT JOIN {$contracts} c ON c.id = p.contract_id
                LEFT JOIN {$suppliers} su ON c.counterparty_type = 'supplier' AND su.id = c.counterparty_id
                WHERE c.counterparty_type = 'supplier' OR c.financial_direction = 'payable' OR p.financial_direction = 'payable'
                ORDER BY p.due_date ASC, p.id ASC";
        $rows = method_exists($wpdb, 'get_results') ? $wpdb->get_results($sql, ARRAY_A) : [];
        return [
            'supplier_payment_resolution' => is_array($rows) ? array_map(
                static fn ($row): array => self::redactRow(is_array($row) ? $row : []),
                $rows
            ) : [],
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private static function redactRow(array $row): array
    {
        $safe = [];
        foreach ($row as $key => $value) {
            $safe[(string) $key] = self::redactValue($value, (string) $key);
        }
        return $safe;
    }

    private static function redactValue(mixed $value, string $key = ''): mixed
    {
        if (self::isSensitiveKey($key)) {
            return '[REDACTED]';
        }
        if (is_array($value)) {
            $safe = [];
            foreach ($value as $nestedKey => $nestedValue) {
                $safe[$nestedKey] = self::redactValue($nestedValue, (string) $nestedKey);
            }
            return $safe;
        }
        if (is_object($value)) {
            return self::redactValue(get_object_vars($value), $key);
        }
        if (is_string($value) && str_contains($value, '-----BEGIN PRIVATE KEY-----')) {
            return '[REDACTED]';
        }
        return $value;
    }

    private static function isSensitiveKey(string $key): bool
    {
        return preg_match('/(?:password|passwd|secret|token|private[_-]?key|api[_-]?key|credential|service[_-]?account)/i', $key) === 1;
    }

    private static function adminText(string $english, string $arabic): string
    {
        if (function_exists('is_rtl') && is_rtl()) {
            return $arabic;
        }
        $locale = function_exists('get_user_locale') ? strtolower((string) get_user_locale()) : '';
        return str_starts_with($locale, 'ar') ? $arabic : $english;
    }
}
