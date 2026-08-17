<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use DomainException;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;

final class AdminReadRepository
{
    public function kpis(array $filters): array
    {
        global $wpdb;
        $normalized = DashboardFilters::normalize($filters);
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $suppliers = $wpdb->prefix . 'safecontracts_suppliers';
        $where = $this->where($normalized, 'c', 'p', 'p.due_date');
        $where[] = 'c.is_archived = 0';
        $where[] = $this->activeCounterpartyWhere('c', 'cu', 's');
        $today = function_exists('wp_date') ? wp_date('Y-m-d') : gmdate('Y-m-d');
        $currency = "COALESCE(NULLIF(p.currency_code, ''), 'UNSET')";
        $sql = "SELECT
                COUNT(DISTINCT c.id) AS contract_count,
                COUNT(DISTINCT {$currency}) AS currency_group_count,
                MAX({$currency}) AS currency_code,
                CASE WHEN COUNT(DISTINCT {$currency}) <= 1 THEN COALESCE(SUM(p.original_amount), 0) ELSE NULL END AS scheduled_total,
                CASE WHEN COUNT(DISTINCT {$currency}) <= 1 THEN COALESCE(SUM(p.remaining_amount), 0) ELSE NULL END AS remaining_total,
                CASE WHEN COUNT(DISTINCT {$currency}) <= 1 THEN COALESCE(SUM(CASE WHEN p.due_date < '" . addslashes($today) . "' AND p.remaining_amount > 0 THEN p.remaining_amount ELSE 0 END), 0) ELSE NULL END AS overdue_exposure,
                CASE WHEN COUNT(DISTINCT {$currency}) <= 1 THEN COALESCE(SUM(p.paid_amount), 0) ELSE NULL END AS collected_total
            FROM {$contracts} c
            LEFT JOIN {$customers} cu ON c.counterparty_type = 'customer' AND cu.id = c.counterparty_id
            LEFT JOIN {$suppliers} s ON c.counterparty_type = 'supplier' AND s.id = c.counterparty_id
            LEFT JOIN {$payments} p ON p.contract_id = c.id AND p.is_archived = 0
            WHERE " . implode(' AND ', $where);
        return $this->firstRow($wpdb->get_results($sql, ARRAY_A), [
            'contract_count' => '0',
            'currency_group_count' => '0',
            'currency_code' => '',
            'scheduled_total' => '0.0000',
            'remaining_total' => '0.0000',
            'overdue_exposure' => '0.0000',
            'collected_total' => '0.0000',
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function customers(array $filters = []): array
    {
        global $wpdb;
        $normalized = DashboardFilters::normalize($filters);
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $where = ['cu.is_active = 1'];
        if ($normalized['customer_id'] > 0) {
            $where[] = 'cu.id = ' . $normalized['customer_id'];
        }
        $where = array_merge($where, $this->periodWhere($normalized, 'DATE(cu.created_at)'));
        if (! current_user_can(Capabilities::VIEW_ALL)) {
            $this->requireAssignedScope();
            $userId = get_current_user_id();
            $where[] = "EXISTS (SELECT 1 FROM {$contracts} sc_scope WHERE sc_scope.counterparty_type = 'customer' AND sc_scope.counterparty_id = cu.id AND sc_scope.accountant_user_id = {$userId} AND sc_scope.is_archived = 0)";
        }
        $sql = "SELECT cu.id, cu.internal_code, cu.name, cu.contact_name, cu.email, cu.phone, cu.notes, cu.is_active, cu.created_at, cu.updated_at
                FROM {$customers} cu WHERE " . implode(' AND ', $where) . ' ORDER BY cu.name ASC LIMIT 500';
        return $this->rows($wpdb->get_results($sql, ARRAY_A));
    }

    /** @return list<array<string,mixed>> */
    public function contracts(array $filters = []): array
    {
        global $wpdb;
        $normalized = DashboardFilters::normalize($filters);
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $suppliers = $wpdb->prefix . 'safecontracts_suppliers';
        $where = $this->where($normalized, 'c', null, 'COALESCE(c.start_date, DATE(c.created_at))');
        $where[] = 'c.is_archived = 0';
        $where[] = $this->activeCounterpartyWhere('c', 'cu', 's');
        $sql = "SELECT c.id, c.contract_number, c.customer_id, c.counterparty_type, c.counterparty_id,
                       c.financial_direction, c.currency_code,
                       CASE WHEN c.counterparty_type = 'customer' THEN cu.name
                            WHEN c.counterparty_type = 'supplier' THEN s.legal_name
                            ELSE NULL END AS counterparty_name,
                       cu.name AS customer_name,
                       c.accountant_user_id, c.status, c.start_date, c.end_date, c.base_value,
                       c.notes, c.is_archived, c.created_at, c.updated_at
                FROM {$contracts} c
                LEFT JOIN {$customers} cu ON c.counterparty_type = 'customer' AND cu.id = c.counterparty_id
                LEFT JOIN {$suppliers} s ON c.counterparty_type = 'supplier' AND s.id = c.counterparty_id
                WHERE " . implode(' AND ', $where) . '
                ORDER BY c.updated_at DESC, c.id DESC LIMIT 500';
        return $this->rows($wpdb->get_results($sql, ARRAY_A));
    }

    /** @return list<array<string,mixed>> */
    public function payments(array $filters = []): array
    {
        global $wpdb;
        $normalized = DashboardFilters::normalize($filters);
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $suppliers = $wpdb->prefix . 'safecontracts_suppliers';
        $where = $this->where($normalized, 'c', 'p', 'p.due_date');
        $where[] = 'c.is_archived = 0';
        $where[] = 'p.is_archived = 0';
        $where[] = $this->activeCounterpartyWhere('c', 'cu', 's');
        $sql = "SELECT p.id, p.contract_id, p.financial_direction, p.currency_code, p.sequence_no, p.reference,
                       p.due_date, p.expected_payment_date, p.original_amount, p.paid_amount,
                       p.remaining_amount, p.status, p.is_archived,
                       c.contract_number, c.accountant_user_id, c.is_archived AS contract_is_archived,
                       c.counterparty_type, c.counterparty_id,
                       CASE WHEN c.counterparty_type = 'customer' THEN cu.name
                            WHEN c.counterparty_type = 'supplier' THEN s.legal_name
                            ELSE NULL END AS counterparty_name,
                       cu.id AS customer_id, cu.name AS customer_name
                FROM {$payments} p
                INNER JOIN {$contracts} c ON c.id = p.contract_id
                LEFT JOIN {$customers} cu ON c.counterparty_type = 'customer' AND cu.id = c.counterparty_id
                LEFT JOIN {$suppliers} s ON c.counterparty_type = 'supplier' AND s.id = c.counterparty_id
                WHERE " . implode(' AND ', $where) . '
                ORDER BY p.due_date ASC, p.sequence_no ASC LIMIT 500';
        return $this->rows($wpdb->get_results($sql, ARRAY_A));
    }

    /** @return list<array<string,mixed>> */
    public function collections(array $filters = []): array
    {
        return $this->collectionRows($filters, false, 500);
    }

    /** @return list<array<string,mixed>> */
    public function collectorAttachments(array $filters = [], int $limit = 12): array
    {
        return $this->collectionRows($filters, true, max(1, min(100, $limit)));
    }

    /**
     * Legacy collection reporting remains receivable/customer-specific. New AP/AR
     * reporting uses the finance read model and canonical transaction ledger.
     *
     * @return array{
     *   contract_count:mixed,currency_group_count:mixed,currency_code:mixed,scheduled_total:mixed,remaining_total:mixed,overdue_exposure:mixed,collected_total:mixed,
     *   collection_transactions:mixed,collection_ledger_total:mixed,followup_events:mixed,followed_up_payments:mixed
     * }
     */
    public function reportSummary(array $filters = []): array
    {
        global $wpdb;
        $normalized = DashboardFilters::normalize($filters);
        $summary = $this->kpis($normalized);
        $collections = $wpdb->prefix . 'safecontracts_payment_collections';
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $followups = $wpdb->prefix . 'safecontracts_payment_followups';

        $collectionWhere = $this->where($normalized, 'c', 'p', 'cl.collection_date');
        $collectionWhere[] = 'c.is_archived = 0';
        $collectionWhere[] = 'p.is_archived = 0';
        $collectionWhere[] = 'cl.is_archived = 0';
        $collectionWhere[] = "c.counterparty_type = 'customer'";
        $collectionWhere[] = 'cu.is_active = 1';
        $collectionSql = "SELECT COUNT(cl.id) AS collection_transactions,
                                 COALESCE(SUM(cl.amount), 0) AS collection_ledger_total
                          FROM {$collections} cl
                          INNER JOIN {$payments} p ON p.id = cl.payment_id
                          INNER JOIN {$contracts} c ON c.id = p.contract_id
                          INNER JOIN {$customers} cu ON c.counterparty_type = 'customer' AND cu.id = c.counterparty_id
                          WHERE " . implode(' AND ', $collectionWhere);
        $collectionTotals = $this->firstRow($wpdb->get_results($collectionSql, ARRAY_A), [
            'collection_transactions' => '0',
            'collection_ledger_total' => '0.0000',
        ]);

        $followupWhere = $this->where($normalized, 'c', 'p', 'DATE(f.created_at)');
        $followupWhere[] = 'c.is_archived = 0';
        $followupWhere[] = 'p.is_archived = 0';
        $followupWhere[] = "c.counterparty_type = 'customer'";
        $followupWhere[] = 'cu.is_active = 1';
        $followupSql = "SELECT COUNT(f.id) AS followup_events,
                               COUNT(DISTINCT f.payment_id) AS followed_up_payments
                        FROM {$followups} f
                        INNER JOIN {$payments} p ON p.id = f.payment_id
                        INNER JOIN {$contracts} c ON c.id = p.contract_id
                        INNER JOIN {$customers} cu ON c.counterparty_type = 'customer' AND cu.id = c.counterparty_id
                        WHERE " . implode(' AND ', $followupWhere);
        $followupTotals = $this->firstRow($wpdb->get_results($followupSql, ARRAY_A), [
            'followup_events' => '0',
            'followed_up_payments' => '0',
        ]);

        return array_merge($summary, $collectionTotals, $followupTotals);
    }

    /** @return list<array{id:int,name:string}> */
    public function customerOptions(): array
    {
        $rows = $this->customers();
        return array_values(array_map(static fn (array $row): array => [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
        ], $rows));
    }

    /** @return list<array{id:int,contract_number:string,customer_id:int,counterparty_type:string,counterparty_id:int,counterparty_name:string}> */
    public function contractOptions(int $customerId = 0): array
    {
        $filters = $customerId > 0 ? ['customer_id' => $customerId] : [];
        $rows = $this->contracts($filters);
        return array_values(array_map(static fn (array $row): array => [
            'id' => (int) ($row['id'] ?? 0),
            'contract_number' => (string) ($row['contract_number'] ?? ''),
            'customer_id' => (int) ($row['customer_id'] ?? 0),
            'counterparty_type' => (string) ($row['counterparty_type'] ?? ''),
            'counterparty_id' => (int) ($row['counterparty_id'] ?? 0),
            'counterparty_name' => (string) ($row['counterparty_name'] ?? ''),
        ], $rows));
    }

    /** @return list<array<string,mixed>> */
    private function collectionRows(array $filters, bool $proofOnly, int $limit): array
    {
        global $wpdb;
        $normalized = DashboardFilters::normalize($filters);
        $collections = $wpdb->prefix . 'safecontracts_payment_collections';
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $methods = $wpdb->prefix . 'safecontracts_payment_methods';
        $where = $this->where($normalized, 'c', 'p', 'cl.collection_date');
        $where[] = 'c.is_archived = 0';
        $where[] = 'p.is_archived = 0';
        $where[] = 'cl.is_archived = 0';
        $where[] = "c.counterparty_type = 'customer'";
        $where[] = 'cu.is_active = 1';
        if ($proofOnly) {
            $where[] = 'cl.proof_media_id IS NOT NULL';
            $where[] = 'cl.proof_media_id > 0';
        }
        $limit = max(1, min(500, $limit));
        $sql = "SELECT cl.id, cl.payment_id, cl.amount, cl.collection_date, cl.payment_method_id,
                       cl.reference, cl.details, cl.proof_media_id, cl.created_by, cl.created_at, cl.is_archived,
                       p.reference AS payment_reference, p.sequence_no, p.due_date, p.status AS payment_status,
                       p.remaining_amount, c.id AS contract_id, c.contract_number, c.accountant_user_id,
                       cu.id AS customer_id, cu.name AS customer_name, pm.name AS payment_method_name
                FROM {$collections} cl
                INNER JOIN {$payments} p ON p.id = cl.payment_id
                INNER JOIN {$contracts} c ON c.id = p.contract_id
                INNER JOIN {$customers} cu ON c.counterparty_type = 'customer' AND cu.id = c.counterparty_id
                INNER JOIN {$methods} pm ON pm.id = cl.payment_method_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY cl.collection_date DESC, cl.id DESC LIMIT {$limit}";
        return $this->rows($wpdb->get_results($sql, ARRAY_A));
    }

    /** @return list<string> */
    private function where(array $filters, string $contractAlias, ?string $paymentAlias, ?string $dateExpression = null): array
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            throw new DomainException('SafeContracts admin data requires access capability.');
        }
        $where = ['1 = 1'];
        if (current_user_can(Capabilities::VIEW_ALL)) {
            if (($filters['accountant_user_id'] ?? 0) > 0) {
                $where[] = $contractAlias . '.accountant_user_id = ' . (int) $filters['accountant_user_id'];
            }
        } else {
            $this->requireAssignedScope();
            $where[] = $contractAlias . '.accountant_user_id = ' . get_current_user_id();
        }
        if (($filters['customer_id'] ?? 0) > 0) {
            $where[] = $contractAlias . ".counterparty_type = 'customer'";
            $where[] = $contractAlias . '.counterparty_id = ' . (int) $filters['customer_id'];
        }
        if (($filters['contract_id'] ?? 0) > 0) {
            $where[] = $contractAlias . '.id = ' . (int) $filters['contract_id'];
        }
        $status = (string) ($filters['status'] ?? '');
        if ($status !== '') {
            $alias = $paymentAlias !== null && in_array($status, PaymentStatus::all(), true)
                ? $paymentAlias
                : $contractAlias;
            $where[] = $alias . ".status = '" . addslashes($status) . "'";
        }

        if ($dateExpression !== null) {
            $where = array_merge($where, $this->periodWhere($filters, $dateExpression));
            if (($filters['date_from'] ?? null) === null && ($filters['date_to'] ?? null) === null && $paymentAlias !== null && $dateExpression === $paymentAlias . '.due_date') {
                if (($filters['due_from'] ?? null) !== null) {
                    $where[] = $dateExpression . " >= '" . addslashes((string) $filters['due_from']) . "'";
                }
                if (($filters['due_to'] ?? null) !== null) {
                    $where[] = $dateExpression . " <= '" . addslashes((string) $filters['due_to']) . "'";
                }
            }
        }

        return $where;
    }

    /** @return list<string> */
    private function periodWhere(array $filters, string $dateExpression): array
    {
        if (! empty($filters['date_range_error'])) {
            return ['1 = 0'];
        }
        $where = [];
        if (($filters['date_from'] ?? null) !== null) {
            $where[] = $dateExpression . " >= '" . addslashes((string) $filters['date_from']) . "'";
        }
        if (($filters['date_to'] ?? null) !== null) {
            $where[] = $dateExpression . " <= '" . addslashes((string) $filters['date_to']) . "'";
        }
        return $where;
    }

    private function activeCounterpartyWhere(string $contractAlias, string $customerAlias, string $supplierAlias): string
    {
        return "(({$contractAlias}.counterparty_type = 'customer' AND {$customerAlias}.is_active = 1)
                 OR ({$contractAlias}.counterparty_type = 'supplier' AND {$supplierAlias}.is_archived = 0))";
    }

    private function requireAssignedScope(): void
    {
        if (! current_user_can(Capabilities::VIEW_ASSIGNED)) {
            throw new DomainException('SafeContracts admin data is outside the current user scope.');
        }
    }

    /** @return list<array<string,mixed>> */
    private function rows(mixed $rows): array
    {
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    private function firstRow(mixed $rows, array $defaults): array
    {
        if (! is_array($rows) || ! isset($rows[0]) || ! is_array($rows[0])) {
            return $defaults;
        }
        return array_merge($defaults, $rows[0]);
    }
}
