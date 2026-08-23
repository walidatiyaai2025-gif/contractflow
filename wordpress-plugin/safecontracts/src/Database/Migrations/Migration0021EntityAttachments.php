<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use RuntimeException;
use SafeContracts\Database\ProductionMigration;

/**
 * Adds one governed attachment-link table shared by contracts, scheduled
 * payments and settlement/collection records. Existing contract attachments
 * and the legacy single collection proof are copied forward without deleting
 * their compatibility fields/tables.
 */
final class Migration0021EntityAttachments implements ProductionMigration
{
    public function preflight(object $wpdb): void
    {
        foreach ([
            $wpdb->prefix . 'safecontracts_contracts',
            $wpdb->prefix . 'safecontracts_scheduled_payments',
            $wpdb->prefix . 'safecontracts_payment_collections',
        ] as $table) {
            $rows = $wpdb->get_results("SELECT id FROM {$table} ORDER BY id ASC LIMIT 1", ARRAY_A);
            if (! is_array($rows)) {
                throw new RuntimeException('SafeContracts attachment migration prerequisite is unavailable: ' . $table);
            }
            $this->assertNoDatabaseError($wpdb, 'SafeContracts attachment migration preflight failed.');
        }
    }

    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $attachments = $wpdb->prefix . 'safecontracts_entity_attachments';
        $contractAttachments = $wpdb->prefix . 'safecontracts_contract_attachments';
        $collections = $wpdb->prefix . 'safecontracts_payment_collections';

        dbDelta("CREATE TABLE {$attachments} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_type varchar(20) NOT NULL,
            entity_id bigint(20) unsigned NOT NULL,
            media_id bigint(20) unsigned NOT NULL,
            label varchar(191) NULL,
            display_order int(11) unsigned NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY entity_media (entity_type, entity_id, media_id),
            KEY entity_order (entity_type, entity_id, display_order, id),
            KEY media_id (media_id)
        ) {$charset};");
        $this->assertNoDatabaseError($wpdb, 'SafeContracts could not create the entity attachment table.');

        $result = $wpdb->query(
            "INSERT IGNORE INTO {$attachments}
                (entity_type, entity_id, media_id, label, display_order, created_by, created_at)
             SELECT 'contract', contract_id, media_id, label, 0, created_by, created_at
             FROM {$contractAttachments}
             WHERE contract_id > 0 AND media_id > 0"
        );
        if ($result === false) {
            throw new RuntimeException('SafeContracts could not migrate existing contract attachments.');
        }

        $result = $wpdb->query(
            "INSERT IGNORE INTO {$attachments}
                (entity_type, entity_id, media_id, label, display_order, created_by, created_at)
             SELECT 'collection', id, proof_media_id, NULL, 0, created_by, created_at
             FROM {$collections}
             WHERE proof_media_id IS NOT NULL AND proof_media_id > 0"
        );
        if ($result === false) {
            throw new RuntimeException('SafeContracts could not migrate existing collection proof attachments.');
        }
    }

    public function verify(object $wpdb): void
    {
        $attachments = $wpdb->prefix . 'safecontracts_entity_attachments';
        $rows = $wpdb->get_results(
            "SELECT entity_type, entity_id, media_id FROM {$attachments} ORDER BY id ASC LIMIT 1",
            ARRAY_A
        );
        if (! is_array($rows)) {
            throw new RuntimeException('SafeContracts entity attachment table verification failed.');
        }
        $this->assertNoDatabaseError($wpdb, 'SafeContracts entity attachment table verification failed.');
    }

    public function rollback(object $wpdb): void
    {
        $attachments = $wpdb->prefix . 'safecontracts_entity_attachments';
        $result = $wpdb->query("DROP TABLE IF EXISTS {$attachments}");
        if ($result === false) {
            throw new RuntimeException('SafeContracts could not remove the entity attachment table during rollback.');
        }
    }

    private function assertNoDatabaseError(object $wpdb, string $message): void
    {
        if (property_exists($wpdb, 'last_error') && trim((string) $wpdb->last_error) !== '') {
            throw new RuntimeException($message . ' ' . trim((string) $wpdb->last_error));
        }
    }
}
