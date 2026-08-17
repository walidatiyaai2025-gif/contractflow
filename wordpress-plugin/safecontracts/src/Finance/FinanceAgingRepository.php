<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use RuntimeException;

final class FinanceAgingRepository
{
    /** @return list<array<string,mixed>> */
    public function aging(array $input = []): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $filters = FinanceReadFilters::normalize($input);
        $directions = FinanceReadAccess::resolveDirections($filters['direction']);
        if ($directions === []) {
            return [];
        }

        $scope = FinanceReadSql::where($filters, $directions, false);
        $today = function_exists('wp_date') ? wp_date('Y-m-d') : gmdate('Y-m-d');
        $todaySql = $wpdb->prepare('%s', $today);
        $bucket = AgingBucket::sqlCase('p.due_date', $todaySql);
        $where = $scope['where'] . ' AND p.remaining_amount > 0';
        $args = $scope['args'];
        if ($filters['aging_bucket'] !== '') {
            $where .= " AND ({$bucket}) = %s";
            $args[] = $filters['aging_bucket'];
        }

        $currency = FinanceReadSql::currencyExpression();
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $sql = "SELECT p.financial_direction,
                       {$currency} AS currency_code,
                       {$bucket} AS aging_bucket,
                       COUNT(p.id) AS obligation_count,
                       COALESCE(SUM(p.remaining_amount), 0) AS outstanding_total
                FROM {$payments} p
                INNER JOIN {$contracts} c ON c.id = p.contract_id
                WHERE {$where}
                GROUP BY p.financial_direction, {$currency}, aging_bucket
                ORDER BY p.financial_direction ASC, currency_code ASC,
                         FIELD(aging_bucket, 'current', '1_30', '31_60', '61_90', '90_plus')";
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
            throw new RuntimeException('SafeContracts finance aging requires WordPress $wpdb.');
        }
    }
}
