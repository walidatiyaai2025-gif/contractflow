<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use DomainException;
use SafeContracts\Payments\FinancialDirection;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;

/**
 * Server-authoritative monthly cash movement for the Dashboard chart.
 *
 * Values come exclusively from the immutable settlement ledger and remain
 * grouped by currency and accounting direction. Cross-currency arithmetic is
 * intentionally impossible in this read model.
 */
final class DashboardMonthlyFlowRepository
{
    /** @return list<array{month:int,financial_direction:string,currency_code:string,settled_total:string}> */
    public function forYear(array $filters = []): array
    {
        global $wpdb;
        if (! is_object($wpdb) || ! method_exists($wpdb, 'get_results') || ! method_exists($wpdb, 'prepare')) {
            throw new DomainException('SafeContracts monthly flow requires WordPress database access.');
        }
        if (! current_user_can(Capabilities::ACCESS)) {
            throw new DomainException('SafeContracts monthly flow requires access capability.');
        }

        $filters['month'] = 0;
        $filters = DashboardFilters::normalize($filters);
        $year = (int) ($filters['year'] ?? 0);
        if ($year === 0) {
            $year = (int) current_time('Y');
            $filters['year'] = $year;
            $filters['date_from'] = sprintf('%04d-01-01', $year);
            $filters['date_to'] = sprintf('%04d-12-31', $year);
        }

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
        $args = [];

        if (current_user_can(Capabilities::VIEW_ALL)) {
            if (($filters['accountant_user_id'] ?? 0) > 0) {
                $where[] = 'c.accountant_user_id = %d';
                $args[] = (int) $filters['accountant_user_id'];
            }
        } else {
            if (! current_user_can(Capabilities::VIEW_ASSIGNED)) {
                throw new DomainException('SafeContracts monthly flow is outside the current user scope.');
            }
            $where[] = 'c.accountant_user_id = %d';
            $args[] = get_current_user_id();
        }

        if (($filters['counterparty_type'] ?? '') !== '') {
            $type = (string) $filters['counterparty_type'] === 'supplier' ? 'supplier' : 'customer';
            $where[] = 'c.counterparty_type = %s';
            $args[] = $type;
        }
        if (($filters['counterparty_id'] ?? 0) > 0) {
            $where[] = 'c.counterparty_id = %d';
            $args[] = (int) $filters['counterparty_id'];
        }
        if (($filters['contract_id'] ?? 0) > 0) {
            $where[] = 'c.id = %d';
            $args[] = (int) $filters['contract_id'];
        }
        if (($filters['financial_direction'] ?? '') !== '') {
            $direction = FinancialDirection::normalize((string) $filters['financial_direction']);
            $where[] = 'cl.financial_direction = %s';
            $args[] = $direction;
        }
        if (($filters['currency_code'] ?? '') !== '') {
            $currency = strtoupper(trim((string) $filters['currency_code']));
            $where[] = 'cl.currency_code = %s';
            $args[] = $currency;
        }
        $status = (string) ($filters['status'] ?? '');
        if ($status !== '' && in_array($status, PaymentStatus::all(), true)) {
            $where[] = 'p.status = %s';
            $args[] = $status;
        }
        $where[] = 'cl.collection_date >= %s';
        $args[] = sprintf('%04d-01-01', $year);
        $where[] = 'cl.collection_date <= %s';
        $args[] = sprintf('%04d-12-31', $year);

        $sql = "SELECT MONTH(cl.collection_date) AS month_number,
                       cl.financial_direction,
                       COALESCE(NULLIF(cl.currency_code, ''), 'UNKNOWN') AS currency_code,
                       COALESCE(SUM(cl.amount), 0) AS settled_total
                FROM {$collections} cl
                INNER JOIN {$payments} p ON p.id = cl.payment_id
                INNER JOIN {$contracts} c ON c.id = p.contract_id
                LEFT JOIN {$customers} cu ON c.counterparty_type = 'customer' AND cu.id = c.counterparty_id
                LEFT JOIN {$suppliers} s ON c.counterparty_type = 'supplier' AND s.id = c.counterparty_id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY MONTH(cl.collection_date), cl.financial_direction,
                         COALESCE(NULLIF(cl.currency_code, ''), 'UNKNOWN')
                ORDER BY currency_code ASC, month_number ASC, cl.financial_direction ASC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_map(static fn (array $row): array => [
            'month' => max(1, min(12, (int) ($row['month_number'] ?? 1))),
            'financial_direction' => (string) ($row['financial_direction'] ?? ''),
            'currency_code' => (string) ($row['currency_code'] ?? 'UNKNOWN'),
            'settled_total' => (string) ($row['settled_total'] ?? '0.0000'),
        ], array_filter($rows, 'is_array')));
    }
}
