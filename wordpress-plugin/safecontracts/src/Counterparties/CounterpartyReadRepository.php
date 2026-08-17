<?php

declare(strict_types=1);

namespace SafeContracts\Counterparties;

use DomainException;
use SafeContracts\Admin\DashboardFilters;
use SafeContracts\Roles\Capabilities;

final class CounterpartyReadRepository
{
    /** @return list<array<string,mixed>> */
    public function contracts(array $filters = []): array
    {
        global $wpdb;
        $f = DashboardFilters::normalize($filters);
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $suppliers = $wpdb->prefix . 'safecontracts_suppliers';
        $where = $this->where($f, 'c', null);
        $where[] = 'c.is_archived = 0';
        $where[] = $this->activeCounterpartySql('c', 'cu', 'su');
        $sql = "SELECT c.id, c.contract_number, c.customer_id,
                       c.counterparty_type, c.counterparty_id,
                       CASE WHEN c.counterparty_type = 'customer' THEN cu.name ELSE su.name END AS counterparty_name,
                       CASE WHEN c.counterparty_type = 'customer' THEN cu.name ELSE NULL END AS customer_name,
                       CASE WHEN c.counterparty_type = 'supplier' THEN su.id ELSE NULL END AS supplier_id,
                       CASE WHEN c.counterparty_type = 'supplier' THEN su.name ELSE NULL END AS supplier_name,
                       c.financial_direction, c.currency_code, c.accountant_user_id,
                       c.status, c.start_date, c.end_date, c.base_value, c.notes, c.is_archived, c.created_at, c.updated_at
                FROM {$contracts} c
                LEFT JOIN {$customers} cu ON c.counterparty_type = 'customer' AND cu.id = c.counterparty_id
                LEFT JOIN {$suppliers} su ON c.counterparty_type = 'supplier' AND su.id = c.counterparty_id
                WHERE " . implode(' AND ', $where) . '
                ORDER BY c.updated_at DESC, c.id DESC LIMIT 500';
        return $this->rows($wpdb->get_results($sql, ARRAY_A));
    }

    /** @return list<array<string,mixed>> */
    public function payments(array $filters = []): array
    {
        global $wpdb;
        $f = DashboardFilters::normalize($filters);
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $suppliers = $wpdb->prefix . 'safecontracts_suppliers';
        $where = $this->where($f, 'c', 'p');
        $where[] = 'c.is_archived = 0';
        $where[] = 'p.is_archived = 0';
        $where[] = $this->activeCounterpartySql('c', 'cu', 'su');
        $sql = "SELECT p.id, p.contract_id, p.financial_direction, p.currency_code, p.sequence_no, p.reference,
                       p.due_date, p.expected_payment_date, p.original_amount, p.paid_amount, p.remaining_amount,
                       p.status, p.is_archived, c.contract_number, c.accountant_user_id,
                       c.is_archived AS contract_is_archived, c.customer_id, c.counterparty_type, c.counterparty_id,
                       CASE WHEN c.counterparty_type = 'customer' THEN cu.name ELSE su.name END AS counterparty_name,
                       CASE WHEN c.counterparty_type = 'customer' THEN cu.name ELSE NULL END AS customer_name,
                       CASE WHEN c.counterparty_type = 'supplier' THEN su.id ELSE NULL END AS supplier_id,
                       CASE WHEN c.counterparty_type = 'supplier' THEN su.name ELSE NULL END AS supplier_name
                FROM {$payments} p
                INNER JOIN {$contracts} c ON c.id = p.contract_id
                LEFT JOIN {$customers} cu ON c.counterparty_type = 'customer' AND cu.id = c.counterparty_id
                LEFT JOIN {$suppliers} su ON c.counterparty_type = 'supplier' AND su.id = c.counterparty_id
                WHERE " . implode(' AND ', $where) . '
                ORDER BY p.due_date ASC, p.sequence_no ASC LIMIT 500';
        return $this->rows($wpdb->get_results($sql, ARRAY_A));
    }

    /** @return list<array<string,mixed>> */
    public function settlements(array $filters = []): array
    {
        global $wpdb;
        $f = DashboardFilters::normalize($filters);
        $collections = $wpdb->prefix . 'safecontracts_payment_collections';
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $suppliers = $wpdb->prefix . 'safecontracts_suppliers';
        $methods = $wpdb->prefix . 'safecontracts_payment_methods';
        $where = $this->where($f, 'c', 'p');
        $where[] = 'c.is_archived = 0';
        $where[] = 'p.is_archived = 0';
        $where[] = 'cl.is_archived = 0';
        $where[] = $this->activeCounterpartySql('c', 'cu', 'su');
        $sql = "SELECT cl.id, cl.payment_id, cl.financial_direction, cl.currency_code, cl.amount, cl.collection_date,
                       cl.payment_method_id, pm.name AS payment_method_name, cl.reference, cl.details, cl.proof_media_id,
                       cl.created_by, cl.created_at, p.reference AS payment_reference, p.sequence_no, p.due_date,
                       p.status AS payment_status, p.remaining_amount, c.id AS contract_id, c.contract_number,
                       c.accountant_user_id, c.customer_id, c.counterparty_type, c.counterparty_id,
                       CASE WHEN c.counterparty_type = 'customer' THEN cu.name ELSE su.name END AS counterparty_name,
                       CASE WHEN c.counterparty_type = 'customer' THEN cu.name ELSE NULL END AS customer_name,
                       CASE WHEN c.counterparty_type = 'supplier' THEN su.id ELSE NULL END AS supplier_id,
                       CASE WHEN c.counterparty_type = 'supplier' THEN su.name ELSE NULL END AS supplier_name
                FROM {$collections} cl
                INNER JOIN {$payments} p ON p.id = cl.payment_id
                INNER JOIN {$contracts} c ON c.id = p.contract_id
                LEFT JOIN {$customers} cu ON c.counterparty_type = 'customer' AND cu.id = c.counterparty_id
                LEFT JOIN {$suppliers} su ON c.counterparty_type = 'supplier' AND su.id = c.counterparty_id
                INNER JOIN {$methods} pm ON pm.id = cl.payment_method_id
                WHERE " . implode(' AND ', $where) . '
                ORDER BY cl.collection_date DESC, cl.id DESC LIMIT 500';
        return $this->rows($wpdb->get_results($sql, ARRAY_A));
    }

