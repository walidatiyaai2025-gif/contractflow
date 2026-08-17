<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

final class FinanceReadSql
{
    /** @return array{where:string,args:list<mixed>} */
    public static function where(array $filters, array $directions, bool $applyDueRange = true): array
    {
        $clauses = ['c.is_archived = 0', 'p.is_archived = 0'];
        $args = [];

        if ($directions === []) {
            return ['where' => '1 = 0', 'args' => []];
        }
        $placeholders = implode(', ', array_fill(0, count($directions), '%s'));
        $clauses[] = "p.financial_direction IN ({$placeholders})";
        array_push($args, ...$directions);

        $scope = FinanceReadAccess::scopeClause((int) ($filters['accountant_user_id'] ?? 0));
        $clauses[] = $scope['clause'];
        array_push($args, ...$scope['args']);

        if (($filters['counterparty_type'] ?? '') !== '') {
            $clauses[] = 'c.counterparty_type = %s';
            $args[] = (string) $filters['counterparty_type'];
        }
        if (($filters['customer_id'] ?? 0) > 0) {
            $clauses[] = "c.counterparty_type = 'customer'";
            $clauses[] = 'c.counterparty_id = %d';
            $args[] = (int) $filters['customer_id'];
        }
        if (($filters['supplier_id'] ?? 0) > 0) {
            $clauses[] = "c.counterparty_type = 'supplier'";
            $clauses[] = 'c.counterparty_id = %d';
            $args[] = (int) $filters['supplier_id'];
        }
        if (($filters['contract_id'] ?? 0) > 0) {
            $clauses[] = 'c.id = %d';
            $args[] = (int) $filters['contract_id'];
        }
        if (($filters['counterparty_id'] ?? 0) > 0) {
            $clauses[] = 'c.counterparty_id = %d';
            $args[] = (int) $filters['counterparty_id'];
        }
        if (($filters['status'] ?? '') !== '') {
            $clauses[] = 'p.status = %s';
            $args[] = (string) $filters['status'];
        }
        if (($filters['currency_code'] ?? '') !== '') {
            if ($filters['currency_code'] === 'UNSET') {
                $clauses[] = "(p.currency_code IS NULL OR p.currency_code = '')";
            } else {
                $clauses[] = 'p.currency_code = %s';
                $args[] = (string) $filters['currency_code'];
            }
        }
        if ($applyDueRange) {
            if (($filters['due_from'] ?? null) !== null) {
                $clauses[] = 'p.due_date >= %s';
                $args[] = (string) $filters['due_from'];
            }
            if (($filters['due_to'] ?? null) !== null) {
                $clauses[] = 'p.due_date <= %s';
                $args[] = (string) $filters['due_to'];
            }
        }

        return ['where' => implode(' AND ', $clauses), 'args' => $args];
    }

    public static function currencyExpression(): string
    {
        return "COALESCE(NULLIF(p.currency_code, ''), 'UNSET')";
    }
}
