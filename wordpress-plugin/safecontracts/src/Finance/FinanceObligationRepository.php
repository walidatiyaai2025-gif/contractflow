<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use RuntimeException;

final class FinanceObligationRepository
{
    /** @return list<array<string,mixed>> */
    public function obligations(array $input = []): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $filters = FinanceReadFilters::normalize($input);
        $directions = FinanceReadAccess::resolveDirections($filters['direction']);
        if ($directions === []) {
            return [];
        }

        $scope = FinanceReadSql::where($filters, $directions);
        $today = function_exists('wp_date') ? wp_date('Y-m-d') : gmdate('Y-m-d');
        $todaySql = $wpdb->prepare('%s', $today);
        $bucket = AgingBucket::sqlCase('p.due_date', $todaySql);
        $where = $scope['where'];
        $args = $scope['args'];
        if ($filters['aging_bucket'] !== '') {
            $where .= " AND ({$bucket}) = %s";
            $args[] = $filters['aging_bucket'];
        }

        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $suppliers = $wpdb->prefix . 'safecontracts_suppliers';
        $currency = FinanceReadSql::currencyExpression();
        $limit = $filters['limit'];

        $sql = "SELECT p.id, p.contract_id, p.sequence_no, p.reference, p.due_date, p.expected_payment_date,
                       p.original_amount, p.paid_amount AS settled_amount, p.remaining_amount, p.status,
                       p.financial_direction, {$currency} AS currency_code,
                       {$bucket} AS aging_bucket,
                       c.contract_number, c.counterparty_type, c.counterparty_id, c.accountant_user_id,
                       CASE
                           WHEN c.counterparty_type = 'customer' THEN cu.name
                           WHEN c.counterparty_type = 'supplier' THEN s.legal_name
                           ELSE NULL
                       END AS counterparty_name
                FROM {$payments} p
                INNER JOIN {$contracts} c ON c.id = p.contract_id
                LEFT JOIN {$customers} cu ON c.counterparty_type = 'customer' AND cu.id = c.counterparty_id
                LEFT JOIN {$suppliers} s ON c.counterparty_type = 'supplier' AND s.id = c.counterparty_id
                WHERE {$where}
                ORDER BY CASE WHEN p.remaining_amount > 0 AND p.due_date < {$todaySql} THEN 0 ELSE 1 END ASC,
                         p.due_date ASC, p.id ASC
                LIMIT {$limit}";
        $query = $args === [] ? $sql : $wpdb->prepare($sql, ...$args);

        return $this->rows($wpdb->get_results($query, ARRAY_A));
    }

    /** @return list<array<string,mixed>> */
    private function rows(mixed $rows): array
    {
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    private function assertWpdb(mixed $wpdb): void
    {
        if (! is_object($wpdb) || ! method_exists($wpdb, 'prepare') || ! method_exists($wpdb, 'get_results')) {
            throw new RuntimeException('SafeContracts finance work queues require WordPress $wpdb.');
        }
    }
}
