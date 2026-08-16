<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantScope;

final class ContractHistoryRepository
{
    /** @param array<string, mixed> $snapshot */
    public function append(int $contractId, string $eventType, ?int $actorUserId, array $snapshot): int
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contract_history';
        $tenantId = CoreTenantScope::tenantId();
        $this->assertOwnedContract($wpdb, $contractId, $tenantId);
        $snapshotJson = json_encode(
            $snapshot,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        if ($tenantId === null && $actorUserId === null) {
            $sql = $wpdb->prepare(
                "INSERT INTO {$table} (contract_id, event_type, actor_user_id, snapshot_json, created_at)
                 VALUES (%d, %s, NULL, %s, UTC_TIMESTAMP())",
                $contractId,
                $eventType,
                $snapshotJson
            );
        } elseif ($tenantId === null) {
            $sql = $wpdb->prepare(
                "INSERT INTO {$table} (contract_id, event_type, actor_user_id, snapshot_json, created_at)
                 VALUES (%d, %s, %d, %s, UTC_TIMESTAMP())",
                $contractId,
                $eventType,
                $actorUserId,
                $snapshotJson
            );
        } elseif ($actorUserId === null) {
            $sql = $wpdb->prepare(
                "INSERT INTO {$table} (tenant_id, contract_id, event_type, actor_user_id, snapshot_json, created_at)
                 VALUES (%d, %d, %s, NULL, %s, UTC_TIMESTAMP())",
                $tenantId,
                $contractId,
                $eventType,
                $snapshotJson
            );
        } else {
            $sql = $wpdb->prepare(
                "INSERT INTO {$table} (tenant_id, contract_id, event_type, actor_user_id, snapshot_json, created_at)
                 VALUES (%d, %d, %s, %d, %s, UTC_TIMESTAMP())",
                $tenantId,
                $contractId,
                $eventType,
                $actorUserId,
                $snapshotJson
            );
        }

        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to append contract history.');
        }

        return (int) $wpdb->insert_id;
    }

    /** @return list<array{id:int, contract_id:int, event_type:string, actor_user_id:?int, snapshot:array<string,mixed>, created_at:string}> */
    public function forContract(int $contractId, int $limit = 100): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contract_history';
        $limit = max(1, min(500, $limit));
        $tenant = $this->tenantCondition();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, contract_id, event_type, actor_user_id, snapshot_json, created_at
                 FROM {$table}
                 WHERE contract_id = %d{$tenant}
                 ORDER BY created_at DESC, id DESC
                 LIMIT %d",
                $contractId,
                $limit
            ),
            ARRAY_A
        );

        $history = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $snapshot = json_decode((string) ($row['snapshot_json'] ?? '{}'), true);
            $history[] = [
                'id' => (int) ($row['id'] ?? 0),
                'contract_id' => (int) ($row['contract_id'] ?? 0),
                'event_type' => (string) ($row['event_type'] ?? ''),
                'actor_user_id' => isset($row['actor_user_id']) && $row['actor_user_id'] !== null
                    ? (int) $row['actor_user_id']
                    : null,
                'snapshot' => is_array($snapshot) ? $snapshot : [],
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $history;
    }

    private function tenantCondition(string $column = 'tenant_id'): string
    {
        $tenantId = CoreTenantScope::tenantId();
        return $tenantId === null ? '' : ' AND ' . $column . ' = ' . $tenantId;
    }

    private function assertOwnedContract(object $wpdb, int $contractId, ?int $tenantId): void
    {
        if ($tenantId === null) {
            return;
        }
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$contracts} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $contractId,
            $tenantId
        ), ARRAY_A);
        if (! is_array($rows) || $rows === []) {
            throw new RuntimeException('Contract history target is outside the current Enterprise tenant.');
        }
    }

    private function assertWpdb(mixed $wpdb): void
    {
        if (! is_object($wpdb)) {
            throw new RuntimeException('SafeContracts contract history requires WordPress $wpdb.');
        }
    }
}
