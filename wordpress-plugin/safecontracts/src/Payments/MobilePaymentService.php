<?php

declare(strict_types=1);

namespace SafeContracts\Payments;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Roles\Capabilities;

final class MobilePaymentService
{
    public function __construct(private ?PaymentRepository $repository = null)
    {
        $this->repository ??= new PaymentRepository();
    }

    public function create(array $input): int
    {
        $this->requireCapability(Capabilities::CREATE_PAYMENTS, 'You do not have permission to create payments.');

        $contractId = $this->positiveInt($input['contract_id'] ?? 0, 'Payment contract ID');
        $contract = $this->repository->contractContext($contractId);
        if ($contract === null) {
            throw new InvalidArgumentException('Payment contract was not found.');
        }
        $this->assertScope($contract['accountant_user_id']);
        if ($contract['is_archived']) {
            throw new DomainException('Archived contracts cannot receive scheduled payments.');
        }

        $sequenceNo = $this->positiveInt($input['sequence_no'] ?? 0, 'Payment sequence number');
        $reference = $this->reference($input['reference'] ?? null);
        $dueDate = $this->requiredDate($input['due_date'] ?? null, 'due date');
        $expectedDate = $this->optionalDate($input['expected_payment_date'] ?? null, 'expected payment date');
        $amount = ContractMoney::normalizeNonNegative($input['original_amount'] ?? '');
        if ($amount === '0.0000') {
            throw new InvalidArgumentException('Payment original amount must be greater than zero.');
        }

        $actorId = get_current_user_id();
        $paymentId = $this->repository->create(
            $contractId,
            $sequenceNo,
            $reference,
            $dueDate,
            $expectedDate,
            $amount,
            $actorId
        );
        do_action(
            'safecontracts_payment_created',
            $paymentId,
            $contractId,
            $sequenceNo,
            $dueDate,
            $expectedDate,
            $amount,
            $actorId
        );
        return $paymentId;
    }

    public function update(int $paymentId, array $input): void
    {
        $this->requireCapability(Capabilities::EDIT_PAYMENTS, 'You do not have permission to edit payments.');
        if ($paymentId <= 0) {
            throw new InvalidArgumentException('Payment ID must be positive.');
        }
        $payment = $this->repository->find($paymentId);
        if ($payment === null) {
            throw new InvalidArgumentException('Payment was not found.');
        }
        $this->assertScope($payment['accountant_user_id']);
        if ($payment['is_archived'] || $payment['contract_is_archived']) {
            throw new DomainException('Archived payments or contracts cannot be edited.');
        }

        $sequenceNo = array_key_exists('sequence_no', $input)
            ? $this->positiveInt($input['sequence_no'], 'Payment sequence number')
            : $payment['sequence_no'];
        $reference = array_key_exists('reference', $input)
            ? $this->reference($input['reference'])
            : $payment['reference'];
        $dueDate = array_key_exists('due_date', $input)
            ? $this->requiredDate($input['due_date'], 'due date')
            : $payment['due_date'];
        $expectedDate = array_key_exists('expected_payment_date', $input)
            ? $this->optionalDate($input['expected_payment_date'], 'expected payment date')
            : $payment['expected_payment_date'];
        $amount = array_key_exists('original_amount', $input)
            ? ContractMoney::normalizeNonNegative($input['original_amount'])
            : $payment['original_amount'];
        if ($amount === '0.0000') {
            throw new InvalidArgumentException('Payment original amount must be greater than zero.');
        }

        if ($amount !== $payment['original_amount'] && ContractMoney::compare($payment['paid_amount'], '0.0000') > 0) {
            throw new DomainException('Payment amount cannot be changed after a collection has been recorded.');
        }

        $remaining = ContractMoney::compare($payment['paid_amount'], '0.0000') === 0
            ? $amount
            : $payment['remaining_amount'];
        $this->updateDefinition(
            $paymentId,
            $sequenceNo,
            $reference,
            $dueDate,
            $expectedDate,
            $amount,
            $remaining,
            get_current_user_id()
        );
        do_action('safecontracts_payment_mobile_updated', $paymentId, get_current_user_id());
    }

    private function updateDefinition(
        int $paymentId,
        int $sequenceNo,
        ?string $reference,
        string $dueDate,
        ?string $expectedDate,
        string $amount,
        string $remaining,
        int $actorId
    ): void {
        global $wpdb;
        if (! is_object($wpdb)) {
            throw new RuntimeException('SafeContracts payments require WordPress $wpdb.');
        }
        $table = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $referenceSql = $reference === null ? 'NULL' : '%s';
        $expectedSql = $expectedDate === null ? 'NULL' : '%s';
        $query = "UPDATE {$table}
            SET sequence_no = %d, reference = {$referenceSql}, due_date = %s,
                expected_payment_date = {$expectedSql}, original_amount = %s,
                remaining_amount = %s, updated_by = %d, updated_at = UTC_TIMESTAMP()
            WHERE id = %d AND is_archived = 0";
        $args = [$sequenceNo];
        if ($reference !== null) {
            $args[] = $reference;
        }
        $args[] = $dueDate;
        if ($expectedDate !== null) {
            $args[] = $expectedDate;
        }
        $args[] = $amount;
        $args[] = $remaining;
        $args[] = $actorId;
        $args[] = $paymentId;
        $sql = $wpdb->prepare($query, ...$args);
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to update scheduled payment.');
        }
    }

    private function assertScope(?int $accountantUserId): void
    {
        if (current_user_can(Capabilities::VIEW_ALL)) {
            return;
        }
        if (
            current_user_can(Capabilities::VIEW_ASSIGNED)
            && $accountantUserId !== null
            && $accountantUserId === get_current_user_id()
        ) {
            return;
        }
        throw new DomainException('Payment is outside the current user data scope.');
    }

    private function positiveInt(mixed $value, string $label): int
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        if (! is_int($value) && (! is_string($value) || ! preg_match('/^[1-9]\d*$/', $value))) {
            throw new InvalidArgumentException("{$label} must be positive.");
        }
        $parsed = (int) $value;
        if ($parsed <= 0) {
            throw new InvalidArgumentException("{$label} must be positive.");
        }
        return $parsed;
    }

    private function reference(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        if (is_array($value) || is_object($value)) {
            throw new InvalidArgumentException('Payment reference must be text.');
        }
        $reference = trim(strip_tags((string) $value));
        if (strlen($reference) > 100) {
            throw new InvalidArgumentException('Payment reference must not exceed 100 characters.');
        }
        return $reference;
    }

    private function requiredDate(mixed $value, string $field): string
    {
        $date = $this->optionalDate($value, $field);
        if ($date === null) {
            throw new InvalidArgumentException("Payment {$field} is required.");
        }
        return $date;
    }

    private function optionalDate(mixed $value, string $field): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        if (is_array($value) || is_object($value)) {
            throw new InvalidArgumentException("Payment {$field} must use YYYY-MM-DD.");
        }
        $date = trim((string) $value);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $parsed === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $parsed->format('Y-m-d') !== $date
        ) {
            throw new InvalidArgumentException("Payment {$field} must use YYYY-MM-DD and be valid.");
        }
        return $date;
    }

    private function requireCapability(string $capability, string $message): void
    {
        if (! current_user_can($capability)) {
            throw new DomainException($message);
        }
    }
}
