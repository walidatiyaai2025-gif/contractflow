<?php

declare(strict_types=1);

namespace SafeContracts\Attachments;

use RuntimeException;

final class EntityAttachmentRepository
{
    /** @return list<array{id:int,entity_type:string,entity_id:int,media_id:int,label:string,display_order:int,created_by:?int,created_at:string}> */
    public function allFor(string $entityType, int $entityId): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_entity_attachments';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, entity_type, entity_id, media_id, label, display_order, created_by, created_at
             FROM {$table}
             WHERE entity_type = %s AND entity_id = %d
             ORDER BY display_order ASC, id ASC",
            $entityType,
            $entityId
        ), ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }
        return array_map([$this, 'normalize'], $rows);
    }

    /** @param list<int> $entityIds @return array<int,list<array{id:int,entity_type:string,entity_id:int,media_id:int,label:string,display_order:int,created_by:?int,created_at:string}>> */
    public function allForMany(string $entityType, array $entityIds): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $ids = array_values(array_unique(array_filter(array_map('intval', $entityIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $table = $wpdb->prefix . 'safecontracts_entity_attachments';
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = $wpdb->prepare(
            "SELECT id, entity_type, entity_id, media_id, label, display_order, created_by, created_at
             FROM {$table}
             WHERE entity_type = %s AND entity_id IN ({$placeholders})
             ORDER BY entity_id ASC, display_order ASC, id ASC",
            $entityType,
            ...$ids
        );
        $rows = $wpdb->get_results($sql, ARRAY_A);
        $grouped = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $normalized = $this->normalize($row);
            $grouped[$normalized['entity_id']][] = $normalized;
        }
        return $grouped;
    }

    public function attach(string $entityType, int $entityId, int $mediaId, string $label, int $displayOrder, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_entity_attachments';
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table}
                (entity_type, entity_id, media_id, label, display_order, created_by, created_at)
             VALUES (%s, %d, %d, %s, %d, %d, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE label = VALUES(label), display_order = VALUES(display_order)",
            $entityType,
            $entityId,
            $mediaId,
            $label,
            max(0, $displayOrder),
            $actorId
        ));
        if ($result === false) {
            throw new RuntimeException('Unable to attach document to SafeContracts entity.');
        }
    }

    public function detach(string $entityType, int $entityId, int $mediaId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_entity_attachments';
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE entity_type = %s AND entity_id = %d AND media_id = %d",
            $entityType,
            $entityId,
            $mediaId
        ));
        if ($result === false) {
            throw new RuntimeException('Unable to detach document from SafeContracts entity.');
        }
    }

    /** @return array{accountant_user_id:?int,entity_is_archived:bool,parent_is_archived:bool}|null */
    public function entityContext(string $entityType, int $entityId): ?array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $collections = $wpdb->prefix . 'safecontracts_payment_collections';

        if ($entityType === EntityAttachmentService::CONTRACT) {
            $sql = $wpdb->prepare(
                "SELECT accountant_user_id, is_archived AS entity_is_archived, 0 AS parent_is_archived
                 FROM {$contracts} WHERE id = %d LIMIT 1",
                $entityId
            );
        } elseif ($entityType === EntityAttachmentService::PAYMENT) {
            $sql = $wpdb->prepare(
                "SELECT c.accountant_user_id, p.is_archived AS entity_is_archived, c.is_archived AS parent_is_archived
                 FROM {$payments} p
                 INNER JOIN {$contracts} c ON c.id = p.contract_id
                 WHERE p.id = %d LIMIT 1",
                $entityId
            );
        } elseif ($entityType === EntityAttachmentService::COLLECTION) {
            $sql = $wpdb->prepare(
                "SELECT c.accountant_user_id, col.is_archived AS entity_is_archived,
                        (p.is_archived OR c.is_archived) AS parent_is_archived
                 FROM {$collections} col
                 INNER JOIN {$payments} p ON p.id = col.payment_id
                 INNER JOIN {$contracts} c ON c.id = p.contract_id
                 WHERE col.id = %d LIMIT 1",
                $entityId
            );
        } else {
            return null;
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! is_array($rows) || $rows === []) {
            return null;
        }
        $row = $rows[0];
        return [
            'accountant_user_id' => isset($row['accountant_user_id']) && $row['accountant_user_id'] !== null
                ? (int) $row['accountant_user_id']
                : null,
            'entity_is_archived' => ! empty($row['entity_is_archived']),
            'parent_is_archived' => ! empty($row['parent_is_archived']),
        ];
    }

    public function setLegacyCollectionProof(int $collectionId, ?int $mediaId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_payment_collections';
        if ($mediaId === null) {
            $sql = $wpdb->prepare("UPDATE {$table} SET proof_media_id = NULL, updated_at = UTC_TIMESTAMP() WHERE id = %d", $collectionId);
        } else {
            $sql = $wpdb->prepare("UPDATE {$table} SET proof_media_id = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d", $mediaId, $collectionId);
        }
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to synchronize legacy collection proof reference.');
        }
    }

    public function syncLegacyContractAttachment(int $contractId, int $mediaId, string $label, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contract_attachments';
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (contract_id, media_id, label, created_by, created_at)
             VALUES (%d, %d, %s, %d, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE label = VALUES(label)",
            $contractId,
            $mediaId,
            $label,
            $actorId
        ));
        if ($result === false) {
            throw new RuntimeException('Unable to synchronize legacy contract attachment reference.');
        }
    }

    public function detachLegacyContractAttachment(int $contractId, int $mediaId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contract_attachments';
        if ($wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE contract_id = %d AND media_id = %d",
            $contractId,
            $mediaId
        )) === false) {
            throw new RuntimeException('Unable to detach legacy contract attachment reference.');
        }
    }

    /** @return array{id:int,entity_type:string,entity_id:int,media_id:int,label:string,display_order:int,created_by:?int,created_at:string} */
    private function normalize(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'entity_type' => (string) ($row['entity_type'] ?? ''),
            'entity_id' => (int) ($row['entity_id'] ?? 0),
            'media_id' => (int) ($row['media_id'] ?? 0),
            'label' => (string) ($row['label'] ?? ''),
            'display_order' => (int) ($row['display_order'] ?? 0),
            'created_by' => isset($row['created_by']) && $row['created_by'] !== null ? (int) $row['created_by'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    private function assertWpdb(mixed $wpdb): void
    {
        if (! is_object($wpdb) || ! method_exists($wpdb, 'prepare') || ! method_exists($wpdb, 'query') || ! method_exists($wpdb, 'get_results')) {
            throw new RuntimeException('SafeContracts entity attachments require WordPress $wpdb.');
        }
    }
}
