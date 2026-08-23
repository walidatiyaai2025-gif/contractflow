<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use DomainException;
use SafeContracts\Contracts\ContractStatus;
use SafeContracts\Roles\Capabilities;

/**
 * Authoritative contract counter for the executive/mobile dashboards.
 *
 * The legacy KPI reader intentionally counts Customer/AR contracts only because
 * its monetary values are receivable-specific. This counter is separate so the
 * displayed contract total can include both Customer and Supplier contracts
 * without weakening the AP/AR accounting boundary.
 */
final class DashboardContractCounter
{
    public static function count(array $filters = []): int
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            throw new DomainException('SafeContracts dashboard data requires access capability.');
        }

        global $wpdb;
        $normalized = DashboardFilters::normalize($filters);
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $suppliers = $wpdb->prefix . 'safecontracts_suppliers';
        $where = ['c.is_archived = 0'];

        if (current_user_can(Capabilities::VIEW_ALL)) {
            if (($normalized['accountant_user_id'] ?? 0) > 0) {
                $where[] = 'c.accountant_user_id = ' . (int) $normalized['accountant_user_id'];
            }
        } elseif (current_user_can(Capabilities::VIEW_ASSIGNED)) {
            $where[] = 'c.accountant_user_id = ' . get_current_user_id();
        } else {
            throw new DomainException('SafeContracts dashboard data is outside the current user scope.');
        }

        if (($normalized['customer_id'] ?? 0) > 0) {
            $where[] = 'c.customer_id = ' . (int) $normalized['customer_id'];
        }
        if (($normalized['counterparty_type'] ?? '') !== '') {
            $where[] = "c.counterparty_type = '" . addslashes((string) $normalized['counterparty_type']) . "'";
        }
        if (($normalized['counterparty_id'] ?? 0) > 0) {
            $where[] = 'c.counterparty_id = ' . (int) $normalized['counterparty_id'];
        }
        if (($normalized['contract_id'] ?? 0) > 0) {
            $where[] = 'c.id = ' . (int) $normalized['contract_id'];
        }
        if (($normalized['financial_direction'] ?? '') !== '') {
            $where[] = "c.financial_direction = '" . addslashes((string) $normalized['financial_direction']) . "'";
        }
        if (($normalized['currency_code'] ?? '') !== '') {
            $where[] = "c.currency_code = '" . addslashes((string) $normalized['currency_code']) . "'";
        }

        $status = (string) ($normalized['status'] ?? '');
        if (in_array($status, ContractStatus::all(), true)) {
            $where[] = "c.status = '" . addslashes($status) . "'";
        }

        $dateExpression = 'COALESCE(c.start_date, DATE(c.created_at))';
        if (! empty($normalized['date_range_error'])) {
            $where[] = '1 = 0';
        } else {
            if (($normalized['date_from'] ?? null) !== null) {
                $where[] = $dateExpression . " >= '" . addslashes((string) $normalized['date_from']) . "'";
            }
            if (($normalized['date_to'] ?? null) !== null) {
                $where[] = $dateExpression . " <= '" . addslashes((string) $normalized['date_to']) . "'";
            }
        }

        // Active Customers retain the historical operational rule; Supplier
        // contracts stay visible for payable auditability even after archival.
        $where[] = "((c.counterparty_type = 'customer' AND cu.is_active = 1)
                     OR c.counterparty_type = 'supplier')";

        $sql = "SELECT COUNT(DISTINCT c.id)
                FROM {$contracts} c
                LEFT JOIN {$customers} cu
                  ON c.counterparty_type = 'customer' AND cu.id = c.counterparty_id
                LEFT JOIN {$suppliers} s
                  ON c.counterparty_type = 'supplier' AND s.id = c.counterparty_id
                WHERE " . implode(' AND ', $where);

        unset($suppliers); // Join is intentionally retained for schema parity/future predicates.
        return max(0, (int) $wpdb->get_var($sql));
    }
}
