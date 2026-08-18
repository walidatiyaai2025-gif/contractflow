<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (! function_exists('add_option')) {
    function add_option(string $key, mixed $value = '', string $deprecated = '', mixed $autoload = true): bool
    {
        unset($deprecated, $autoload);
        if (array_key_exists($key, $GLOBALS['sc_test_options'])) {
            return false;
        }
        $GLOBALS['sc_test_options'][$key] = $value;
        return true;
    }
}
if (! function_exists('delete_option')) {
    function delete_option(string $key): bool
    {
        if (! array_key_exists($key, $GLOBALS['sc_test_options'])) {
            return false;
        }
        unset($GLOBALS['sc_test_options'][$key]);
        return true;
    }
}

require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migration;
use SafeContracts\Database\MigrationGuard;
use SafeContracts\Database\Migrator;
use SafeContracts\Database\ProductionMigration;

$tests = 0;
function sc_prod_migration_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

final class SC_Prod_SuccessMigration implements ProductionMigration
{
    public static array $calls = [];
    public function preflight(object $wpdb): void { unset($wpdb); self::$calls[] = 'preflight'; }
    public function up(object $wpdb): void { unset($wpdb); self::$calls[] = 'up'; }
    public function verify(object $wpdb): void { unset($wpdb); self::$calls[] = 'verify'; }
    public function rollback(object $wpdb): void { unset($wpdb); self::$calls[] = 'rollback'; }
}

final class SC_Prod_VerifyFailureMigration implements ProductionMigration
{
    public static array $calls = [];
    public function preflight(object $wpdb): void { unset($wpdb); self::$calls[] = 'preflight'; }
    public function up(object $wpdb): void { unset($wpdb); self::$calls[] = 'up'; }
    public function verify(object $wpdb): void { unset($wpdb); self::$calls[] = 'verify'; throw new RuntimeException('verification failed'); }
    public function rollback(object $wpdb): void { unset($wpdb); self::$calls[] = 'rollback'; }
}

final class SC_Prod_RollbackFailureMigration implements ProductionMigration
{
    public function preflight(object $wpdb): void { unset($wpdb); }
    public function up(object $wpdb): void { unset($wpdb); throw new RuntimeException('mutation failed'); }
    public function verify(object $wpdb): void { unset($wpdb); }
    public function rollback(object $wpdb): void { unset($wpdb); throw new RuntimeException('rollback failed'); }
}

final class SC_Prod_UnguardedMigration implements Migration
{
    public static int $upCalls = 0;
    public function up(object $wpdb): void { unset($wpdb); self::$upCalls++; }
}

function sc_prod_reset(string $version = '1.17.0'): void
{
    $GLOBALS['sc_test_options'] = [Migrator::VERSION_OPTION => $version];
    SC_Prod_SuccessMigration::$calls = [];
    SC_Prod_VerifyFailureMigration::$calls = [];
    SC_Prod_UnguardedMigration::$upCalls = 0;
}

sc_prod_reset();
(new Migrator(['1.18.0' => SC_Prod_SuccessMigration::class], new MigrationGuard(), '1.18.0'))->migrate();
sc_prod_migration_assert(get_option(Migrator::VERSION_OPTION) === '1.18.0', 'version advances only after guarded migration succeeds');
sc_prod_migration_assert(SC_Prod_SuccessMigration::$calls === ['preflight', 'up', 'verify'], 'guarded migration runs preflight, up and verify in order');
sc_prod_migration_assert(get_option(MigrationGuard::LOCK_OPTION, null) === null, 'migration lock is released after success');
sc_prod_migration_assert(MigrationGuard::failureState() === null, 'successful migration clears prior failure state');
$journal = get_option(MigrationGuard::JOURNAL_OPTION, []);
sc_prod_migration_assert(is_array($journal) && ($journal[array_key_last($journal)]['status'] ?? '') === 'succeeded', 'migration success is journaled');

