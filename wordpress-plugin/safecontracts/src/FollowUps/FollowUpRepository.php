<?php

declare(strict_types=1);

namespace SafeContracts\FollowUps;

use RuntimeException;

final class FollowUpRepository
{
    /** @return list<array<string, mixed>> */
    public function queue(?int $accountantUserId, int $limit, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);

        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $followups = $wpdb->prefix . 'safecontracts_payment_followups';
        $scope = $accountantUserId === null ? '' : ' AND c.accountant_user_id = %d';
        $period = '';
        if ($dateFrom !== null) {
            $period .= ' AND p.due_date >= %s';
        }
        if ($dateTo !== null) {
            $period .= ' AND p.due_date <= %s';
        }
        $sql = "SELECT p.id AS payment_id, p.contract_id, c.customer_id, c.accountant_user_id,
                       c.status AS contract_status,
                       p.reference, p.due_date, p.expected_payment_date, p.original_amount,
                       p.paid_amount, p.remaining_amount, p.status,
                       (SELECT f.state FROM {$followups} f WHERE f.payment_id = p.id
                        ORDER BY f.created_at DESC, f.id DESC LIMIT 1) AS followup_state
                FROM {$payments} p
                INNER JOIN {$contracts} c ON c.id = p.contract_id
                WHERE c.is_archived = 0
                  AND p.is_archived = 0
                  AND p.remaining_amount > 0
                  AND p.status <> 'paid'{$scope}{$period}
                ORDER BY p.due_date ASC, p.id ASC
                LIMIT %d";
        $args = [];
        if ($accountantUserId !== null) {
            $args[] = $accountantUserId;
        }
        if ($dateFrom !== null) {
            $args[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $args[] = $dateTo;
        }
        $args[] = $limit;
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    public function append(
        int $paymentId,
        string $state,
        ?string $note,
        ?string $promisedDate,
        ?string $deferredUntil,
        int $actorId
    ): int {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_payment_followups';

        $noteSql = $note === null ? 'NULL' : '%s';
        $promiseSql = $promisedDate === null ? 'NULL' : '%s';
        $deferredSql = $deferredUntil === null ? 'NULL' : '%s';
        $statement = "INSERT INTO {$table}
            (payment_id, state, note, promised_date, deferred_until, created_by, created_at)
            VALUES (%d, %s, {$noteSql}, {$promiseSql}, {$deferredSql}, %d, UTC_TIMESTAMP())";
        $args = [$paymentId, $state];
        if ($note !== null) {
            $args[] = $note;
        }
        if ($promisedDate !== null) {
            $args[] = $promisedDate;
        }
        if ($deferredUntil !== null) {
            $args[] = $deferredUntil;
        }
        $args[] = $actorId;

        if ($wpdb->query($wpdb->prepare($statement, ...$args)) === false) {
            throw new RuntimeException('Unable to append SafeContracts follow-up history.');
        }
        return (int) $wpdb->insert_id;
    }

    /** @return list<array<string, mixed>> */
    public function history(int $paymentId, int $limit, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_payment_followups';
        $users = $this->usersTable($wpdb);
        $period = '';
        $args = [$paymentId];
        if ($dateFrom !== null) {
            $period .= ' AND DATE(f.created_at) >= %s';
            $args[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $period .= ' AND DATE(f.created_at) <= %s';
            $args[] = $dateTo;
        }
        $args[] = $limit;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT f.id, f.payment_id, f.state, f.note, f.promised_date, f.deferred_until,
                        f.created_by, f.created_at,
                        (SELECT COALESCE(NULLIF(u.display_name, ''), u.user_login)
                         FROM {$users} u WHERE u.ID = f.created_by LIMIT 1) AS author_name
                 FROM {$table} f
                 WHERE f.payment_id = %d{$period}
                 ORDER BY created_at DESC, id DESC LIMIT %d",
                ...$args
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string,mixed>> */
    public function recent(?int $accountantUserId, int $limit, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $followups = $wpdb->prefix . 'safecontracts_payment_followups';
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $suppliers = $wpdb->prefix . 'safecontracts_suppliers';
        $users = $this->usersTable($wpdb);
        $where = ['c.is_archived = 0', 'p.is_archived = 0'];
        $args = [];
        if ($accountantUserId !== null) {
            $where[] = 'c.accountant_user_id = %d';
            $args[] = $accountantUserId;
        }
        if ($dateFrom !== null) {
            $where[] = 'DATE(f.created_at) >= %s';
            $args[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $where[] = 'DATE(f.created_at) <= %s';
            $args[] = $dateTo;
        }
        $args[] = $limit;
        $sql = "SELECT f.id, f.payment_id, f.state, f.note, f.promised_date, f.deferred_until,
                       f.created_by, f.created_at,
                       p.reference AS payment_reference, p.due_date, p.remaining_amount, p.currency_code,
                       c.id AS contract_id, c.contract_number, c.accountant_user_id,
                       CASE WHEN c.counterparty_type = 'supplier' THEN s.name ELSE cu.name END AS counterparty_name,
                       COALESCE(NULLIF(u.display_name, ''), u.user_login) AS author_name
                FROM {$followups} f
                INNER JOIN {$payments} p ON p.id = f.payment_id
                INNER JOIN {$contracts} c ON c.id = p.contract_id
                LEFT JOIN {$customers} cu ON c.counterparty_type = 'customer' AND cu.id = c.counterparty_id
                LEFT JOIN {$suppliers} s ON c.counterparty_type = 'supplier' AND s.id = c.counterparty_id
                LEFT JOIN {$users} u ON u.ID = f.created_by
                WHERE " . implode(' AND ', $where) . "
                ORDER BY f.created_at DESC, f.id DESC LIMIT %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    private function assertWpdb(mixed $wpdb): void
    {
        if (! is_object($wpdb) || ! method_exists($wpdb, 'prepare') || ! method_exists($wpdb, 'query') || ! method_exists($wpdb, 'get_results')) {
            throw new RuntimeException('SafeContracts follow-up repository requires WordPress $wpdb.');
        }
    }

    private function usersTable(object $wpdb): string
    {
        if (isset($wpdb->users) && is_string($wpdb->users) && $wpdb->users !== '') {
            return $wpdb->users;
        }

        return (string) $wpdb->prefix . 'users';
    }
}
