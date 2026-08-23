<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use DomainException;
use SafeContracts\Payments\FinancialDirection;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;

/**
 * Direction-aware administrative read model for the legacy collection ledger.
 *
 * Collection rows are settlement transactions. A receivable settlement is
 * money coming into the company; a payable settlement is money leaving it.
 * Stored amounts remain non-negative for ledger integrity. Presentation signs
 * are derived exclusively from the server-authoritative financial direction.
 */
final class FinancialSettlementAdminRepository
{
    /** @return list<array<string,mixed>> */
    public function collections(array $filters = [], int $limit = 500): array
    {
        global $wpdb;
        if (! is_object($wpdb) || ! method_exists($wpdb, 'get_results')) {
            throw new DomainException('SafeContracts settlement reads require WordPress database access.');
        }
        if (! current_user_can(Capabilities::ACCESS)) {
            throw new DomainException('SafeContracts settlement reads require access capability.');
        }

        $filters = DashboardFilters::normalize($filters);
        $collections = $wpdb->prefix . 'safecontracts_payment_collections';
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $suppliers = $wpdb->prefix . 'safecontracts_suppliers';
        $methods = $wpdb->prefix . 'safecontracts_payment_methods';

        $where = [
            'c.is_archived = 0',
            'p.is_archived = 0',
            'cl.is_archived = 0',
            "cl.financial_direction IN ('receivable','payable')",
            "((c.counterparty_type = 'customer' AND cu.id IS NOT NULL AND cu.is_active = 1) OR (c.counterparty_type = 'supplier' AND s.id IS NOT NULL))",
        ];

        if (current_user_can(Capabilities::VIEW_ALL)) {
            if (($filters['accountant_user_id'] ?? 0) > 0) {
                $where[] = 'c.accountant_user_id = ' . (int) $filters['accountant_user_id'];
            }
        } else {
            if (! current_user_can(Capabilities::VIEW_ASSIGNED)) {
                throw new DomainException('SafeContracts settlement reads require an assigned data scope.');
            }
            $where[] = 'c.accountant_user_id = ' . get_current_user_id();
        }

        if (($filters['customer_id'] ?? 0) > 0) {
            $where[] = "c.counterparty_type = 'customer'";
            $where[] = 'c.counterparty_id = ' . (int) $filters['customer_id'];
        }
        if (($filters['counterparty_type'] ?? '') !== '') {
            $type = (string) $filters['counterparty_type'] === 'supplier' ? 'supplier' : 'customer';
            $where[] = "c.counterparty_type = '" . $type . "'";
        }
        if (($filters['counterparty_id'] ?? 0) > 0) {
            $where[] = 'c.counterparty_id = ' . (int) $filters['counterparty_id'];
        }
        if (($filters['contract_id'] ?? 0) > 0) {
            $where[] = 'c.id = ' . (int) $filters['contract_id'];
        }
        if (($filters['financial_direction'] ?? '') !== '') {
            $direction = FinancialDirection::normalize((string) $filters['financial_direction']);
            $where[] = "cl.financial_direction = '" . addslashes($direction) . "'";
        }
        if (($filters['currency_code'] ?? '') !== '') {
            $currency = strtoupper(trim((string) $filters['currency_code']));
            $where[] = "cl.currency_code = '" . addslashes($currency) . "'";
        }
        $status = (string) ($filters['status'] ?? '');
        if ($status !== '' && in_array($status, PaymentStatus::all(), true)) {
            $where[] = "p.status = '" . addslashes($status) . "'";
        }
        if (! empty($filters['date_range_error'])) {
            $where[] = '1 = 0';
        } else {
            if (($filters['date_from'] ?? null) !== null) {
                $where[] = "cl.collection_date >= '" . addslashes((string) $filters['date_from']) . "'";
            }
            if (($filters['date_to'] ?? null) !== null) {
                $where[] = "cl.collection_date <= '" . addslashes((string) $filters['date_to']) . "'";
            }
        }

        $limit = max(1, min(500, $limit));
        $sql = "SELECT cl.id, cl.payment_id, cl.financial_direction, cl.currency_code, cl.amount,
                       cl.collection_date, cl.payment_method_id, cl.reference, cl.details,
                       cl.proof_media_id, cl.created_by, cl.created_at, cl.is_archived,
                       p.reference AS payment_reference, p.sequence_no, p.due_date,
                       p.status AS payment_status, p.remaining_amount,
                       c.id AS contract_id, c.contract_number, c.accountant_user_id,
                       c.counterparty_type, c.counterparty_id,
                       CASE WHEN c.counterparty_type = 'customer' THEN cu.name
                            WHEN c.counterparty_type = 'supplier' THEN s.name
                            ELSE NULL END AS counterparty_name,
                       CASE WHEN c.counterparty_type = 'customer' THEN cu.name ELSE NULL END AS customer_name,
                       CASE WHEN c.counterparty_type = 'supplier' THEN s.name ELSE NULL END AS supplier_name,
                       pm.name AS payment_method_name
                FROM {$collections} cl
                INNER JOIN {$payments} p ON p.id = cl.payment_id
                INNER JOIN {$contracts} c ON c.id = p.contract_id
                LEFT JOIN {$customers} cu ON c.counterparty_type = 'customer' AND cu.id = c.counterparty_id
                LEFT JOIN {$suppliers} s ON c.counterparty_type = 'supplier' AND s.id = c.counterparty_id
                INNER JOIN {$methods} pm ON pm.id = cl.payment_method_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY cl.collection_date DESC, cl.id DESC
                LIMIT {$limit}";

        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }
}
