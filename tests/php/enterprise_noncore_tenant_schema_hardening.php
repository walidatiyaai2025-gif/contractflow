<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Tenancy\NonCoreTenantEnforcement;
use SafeContracts\Tenancy\NonCoreTenantSchemaHardener;

$assertions = 0;

function esc_noncore_schema_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

final class ESC_NonCore_Schema_Wpdb
{
    public string $prefix = 'wp_';
    public int $duplicateRuleCodes = 0;
    /** @var list<string> */
    public array $queries = [];
    /** @var array<string,bool> */
    private array $notNull = [];
    /** @var array<string,array<string,bool>> */
    private array $indexes = [];

    public function __construct()
    {
        foreach ([
            'safecontracts_notification_rules',
            'safecontracts_notification_templates',
            'safecontracts_device_tokens',
            'safecontracts_notification_deliveries',
            'safecontracts_notification_schedule',
            'safecontracts_notification_suppressions',
            'safecontracts_import_runs',
            'safecontracts_import_errors',
            'safecontracts_audit_log',
        ] as $suffix) {
            $table = $this->prefix . $suffix;
            $this->indexes[$table] = ['esc_tenant_record' => true];
        }

        $this->indexes[$this->prefix . 'safecontracts_notification_rules']['code'] = true;
        $this->indexes[$this->prefix . 'safecontracts_notification_templates']['code'] = true;
        $this->indexes[$this->prefix . 'safecontracts_device_tokens']['token_hash'] = true;
        $this->indexes[$this->prefix . 'safecontracts_notification_schedule']['rule_payment_attempt'] = true;
        $this->indexes[$this->prefix . 'safecontracts_notification_suppressions']['scope'] = true;
        $this->indexes[$this->prefix . 'safecontracts_import_runs']['storage_key'] = true;
    }

    public function prepare(string $query, mixed ...$args): string
    {
        $prepared = array_map(
            static fn (mixed $value): mixed => is_int($value) ? $value : "'" . addslashes((string) $value) . "'",
            $args
        );
        return vsprintf($query, $prepared);
    }

    public function get_results(string $sql, mixed $output = null): array
    {
        unset($output);
        if (str_contains($sql, 'esc_noncore_duplicates')) {
            if (str_contains($sql, 'safecontracts_notification_rules')) {
                return [['total' => (string) $this->duplicateRuleCodes]];
            }
            return [['total' => '0']];
        }
        if (str_starts_with(ltrim($sql), 'SELECT COUNT(*) AS total')) {
            return [['total' => '0']];
        }
        if (preg_match("/^SHOW COLUMNS FROM ([a-z0-9_]+) LIKE 'tenant_id'$/i", trim($sql), $matches) === 1) {
            return [['Field' => 'tenant_id', 'Null' => ! empty($this->notNull[$matches[1]]) ? 'NO' : 'YES']];
        }
        if (preg_match("/^SHOW INDEX FROM ([a-z0-9_]+) WHERE Key_name = '([^']+)'$/i", trim($sql), $matches) === 1) {
            return ! empty($this->indexes[$matches[1]][$matches[2]]) ? [['Key_name' => $matches[2]]] : [];
        }
        return [];
    }

    public function query(string $sql): int|false
    {
        $this->queries[] = $sql;
        if (preg_match('/^ALTER TABLE ([a-z0-9_]+) MODIFY COLUMN tenant_id /i', $sql, $matches) === 1) {
            $this->notNull[$matches[1]] = true;
            return 1;
        }
        if (preg_match('/^ALTER TABLE ([a-z0-9_]+) ADD (?:UNIQUE )?KEY ([a-z0-9_]+)/i', $sql, $matches) === 1) {
            $this->indexes[$matches[1]][$matches[2]] = true;
            return 1;
        }
        if (preg_match('/^ALTER TABLE ([a-z0-9_]+) DROP INDEX ([a-z0-9_]+)/i', $sql, $matches) === 1) {
            unset($this->indexes[$matches[1]][$matches[2]]);
            return 1;
        }
        return 1;
    }
}

$originalWpdb = $GLOBALS['wpdb'];
$database = new ESC_NonCore_Schema_Wpdb();
$GLOBALS['wpdb'] = $database;
$GLOBALS['sc_test_options'][NonCoreTenantSchemaHardener::OPTION] = '0';
$GLOBALS['sc_test_options'][NonCoreTenantEnforcement::OPTION] = '0';

$hardener = new NonCoreTenantSchemaHardener();
$preflight = $hardener->preflight();
esc_noncore_schema_assert($preflight['runtime_enforcement'] === false, 'schema preflight reports runtime enforcement state');
esc_noncore_schema_assert($preflight['ready'] === false, 'ownership alone cannot skip runtime enforcement and harden immediately');
$blockedBeforeRuntime = false;
try {
    $hardener->harden();
} catch (Throwable $error) {
    $blockedBeforeRuntime = str_contains($error->getMessage(), 'runtime tenant enforcement');
}
esc_noncore_schema_assert($blockedBeforeRuntime, 'non-core hardener fails closed until runtime tenant enforcement is enabled and validated');

