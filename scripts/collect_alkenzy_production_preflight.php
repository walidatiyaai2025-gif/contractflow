<?php

declare(strict_types=1);

use SafeContracts\Database\MigrationGuard;
use SafeContracts\Database\Migrator;

/**
 * Read-only Alkenzy ADV / SafeContracts production preflight evidence collector.
 *
 * Intended usage from a WordPress root with WP-CLI:
 *   wp eval-file /path/to/collect_alkenzy_production_preflight.php > safecontracts-preflight.json
 *
 * Exit codes:
 *   0 = preflight state is clear for a controlled deployment window
 *   2 = WordPress / SafeContracts runtime is unavailable
 *   3 = a database compatibility, migration-lock or migration-failure blocker exists
 *
 * This script deliberately never acquires a migration lock and never writes,
 * updates or deletes WordPress options. It also never emits migration lock
 * tokens, credentials, Firebase material or authentication/session data.
 */

if (! defined('ABSPATH') || ! function_exists('get_option')) {
    fwrite(STDERR, "FAIL: run this collector inside a bootstrapped WordPress runtime (for example with wp eval-file).\n");
    exit(2);
}

if (! class_exists(Migrator::class) || ! class_exists(MigrationGuard::class)) {
    fwrite(STDERR, "FAIL: SafeContracts is not active or its database classes are unavailable.\n");
    exit(2);
}

/** @param mixed $value */
function safecontracts_preflight_scalar($value): ?string
{
    if (! is_scalar($value)) {
        return null;
    }

    $text = trim((string) $value);
    return $text === '' ? null : $text;
}

/** @param mixed $entry @return array<string,mixed>|null */
function safecontracts_preflight_journal_entry($entry): ?array
{
    if (! is_array($entry)) {
        return null;
    }

    $allowed = [
        'run_id',
        'status',
        'from_version',
        'to_version',
        'migration',
        'started_at',
        'completed_at',
        'rollback_status',
        'error_type',
    ];

    $result = [];
    foreach ($allowed as $key) {
        $value = safecontracts_preflight_scalar($entry[$key] ?? null);
        if ($value !== null) {
            $result[$key] = $value;
        }
    }

    return $result === [] ? null : $result;
}

$currentVersion = (string) get_option(Migrator::VERSION_OPTION, '0.0.0');
$targetVersion = Migrator::LATEST_VERSION;
$databaseCompatible = version_compare($currentVersion, $targetVersion, '<=');

$lock = get_option(MigrationGuard::LOCK_OPTION, null);
$lockPresent = is_array($lock);
$lockAcquiredAt = $lockPresent ? (int) ($lock['acquired_at'] ?? 0) : 0;
$lockAgeSeconds = $lockAcquiredAt > 0 ? max(0, time() - $lockAcquiredAt) : null;
$lockStale = $lockPresent
    ? ($lockAcquiredAt <= 0 || $lockAgeSeconds > MigrationGuard::LOCK_TTL_SECONDS)
    : false;
$activeLock = $lockPresent && ! $lockStale;

$failure = MigrationGuard::failureState();
$failureEvidence = null;
if (is_array($failure)) {
    $failureEvidence = [];
    foreach ([
        'stage',
        'run_id',
        'status',
        'from_version',
        'to_version',
        'migration',
        'rollback_status',
        'error_type',
        'recorded_at',
    ] as $key) {
        $value = safecontracts_preflight_scalar($failure[$key] ?? null);
        if ($value !== null) {
            $failureEvidence[$key] = $value;
        }
    }
}

$journal = get_option(MigrationGuard::JOURNAL_OPTION, []);
$journalTail = [];
if (is_array($journal)) {
    foreach (array_slice($journal, -5) as $entry) {
        $normalized = safecontracts_preflight_journal_entry($entry);
        if ($normalized !== null) {
            $journalTail[] = $normalized;
        }
    }
}

$blockingReasons = [];
if (! $databaseCompatible) {
    $blockingReasons[] = sprintf(
        'Database schema %s is newer than plugin target %s.',
        $currentVersion,
        $targetVersion
    );
}
if ($activeLock) {
    $blockingReasons[] = 'A non-stale SafeContracts migration lock is present.';
}
if ($failureEvidence !== null) {
    $blockingReasons[] = 'SafeContracts has unresolved migration failure evidence.';
}

$safeToStart = $blockingReasons === [];

$payload = [
    'schema_version' => 1,
    'generated_at_utc' => gmdate('c'),
    'site' => [
        'home_url' => function_exists('home_url') ? (string) home_url('/') : null,
        'wordpress_version' => function_exists('get_bloginfo') ? (string) get_bloginfo('version') : null,
        'php_version' => PHP_VERSION,
    ],
    'safecontracts' => [
        'plugin_version' => defined('SAFECONTRACTS_VERSION') ? (string) SAFECONTRACTS_VERSION : null,
        'database_version' => $currentVersion,
        'target_database_version' => $targetVersion,
        'database_compatible' => $databaseCompatible,
        'migration_lock' => [
            'present' => $lockPresent,
            'active' => $activeLock,
            'stale' => $lockStale,
            'acquired_at_utc' => $lockPresent ? safecontracts_preflight_scalar($lock['acquired_at_utc'] ?? null) : null,
            'age_seconds' => $lockAgeSeconds,
            // The lock token is intentionally never exported.
        ],
        'migration_failure' => $failureEvidence,
        'migration_journal_tail' => $journalTail,
    ],
    'preflight' => [
        'safe_to_start_deployment' => $safeToStart,
        'blocking_reasons' => $blockingReasons,
        'backup_evidence_required_separately' => true,
        'rollback_artifact_required_separately' => true,
    ],
];

$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
$json = function_exists('wp_json_encode')
    ? wp_json_encode($payload, $flags)
    : json_encode($payload, $flags);

if (! is_string($json)) {
    fwrite(STDERR, "FAIL: could not encode preflight evidence.\n");
    exit(2);
}

echo $json . PHP_EOL;
exit($safeToStart ? 0 : 3);
