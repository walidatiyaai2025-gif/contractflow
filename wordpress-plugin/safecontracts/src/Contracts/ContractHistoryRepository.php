<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

use RuntimeException;

final class ContractHistoryRepository
{
    /** @param array<string, mixed> $context */
    public function record(int $contractId, string $action, ?int $actorUserId, array $context = []): int
    {
        global $wpdb;
        $this->assertWpdb($wpdb);

        $table = $wpdb->prefix . 'safecontracts_contract_history';
        $contextJson = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($contextJson === false) {
            throw new RuntimeException('Unable to encode contract history context.');
        }

        if ($actorUserId === null) {
            $sql = $wpdb->prepare(
                "INSERT INTO {$table} (contract_id, action, actor_user_id, context_json, created_at)
                 VALUES (%d, %s, NULL, %s, UTC_TIMESTAMP())",
                $contractId,
                $action,
                $contextJson
            );
        } else {
            $sql = $wpdb->prepare(
                "INSERT INTO {$table} (contract_id, action, actor_user_id, context_json, created_at)
                 VALUES (%d, %s, %d, %s, UTC_TIMESTAMP())",
                $contractId,
                $action,
                $actorUserId,
                $contextJson
            );
        }

        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to record contract history.');
        }

        return (int) $wpdb->insert_id;
    }

    /** @return list<array{id:int, action:string, actor_user_id:?int, context:array<string, mixed>, created_at:string}> */
    public function forContract(int $contractId, int $limit = 100): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);

        $limit = max(1, min(200, $limit));
        $table = $wpdb->prefix . 'safecontracts_contract_history';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, action, actor_user_id, context_json, created_at
                 FROM {$table}
                 WHERE contract_id = %d
                 ORDER BY created_at DESC, id DESC
                 LIMIT %d",
                $contractId,
                $limit
            ),
            ARRAY_A
        );

        if (! is_array($rows)) {
            return [];
        }

        return array_map(static function (array $row): array {
            $decoded = json_decode((string) ($row['context_json'] ?? '{}'), true);
            return [
                'id' => (int) ($row['id'] ?? 0),
                'action' => (string) ($row['action'] ?? ''),
                'actor_user_id' => isset($row['actor_user_id']) && $row['actor_user_id'] !== null
                    ? (int) $row['actor_user_id']
                    : null,
                'context' => is_array($decoded) ? $decoded : [],
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }, $rows);
    }

    private function assertWpdb(mixed $wpdb): void
    {
        if (! is_object($wpdb)) {
            throw new RuntimeException('SafeContracts contract history requires WordPress $wpdb.');
        }
    }
}
