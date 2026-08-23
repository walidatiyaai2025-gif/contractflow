<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use RuntimeException;

final class ContractPaymentTreeRepository
{
    /**
     * The supplied contract IDs must come from AdminReadRepository::contracts(),
     * which already applies the current user's governed data scope.
     *
     * @param list<int> $contractIds
     * @return array<int,list<array<string,mixed>>>
     */
    public function forVisibleContracts(array $contractIds): array
    {
        global $wpdb;
        if (! is_object($wpdb) || ! method_exists($wpdb, 'get_results')) {
            throw new RuntimeException('SafeContracts contract payment tree requires WordPress $wpdb.');
        }

        $contractIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $contractIds),
            static fn (int $id): bool => $id > 0
        )));
        if ($contractIds === []) {
            return [];
        }

        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $idList = implode(',', $contractIds);
        $rows = $wpdb->get_results(
            "SELECT id, contract_id, financial_direction, currency_code, sequence_no, reference,
                    due_date, expected_payment_date, original_amount, paid_amount,
                    remaining_amount, status, is_archived
             FROM {$payments}
             WHERE is_archived = 0 AND contract_id IN ({$idList})
             ORDER BY contract_id ASC, due_date ASC, sequence_no ASC, id ASC",
            ARRAY_A
        );
        if (! is_array($rows)) {
            return [];
        }

        $grouped = [];
        foreach ($rows as $row) {
            $contractId = (int) ($row['contract_id'] ?? 0);
            if ($contractId <= 0 || ! in_array($contractId, $contractIds, true)) {
                continue;
            }
            $grouped[$contractId] ??= [];
            $grouped[$contractId][] = [
                'id' => (int) ($row['id'] ?? 0),
                'contract_id' => $contractId,
                'financial_direction' => (string) ($row['financial_direction'] ?? ''),
                'currency_code' => (string) ($row['currency_code'] ?? ''),
                'sequence_no' => (int) ($row['sequence_no'] ?? 0),
                'reference' => isset($row['reference']) && $row['reference'] !== null ? (string) $row['reference'] : null,
                'due_date' => (string) ($row['due_date'] ?? ''),
                'expected_payment_date' => isset($row['expected_payment_date']) && $row['expected_payment_date'] !== null ? (string) $row['expected_payment_date'] : null,
                'original_amount' => (string) ($row['original_amount'] ?? '0.0000'),
                'paid_amount' => (string) ($row['paid_amount'] ?? '0.0000'),
                'remaining_amount' => (string) ($row['remaining_amount'] ?? '0.0000'),
                'status' => (string) ($row['status'] ?? ''),
            ];
        }

        return $grouped;
    }
}
