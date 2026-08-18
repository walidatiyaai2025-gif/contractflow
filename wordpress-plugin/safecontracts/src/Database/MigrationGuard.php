<?php

declare(strict_types=1);

namespace SafeContracts\Database;

use RuntimeException;
use Throwable;

final class MigrationGuard
{
    public const LOCK_OPTION = 'safecontracts_migration_lock';
    public const JOURNAL_OPTION = 'safecontracts_migration_journal';
    public const FAILURE_OPTION = 'safecontracts_migration_failure';
    public const LOCK_TTL_SECONDS = 900;
    public const JOURNAL_LIMIT = 25;

    private ?string $lockToken = null;

    public function assertDatabaseCompatible(string $currentVersion, string $latestVersion): void
    {
        if (version_compare($currentVersion, $latestVersion, '>')) {
            $message = sprintf(
                'SafeContracts database version %s is newer than plugin schema %s. Refusing to run an older plugin against a newer database.',
                $currentVersion,
                $latestVersion
            );
            $this->recordFailure([
                'stage' => 'compatibility',
                'from_version' => $currentVersion,
                'to_version' => $latestVersion,
                'message' => $message,
            ]);
            throw new RuntimeException($message);
        }
    }

    public function acquire(): void
    {
        if ($this->lockToken !== null) {
            throw new RuntimeException('SafeContracts migration lock is already held by this process.');
        }

        $existing = get_option(self::LOCK_OPTION, null);
        if (is_array($existing) && ! $this->isStaleLock($existing)) {
            throw new RuntimeException('Another SafeContracts database migration is already in progress.');
        }

        if ($existing !== null && $existing !== false) {
            $this->deleteOption(self::LOCK_OPTION);
        }

        $token = bin2hex(random_bytes(16));
        $lock = [
            'token' => $token,
            'acquired_at' => time(),
            'acquired_at_utc' => gmdate('c'),
        ];

        if (! $this->createLock($lock)) {
            throw new RuntimeException('SafeContracts could not acquire the database migration lock.');
        }

        $this->lockToken = $token;
    }

    public function release(): void
    {
        if ($this->lockToken === null) {
            return;
        }

        $existing = get_option(self::LOCK_OPTION, null);
        if (is_array($existing) && hash_equals($this->lockToken, (string) ($existing['token'] ?? ''))) {
            $this->deleteOption(self::LOCK_OPTION);
        }
        $this->lockToken = null;
    }

    /**
     * @param callable():void $operation
     */
    public function withLock(callable $operation): void
    {
        $this->acquire();
        try {
            $operation();
        } finally {
            $this->release();
        }
    }

    public function startMigration(string $fromVersion, string $toVersion, string $migrationClass): string
    {
        $runId = gmdate('Ymd\THis\Z') . '-' . substr(hash('sha256', $migrationClass . '|' . $toVersion . '|' . microtime(true)), 0, 12);
        $this->appendJournal([
            'run_id' => $runId,
            'status' => 'running',
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'migration' => $migrationClass,
            'started_at' => gmdate('c'),
            'completed_at' => null,
            'rollback_status' => 'not_required',
        ]);
        return $runId;
    }

    public function markSucceeded(string $runId, string $fromVersion, string $toVersion, string $migrationClass): void
    {
        $this->appendJournal([
            'run_id' => $runId,
            'status' => 'succeeded',
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'migration' => $migrationClass,
            'started_at' => null,
            'completed_at' => gmdate('c'),
            'rollback_status' => 'not_required',
        ]);
        $this->deleteOption(self::FAILURE_OPTION);
    }

    public function markFailed(
        string $runId,
        string $fromVersion,
        string $toVersion,
        string $migrationClass,
        Throwable $error,
        string $rollbackStatus
    ): void {
        $entry = [
            'run_id' => $runId,
            'status' => 'failed',
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'migration' => $migrationClass,
            'started_at' => null,
            'completed_at' => gmdate('c'),
            'rollback_status' => $rollbackStatus,
            'error_type' => $error::class,
            'message' => $this->sanitizeMessage($error->getMessage()),
        ];
        $this->appendJournal($entry);
        $this->recordFailure($entry);
    }

    /** @return array<string,mixed>|null */
    public static function failureState(): ?array
    {
        $value = get_option(self::FAILURE_OPTION, null);
        return is_array($value) ? $value : null;
    }

    /** @param array<string,mixed> $lock */
    private function isStaleLock(array $lock): bool
    {
        $acquiredAt = (int) ($lock['acquired_at'] ?? 0);
        return $acquiredAt <= 0 || (time() - $acquiredAt) > self::LOCK_TTL_SECONDS;
    }

    /** @param array<string,mixed> $lock */
    private function createLock(array $lock): bool
    {
        // WordPress add_option() is an atomic INSERT guarded by the unique
        // option_name key, so production receives a true single-writer lock.
        if (function_exists('add_option')) {
            return add_option(self::LOCK_OPTION, $lock, '', false);
        }

        // Minimal test-harness fallback. WordPress production never uses this
        // branch; it exists so isolated unit tests without the full option API
        // can exercise migration behavior.
        if (get_option(self::LOCK_OPTION, null) !== null) {
            return false;
        }
        return update_option(self::LOCK_OPTION, $lock, false);
    }

    private function deleteOption(string $option): void
    {
        if (function_exists('delete_option')) {
            delete_option($option);
            return;
        }
        update_option($option, null, false);
    }

    /** @param array<string,mixed> $entry */
    private function appendJournal(array $entry): void
    {
        $journal = get_option(self::JOURNAL_OPTION, []);
        if (! is_array($journal)) {
            $journal = [];
        }
        $journal[] = $entry;
        if (count($journal) > self::JOURNAL_LIMIT) {
            $journal = array_slice($journal, -self::JOURNAL_LIMIT);
        }
        update_option(self::JOURNAL_OPTION, $journal, false);
    }

    /** @param array<string,mixed> $failure */
    private function recordFailure(array $failure): void
    {
        $failure['recorded_at'] = gmdate('c');
        update_option(self::FAILURE_OPTION, $failure, false);
    }

    private function sanitizeMessage(string $message): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', ' ', trim($message));
        if (! is_string($clean) || $clean === '') {
            return 'Database migration failed. Review the migration journal and production rollback runbook.';
        }
        return substr($clean, 0, 500);
    }
}
