<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use DomainException;
use SafeContracts\Roles\Capabilities;

final class AdminYearOptions
{
    /** @return list<int> */
    public static function forCurrentUser(): array
    {
        global $wpdb;
        if (! is_object($wpdb) || ! current_user_can(Capabilities::ACCESS)) {
            return [];
        }

        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $collections = $wpdb->prefix . 'safecontracts_payment_collections';
        $scope = ['c.is_archived = 0'];
        if (! current_user_can(Capabilities::VIEW_ALL)) {
            if (! current_user_can(Capabilities::VIEW_ASSIGNED)) {
                throw new DomainException('SafeContracts year options are outside the current user scope.');
            }
            $scope[] = 'c.accountant_user_id = ' . get_current_user_id();
        }
        $contractWhere = implode(' AND ', $scope);

        $sql = "SELECT DISTINCT year_value FROM (
                    SELECT YEAR(COALESCE(c.start_date, DATE(c.created_at))) AS year_value
                    FROM {$contracts} c
                    WHERE {$contractWhere}
                    UNION
                    SELECT YEAR(p.due_date) AS year_value
                    FROM {$payments} p
                    INNER JOIN {$contracts} c ON c.id = p.contract_id
                    WHERE {$contractWhere} AND p.is_archived = 0
                    UNION
                    SELECT YEAR(cl.collection_date) AS year_value
                    FROM {$collections} cl
                    INNER JOIN {$payments} p ON p.id = cl.payment_id
                    INNER JOIN {$contracts} c ON c.id = p.contract_id
                    WHERE {$contractWhere} AND p.is_archived = 0 AND cl.is_archived = 0
                ) years
                WHERE year_value BETWEEN 1900 AND 2200
                ORDER BY year_value DESC";
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        $years = [];
        foreach ($rows as $row) {
            $year = (int) ($row['year_value'] ?? 0);
            if ($year >= 1900 && $year <= 2200) {
                $years[$year] = $year;
            }
        }
        rsort($years, SORT_NUMERIC);
        return array_values($years);
    }
}
