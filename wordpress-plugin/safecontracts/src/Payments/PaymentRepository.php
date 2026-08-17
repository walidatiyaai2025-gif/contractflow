<?php

declare(strict_types=1);

namespace SafeContracts\Payments;

use RuntimeException;

final class PaymentRepository
{
    /** @return array<string,mixed>|null */
    public function contractContext(int $contractId): ?array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);

        $table = $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, accountant_user_id, is_archived, counterparty_type, counterparty_id,
                        financial_direction, currency_code
                 FROM {$table} WHERE id = %d LIMIT 1",
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
            'accountant_user_id' => isset($row['accountant_user_id']) && $row['accountant_user_id'] !== null
                ? (int) $row['accountant_user_id']
                : null,
            'is_archived' => (bool) ($row['is_archived'] ?? false),
            'counterparty_type' => (string) ($row['counterparty_type'] ?? ''),
            'counterparty_id' => isset($row['counterparty_id']) && $row['counterparty_id'] !== null
                ? (int) $row['counterparty_id']
                : null,
            'financial_direction' => (string) ($row['financial_direction'] ?? ''),
            'currency_code' => strtoupper(trim((string) ($row['currency_code'] ?? ''))),
        ];
    }

    /** @return array<string,mixed>|null */
    public function find(int $paymentId): ?array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);

        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.id, p.contract_id, p.financial_direction, p.currency_code, p.sequence_no, p.reference,
                        p.due_date, p.expected_payment_date, p.original_amount, p.paid_amount, p.remaining_amount,
                        p.status, p.is_archived, c.accountant_user_id, c.is_archived AS contract_is_archived,
                        c.counterparty_type, c.counterparty_id
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
            'financial_direction' => (string) ($row['financial_direction'] ?? ''),
            'currency_code' => strtoupper(trim((string) ($row['currency_code'] ?? ''))),
            'sequence_no' => (int) ($row['sequence_no'] ?? 0),
            'reference' => isset($row['reference']) && $row['reference'] !== null ? (string) $row['reference'] : null,
            'due_date' => (string) ($row['due_date'] ?? ''),
            'expected_payment_date' => isset($row['expected_payment_date']) && $row['expected_payment_date'] !== null
                ? (string) $row['expected_payment_date']
                : null,
            'original_amount' => (string) ($row['original_amount'] ?? '0.0000'),
            'paid_amount' => (string) ($row['paid_amount'] ?? '0.0000'),
            'remaining_amount' => (string) ($row['remaining_amount'] ?? '0.0000'),
            'status' => (string) ($row['status'] ?? PaymentStatus::UPCOMING),
            'is_archived' => (bool) ($row['is_archived'] ?? false),
            'accountant_user_id' => isset($row['accountant_user_id']) && $row['accountant_user_id'] !== null
                ? (int) $row['accountant_user_id']
                : null,
            'contract_is_archived' => (bool) ($row['contract_is_archived'] ?? false),
            'counterparty_type' => (string) ($row['counterparty_type'] ?? ''),
            'counterparty_id' => isset($row['counterparty_id']) && $row['counterparty_id'] !== null
                ? (int) $row['counterparty_id']
                : null,
        ];
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

        // PaymentService already resolves and authorizes the contract context. Reuse
        // that validated context when supplied so creation remains one read + one
        // mutation and does not introduce a redundant contract query.
        if ($financialDirection === null || $currencyCode === null) {
            $contract = $this->contractContext($contractId);
            if ($contract === null) {
                throw new RuntimeException('Unable to resolve payment contract context.');
            }
            $financialDirection ??= (string) ($contract['financial_direction'] ?? '');
            $currencyCode ??= (string) ($contract['currency_code'] ?? '');
        }

        $direction = trim($financialDirection);
        $currency = strtoupper(trim($currencyCode));
        $referenceSql = $reference === null ? 'NULL' : '%s';
        $expectedSql = $expectedPaymentDate === null ? 'NULL' : '%s';
        $directionSql = $direction === '' ? 'NULL' : '%s';
        $currencySql = $currency === '' ? 'NULL' : '%s';
        $query = "INSERT INTO {$table}
            (contract_id, financial_direction, currency_code, sequence_no, reference, due_date, expected_payment_date,
             original_amount, paid_amount, remaining_amount, status, created_by, updated_by, created_at, updated_at)
            VALUES (%d, {$directionSql}, {$currencySql}, %d, {$referenceSql}, %s, {$expectedSql}, %s, '0.0000', %s, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())";

        $args = [$contractId];
        if ($direction !== '') {
            $args[] = $direction;
        }
        if ($currency !== '') {
            $args[] = $currency;
        }
        $args[] = $sequenceNo;
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
            "UPDATE {$table}
             SET status = %s, updated_by = %d, updated_at = UTC_TIMESTAMP()
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
                "UPDATE {$table}
                 SET due_date = %s, expected_payment_date = NULL, updated_by = %d, updated_at = UTC_TIMESTAMP()
                 WHERE id = %d AND is_archived = 0",
                $dueDate,
                $actorId,
                $paymentId
            );
        } else {
            $sql = $wpdb->prepare(
                "UPDATE {$table}
                 SET due_date = %s, expected_payment_date = %s, updated_by = %d, updated_at = UTC_TIMESTAMP()
                 WHERE id = %d AND is_archived = 0",
                $dueDate,
                $expectedPaymentDate,
                $actorId,
                $paymentId
            );
        }

        $this->executeMutation($wpdb, $sql, 'Unable to update payment dates.');
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
