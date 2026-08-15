<?php

declare(strict_types=1);

namespace SafeContracts\Payments;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Contracts\ContractRepository;
use SafeContracts\Contracts\ContractStatus;
use SafeContracts\Roles\Capabilities;

final class PaymentService
{
    public function __construct(
        private ?PaymentRepository $repository = null,
        private ?ContractRepository $contracts = null
    ) {
        $this->repository ??= new PaymentRepository();
        $this->contracts ??= new ContractRepository();
    }

    /**
     * @param array{
     *   contract_id:mixed,
     *   sequence_no:mixed,
     *   reference?:mixed,
     *   original_amount:mixed,
     *   due_date:mixed,
     *   expected_payment_date?:mixed
     * } $input
     */
    public function create(array $input): int
    {
        $this->requireCapability(Capabilities::MANAGE_PAYMENTS, 'You do not have permission to manage scheduled payments.');

        $contractId = (int) ($input['contract_id'] ?? 0);
        $this->requireMutableContract($contractId);
        $sequenceNo = $this->normalizeSequence($input['sequence_no'] ?? null);
        $reference = $this->normalizeReference($input['reference'] ?? null);
        $amount = ContractMoney::normalizeNonNegative($input['original_amount'] ?? '');
        if ($amount === '0.0000') {
            throw new InvalidArgumentException('Scheduled payment amount must be greater than zero.');
        }
        $dueDate = $this->normalizeRequiredDate($input['due_date'] ?? null, 'due date');
        $expectedDate = $this->normalizeOptionalDate($input['expected_payment_date'] ?? null, 'expected payment date');

        $actorId = get_current_user_id();
        $paymentId = $this->repository->create(
            $contractId,
            $sequenceNo,
            $reference,
            $amount,
            $dueDate,
            $expectedDate,
            $actorId
        );
        do_action('safecontracts_payment_scheduled', $paymentId, $contractId, $actorId);

        return $paymentId;
    }

    public function updateDates(int $paymentId, mixed $dueDate, mixed $expectedPaymentDate = null): void
    {
        $this->requireCapability(Capabilities::MANAGE_PAYMENTS, 'You do not have permission to edit payment dates.');
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

    public function cancel(int $paymentId): void
    {
        $this->requireCapability(Capabilities::MANAGE_PAYMENTS, 'You do not have permission to cancel scheduled payments.');
        $this->editablePayment($paymentId);
        $actorId = get_current_user_id();
        $this->repository->cancel($paymentId, $actorId);
        do_action('safecontracts_payment_cancelled', $paymentId, $actorId);
    }

    public function state(
        int $paymentId,
        mixed $paidAmount = '0',
        ?DateTimeImmutable $today = null,
        int $dueSoonDays = 10
    ): string {
        $this->requireCapability(Capabilities::ACCESS, 'You do not have access to SafeContracts payments.');
        $payment = $this->requirePayment($paymentId);
        $this->assertScope($payment['contract_accountant_user_id']);

        return PaymentState::derive(
            $payment['due_date'],
            $payment['original_amount'],
            $paidAmount,
            $today,
            $dueSoonDays,
            $payment['is_cancelled']
        );
    }

    /**
     * @return array{
     *   id:int, contract_id:int, sequence_no:int, reference:?string,
     *   original_amount:string, due_date:string, expected_payment_date:?string,
     *   is_cancelled:bool, contract_accountant_user_id:?int,
     *   contract_status:string, contract_is_archived:bool
     * }
     */
    private function editablePayment(int $paymentId): array
    {
        $payment = $this->requirePayment($paymentId);
        $this->assertScope($payment['contract_accountant_user_id']);
        if ($payment['is_cancelled']) {
            throw new DomainException('Cancelled scheduled payments cannot be edited.');
        }
        if ($payment['contract_is_archived']) {
            throw new DomainException('Payments on archived contracts cannot be edited.');
        }
        if (in_array($payment['contract_status'], [ContractStatus::COMPLETED, ContractStatus::CANCELLED], true)) {
            throw new DomainException('Payments on terminal contracts cannot be edited.');
        }

        return $payment;
    }

    /** @return array{id:int, contract_id:int, sequence_no:int, reference:?string, original_amount:string, due_date:string, expected_payment_date:?string, is_cancelled:bool, contract_accountant_user_id:?int, contract_status:string, contract_is_archived:bool} */
    private function requirePayment(int $paymentId): array
    {
        if ($paymentId <= 0) {
            throw new InvalidArgumentException('Payment ID must be positive.');
        }
        $payment = $this->repository->find($paymentId);
        if ($payment === null) {
            throw new InvalidArgumentException('Scheduled payment was not found.');
        }

        return $payment;
    }

    /** @return array{id:int, contract_number:string, customer_id:int, accountant_user_id:?int, status:string, start_date:?string, end_date:?string, base_value:string, notes:string, is_archived:bool} */
    private function requireMutableContract(int $contractId): array
    {
        if ($contractId <= 0) {
            throw new InvalidArgumentException('Contract ID must be positive.');
        }
        $contract = $this->contracts->find($contractId);
        if ($contract === null) {
            throw new InvalidArgumentException('Payment contract was not found.');
        }
        $this->assertScope($contract['accountant_user_id']);
        if ($contract['is_archived']) {
            throw new DomainException('Cannot schedule payments on an archived contract.');
        }
        if (in_array($contract['status'], [ContractStatus::COMPLETED, ContractStatus::CANCELLED], true)) {
            throw new DomainException('Cannot schedule payments on a terminal contract.');
        }

        return $contract;
    }

    private function assertScope(?int $accountantUserId): void
    {
        if (current_user_can(Capabilities::VIEW_ALL)) {
            return;
        }
        if (current_user_can(Capabilities::VIEW_ASSIGNED) && $accountantUserId !== null && $accountantUserId === get_current_user_id()) {
            return;
        }

        throw new DomainException('Payment is outside the current user data scope.');
    }

    private function normalizeSequence(mixed $value): int
    {
        $raw = trim((string) $value);
        if (! preg_match('/^[1-9][0-9]*$/', $raw)) {
            throw new InvalidArgumentException('Payment sequence must be a positive integer.');
        }
        $sequence = (int) $raw;
        if ($sequence <= 0 || $sequence > 2147483647) {
            throw new InvalidArgumentException('Payment sequence is outside the supported range.');
        }

        return $sequence;
    }

    private function normalizeReference(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $reference = trim((string) $value);
        if ($reference === '') {
            return null;
        }
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
