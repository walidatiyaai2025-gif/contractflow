<?php

declare(strict_types=1);

namespace SafeContracts\Payments;

use RuntimeException;

final class PaymentRepository
{
    /** @return array{id:int, accountant_user_id:?int, is_archived:bool, counterparty_type:string, counterparty_id:int, financial_direction:string, currency_code:string, base_value:?string, scheduled_total:?string}|null */
    public function contractContext(int $contractId): ?array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);

        $table = $wpdb->prefix . 'safecontracts_contracts';
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.id, c.accountant_user_id, c.is_archived, c.counterparty_type, c.counterparty_id, c.financial_direction, c.currency_code, c.base_value,
                        (SELECT COALESCE(SUM(sp.original_amount), 0.0000) FROM {$payments} sp WHERE sp.contract_id = c.id AND sp.is_archived = 0) AS scheduled_total
                 FROM {$table} c WHERE c.id = %d LIMIT 1",
                $contractId
            ),
            ARRAY_A
        );

        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $row = $rows[0];
        return [
            'id' => (int) ($row['id'] ?? 0),
            'accountant_user_id' => isset($row['accountant_user_id']) && $row['accountant_user_id'] !== null ? (int) $row['accountant_user_id'] : null,
            'is_archived' => (bool) ($row['is_archived'] ?? false),
            'counterparty_type' => (string) ($row['counterparty_type'] ?? ''),
            'counterparty_id' => (int) ($row['counterparty_id'] ?? 0),
            'financial_direction' => self::directionFromRow($row),
            'currency_code' => self::currencyFromRow($row),
            // These keys are always selected in production. Nullable fallbacks keep
            // legacy repository mocks compatible without weakening the real SQL path.
            'base_value' => array_key_exists('base_value', $row) ? (string) $row['base_value'] : null,
            'scheduled_total' => array_key_exists('scheduled_total', $row) ? (string) $row['scheduled_total'] : null,
        ];
    }

    /** @return array{id:int, contract_id:int, financial_direction:string, currency_code:string, sequence_no:int, reference:?string, due_date:string, expected_payment_date:?string, original_amount:string, paid_amount:string, remaining_amount:string, status:string, is_archived:bool, accountant_user_id:?int, contract_is_archived:bool, counterparty_type:string, counterparty_id:int, contract_base_value:?string, contract_scheduled_total:?string}|null */
    public function find(int $paymentId): ?array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.id, p.contract_id, p.financial_direction, p.currency_code, p.sequence_no, p.reference, p.due_date, p.expected_payment_date,
                        p.original_amount, p.paid_amount, p.remaining_amount, p.status, p.is_archived,
                        c.accountant_user_id, c.is_archived AS contract_is_archived, c.counterparty_type, c.counterparty_id, c.base_value AS contract_base_value,
                        (SELECT COALESCE(SUM(sp.original_amount), 0.0000) FROM {$payments} sp WHERE sp.contract_id = c.id AND sp.is_archived = 0) AS contract_scheduled_total
                 FROM {$payments} p
                 INNER JOIN {$contracts} c ON c.id = p.contract_id
                 WHERE p.id = %d AND p.is_archived = 0 LIMIT 1",
                $paymentId
            ),
            ARRAY_A
        );
        if (! is_array($rows) || $rows === []) {
            return null;
        }
        $row = $rows[0];
        return [
            'id' => (int) ($row['id'] ?? 0),
            'contract_id' => (int) ($row['contract_id'] ?? 0),
            'financial_direction' => self::directionFromRow($row),
            'currency_code' => self::currencyFromRow($row),
            'sequence_no' => (int) ($row['sequence_no'] ?? 0),
            'reference' => isset($row['reference']) && $row['reference'] !== null ? (string) $row['reference'] : null,
            'due_date' => (string) ($row['due_date'] ?? ''),
            'expected_payment_date' => isset($row['expected_payment_date']) && $row['expected_payment_date'] !== null ? (string) $row['expected_payment_date'] : null,
            'original_amount' => (string) ($row['original_amount'] ?? '0.0000'),
            'paid_amount' => (string) ($row['paid_amount'] ?? '0.0000'),
            'remaining_amount' => (string) ($row['remaining_amount'] ?? '0.0000'),
            'status' => (string) ($row['status'] ?? PaymentStatus::UPCOMING),
            'is_archived' => (bool) ($row['is_archived'] ?? false),
            'accountant_user_id' => isset($row['accountant_user_id']) && $row['accountant_user_id'] !== null ? (int) $row['accountant_user_id'] : null,
            'contract_is_archived' => (bool) ($row['contract_is_archived'] ?? false),
            'counterparty_type' => (string) ($row['counterparty_type'] ?? ''),
            'counterparty_id' => (int) ($row['counterparty_id'] ?? 0),
            'contract_base_value' => array_key_exists('contract_base_value', $row) ? (string) $row['contract_base_value'] : null,
            'contract_scheduled_total' => array_key_exists('contract_scheduled_total', $row) ? (string) $row['contract_scheduled_total'] : null,
        ];
    }

    /** @param list<int> $contractIds @return array<int,string> */
    public function scheduledTotalsForContracts(array $contractIds): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $contractIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $contractIds),
            static fn (int $id): bool => $id > 0
        )));
        if ($contractIds === []) {
            return [];
        }
        $table = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $idList = implode(',', $contractIds);
        $rows = $wpdb->get_results(
            "SELECT contract_id, COALESCE(SUM(original_amount), 0) AS scheduled_total
             FROM {$table}
             WHERE is_archived = 0 AND contract_id IN ({$idList})
             GROUP BY contract_id",
            ARRAY_A
        );
        if (! is_array($rows)) {
            return [];
        }
        $totals = [];
        foreach ($rows as $row) {
            $contractId = (int) ($row['contract_id'] ?? 0);
            if ($contractId > 0) {
                $totals[$contractId] = (string) ($row['scheduled_total'] ?? '0.0000');
            }
        }
        return $totals;
    }

    public function nextSequenceNo(int $contractId): int
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        if ($contractId <= 0) {
            throw new RuntimeException('Payment contract ID must be positive.');
        }
        $table = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT COALESCE(MAX(sequence_no), 0) + 1 AS next_sequence
                 FROM {$table} WHERE contract_id = %d",
                $contractId
            ),
            ARRAY_A
        );
        $next = is_array($rows) && isset($rows[0]['next_sequence']) ? (int) $rows[0]['next_sequence'] : 1;
        return max(1, $next);
    }

    /** @return array{id:int,sequence_no:int} */
    public function createAutoSequenced(
        int $contractId,
        ?string $reference,
        string $dueDate,
        ?string $expectedPaymentDate,
        string $amount,
        int $actorId,
        string $financialDirection,
        string $currencyCode
    ): array {
        global $wpdb;
        $this->assertWpdb($wpdb);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $sequenceNo = $this->nextSequenceNo($contractId);
            try {
                $id = $this->create(
                    $contractId,
                    $sequenceNo,
                    $reference,
                    $dueDate,
                    $expectedPaymentDate,
                    $amount,
                    $actorId,
                    $financialDirection,
                    $currencyCode
                );
                return ['id' => $id, 'sequence_no' => $sequenceNo];
            } catch (RuntimeException $error) {
                $lastError = strtolower((string) ($wpdb->last_error ?? ''));
                if (! str_contains($lastError, 'duplicate') || $attempt === 2) {
                    throw $error;
                }
            }
        }
        throw new RuntimeException('Unable to allocate scheduled payment sequence.');
    }

    public function create(
        int $contractId,
        int $sequenceNo,
        ?string $reference,
        string $dueDate,
        ?string $expectedPaymentDate,
        string $amount,
        int $actorId,
        ?string $financialDirection = null,
        ?string $currencyCode = null
    ): int {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_scheduled_payments';
        if ($financialDirection === null || $currencyCode === null) {
            $context = $this->contractContext($contractId);
            if ($context === null) {
                throw new RuntimeException('Unable to resolve contract financial context.');
            }
            $financialDirection ??= $context['financial_direction'];
            $currencyCode ??= $context['currency_code'];
        }
        $direction = FinancialDirection::normalize($financialDirection);
        $currency = CurrencyCode::normalize($currencyCode);
        $referenceSql = $reference === null ? 'NULL' : '%s';
        $expectedSql = $expectedPaymentDate === null ? 'NULL' : '%s';
        $query = "INSERT INTO {$table}
            (contract_id, financial_direction, currency_code, sequence_no, reference, due_date, expected_payment_date, original_amount, paid_amount, remaining_amount, status, created_by, updated_by, created_at, updated_at)
            VALUES (%d, %s, %s, %d, {$referenceSql}, %s, {$expectedSql}, %s, '0.0000', %s, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())";
        $args = [$contractId, $direction, $currency, $sequenceNo];
        if ($reference !== null) {
            $args[] = $reference;
        }
        $args[] = $dueDate;
        if ($expectedPaymentDate !== null) {
            $args[] = $expectedPaymentDate;
        }
        $args[] = $amount;
        $args[] = $amount;
        $args[] = PaymentStatus::UPCOMING;
        $args[] = $actorId;
        $args[] = $actorId;
        $sql = $wpdb->prepare($query, ...$args);
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to create scheduled payment.');
        }
        return (int) $wpdb->insert_id;
    }

    public function updateStatus(int $paymentId, string $status, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $sql = $wpdb->prepare(
            "UPDATE {$table} SET status = %s, updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE id = %d AND is_archived = 0",
            $status,
            $actorId,
            $paymentId
        );
        $this->executeMutation($wpdb, $sql, 'Unable to update payment status.');
    }

    public function updateDates(int $paymentId, string $dueDate, ?string $expectedPaymentDate, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_scheduled_payments';
        if ($expectedPaymentDate === null) {
            $sql = $wpdb->prepare(
                "UPDATE {$table} SET due_date = %s, expected_payment_date = NULL, updated_by = %d, updated_at = UTC_TIMESTAMP()
                 WHERE id = %d AND is_archived = 0",
                $dueDate,
                $actorId,
                $paymentId
            );
        } else {
            $sql = $wpdb->prepare(
                "UPDATE {$table} SET due_date = %s, expected_payment_date = %s, updated_by = %d, updated_at = UTC_TIMESTAMP()
                 WHERE id = %d AND is_archived = 0",
                $dueDate,
                $expectedPaymentDate,
                $actorId,
                $paymentId
            );
        }
        $this->executeMutation($wpdb, $sql, 'Unable to update payment dates.');
    }

    public function updateEditable(
        int $paymentId,
        ?string $reference,
        string $dueDate,
        ?string $expectedPaymentDate,
        string $originalAmount,
        string $remainingAmount,
        int $actorId
    ): void {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $referenceSql = $reference === null ? 'NULL' : '%s';
        $expectedSql = $expectedPaymentDate === null ? 'NULL' : '%s';
        $query = "UPDATE {$table}
                  SET reference = {$referenceSql}, due_date = %s, expected_payment_date = {$expectedSql},
                      original_amount = %s, remaining_amount = %s, updated_by = %d, updated_at = UTC_TIMESTAMP()
                  WHERE id = %d AND is_archived = 0";
        $args = [];
        if ($reference !== null) {
            $args[] = $reference;
        }
        $args[] = $dueDate;
        if ($expectedPaymentDate !== null) {
            $args[] = $expectedPaymentDate;
        }
        $args[] = $originalAmount;
        $args[] = $remainingAmount;
        $args[] = $actorId;
        $args[] = $paymentId;
        $this->executeMutation($wpdb, $wpdb->prepare($query, ...$args), 'Unable to update payment details.');
    }

    private static function directionFromRow(array $row): string
    {
        if (! array_key_exists('financial_direction', $row)) {
            return FinancialDirection::RECEIVABLE;
        }
        return FinancialDirection::normalize($row['financial_direction']);
    }

    private static function currencyFromRow(array $row): string
    {
        if (! array_key_exists('currency_code', $row)) {
            return CurrencyCode::UNKNOWN;
        }
        return CurrencyCode::normalize($row['currency_code']);
    }

    private function assertWpdb(mixed $wpdb): void
    {
        if (! is_object($wpdb)) {
            throw new RuntimeException('SafeContracts payments require WordPress $wpdb.');
        }
    }

    private function executeMutation(object $wpdb, string $sql, string $message): void
    {
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException($message);
        }
    }
}
