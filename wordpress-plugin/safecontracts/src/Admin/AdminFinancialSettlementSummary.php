<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use DomainException;
use SafeContracts\Payments\FinancialDirection;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;

/**
 * Authoritative dashboard aggregation over the immutable settlement ledger.
 *
 * The scheduled-payment table describes obligations. Actual money movement is
 * represented by non-archived payment_collections rows in both AR and AP.
 */
final class AdminFinancialSettlementSummary
{
    /** @return list<array{financial_direction:string,currency_code:string,settlement_count:int,settled_total:string}> */
    public function totals(array $filters = []): array
    {
        global $wpdb;
        if (! is_object($wpdb) || ! method_exists($wpdb, 'get_results')) {
            throw new DomainException('SafeContracts settlement summary requires WordPress database access.');
        }
        if (! current_user_can(Capabilities::ACCESS)) {
            throw new DomainException('SafeContracts settlement summary requires access capability.');
        }

        $filters = DashboardFilters::normalize($filters);
        $collections = $wpdb->prefix . 'safecontracts_payment_collections';
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $suppliers = $wpdb->prefix . 'safecontracts_suppliers';

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
                throw new DomainException('SafeContracts settlement summary is outside the current user scope.');
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

        $sql = "SELECT cl.financial_direction,
                       COALESCE(NULLIF(cl.currency_code, ''), 'UNKNOWN') AS currency_code,
                       COUNT(cl.id) AS settlement_count,
                       COALESCE(SUM(cl.amount), 0) AS settled_total
                FROM {$collections} cl
                INNER JOIN {$payments} p ON p.id = cl.payment_id
                INNER JOIN {$contracts} c ON c.id = p.contract_id
                LEFT JOIN {$customers} cu ON c.counterparty_type = 'customer' AND cu.id = c.counterparty_id
                LEFT JOIN {$suppliers} s ON c.counterparty_type = 'supplier' AND s.id = c.counterparty_id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY cl.financial_direction, COALESCE(NULLIF(cl.currency_code, ''), 'UNKNOWN')
                ORDER BY currency_code ASC, cl.financial_direction ASC";

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_map(static fn (array $row): array => [
            'financial_direction' => (string) ($row['financial_direction'] ?? ''),
            'currency_code' => (string) ($row['currency_code'] ?? 'UNKNOWN'),
            'settlement_count' => (int) ($row['settlement_count'] ?? 0),
            'settled_total' => (string) ($row['settled_total'] ?? '0.0000'),
        ], array_filter($rows, 'is_array')));
    }
}
