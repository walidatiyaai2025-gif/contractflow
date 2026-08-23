<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use RuntimeException;

final class NotificationScheduleRepository
{
    /** @return list<array<string,mixed>> */
    public function candidatePayments(int $limit = 5000): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $limit = max(1, min(10000, $limit));

        $rows = $wpdb->get_results(
            "SELECT p.id, p.contract_id, p.reference, p.due_date, p.remaining_amount, p.status,
                    c.accountant_user_id, c.contract_number, cu.name AS customer_name
             FROM {$payments} p
             INNER JOIN {$contracts} c ON c.id = p.contract_id
             INNER JOIN {$customers} cu ON cu.id = c.customer_id
             WHERE p.is_archived = 0 AND c.is_archived = 0 AND cu.is_active = 1
               AND p.remaining_amount > 0 AND p.status <> 'paid'
             ORDER BY p.due_date ASC, p.id ASC
             LIMIT {$limit}",
            ARRAY_A
        );
        return is_array($rows) ? array_values($rows) : [];
    }

    /** @return array<string,mixed>|null */
    public function payment(int $paymentId): ?array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.id, p.contract_id, p.reference, p.due_date, p.remaining_amount, p.status,
                    c.accountant_user_id, c.contract_number, cu.name AS customer_name
             FROM {$payments} p
             INNER JOIN {$contracts} c ON c.id = p.contract_id
             INNER JOIN {$customers} cu ON cu.id = c.customer_id
             WHERE p.id = %d AND p.is_archived = 0 AND c.is_archived = 0 AND cu.is_active = 1 LIMIT 1",
            $paymentId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] ? $rows[0] : null;
    }

    /** @param array<string,mixed> $plan */
    public function upsert(array $plan, int $attemptNo, string $scheduledDate, string $scheduledUtc): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_notification_schedule';
        $recipientIds = array_values(array_unique(array_map('intval', is_array($plan['recipient_ids'] ?? null) ? $plan['recipient_ids'] : [])));
        sort($recipientIds, SORT_NUMERIC);
        $json = wp_json_encode($recipientIds);
        if (! is_string($json)) {
            $json = '[]';
        }
        $count = count($recipientIds);
        $channels = [];
        if (! empty($plan['push_enabled'])) {
            $channels[] = 'push';
        }
        if (! empty($plan['email_enabled'])) {
            $channels[] = 'email';
        }
        $channel = $channels !== [] ? implode('+', $channels) : 'none';

        $sql = $wpdb->prepare(
            "INSERT INTO {$table}
                (rule_id, payment_id, attempt_no, recipient_ids_json, template_code, channel, scheduled_date, scheduled_for, status, recipient_count, sent_count, failed_count, manual_attempts, created_at, updated_at)
             VALUES (%d, %d, %d, %s, %s, %s, %s, %s, 'pending', %d, 0, 0, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                recipient_ids_json = IF(status IN ('pending','failed','skipped'), VALUES(recipient_ids_json), recipient_ids_json),
                template_code = IF(status IN ('pending','failed','skipped'), VALUES(template_code), template_code),
                channel = IF(status IN ('pending','failed','skipped'), VALUES(channel), channel),
                scheduled_date = IF(status IN ('pending','failed','skipped'), VALUES(scheduled_date), scheduled_date),
                scheduled_for = IF(status IN ('pending','failed','skipped'), VALUES(scheduled_for), scheduled_for),
                recipient_count = IF(status IN ('pending','failed','skipped'), VALUES(recipient_count), recipient_count),
                updated_at = UTC_TIMESTAMP()",
            (int) ($plan['rule_id'] ?? 0),
            (int) ($plan['payment_id'] ?? 0),
            $attemptNo,
            $json,
            (string) ($plan['template_code'] ?? ''),
            $channel,
            $scheduledDate,
            $scheduledUtc,
            $count
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to persist notification schedule occurrence.');
        }
    }

    /** @return list<array<string,mixed>> */
    public function recent(?string $dateFrom, ?string $dateTo, string $status = '', int $limit = 250): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $schedule = $wpdb->prefix . 'safecontracts_notification_schedule';
        $rules = $wpdb->prefix . 'safecontracts_notification_rules';
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $where = ['p.is_archived = 0', 'c.is_archived = 0', 'cu.is_active = 1'];
        $args = [];
        if ($dateFrom !== null && $dateFrom !== '') { $where[] = 's.scheduled_date >= %s'; $args[] = $dateFrom; }
        if ($dateTo !== null && $dateTo !== '') { $where[] = 's.scheduled_date <= %s'; $args[] = $dateTo; }
        if ($status !== '' && in_array($status, self::statuses(), true)) { $where[] = 's.status = %s'; $args[] = $status; }
        $limit = max(1, min(1000, $limit));
        $query = "SELECT s.*, r.code AS rule_code, r.name AS rule_name, p.reference AS payment_reference,
                         p.due_date, c.contract_number, cu.name AS customer_name
                  FROM {$schedule} s
                  INNER JOIN {$rules} r ON r.id = s.rule_id
                  INNER JOIN {$payments} p ON p.id = s.payment_id
                  INNER JOIN {$contracts} c ON c.id = p.contract_id
                  INNER JOIN {$customers} cu ON cu.id = c.customer_id
                  WHERE " . implode(' AND ', $where) . "
                  ORDER BY s.scheduled_for DESC, s.id DESC LIMIT {$limit}";
        if ($args !== []) { $query = $wpdb->prepare($query, ...$args); }
        $rows = $wpdb->get_results($query, ARRAY_A);
        return array_map([$this, 'normalize'], is_array($rows) ? $rows : []);
    }

    /** @return list<array<string,mixed>> */
    public function due(int $limit = 50): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_notification_schedule';
        $limit = max(1, min(200, $limit));
        $rows = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE status = 'pending' AND scheduled_for <= UTC_TIMESTAMP() ORDER BY scheduled_for ASC, id ASC LIMIT {$limit}",
            ARRAY_A
        );
        return array_map([$this, 'normalize'], is_array($rows) ? $rows : []);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_notification_schedule';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id), ARRAY_A);
        return is_array($rows) && $rows !== [] ? $this->normalize($rows[0]) : null;
    }

    public function claim(int $id, bool $manual): bool
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_notification_schedule';
        if ($manual) {
            $sql = $wpdb->prepare(
                "UPDATE {$table} SET status = 'processing', manual_attempts = manual_attempts + 1, last_attempt_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = %d AND status <> 'processing'",
                $id
            );
        } else {
            $sql = $wpdb->prepare(
                "UPDATE {$table} SET status = 'processing', last_attempt_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = %d AND status = 'pending' AND scheduled_for <= UTC_TIMESTAMP()",
                $id
            );
        }
        return (int) $wpdb->query($sql) === 1;
    }

    public function complete(int $id, string $status, int $sent, int $failed, ?string $errorCode): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        if (! in_array($status, ['sent','partial','failed','skipped'], true)) {
            throw new RuntimeException('Unsupported notification schedule result status.');
        }
        $table = $wpdb->prefix . 'safecontracts_notification_schedule';
        $sentAt = $status === 'sent' || $status === 'partial' ? 'UTC_TIMESTAMP()' : 'sent_at';
        $errorSql = $errorCode === null || $errorCode === '' ? 'NULL' : '%s';
        $query = "UPDATE {$table} SET status = %s, sent_count = %d, failed_count = %d, sent_at = {$sentAt}, last_error_code = {$errorSql}, updated_at = UTC_TIMESTAMP() WHERE id = %d";
        $args = [$status, max(0, $sent), max(0, $failed)];
        if ($errorSql === '%s') { $args[] = $errorCode; }
        $args[] = $id;
        if ($wpdb->query($wpdb->prepare($query, ...$args)) === false) {
            throw new RuntimeException('Unable to update notification schedule result.');
        }
    }

    public function hasProcessingForRule(int $ruleId): bool
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        if ($ruleId <= 0) {
            return false;
        }
        $table = $wpdb->prefix . 'safecontracts_notification_schedule';
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE rule_id = %d AND status = 'processing'",
            $ruleId
        ));
        return (int) $count > 0;
    }

    public function deleteForRule(int $ruleId): int
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        if ($ruleId <= 0) {
            return 0;
        }
        if ($this->hasProcessingForRule($ruleId)) {
            throw new RuntimeException('Notification rule has an in-flight schedule dispatch. Retry after it finishes.');
        }
        $table = $wpdb->prefix . 'safecontracts_notification_schedule';
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE rule_id = %d",
            $ruleId
        ));
        if ($deleted === false) {
            throw new RuntimeException('Unable to clear scheduled notifications for the rule.');
        }
        return (int) $deleted;
    }

    /** @return list<string> */
    public static function statuses(): array
    {
        return ['pending','processing','sent','partial','failed','skipped'];
    }

    /** @return array<string,mixed> */
    private function normalize(array $row): array
    {
        $ids = json_decode((string) ($row['recipient_ids_json'] ?? '[]'), true);
        return array_merge($row, [
            'id' => (int) ($row['id'] ?? 0),
            'rule_id' => (int) ($row['rule_id'] ?? 0),
            'payment_id' => (int) ($row['payment_id'] ?? 0),
            'attempt_no' => (int) ($row['attempt_no'] ?? 0),
            'recipient_ids' => array_values(array_map('intval', is_array($ids) ? $ids : [])),
            'recipient_count' => (int) ($row['recipient_count'] ?? 0),
            'sent_count' => (int) ($row['sent_count'] ?? 0),
            'failed_count' => (int) ($row['failed_count'] ?? 0),
            'manual_attempts' => (int) ($row['manual_attempts'] ?? 0),
            'channel' => (string) ($row['channel'] ?? 'push'),
        ]);
    }

    private function assertWpdb(mixed $wpdb): void
    {
        if (! is_object($wpdb)) { throw new RuntimeException('SafeContracts notification schedule requires WordPress $wpdb.'); }
    }
}
