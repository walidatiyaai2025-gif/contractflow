<?php

declare(strict_types=1);

if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Database/Migration.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Database/ProductionMigration.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0019NullableLegacyCustomer.php';

use SafeContracts\Database\Migrations\Migration0019NullableLegacyCustomer;
use SafeContracts\Database\ProductionMigration;

$tests = 0;
function sc_customer_nullable_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

final class SC_CustomerNullableWpdb
{
    public string $prefix = 'wp_';
    public bool $nullable;
    public int $nullRows = 0;
    public bool $includeCounterparty = true;
    public string $customerType = 'bigint(20) unsigned';
    /** @var list<string> */
    public array $queries = [];

    public function __construct(bool $nullable)
    {
        $this->nullable = $nullable;
    }

    /** @return list<array<string,string>> */
    public function get_results(string $sql, mixed $output = null): array
    {
        unset($output);
        if (! str_contains($sql, 'SHOW COLUMNS FROM wp_safecontracts_contracts')) {
            return [];
        }
        $rows = [[
            'Field' => 'customer_id',
            'Type' => $this->customerType,
            'Null' => $this->nullable ? 'YES' : 'NO',
        ]];
        if ($this->includeCounterparty) {
            $rows[] = ['Field' => 'counterparty_type', 'Type' => 'varchar(16)', 'Null' => 'YES'];
            $rows[] = ['Field' => 'counterparty_id', 'Type' => 'bigint(20) unsigned', 'Null' => 'YES'];
            $rows[] = ['Field' => 'financial_direction', 'Type' => 'varchar(16)', 'Null' => 'YES'];
        }
        return $rows;
    }

    public function query(string $sql): int|false
    {
        $this->queries[] = $sql;
        if (str_contains($sql, 'MODIFY customer_id bigint(20) unsigned NOT NULL')) {
            $this->nullable = false;
            return 1;
        }
        if (str_contains($sql, 'MODIFY customer_id bigint(20) unsigned NULL')) {
            $this->nullable = true;
            return 1;
        }
        return false;
    }

    public function get_var(string $sql): int
    {
        sc_customer_nullable_assert(str_contains($sql, 'WHERE customer_id IS NULL'), 'rollback checks for supplier-compatible NULL customer_id rows');
        return $this->nullRows;
    }
}

$migration = new Migration0019NullableLegacyCustomer();
sc_customer_nullable_assert($migration instanceof ProductionMigration, 'migration is protected by ProductionMigration contract');
$wpdb = new SC_CustomerNullableWpdb(false);
$migration->preflight($wpdb);
$migration->up($wpdb);
sc_customer_nullable_assert($wpdb->nullable, 'legacy NOT NULL customer_id is relaxed');
$migration->verify($wpdb);
sc_customer_nullable_assert(count($wpdb->queries) === 1, 'migration performs exactly one ALTER for legacy schema');
$migration->up($wpdb);
sc_customer_nullable_assert(count($wpdb->queries) === 1, 'migration is idempotent when customer_id is already nullable');
$migration->rollback($wpdb);
sc_customer_nullable_assert(! $wpdb->nullable, 'best-effort rollback restores NOT NULL when no NULL rows exist');

$alreadyNullable = new SC_CustomerNullableWpdb(true);
$idempotent = new Migration0019NullableLegacyCustomer();
$idempotent->preflight($alreadyNullable);
$idempotent->up($alreadyNullable);
$idempotent->verify($alreadyNullable);
$idempotent->rollback($alreadyNullable);
sc_customer_nullable_assert($alreadyNullable->queries === [], 'pre-existing nullable schema is never tightened by rollback');

$withSupplierRows = new SC_CustomerNullableWpdb(false);
$guardedRollback = new Migration0019NullableLegacyCustomer();
$guardedRollback->preflight($withSupplierRows);
$guardedRollback->up($withSupplierRows);
$withSupplierRows->nullRows = 1;
try {
    $guardedRollback->rollback($withSupplierRows);
    sc_customer_nullable_assert(false, 'rollback must refuse to tighten customer_id after supplier rows exist');
} catch (RuntimeException $error) {
    sc_customer_nullable_assert(str_contains($error->getMessage(), 'verified pre-deployment backup'), 'rollback refusal explicitly requires backup restore');
}
sc_customer_nullable_assert($withSupplierRows->nullable, 'unsafe rollback leaves nullable schema intact');

$missingCounterparty = new SC_CustomerNullableWpdb(false);
$missingCounterparty->includeCounterparty = false;
try {
    (new Migration0019NullableLegacyCustomer())->preflight($missingCounterparty);
    sc_customer_nullable_assert(false, 'preflight rejects incomplete counterparty schema');
} catch (RuntimeException) {
    sc_customer_nullable_assert(true, 'incomplete counterparty schema fails before mutation');
}
sc_customer_nullable_assert($missingCounterparty->queries === [], 'failed preflight performs no mutation');

$unexpectedType = new SC_CustomerNullableWpdb(false);
$unexpectedType->customerType = 'int unsigned';
try {
    (new Migration0019NullableLegacyCustomer())->preflight($unexpectedType);
    sc_customer_nullable_assert(false, 'preflight rejects unexpected customer_id type');
} catch (RuntimeException) {
    sc_customer_nullable_assert(true, 'unexpected legacy type fails closed');
}

$migratorSource = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
sc_customer_nullable_assert(str_contains($migratorSource, "LATEST_VERSION = '1.18.0'"), 'Migrator latest version advances to 1.18.0');
sc_customer_nullable_assert(str_contains($migratorSource, "'1.18.0' => Migration0019NullableLegacyCustomer::class"), 'Migrator registers the guarded migration');

fwrite(STDOUT, "Supplier contract customer_id compatibility #605 passed ({$tests} assertions).\n");
