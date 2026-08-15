<?php

declare(strict_types=1);

namespace SafeContracts\Payments;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Roles\Capabilities;

final class PaymentService
{
    public function __construct(private ?PaymentRepository $repository = null)
    {
        $this->repository ??= new PaymentRepository();
    }

    /** @param array{contract_id:mixed, sequence_no:mixed, reference?:mixed, due_date:mixed, expected_payment_date?:mixed, original_amount:mixed} $input */
    public function create(array $input): int
    {
        $this->requireCapability(Capabilities::MANAGE_PAYMENTS, 'You do not have permission to manage payments.');

        $contractId = (int) ($input['contract_id'] ?? 0);
        if ($contractId <= 0) {
            throw new InvalidArgumentException('Payment contract ID must be positive.');
        }

        $contract = $this->repository->contractContext($contractId);
        if ($contract === null) {
            throw new InvalidArgumentException('Payment contract was not found.');
        }
        $this->assertScope($contract['accountant_user_id']);
        if ($contract['is_archived']) {
            throw new DomainException('Archived contracts cannot receive scheduled payments.');
        }

        $sequenceNo = (int) ($input['sequence_no'] ?? 0);
        if ($sequenceNo <= 0) {
            throw new InvalidArgumentException('Payment sequence number must be positive.');
        }

        $reference = $this->normalizeReference($input['reference'] ?? null);
        $dueDate = $this->normalizeRequiredDate($input['due_date'] ?? null, 'due date');
        $expectedPaymentDate = $this->normalizeOptionalDate($input['expected_payment_date'] ?? null, 'expected payment date');
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
            $expectedPaymentDate,
            $amount,
            $actorId
        );
        do_action(
            'safecontracts_payment_created',
            $paymentId,
            $contractId,
            $sequenceNo,
            $dueDate,
            $expectedPaymentDate,
            $amount,
            $actorId
        );

        return $paymentId;
    }

    public function changeStatus(int $paymentId, mixed $targetStatus): void
    {
        $this->requireCapability(Capabilities::MANAGE_PAYMENTS, 'You do not have permission to manage payments.');
        $payment = $this->editablePayment($paymentId);
        $target = PaymentStatus::normalize($targetStatus);
        $current = PaymentStatus::normalize($payment['status']);
        PaymentStatus::assertTransition($current, $target);

        if ($current === $target) {
            return;
        }

        $actorId = get_current_user_id();
        $this->repository->updateStatus($paymentId, $target, $actorId);
        do_action('safecontracts_payment_status_changed', $paymentId, $current, $target, $actorId);
    }

    public function updateDates(int $paymentId, mixed $dueDate, mixed $expectedPaymentDate): void
    {
        $this->requireCapability(Capabilities::MANAGE_PAYMENTS, 'You do not have permission to manage payment dates.');
        $payment = $this->editablePayment($paymentId);
        $due = $this->normalizeRequiredDate($dueDate, 'due date');
        $expected = $this->normalizeOptionalDate($expectedPaymentDate, 'expected payment date');
        $actorId = get_current_user_id();

        $this->repository->updateDates($paymentId, $due, $expected, $actorId);
        do_action(
            'safecontracts_payment_dates_changed',
            $paymentId,
            $payment['due_date'],
            $due,
            $payment['expected_payment_date'],
            $expected,
            $actorId
        );
    }

    public function effectiveDate(int $paymentId): string
    {
        $payment = $this->requirePayment($paymentId);
        $this->assertScope($payment['accountant_user_id']);

        return $payment['expected_payment_date'] ?? $payment['due_date'];
    }

    public function temporalStatus(
        int $paymentId,
        ?DateTimeImmutable $today = null,
        int $dueSoonDays = 10
    ): string {
        $this->requireCapability(Capabilities::ACCESS, 'You do not have access to SafeContracts payments.');
        $payment = $this->requirePayment($paymentId);
        $this->assertScope($payment['accountant_user_id']);

        if ($payment['status'] === PaymentStatus::PAID || $payment['status'] === PaymentStatus::PARTIALLY_PAID) {
            return PaymentStatus::normalize($payment['status']);
        }

        // Contractual due_date remains authoritative for Due/Due Soon/Overdue.
        // expected_payment_date is an operational promise and never rewrites due history.
        return PaymentStatus::temporalForDueDate($payment['due_date'], $today, $dueSoonDays);
    }

    /** @return array{id:int, contract_id:int, sequence_no:int, reference:?string, due_date:string, expected_payment_date:?string, original_amount:string, paid_amount:string, remaining_amount:string, status:string, accountant_user_id:?int, contract_is_archived:bool} */
    private function editablePayment(int $paymentId): array
    {
        $payment = $this->requirePayment($paymentId);
        $this->assertScope($payment['accountant_user_id']);
        if ($payment['contract_is_archived']) {
            throw new DomainException('Payments on archived contracts cannot be edited.');
        }

        return $payment;
    }

    /** @return array{id:int, contract_id:int, sequence_no:int, reference:?string, due_date:string, expected_payment_date:?string, original_amount:string, paid_amount:string, remaining_amount:string, status:string, accountant_user_id:?int, contract_is_archived:bool} */
    private function requirePayment(int $paymentId): array
    {
        if ($paymentId <= 0) {
            throw new InvalidArgumentException('Payment ID must be positive.');
        }

        $payment = $this->repository->find($paymentId);
        if ($payment === null) {
            throw new InvalidArgumentException('Payment was not found.');
        }

        return $payment;
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

    private function normalizeReference(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $reference = trim((string) $value);
        if (strlen($reference) > 100) {
            throw new InvalidArgumentException('Payment reference must not exceed 100 characters.');
        }

        return $reference;
    }

    private function normalizeRequiredDate(mixed $value, string $field): string
    {
        $date = $this->normalizeOptionalDate($value, $field);
        if ($date === null) {
            throw new InvalidArgumentException("Payment {$field} is required.");
        }

        return $date;
    }

    private function normalizeOptionalDate(mixed $value, string $field): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $date = trim((string) $value);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Payment {$field} must use YYYY-MM-DD and be a valid calendar date.");
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
