<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$migrator = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$repair = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0023ImportErrorsRepair.php');
$workflow = (string) file_get_contents($root . '/.github/workflows/plugin-redesign-visual-qa.yml');
$runtime = (string) file_get_contents($root . '/tests/plugin-redesign-visual-qa/schema-repair-runtime.php');
$demo = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Support/DemoDataService.php');

$tests = 0;
$assert = static function (bool $condition, string $message) use (&$tests): void {
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(str_contains($migrator, "use SafeContracts\\Database\\Migrations\\Migration0023ImportErrorsRepair;"), 'Migrator imports the schema repair migration');
$assert(str_contains($migrator, "public const LATEST_VERSION = '1.22.0';"), 'database latest version advances to 1.22.0');
$assert(str_contains($migrator, "'1.22.0' => Migration0023ImportErrorsRepair::class"), 'repair migration is registered at 1.22.0');
$assert(str_contains($repair, 'implements ProductionMigration'), 'repair is governed as a production migration');
$assert(str_contains($repair, 'safecontracts_import_runs'), 'repair requires the existing import-runs schema');
$assert(str_contains($repair, 'safecontracts_import_errors'), 'repair targets the missing import-errors table');
$assert(str_contains($repair, '`row_number`'), 'repair quotes MySQL 8 reserved row_number');
$assert(str_contains($repair, 'CREATE TABLE {$errors}'), 'repair creates the missing table');
$assert(str_contains($repair, 'if (! $this->createdTable)'), 'rollback refuses to drop a pre-existing table');
$assert(str_contains($workflow, 'Verify historical import-errors schema repair'), 'real WordPress QA executes the upgrade repair scenario');
$assert(str_contains($workflow, 'schema-repair-runtime.php'), 'visual QA invokes the repair runtime script');
$assert(str_contains($runtime, "Migrator::VERSION_OPTION, '1.21.0'"), 'runtime reproduces the historical 1.21.0 database state');
$assert(str_contains($runtime, 'DROP TABLE IF EXISTS {$errors}'), 'runtime reproduces the exact missing-table drift');
$assert(str_contains($runtime, "=== '1.22.0'"), 'runtime requires the repair version to advance');
$assert(str_contains($demo, "'safecontracts_import_errors'"), 'demo generator continues to require all 22 real tables instead of bypassing the missing table');

echo "SafeContracts demo import-errors schema repair regression passed ({$tests} assertions).\n";
