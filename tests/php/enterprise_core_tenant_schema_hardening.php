<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\CoreTenantSchemaHardener;

$assertions = 0;

function esc_schema_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

final class ESC_Schema_Wpdb
{
    public string $prefix = 'wp_';
    public int $duplicateCustomerCodes = 0;
    public int $duplicateContractNumbers = 0;
    /** @var list<string> */
    public array $queries = [];
    /** @var array<string,bool> */
    private array $notNull = [];
    /** @var array<string,array<string,bool>> */
    private array $indexes = [];

    public function __construct()
    {
        foreach ([
            'safecontracts_customers',
            'safecontracts_contracts',
            'safecontracts_contract_financial_items',
            'safecontracts_contract_adjustments',
            'safecontracts_contract_attachments',
            'safecontracts_contract_history',
            'safecontracts_scheduled_payments',
            'safecontracts_payment_collections',
            'safecontracts_payment_followups',
        ] as $suffix) {
            $table = $this->prefix . $suffix;
            $this->indexes[$table] = ['esc_tenant_record' => true];
        }
        $this->indexes[$this->prefix . 'safecontracts_customers']['internal_code'] = true;
        $this->indexes[$this->prefix . 'safecontracts_contracts']['contract_number'] = true;
    }

    public function get_results(string $sql, mixed $output = null): array
    {
        unset($output);
        if (str_contains($sql, 'esc_duplicate_customer_codes')) {
            return [['total' => (string) $this->duplicateCustomerCodes]];
        }
        if (str_contains($sql, 'esc_duplicate_contract_numbers')) {
            return [['total' => (string) $this->duplicateContractNumbers]];
        }
        if (str_starts_with($sql, 'SELECT COUNT(*) AS total')) {
            return [['total' => '0']];
        }
        if (preg_match("/^SHOW COLUMNS FROM ([a-z0-9_]+) LIKE 'tenant_id'$/i", $sql, $matches) === 1) {
            return [['Field' => 'tenant_id', 'Null' => ! empty($this->notNull[$matches[1]]) ? 'NO' : 'YES']];
        }
        if (preg_match("/^SHOW INDEX FROM ([a-z0-9_]+) WHERE Key_name = '([^']+)'$/i", $sql, $matches) === 1) {
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
$database = new ESC_Schema_Wpdb();
$GLOBALS['wpdb'] = $database;
$GLOBALS['sc_test_options'][CoreTenantSchemaHardener::OPTION] = '0';
$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '0';

$hardener = new CoreTenantSchemaHardener();
$preflight = $hardener->preflight();
esc_schema_assert($preflight['ready'] === false, 'schema hardening refuses to run before runtime enforcement is enabled');

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
$database->duplicateCustomerCodes = 1;
$preflight = $hardener->preflight();
esc_schema_assert($preflight['ready'] === false, 'tenant duplicate customer codes block hardening');
$blocked = false;
try {
    $hardener->harden();
} catch (Throwable $error) {
    $blocked = str_contains($error->getMessage(), 'not ready');
}
esc_schema_assert($blocked, 'hardener fails closed while uniqueness preflight is red');

$database->duplicateCustomerCodes = 0;
$database->duplicateContractNumbers = 0;
$preflight = $hardener->preflight();
esc_schema_assert($preflight['ready'] === true, 'verified ownership plus enforcement and unique business keys unlock hardening');

$result = $hardener->harden();
esc_schema_assert($result['ready'] === true && $result['hardened'] === true, 'hardening completes with structural verification');
esc_schema_assert($hardener->isHardened(), 'schema hardened marker is persisted only after verification');

$ddl = implode("\n", $database->queries);
foreach ([
    'wp_safecontracts_customers',
    'wp_safecontracts_contracts',
    'wp_safecontracts_contract_financial_items',
    'wp_safecontracts_contract_adjustments',
    'wp_safecontracts_contract_attachments',
    'wp_safecontracts_contract_history',
    'wp_safecontracts_scheduled_payments',
    'wp_safecontracts_payment_collections',
    'wp_safecontracts_payment_followups',
] as $table) {
    esc_schema_assert(str_contains($ddl, "ALTER TABLE {$table} MODIFY COLUMN tenant_id bigint(20) unsigned NOT NULL"), "{$table} tenant ownership becomes NOT NULL");
}
esc_schema_assert(str_contains($ddl, 'ADD UNIQUE KEY esc_tenant_internal_code (tenant_id, internal_code)'), 'customer internal code uniqueness becomes tenant scoped');
esc_schema_assert(str_contains($ddl, 'DROP INDEX internal_code'), 'global customer internal code uniqueness is removed');
esc_schema_assert(str_contains($ddl, 'ADD UNIQUE KEY esc_tenant_contract_number (tenant_id, contract_number)'), 'contract number uniqueness becomes tenant scoped');
esc_schema_assert(str_contains($ddl, 'DROP INDEX contract_number'), 'global contract number uniqueness is removed');
esc_schema_assert(str_contains($ddl, 'esc_tenant_contract_status_due'), 'scheduled payment query shape gets tenant-first index');
esc_schema_assert(str_contains($ddl, 'esc_tenant_payment_date'), 'collection query shape gets tenant-first index');
esc_schema_assert(str_contains($ddl, 'esc_tenant_payment_timeline'), 'follow-up query shape gets tenant-first index');

$verification = $hardener->verify($database);
esc_schema_assert($verification['ready'] === true, 'post-DDL verification sees no nullable core ownership or missing tenant indexes');
esc_schema_assert($verification['legacy_global_unique_indexes'] === [], 'post-DDL verification confirms legacy global uniqueness is gone');

$GLOBALS['wpdb'] = $originalWpdb;
$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '0';
$GLOBALS['sc_test_options'][CoreTenantSchemaHardener::OPTION] = '0';

fwrite(STDOUT, "Enterprise core tenant schema hardening passed ({$assertions} assertions).\n");
