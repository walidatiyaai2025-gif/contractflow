<?php

declare(strict_types=1);

namespace SafeContracts\Collections;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Payments\PaymentRepository;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;
use Throwable;

final class CollectionService
{
    public function __construct(
        private ?CollectionRepository $repository = null,
        private ?PaymentRepository $payments = null
    ) {
        $this->repository ??= new CollectionRepository();
        $this->payments ??= new PaymentRepository();
    }

    /**
     * Record a settlement against either a receivable or payable obligation.
     * The legacy collection terminology is retained for API compatibility.
     *
     * @param array{
     *   payment_id:mixed,
     *   amount:mixed,
     *   collection_date:mixed,
     *   payment_method_id:mixed,
     *   reference?:mixed,
     *   details?:mixed,
     *   proof_media_id?:mixed
     * } $input
     */
    public function record(array $input): int
    {
        $this->requireAny(
            [Capabilities::MANAGE_COLLECTIONS, Capabilities::MANAGE_FINANCE],
            'You do not have permission to record financial settlements.'
        );

        $paymentId = (int) ($input['payment_id'] ?? 0);
        if ($paymentId <= 0) {
            throw new InvalidArgumentException('Settlement payment ID must be positive.');
        }

        $amount = ContractMoney::normalizeNonNegative($input['amount'] ?? '');
        if ($amount === '0.0000') {
            throw new InvalidArgumentException('Settlement amount must be greater than zero.');
        }
        $collectionDate = $this->normalizeDate($input['collection_date'] ?? null);

        $paymentMethodId = (int) ($input['payment_method_id'] ?? 0);
        if ($paymentMethodId <= 0) {
            throw new InvalidArgumentException('Payment method is required for every settlement transaction.');
        }

        $reference = $this->normalizeOptionalText($input['reference'] ?? null, 191, 'Settlement reference');
        $details = $this->normalizeOptionalText($input['details'] ?? null, 5000, 'Settlement details');
        $proofMediaId = $this->normalizeProofMediaId($input['proof_media_id'] ?? null);
        $actorId = get_current_user_id();

        $this->repository->beginTransaction();
        try {
            $payment = $this->repository->lockPayment($paymentId);
            if ($payment === null) {
                throw new InvalidArgumentException('Settlement payment was not found.');
            }
            $this->assertScope($payment['accountant_user_id']);
            if (! empty($payment['payment_is_archived'])) {
                throw new DomainException('Settlements cannot be recorded against archived payments.');
            }
            if ($payment['contract_is_archived']) {
                throw new DomainException('Settlements cannot be recorded against archived contracts.');
            }
            if (! $this->repository->paymentMethodIsActive($paymentMethodId)) {
                throw new InvalidArgumentException('Settlement payment method must be an active SafeContracts payment method.');
            }

            $originalAmount = ContractMoney::normalizeNonNegative($payment['original_amount']);
            $storedPaid = ContractMoney::normalizeNonNegative($payment['paid_amount']);
            $storedRemaining = ContractMoney::normalizeNonNegative($payment['remaining_amount']);
            $storedStatus = PaymentStatus::normalize($payment['status']);
            $ledgerCollected = ContractMoney::normalizeNonNegative($this->repository->collectedTotal($paymentId));

            $this->assertStoredIntegrity(
                $originalAmount,
                $storedPaid,
                $storedRemaining,
                $ledgerCollected,
                $storedStatus
            );

            $newPaid = ContractMoney::add($ledgerCollected, $amount);
            if (ContractMoney::compare($newPaid, $originalAmount) > 0) {
                throw new DomainException('Settlement amount exceeds the payment remaining balance.');
            }
            $newRemaining = ContractMoney::subtract($originalAmount, $newPaid);
            $newStatus = $newRemaining === '0.0000' ? PaymentStatus::PAID : PaymentStatus::PARTIALLY_PAID;

            $collectionId = $this->repository->create(
                $paymentId,
                $payment['financial_direction'],
                $payment['currency_code'],
                $amount,
                $collectionDate,
                $paymentMethodId,
                $reference,
                $details,
                $proofMediaId,
                $actorId
            );
            $this->repository->updatePaymentSettlement(
                $paymentId,
                $newPaid,
                $newRemaining,
                $newStatus,
                $actorId
            );
            $this->repository->commitTransaction();
        } catch (Throwable $error) {
            $this->repository->rollbackTransaction();
            throw $error;
        }

        do_action(
            'safecontracts_collection_recorded',
            $collectionId,
            $paymentId,
            $amount,
            $collectionDate,
            $paymentMethodId,
            $proofMediaId,
            $actorId,
            $payment['financial_direction'],
            $payment['currency_code']
        );
        do_action(
            'safecontracts_payment_settled',
            $paymentId,
            $amount,
            $newPaid,
            $newRemaining,
            $newStatus,
            $actorId,
            $storedPaid,
            $storedRemaining,
            $storedStatus,
            $payment['financial_direction'],
            $payment['currency_code']
        );

        return $collectionId;
    }

    /** @return list<array<string, mixed>> */
    public function forPayment(int $paymentId): array
    {
        $this->requireCapability(Capabilities::ACCESS, 'You do not have access to SafeContracts settlements.');
        $payment = $this->requirePayment($paymentId);
        $this->assertScope($payment['accountant_user_id']);
        if ($payment['is_archived']) {
            throw new DomainException('Archived payments do not expose an active settlement ledger.');
        }

        return $this->repository->forPayment($paymentId);
    }

