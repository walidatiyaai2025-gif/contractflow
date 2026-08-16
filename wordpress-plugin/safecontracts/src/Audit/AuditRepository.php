<?php

declare(strict_types=1);

namespace SafeContracts\Audit;

use RuntimeException;
use SafeContracts\Tenancy\TenantContextStore;

final class AuditRepository
{
    public function append(
        string $entityType,
        ?int $entityId,
        string $eventType,
        ?int $actorUserId,
        ?array $before,
        ?array $after,
        ?array $context
    ): int {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_audit_log';

        $tenantId = TenantContextStore::context()->tenantId();
        if ($tenantId !== null) {
            $context ??= [];
            $context['tenant_id'] = $tenantId;
        }

        $entityIdSql = $entityId === null ? 'NULL' : '%d';
        $actorSql = $actorUserId === null ? 'NULL' : '%d';
        $beforeJson = $this->encode($before);
        $afterJson = $this->encode($after);
        $contextJson = $this->encode($context);
        $beforeSql = $beforeJson === null ? 'NULL' : '%s';
        $afterSql = $afterJson === null ? 'NULL' : '%s';
        $contextSql = $contextJson === null ? 'NULL' : '%s';

        $statement = "INSERT INTO {$table}
            (entity_type, entity_id, event_type, actor_user_id, before_json, after_json, context_json, created_at)
            VALUES (%s, {$entityIdSql}, %s, {$actorSql}, {$beforeSql}, {$afterSql}, {$contextSql}, UTC_TIMESTAMP())";
        $args = [$entityType];
        if ($entityId !== null) {
            $args[] = $entityId;
        }
        $args[] = $eventType;
        if ($actorUserId !== null) {
            $args[] = $actorUserId;
        }
        if ($beforeJson !== null) {
            $args[] = $beforeJson;
        }
        if ($afterJson !== null) {
            $args[] = $afterJson;
        }
        if ($contextJson !== null) {
            $args[] = $contextJson;
        }

        if ($wpdb->query($wpdb->prepare($statement, ...$args)) === false) {
            throw new RuntimeException('Unable to append SafeContracts audit record.');
        }
        return (int) $wpdb->insert_id;
    }

    /** @return list<array<string, mixed>> */
    public function forEntity(string $entityType, ?int $entityId, int $limit): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_audit_log';
        if ($entityId === null) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE entity_type = %s ORDER BY created_at DESC, id DESC LIMIT %d",
                $entityType,
                $limit
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE entity_type = %s AND entity_id = %d ORDER BY created_at DESC, id DESC LIMIT %d",
                $entityType,
                $entityId,
                $limit
            );
        }
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    private function encode(?array $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Unable to encode SafeContracts audit payload.');
        }
        return $json;
    }

    private function assertWpdb(mixed $wpdb): void
    {
        if (! is_object($wpdb) || ! method_exists($wpdb, 'prepare') || ! method_exists($wpdb, 'query') || ! method_exists($wpdb, 'get_results')) {
            throw new RuntimeException('SafeContracts audit repository requires WordPress $wpdb.');
        }
    }
}
