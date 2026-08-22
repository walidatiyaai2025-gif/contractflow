<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use RuntimeException;
use SafeContracts\Database\ProductionMigration;
use SafeContracts\Notifications\NotificationRecipientRolePolicy;

/**
 * Repairs notification rules created by older builds that persisted generic
 * WordPress or historical SafeContracts role slugs.
 *
 * Known aliases are mapped to current SafeContracts roles. Unknown roles are
 * dropped fail-closed. If an active rule would otherwise have no recipients,
 * the rule is disabled instead of broadening delivery.
 */
final class Migration0020NotificationRecipientRepair implements ProductionMigration
{
    /** @var array<int,array{recipient_roles_json:string,escalation_roles_json:string,is_active:int}> */
    private array $originalRows = [];

    public function preflight(object $wpdb): void
    {
        $table = $wpdb->prefix . 'safecontracts_notification_rules';
        $rows = $wpdb->get_results("SELECT id FROM {$table} ORDER BY id ASC LIMIT 1", ARRAY_A);
        if (! is_array($rows)) {
            throw new RuntimeException('SafeContracts notification rules table is unavailable for recipient repair preflight.');
        }
        if (property_exists($wpdb, 'last_error') && trim((string) $wpdb->last_error) !== '') {
            throw new RuntimeException('SafeContracts notification rules preflight failed: ' . trim((string) $wpdb->last_error));
        }
    }

    public function up(object $wpdb): void
    {
        $table = $wpdb->prefix . 'safecontracts_notification_rules';
        $rows = $wpdb->get_results(
            "SELECT id, recipient_roles_json, recipient_user_ids_json, escalation_roles_json, target_assigned_accountant, is_active FROM {$table} ORDER BY id ASC",
            ARRAY_A
        );
        if (! is_array($rows)) {
            throw new RuntimeException('SafeContracts notification recipient repair could not read notification rules.');
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $recipientRolesJson = (string) ($row['recipient_roles_json'] ?? '[]');
            $escalationRolesJson = (string) ($row['escalation_roles_json'] ?? '[]');
            $recipientRoles = NotificationRecipientRolePolicy::normalizeStoredRoles($this->decodeList($recipientRolesJson));
            $escalationRoles = NotificationRecipientRolePolicy::normalizeStoredRoles($this->decodeList($escalationRolesJson));
            $recipientUsers = $this->positiveIds($this->decodeList((string) ($row['recipient_user_ids_json'] ?? '[]')));
            $targetAssigned = (int) ($row['target_assigned_accountant'] ?? 0) === 1;
            $isActive = (int) ($row['is_active'] ?? 0) === 1;
            $shouldRemainActive = $isActive && ($recipientRoles !== [] || $recipientUsers !== [] || $targetAssigned);

            $newRecipientRolesJson = $this->encode($recipientRoles);
            $newEscalationRolesJson = $this->encode($escalationRoles);
            $newActive = $shouldRemainActive ? 1 : 0;

            if (
                $newRecipientRolesJson === $recipientRolesJson
                && $newEscalationRolesJson === $escalationRolesJson
                && $newActive === (int) ($row['is_active'] ?? 0)
            ) {
                continue;
            }

            $this->originalRows[$id] = [
                'recipient_roles_json' => $recipientRolesJson,
                'escalation_roles_json' => $escalationRolesJson,
                'is_active' => (int) ($row['is_active'] ?? 0),
            ];

            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$table}
                 SET recipient_roles_json = %s, escalation_roles_json = %s, is_active = %d
                 WHERE id = %d",
                $newRecipientRolesJson,
                $newEscalationRolesJson,
                $newActive,
                $id
            ));
            if ($result === false) {
                throw new RuntimeException('SafeContracts could not repair notification recipient roles for rule #' . $id . '.');
            }
        }
    }

    public function verify(object $wpdb): void
    {
        $table = $wpdb->prefix . 'safecontracts_notification_rules';
        $rows = $wpdb->get_results(
            "SELECT id, recipient_roles_json, recipient_user_ids_json, escalation_roles_json, target_assigned_accountant, is_active FROM {$table} ORDER BY id ASC",
            ARRAY_A
        );
        if (! is_array($rows)) {
            throw new RuntimeException('SafeContracts notification recipient repair verification could not read notification rules.');
        }

        $allowed = array_flip(NotificationRecipientRolePolicy::allowed());
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            foreach (['recipient_roles_json', 'escalation_roles_json'] as $field) {
                foreach ($this->decodeList((string) ($row[$field] ?? '[]')) as $role) {
                    if (! is_string($role) || ! isset($allowed[$role])) {
                        throw new RuntimeException('SafeContracts notification rule #' . $id . ' still contains an unsupported recipient role.');
                    }
                }
            }

            if ((int) ($row['is_active'] ?? 0) !== 1) {
                continue;
            }
            $roles = $this->decodeList((string) ($row['recipient_roles_json'] ?? '[]'));
            $users = $this->positiveIds($this->decodeList((string) ($row['recipient_user_ids_json'] ?? '[]')));
            $assigned = (int) ($row['target_assigned_accountant'] ?? 0) === 1;
            if ($roles === [] && $users === [] && ! $assigned) {
                throw new RuntimeException('SafeContracts active notification rule #' . $id . ' has no valid recipients after repair.');
            }
        }
    }

    public function rollback(object $wpdb): void
    {
        if ($this->originalRows === []) {
            return;
        }

        $table = $wpdb->prefix . 'safecontracts_notification_rules';
        foreach ($this->originalRows as $id => $original) {
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$table}
                 SET recipient_roles_json = %s, escalation_roles_json = %s, is_active = %d
                 WHERE id = %d",
                $original['recipient_roles_json'],
                $original['escalation_roles_json'],
                $original['is_active'],
                $id
            ));
            if ($result === false) {
                throw new RuntimeException('SafeContracts could not restore notification rule #' . $id . ' during rollback.');
            }
        }
    }

    /** @return list<mixed> */
    private function decodeList(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    /** @param list<mixed> $values @return list<int> */
    private function positiveIds(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value <= 0) {
                continue;
            }
            $ids[(int) $value] = (int) $value;
        }
        ksort($ids, SORT_NUMERIC);
        return array_values($ids);
    }

    /** @param list<string> $values */
    private function encode(array $values): string
    {
        $json = json_encode(array_values($values), JSON_UNESCAPED_SLASHES);
        if (! is_string($json)) {
            throw new RuntimeException('SafeContracts could not encode repaired notification recipient roles.');
        }
        return $json;
    }
}
