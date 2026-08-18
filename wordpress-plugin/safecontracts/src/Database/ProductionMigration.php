<?php

declare(strict_types=1);

namespace SafeContracts\Database;

/**
 * Contract required for every schema/data migration introduced after the
 * production baseline. MySQL/WordPress DDL is not assumed to be transactional,
 * therefore every new migration must be able to preflight, verify and perform
 * a best-effort application rollback while the deployment runbook retains the
 * database backup as the authoritative disaster-recovery path.
 */
interface ProductionMigration extends Migration
{
    /**
     * Validate prerequisites before the first mutating statement is executed.
     */
    public function preflight(object $wpdb): void;

    /**
     * Prove that the intended post-migration state exists before the database
     * version marker is advanced.
     */
    public function verify(object $wpdb): void;

    /**
     * Best-effort application rollback for changes made by up().
     *
     * This does not replace restoring the verified pre-deployment database
     * backup when DDL/data loss cannot be reversed safely in-place.
     */
    public function rollback(object $wpdb): void;
}
