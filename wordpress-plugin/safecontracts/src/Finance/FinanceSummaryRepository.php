<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use DateTimeImmutable;
use RuntimeException;

final class FinanceSummaryRepository
{
    /** @return list<array<string,mixed>> */
    public function summary(array $input = []): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $filters = FinanceReadFilters::normalize($input);
        $directions = FinanceReadAccess::resolveDirections($filters['direction']);
        if ($directions === []) {
            return [];
        }

        $scope = FinanceReadSql::where($filters, $directions);
        $currency = FinanceReadSql::currencyExpression();
        $today = $this->today();
        $plus7 = $this->datePlusDays($today, 7);
        $plus30 = $this->datePlusDays($today, 30);
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';

        $sql = "SELECT p.financial_direction,
                       {$currency} AS currency_code,
                       COUNT(p.id) AS obligation_count,
                       COALESCE(SUM(p.original_amount), 0) AS original_total,
                       COALESCE(SUM(p.paid_amount), 0) AS settled_total,
                       COALESCE(SUM(p.remaining_amount), 0) AS outstanding_total,
                       COALESCE(SUM(CASE WHEN p.remaining_amount > 0 AND p.due_date < %s THEN p.remaining_amount ELSE 0 END), 0) AS overdue_total,
                       SUM(CASE WHEN p.remaining_amount > 0 AND p.due_date < %s THEN 1 ELSE 0 END) AS overdue_count,
                       COALESCE(SUM(CASE WHEN p.remaining_amount > 0 AND p.due_date = %s THEN p.remaining_amount ELSE 0 END), 0) AS due_today_total,
                       SUM(CASE WHEN p.remaining_amount > 0 AND p.due_date = %s THEN 1 ELSE 0 END) AS due_today_count,
                       COALESCE(SUM(CASE WHEN p.remaining_amount > 0 AND p.due_date > %s AND p.due_date <= %s THEN p.remaining_amount ELSE 0 END), 0) AS due_7_total,
                       SUM(CASE WHEN p.remaining_amount > 0 AND p.due_date > %s AND p.due_date <= %s THEN 1 ELSE 0 END) AS due_7_count,
                       COALESCE(SUM(CASE WHEN p.remaining_amount > 0 AND p.due_date > %s AND p.due_date <= %s THEN p.remaining_amount ELSE 0 END), 0) AS due_30_total,
                       SUM(CASE WHEN p.remaining_amount > 0 AND p.due_date > %s AND p.due_date <= %s THEN 1 ELSE 0 END) AS due_30_count,
                       COALESCE(SUM(CASE WHEN p.remaining_amount > 0 AND p.due_date > %s THEN p.remaining_amount ELSE 0 END), 0) AS upcoming_total
                FROM {$payments} p
                INNER JOIN {$contracts} c ON c.id = p.contract_id
                WHERE {$scope['where']}
                GROUP BY p.financial_direction, {$currency}
                ORDER BY p.financial_direction ASC, currency_code ASC";
        $args = [
            $today, $today, $today, $today,
            $today, $plus7, $today, $plus7,
            $today, $plus30, $today, $plus30,
            $today,
            ...$scope['args'],
        ];

        return $this->rows($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A));
    }

    /** @return list<array<string,mixed>> */
    public function cashFlow(array $input = [], int $days = 90): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $filters = FinanceReadFilters::normalize($input);
        $directions = FinanceReadAccess::resolveDirections($filters['direction']);
        if ($directions === []) {
            return [];
        }

        $days = max(1, min(365, $days));
        $today = $this->today();
        $through = $this->datePlusDays($today, $days);
        $scope = FinanceReadSql::where($filters, $directions, false);
        $currency = FinanceReadSql::currencyExpression();
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';

        $sql = "SELECT p.due_date,
                       p.financial_direction,
                       {$currency} AS currency_code,
                       COUNT(p.id) AS obligation_count,
                       COALESCE(SUM(p.remaining_amount), 0) AS expected_amount
                FROM {$payments} p
                INNER JOIN {$contracts} c ON c.id = p.contract_id
                WHERE {$scope['where']}
                  AND p.remaining_amount > 0
                  AND p.due_date >= %s
                  AND p.due_date <= %s
                GROUP BY p.due_date, p.financial_direction, {$currency}
                ORDER BY p.due_date ASC, p.financial_direction ASC, currency_code ASC";
        $args = [...$scope['args'], $today, $through];

        return $this->rows($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A));
    }

    private function today(): string
    {
        return function_exists('wp_date') ? wp_date('Y-m-d') : gmdate('Y-m-d');
    }

    private function datePlusDays(string $date, int $days): string
    {
        return (new DateTimeImmutable($date))->modify('+' . $days . ' days')->format('Y-m-d');
    }

    /** @return list<array<string,mixed>> */
    private function rows(mixed $rows): array
    {
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    private function assertWpdb(mixed $wpdb): void
    {
        if (! is_object($wpdb) || ! method_exists($wpdb, 'prepare') || ! method_exists($wpdb, 'get_results')) {
            throw new RuntimeException('SafeContracts finance summaries require WordPress $wpdb.');
        }
    }
}
