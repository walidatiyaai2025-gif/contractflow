<?php

declare(strict_types=1);

namespace SafeContracts\FollowUps;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantScope;

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
        $tenantId = CoreTenantScope::tenantId();
        $tenantScope = $tenantId === null
            ? ''
            : ' AND p.tenant_id = ' . $tenantId . ' AND c.tenant_id = ' . $tenantId;
        $followUpTenant = $tenantId === null ? '' : ' AND f.tenant_id = ' . $tenantId;
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
                       (SELECT f.state FROM {$followups} f WHERE f.payment_id = p.id{$followUpTenant}
                        ORDER BY f.created_at DESC, f.id DESC LIMIT 1) AS followup_state
                FROM {$payments} p
                INNER JOIN {$contracts} c ON c.id = p.contract_id
                WHERE c.is_archived = 0
                  AND p.is_archived = 0
                  AND p.remaining_amount > 0
                  AND p.status <> 'paid'{$tenantScope}{$scope}{$period}
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
        $tenantId = CoreTenantScope::tenantId();

        $noteSql = $note === null ? 'NULL' : '%s';
        $promiseSql = $promisedDate === null ? 'NULL' : '%s';
        $deferredSql = $deferredUntil === null ? 'NULL' : '%s';
        $tenantColumn = $tenantId === null ? '' : 'tenant_id, ';
        $tenantPlaceholder = $tenantId === null ? '' : '%d, ';
        $statement = "INSERT INTO {$table}
            ({$tenantColumn}payment_id, state, note, promised_date, deferred_until, created_by, created_at)
            VALUES ({$tenantPlaceholder}%d, %s, {$noteSql}, {$promiseSql}, {$deferredSql}, %d, UTC_TIMESTAMP())";
        $args = [];
        if ($tenantId !== null) {
            $args[] = $tenantId;
        }
        $args[] = $paymentId;
        $args[] = $state;
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
        $tenant = $this->tenantCondition();
        $period = '';
        $args = [$paymentId];
        if ($dateFrom !== null) {
            $period .= ' AND DATE(created_at) >= %s';
            $args[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $period .= ' AND DATE(created_at) <= %s';
            $args[] = $dateTo;
        }
        $args[] = $limit;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, payment_id, state, note, promised_date, deferred_until, created_by, created_at
                 FROM {$table} WHERE payment_id = %d{$tenant}{$period}
                 ORDER BY created_at DESC, id DESC LIMIT %d",
                ...$args
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    private function tenantCondition(string $column = 'tenant_id'): string
    {
        $tenantId = CoreTenantScope::tenantId();
        return $tenantId === null ? '' : ' AND ' . $column . ' = ' . $tenantId;
    }

    private function assertWpdb(mixed $wpdb): void
    {
        if (! is_object($wpdb) || ! method_exists($wpdb, 'prepare') || ! method_exists($wpdb, 'query') || ! method_exists($wpdb, 'get_results')) {
            throw new RuntimeException('SafeContracts follow-up repository requires WordPress $wpdb.');
        }
    }
}