sc_prod_reset();
try {
    (new Migrator(['1.18.0' => SC_Prod_VerifyFailureMigration::class], new MigrationGuard(), '1.18.0'))->migrate();
    sc_prod_migration_assert(false, 'verification failure must throw');
} catch (RuntimeException) {
    sc_prod_migration_assert(true, 'verification failure is fail-closed');
}
sc_prod_migration_assert(get_option(Migrator::VERSION_OPTION) === '1.17.0', 'failed verification never advances database version');
sc_prod_migration_assert(SC_Prod_VerifyFailureMigration::$calls === ['preflight', 'up', 'verify', 'rollback'], 'failed verification invokes rollback');
$failure = MigrationGuard::failureState();
sc_prod_migration_assert(($failure['rollback_status'] ?? '') === 'succeeded', 'successful rollback is recorded');
sc_prod_migration_assert(get_option(MigrationGuard::LOCK_OPTION, null) === null, 'migration lock is released after failure');

sc_prod_reset();
try {
    (new Migrator(['1.18.0' => SC_Prod_RollbackFailureMigration::class], new MigrationGuard(), '1.18.0'))->migrate();
    sc_prod_migration_assert(false, 'rollback failure path must throw');
} catch (RuntimeException) {
    sc_prod_migration_assert(true, 'rollback failure remains fail-closed');
}
$failure = MigrationGuard::failureState();
sc_prod_migration_assert(($failure['rollback_status'] ?? '') === 'failed_restore_backup_required', 'rollback failure explicitly requires backup restore');
sc_prod_migration_assert(get_option(Migrator::VERSION_OPTION) === '1.17.0', 'rollback failure still leaves version marker unchanged');

sc_prod_reset();
try {
    (new Migrator(['1.18.0' => SC_Prod_UnguardedMigration::class], new MigrationGuard(), '1.18.0'))->migrate();
    sc_prod_migration_assert(false, 'post-baseline migration without ProductionMigration must be rejected');
} catch (RuntimeException) {
    sc_prod_migration_assert(true, 'post-baseline rollback contract is mandatory');
}
sc_prod_migration_assert(SC_Prod_UnguardedMigration::$upCalls === 0, 'unguarded future migration is rejected before mutation');
sc_prod_migration_assert(get_option(Migrator::VERSION_OPTION) === '1.17.0', 'unguarded migration cannot advance version');

sc_prod_reset('1.19.0');
try {
    (new Migrator([], new MigrationGuard(), '1.18.0'))->maybeMigrate();
    sc_prod_migration_assert(false, 'older plugin must refuse newer database schema');
} catch (RuntimeException) {
    sc_prod_migration_assert(true, 'newer database compatibility guard is enforced');
}
sc_prod_migration_assert((MigrationGuard::failureState()['stage'] ?? '') === 'compatibility', 'newer database refusal leaves recovery evidence');

sc_prod_reset();
update_option(MigrationGuard::LOCK_OPTION, [
    'token' => 'another-request',
    'acquired_at' => time(),
    'acquired_at_utc' => gmdate('c'),
], false);
try {
    (new Migrator(['1.18.0' => SC_Prod_SuccessMigration::class], new MigrationGuard(), '1.18.0'))->migrate();
    sc_prod_migration_assert(false, 'concurrent migration lock must block a second writer');
} catch (RuntimeException) {
    sc_prod_migration_assert(true, 'concurrent migration is blocked');
}
sc_prod_migration_assert(get_option(Migrator::VERSION_OPTION) === '1.17.0', 'blocked concurrent migration does not mutate version');

sc_prod_reset();
update_option(MigrationGuard::LOCK_OPTION, [
    'token' => 'stale-request',
    'acquired_at' => time() - MigrationGuard::LOCK_TTL_SECONDS - 5,
    'acquired_at_utc' => gmdate('c', time() - MigrationGuard::LOCK_TTL_SECONDS - 5),
], false);
(new Migrator(['1.18.0' => SC_Prod_SuccessMigration::class], new MigrationGuard(), '1.18.0'))->migrate();
sc_prod_migration_assert(get_option(Migrator::VERSION_OPTION) === '1.18.0', 'explicitly stale migration lock can be recovered');
sc_prod_migration_assert(get_option(MigrationGuard::LOCK_OPTION, null) === null, 'recovered stale lock is released after migration');

fwrite(STDOUT, "Alkenzy production migration guard #586 passed ({$tests} assertions).\n");
