<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0013SafeDeletion implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $collections = $wpdb->prefix . 'safecontracts_payment_collections';

        dbDelta("CREATE TABLE {$payments} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            contract_id bigint(20) unsigned NOT NULL,
            sequence_no int(11) unsigned NOT NULL,
            reference varchar(100) NULL,
            due_date date NOT NULL,
            expected_payment_date date NULL,
            original_amount decimal(20,4) NOT NULL DEFAULT 0.0000,
            paid_amount decimal(20,4) NOT NULL DEFAULT 0.0000,
            remaining_amount decimal(20,4) NOT NULL DEFAULT 0.0000,
            status varchar(32) NOT NULL DEFAULT 'upcoming',
            is_archived tinyint(1) NOT NULL DEFAULT 0,
            archived_by bigint(20) unsigned NULL,
            archived_at datetime NULL,
            followup_notes longtext NULL,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY contract_sequence (contract_id, sequence_no),
            KEY contract_status_due (contract_id, status, due_date),
            KEY due_status (due_date, status),
            KEY expected_date (expected_payment_date),
            KEY archived_due (is_archived, due_date, id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$collections} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            payment_id bigint(20) unsigned NOT NULL,
            amount decimal(20,4) NOT NULL,
            collection_date date NOT NULL,
            payment_method_id bigint(20) unsigned NOT NULL,
            reference varchar(191) NULL,
            details text NULL,
            proof_media_id bigint(20) unsigned NULL,
            is_archived tinyint(1) NOT NULL DEFAULT 0,
            archived_by bigint(20) unsigned NULL,
            archived_at datetime NULL,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY payment_date (payment_id, collection_date, id),
            KEY method_date (payment_method_id, collection_date, id),
            KEY collection_date (collection_date, id),
            KEY proof_media (proof_media_id),
            KEY archived_payment_date (is_archived, payment_id, collection_date, id)
        ) {$charset};");
    }
}