    /**
     * @return array{
     *   original_amount:string, ledger_collected:string, stored_paid_amount:string,
     *   stored_remaining_amount:string, expected_remaining_amount:string,
     *   stored_status:string, expected_financial_status:?string,
     *   over_collected:bool, is_balanced:bool
     * }
     */
    public function reconcilePayment(int $paymentId): array
    {
        $this->requireCapability(Capabilities::ACCESS, 'You do not have access to SafeContracts settlement reconciliation.');
        $payment = $this->requirePayment($paymentId);
        $this->assertScope($payment['accountant_user_id']);
        if ($payment['is_archived']) {
            throw new DomainException('Archived payments are outside the active settlement ledger.');
        }

        $original = ContractMoney::normalizeNonNegative($payment['original_amount']);
        $ledger = ContractMoney::normalizeNonNegative($this->repository->collectedTotal($paymentId));
        $storedPaid = ContractMoney::normalizeNonNegative($payment['paid_amount']);
        $storedRemaining = ContractMoney::normalizeNonNegative($payment['remaining_amount']);
        $overCollected = ContractMoney::compare($ledger, $original) > 0;
        $expectedRemaining = ContractMoney::difference($original, $ledger);
        $expectedStatus = null;
        if ($ledger !== '0.0000' && ! $overCollected) {
            $expectedStatus = $ledger === $original ? PaymentStatus::PAID : PaymentStatus::PARTIALLY_PAID;
        }

        $amountsBalanced = ! $overCollected
            && ContractMoney::compare($ledger, $storedPaid) === 0
            && $expectedRemaining === $storedRemaining
            && ContractMoney::add($storedPaid, $storedRemaining) === $original;
        $statusBalanced = $expectedStatus === null || PaymentStatus::normalize($payment['status']) === $expectedStatus;

        return [
            'original_amount' => $original,
            'ledger_collected' => $ledger,
            'stored_paid_amount' => $storedPaid,
            'stored_remaining_amount' => $storedRemaining,
            'expected_remaining_amount' => $expectedRemaining,
            'stored_status' => PaymentStatus::normalize($payment['status']),
            'expected_financial_status' => $expectedStatus,
            'over_collected' => $overCollected,
            'is_balanced' => $amountsBalanced && $statusBalanced,
        ];
    }

    /** @return array<string,mixed> */
    private function requirePayment(int $paymentId): array
    {
        if ($paymentId <= 0) {
            throw new InvalidArgumentException('Settlement payment ID must be positive.');
        }
        $payment = $this->payments->find($paymentId);
        if ($payment === null) {
            throw new InvalidArgumentException('Settlement payment was not found.');
        }

        return $payment;
    }

    private function assertStoredIntegrity(
        string $originalAmount,
        string $storedPaid,
        string $storedRemaining,
        string $ledgerCollected,
        string $status
    ): void {
        if (ContractMoney::compare($ledgerCollected, $originalAmount) > 0) {
            throw new DomainException('Settlement ledger already exceeds the payment original amount.');
        }
        if (ContractMoney::compare($storedPaid, $ledgerCollected) !== 0) {
            throw new DomainException('Payment paid amount does not reconcile with the settlement ledger.');
        }
        $expectedRemaining = ContractMoney::subtract($originalAmount, $ledgerCollected);
        if ($storedRemaining !== $expectedRemaining) {
            throw new DomainException('Payment remaining amount does not reconcile with original amount and settlements.');
        }
        if (ContractMoney::add($storedPaid, $storedRemaining) !== $originalAmount) {
            throw new DomainException('Payment paid and remaining balances do not reconcile to the original amount.');
        }

        if ($ledgerCollected !== '0.0000') {
            $expectedStatus = $ledgerCollected === $originalAmount ? PaymentStatus::PAID : PaymentStatus::PARTIALLY_PAID;
            if (PaymentStatus::normalize($status) !== $expectedStatus) {
                throw new DomainException('Payment financial status does not reconcile with settled amount.');
            }
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

        throw new DomainException('Settlement is outside the current user data scope.');
    }

    private function normalizeDate(mixed $value): string
    {
        $date = trim((string) $value);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Settlement date must use YYYY-MM-DD and be a valid calendar date.');
        }

        return $date;
    }

    private function normalizeProofMediaId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $mediaId = (int) $value;
        if ($mediaId <= 0 || ! function_exists('get_post_type') || get_post_type($mediaId) !== 'attachment') {
            throw new InvalidArgumentException('Settlement proof must reference a WordPress Media attachment when supplied.');
        }

        return $mediaId;
    }

    private function normalizeOptionalText(mixed $value, int $maxLength, string $field): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        if (strlen($text) > $maxLength) {
            throw new InvalidArgumentException("{$field} must not exceed {$maxLength} characters.");
        }

        return $text;
    }

    private function requireCapability(string $capability, string $message): void
    {
        if (! current_user_can($capability)) {
            throw new DomainException($message);
        }
    }

    /** @param list<string> $capabilities */
    private function requireAny(array $capabilities, string $message): void
    {
        foreach ($capabilities as $capability) {
            if (current_user_can($capability)) {
                return;
            }
        }
        throw new DomainException($message);
    }
}
