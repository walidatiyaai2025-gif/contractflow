<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

use RuntimeException;

final class ContractFinancialRepository
{
    public function createItem(
        int $contractId,
        string $type,
        string $description,
        string $amount,
        int $displayOrder,
        int $actorId
    ): int {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contract_financial_items';
        $sql = $wpdb->prepare(
            "INSERT INTO {$table}
                (contract_id, item_type, description, amount, display_order, is_active, created_by, updated_by, created_at, updated_at)
             VALUES (%d, %s, %s, %s, %d, 1, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            $contractId,
            $type,
            $description,
            $amount,
            $displayOrder,
            $actorId,
            $actorId
        );

        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to add contract financial item.');
        }

        return (int) $wpdb->insert_id;
    }

    public function deactivateItem(int $contractId, int $itemId, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contract_financial_items';
        $sql = $wpdb->prepare(
            "UPDATE {$table}
             SET is_active = 0, updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE id = %d AND contract_id = %d AND is_active = 1",
            $actorId,
            $itemId,
            $contractId
        );
        $this->executeMutation($wpdb, $sql, 'Unable to deactivate contract financial item.');
    }

    /** @return array{line:string, addition:string, discount:string} */
    public function totals(int $contractId): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contract_financial_items';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT item_type, COALESCE(SUM(amount), 0.0000) AS total
                 FROM {$table}
                 WHERE contract_id = %d AND is_active = 1
                 GROUP BY item_type",
                $contractId
            ),
            ARRAY_A
        );

        $totals = [
            FinancialItemType::LINE => '0.0000',
            FinancialItemType::ADDITION => '0.0000',
            FinancialItemType::DISCOUNT => '0.0000',
        ];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $type = (string) ($row['item_type'] ?? '');
            if (! array_key_exists($type, $totals)) {
                continue;
            }
            $totals[$type] = DecimalAmount::normalize($row['total'] ?? '0');
        }

        return $totals;
    }

    public function attachMedia(int $contractId, int $mediaId, string $label, int $actorId): int
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contract_attachments';
        $sql = $wpdb->prepare(
            "INSERT INTO {$table}
                (contract_id, media_id, label, is_active, created_by, updated_by, created_at, updated_at)
             VALUES (%d, %d, %s, 1, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE label = VALUES(label), is_active = 1, updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)",
            $contractId,
            $mediaId,
            $label,
            $actorId,
            $actorId
        );

        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to attach contract media.');
        }

        return (int) $wpdb->insert_id;
    }

    public function detachMedia(int $contractId, int $mediaId, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contract_attachments';
        $sql = $wpdb->prepare(
            "UPDATE {$table}
             SET is_active = 0, updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE contract_id = %d AND media_id = %d AND is_active = 1",
            $actorId,
            $contractId,
            $mediaId
        );
        $this->executeMutation($wpdb, $sql, 'Unable to detach contract media.');
    }

    private function assertWpdb(mixed $wpdb): void
    {
        if (! is_object($wpdb)) {
            throw new RuntimeException('SafeContracts contract finance requires WordPress $wpdb.');
        }
    }

    private function executeMutation(object $wpdb, string $sql, string $message): void
    {
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException($message);
        }
    }
}