    /**
     * Currency-safe AP/AR metrics. Amounts are never summed across different
     * currencies or directions; every row is one financial bucket.
     *
     * @return list<array<string,mixed>>
     */
    public function financialSummary(array $filters = []): array
    {
        global $wpdb;
        $f = DashboardFilters::normalize($filters);
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $where = $this->where($f, 'c', 'p');
        $where[] = 'c.is_archived = 0';
        $where[] = 'p.is_archived = 0';
        $sql = "SELECT p.financial_direction, p.currency_code,
                       COUNT(p.id) AS obligation_count,
                       COALESCE(SUM(p.original_amount), 0.0000) AS scheduled_total,
                       COALESCE(SUM(p.paid_amount), 0.0000) AS settled_total,
                       COALESCE(SUM(p.remaining_amount), 0.0000) AS outstanding_total
                FROM {$payments} p
                INNER JOIN {$contracts} c ON c.id = p.contract_id
                WHERE " . implode(' AND ', $where) . '
                GROUP BY p.financial_direction, p.currency_code
                ORDER BY p.financial_direction ASC, p.currency_code ASC';
        return $this->rows($wpdb->get_results($sql, ARRAY_A));
    }

    /** @return list<string> */
    private function where(array $filters, string $contractAlias, ?string $paymentAlias): array
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            throw new DomainException('SafeContracts counterparty data requires access capability.');
        }
        $where = ['1 = 1'];
        if (current_user_can(Capabilities::VIEW_ALL)) {
            if ($filters['accountant_user_id'] > 0) {
                $where[] = $contractAlias . '.accountant_user_id = ' . (int) $filters['accountant_user_id'];
            }
        } else {
            if (! current_user_can(Capabilities::VIEW_ASSIGNED)) {
                throw new DomainException('SafeContracts counterparty data is outside the current user scope.');
            }
            $where[] = $contractAlias . '.accountant_user_id = ' . get_current_user_id();
        }

        if ($filters['customer_id'] > 0) {
            $where[] = $contractAlias . '.customer_id = ' . (int) $filters['customer_id'];
        }
        if ($filters['counterparty_type'] !== '') {
            $where[] = $contractAlias . ".counterparty_type = '" . addslashes($filters['counterparty_type']) . "'";
        }
        if ($filters['counterparty_id'] > 0) {
            $where[] = $contractAlias . '.counterparty_id = ' . (int) $filters['counterparty_id'];
        }
        if ($filters['contract_id'] > 0) {
            $where[] = $contractAlias . '.id = ' . (int) $filters['contract_id'];
        }
        $directionAlias = $paymentAlias ?? $contractAlias;
        if ($filters['financial_direction'] !== '') {
            $where[] = $directionAlias . ".financial_direction = '" . addslashes($filters['financial_direction']) . "'";
        }
        if ($filters['currency_code'] !== '') {
            $where[] = $directionAlias . ".currency_code = '" . addslashes($filters['currency_code']) . "'";
        }
        if ($filters['status'] !== '') {
            $paymentStatuses = ['upcoming', 'due_soon', 'due', 'overdue', 'partially_paid', 'paid'];
            $alias = $paymentAlias !== null && in_array($filters['status'], $paymentStatuses, true)
                ? $paymentAlias
                : $contractAlias;
            $where[] = $alias . ".status = '" . addslashes($filters['status']) . "'";
        }
        if ($paymentAlias !== null) {
            if ($filters['due_from'] !== null) {
                $where[] = $paymentAlias . ".due_date >= '" . addslashes($filters['due_from']) . "'";
            }
            if ($filters['due_to'] !== null) {
                $where[] = $paymentAlias . ".due_date <= '" . addslashes($filters['due_to']) . "'";
            }
        }
        return $where;
    }

    private function activeCounterpartySql(string $contractAlias, string $customerAlias, string $supplierAlias): string
    {
        return "(({$contractAlias}.counterparty_type = 'customer' AND {$customerAlias}.id IS NOT NULL AND {$customerAlias}.is_active = 1)
            OR ({$contractAlias}.counterparty_type = 'supplier' AND {$supplierAlias}.id IS NOT NULL AND {$supplierAlias}.is_active = 1 AND {$supplierAlias}.is_archived = 0))";
    }

    /** @return list<array<string,mixed>> */
    private function rows(mixed $rows): array
    {
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }
}