$GLOBALS['sc_test_options'][NonCoreTenantEnforcement::OPTION] = '1';
$preflight = $hardener->preflight();
esc_noncore_schema_assert($preflight['runtime_enforcement'] === true, 'schema preflight sees active runtime tenant enforcement');
esc_noncore_schema_assert($preflight['ready'] === true, 'verified ownership plus runtime enforcement unlocks hardening preflight');

$database->duplicateRuleCodes = 1;
$preflight = $hardener->preflight();
esc_noncore_schema_assert($preflight['ready'] === false, 'duplicate rule code inside a tenant blocks hardening');
$blocked = false;
try {
    $hardener->harden();
} catch (Throwable $error) {
    $blocked = str_contains($error->getMessage(), 'not ready');
}
esc_noncore_schema_assert($blocked, 'non-core hardener fails closed while uniqueness preflight is red');

$database->duplicateRuleCodes = 0;
$result = $hardener->harden();
esc_noncore_schema_assert($result['ready'] === true && $result['hardened'] === true, 'non-core hardening completes only after runtime enforcement and structural verification');
esc_noncore_schema_assert($hardener->isHardened(), 'non-core hardened marker is persisted only after verification');

$ddl = implode("\n", $database->queries);
foreach ([
    'wp_safecontracts_notification_rules',
    'wp_safecontracts_notification_templates',
    'wp_safecontracts_device_tokens',
    'wp_safecontracts_notification_deliveries',
    'wp_safecontracts_notification_schedule',
    'wp_safecontracts_notification_suppressions',
    'wp_safecontracts_import_runs',
    'wp_safecontracts_import_errors',
] as $table) {
    esc_noncore_schema_assert(
        str_contains($ddl, "ALTER TABLE {$table} MODIFY COLUMN tenant_id bigint(20) unsigned NOT NULL"),
        "{$table} tenant ownership becomes NOT NULL"
    );
}
esc_noncore_schema_assert(! str_contains($ddl, 'ALTER TABLE wp_safecontracts_audit_log MODIFY COLUMN tenant_id'), 'platform-global audit support keeps audit tenant ownership nullable');

foreach ([
    'esc_tenant_rule_code (tenant_id, code)',
    'esc_tenant_template_code (tenant_id, code)',
    'esc_tenant_token_hash (tenant_id, token_hash)',
    'esc_tenant_rule_payment_attempt (tenant_id, rule_id, payment_id, attempt_no)',
    'esc_tenant_suppression_scope (tenant_id, scope_type, scope_id)',
    'esc_tenant_storage_key (tenant_id, storage_key)',
] as $indexDefinition) {
    esc_noncore_schema_assert(str_contains($ddl, $indexDefinition), "tenant-scoped unique index {$indexDefinition} is created");
}
foreach (['code', 'token_hash', 'rule_payment_attempt', 'scope', 'storage_key'] as $legacy) {
    esc_noncore_schema_assert(str_contains($ddl, 'DROP INDEX ' . $legacy), "legacy global unique index {$legacy} is removed after scoped replacement");
}
esc_noncore_schema_assert(str_contains($ddl, 'esc_tenant_user_active (tenant_id, user_id, is_active, id)'), 'device lookup uses live is_active field');
esc_noncore_schema_assert(str_contains($ddl, 'esc_tenant_status_due (tenant_id, status, scheduled_for, id)'), 'schedule due queue uses live scheduled_for field');
esc_noncore_schema_assert(str_contains($ddl, 'esc_tenant_run_row (tenant_id, import_run_id, row_number, id)'), 'import error index uses live import_run_id field');
esc_noncore_schema_assert(str_contains($ddl, 'esc_tenant_scope_active (tenant_id, scope_type, scope_id, is_active, id)'), 'suppression index uses live scope fields');
esc_noncore_schema_assert(str_contains($ddl, 'esc_tenant_audit_entity'), 'audit browsing gets tenant-first entity index');

$source = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Tenancy/NonCoreTenantSchemaHardener.php');
esc_noncore_schema_assert(str_contains($source, 'NonCoreTenantEnforcement::isEnabled()'), 'hardener explicitly depends on runtime enforcement state');
esc_noncore_schema_assert(! str_contains($source, 'idempotency_key'), 'hardener does not invent a delivery idempotency column');
esc_noncore_schema_assert(! str_contains($source, 'scheduled_for_utc'), 'hardener contains no obsolete scheduled_for_utc field');
esc_noncore_schema_assert(! str_contains($source, 'enabled, id'), 'hardener contains no obsolete device enabled field');
esc_noncore_schema_assert(! str_contains($source, 'run_id, row_number'), 'hardener contains no obsolete import error run_id field');

$verification = $hardener->verify($database);
esc_noncore_schema_assert($verification['ready'] === true, 'post-DDL non-core verification is green');
esc_noncore_schema_assert($verification['audit_tenant_nullable'] === true, 'post-DDL verification preserves nullable platform-global audit ownership');
esc_noncore_schema_assert($verification['legacy_global_unique_indexes'] === [], 'post-DDL verification confirms legacy global unique indexes are gone');

$GLOBALS['wpdb'] = $originalWpdb;
$GLOBALS['sc_test_options'][NonCoreTenantSchemaHardener::OPTION] = '0';
$GLOBALS['sc_test_options'][NonCoreTenantEnforcement::OPTION] = '0';

fwrite(STDOUT, "Enterprise non-core tenant schema hardening passed ({$assertions} assertions).\n");
