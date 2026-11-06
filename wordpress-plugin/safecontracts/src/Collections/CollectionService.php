<?php

declare(strict_types=1);

namespace SafeContracts\Collections;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Diagnostics\RuntimeInspector;
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
        RuntimeInspector::begin('settlement.record', [
            'payment_id' => (int) ($input['payment_id'] ?? 0),
            'payment_method_id' => (int) ($input['payment_method_id'] ?? 0),
            'amount' => is_scalar($input['amount'] ?? null) ? (string) $input['amount'] : '',
            'collection_date' => is_scalar($input['collection_date'] ?? null) ? (string) $input['collection_date'] : '',
            'proof_media_id' => ($input['proof_media_id'] ?? null) === null ? null : (int) ($input['proof_media_id'] ?? 0),
        ]);
        try {
            return $this->recordTraced($input);
        } catch (Throwable $error) {
            RuntimeInspector::capture($error);
            throw $error;
        } finally {
            RuntimeInspector::finish();
        }
    }

    /** @param array<string,mixed> $input */
    private function recordTraced(array $input): int
    {
        RuntimeInspector::stage('settlement.record.authorization');
        $this->requireAny(
            [Capabilities::MANAGE_COLLECTIONS, Capabilities::MANAGE_FINANCE],
            'You do not have permission to record financial settlements.'
        );

        RuntimeInspector::stage('settlement.record.payment_id');
        $paymentId = (int) ($input['payment_id'] ?? 0);
        if ($paymentId <= 0) {
            throw new InvalidArgumentException('Settlement payment ID must be positive.');
        }

        RuntimeInspector::stage('settlement.record.amount', ['payment_id' => $paymentId]);
        $amount = ContractMoney::normalizeNonNegative($input['amount'] ?? '');
        if ($amount === '0.0000') {
            throw new InvalidArgumentException('Settlement amount must be greater than zero.');
        }

        RuntimeInspector::stage('settlement.record.date', ['payment_id' => $paymentId, 'amount' => $amount]);
        $collectionDate = $this->normalizeDate($input['collection_date'] ?? null);

        RuntimeInspector::stage('settlement.record.payment_method', ['payment_id' => $paymentId]);
        $paymentMethodId = (int) ($input['payment_method_id'] ?? 0);
        if ($paymentMethodId <= 0) {
            throw new InvalidArgumentException('Payment method is required for every settlement transaction.');
        }

        RuntimeInspector::stage('settlement.record.optional_fields', [
            'payment_id' => $paymentId,
            'payment_method_id' => $paymentMethodId,
            'amount' => $amount,
            'collection_date' => $collectionDate,
        ]);
        $reference = $this->normalizeOptionalText($input['reference'] ?? null, 191, 'Settlement reference');
        $details = $this->normalizeOptionalText($input['details'] ?? null, 5000, 'Settlement details');
        $proofMediaId = $this->normalizeProofMediaId($input['proof_media_id'] ?? null);
        $actorId = get_current_user_id();

        RuntimeInspector::stage('settlement.record.transaction.begin', ['payment_id' => $paymentId]);
        $this->repository->beginTransaction();
        try {
            RuntimeInspector::stage('settlement.record.payment.lock', ['payment_id' => $paymentId]);
            $payment = $this->repository->lockPayment($paymentId);
            if ($payment === null) {
                throw new InvalidArgumentException('Settlement payment was not found.');
            }

            RuntimeInspector::stage('settlement.record.scope', [
                'payment_id' => $paymentId,
                'accountant_user_id' => $payment['accountant_user_id'] ?? null,
            ]);
            $this->assertScope($payment['accountant_user_id']);

            RuntimeInspector::stage('settlement.record.payment_state', [
                'payment_id' => $paymentId,
                'payment_is_archived' => ! empty($payment['payment_is_archived']),
                'contract_is_archived' => ! empty($payment['contract_is_archived']),
            ]);
            if (! empty($payment['payment_is_archived'])) {
                throw new DomainException('Settlements cannot be recorded against archived payments.');
            }
            if ($payment['contract_is_archived']) {
                throw new DomainException('Settlements cannot be recorded against archived contracts.');
            }

            RuntimeInspector::stage('settlement.record.payment_method.active', [
                'payment_id' => $paymentId,
                'payment_method_id' => $paymentMethodId,
            ]);
            if (! $this->repository->paymentMethodIsActive($paymentMethodId)) {
                throw new InvalidArgumentException('Settlement payment method must be an active SafeContracts payment method.');
            }

            RuntimeInspector::stage('settlement.record.ledger.read', ['payment_id' => $paymentId]);
            $originalAmount = ContractMoney::normalizeNonNegative($payment['original_amount']);
            $storedPaid = ContractMoney::normalizeNonNegative($payment['paid_amount']);
            $storedRemaining = ContractMoney::normalizeNonNegative($payment['remaining_amount']);
            $storedStatus = PaymentStatus::normalize($payment['status']);
            $ledgerCollected = ContractMoney::normalizeNonNegative($this->repository->collectedTotal($paymentId));

            RuntimeInspector::stage('settlement.record.ledger.integrity', [
                'payment_id' => $paymentId,
                'original_amount' => $originalAmount,
                'stored_paid_amount' => $storedPaid,
                'stored_remaining_amount' => $storedRemaining,
                'ledger_settled_amount' => $ledgerCollected,
                'stored_status' => $storedStatus,
            ]);
            $this->assertStoredIntegrity(
                $originalAmount,
                $storedPaid,
                $storedRemaining,
                $ledgerCollected,
                $storedStatus
            );

            RuntimeInspector::stage('settlement.record.payment_capacity', [
                'payment_id' => $paymentId,
                'amount' => $amount,
                'original_amount' => $originalAmount,
                'ledger_settled_amount' => $ledgerCollected,
            ]);
            $newPaid = ContractMoney::add($ledgerCollected, $amount);
            if (ContractMoney::compare($newPaid, $originalAmount) > 0) {
                // Keep the canonical P10 guard text stable for integrations and
                // governance; the admin Arabic surface explains the limit in detail.
                throw new DomainException('Collection amount exceeds the payment remaining balance.');
            }

            RuntimeInspector::stage('settlement.record.contract_capacity', [
                'payment_id' => $paymentId,
                'amount' => $amount,
                'contract_base_value' => $payment['contract_base_value'] ?? null,
                'contract_settled_total' => $payment['contract_settled_total'] ?? null,
            ]);
            $this->assertContractSettlementCapacity($payment, $amount);
            $newRemaining = ContractMoney::subtract($originalAmount, $newPaid);
            $newStatus = $newRemaining === '0.0000' ? PaymentStatus::PAID : PaymentStatus::PARTIALLY_PAID;

            RuntimeInspector::stage('settlement.record.database.insert', [
                'payment_id' => $paymentId,
                'payment_method_id' => $paymentMethodId,
                'amount' => $amount,
                'new_paid_amount' => $newPaid,
                'new_remaining_amount' => $newRemaining,
                'new_status' => $newStatus,
            ]);
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

            RuntimeInspector::stage('settlement.record.payment.update', [
                'payment_id' => $paymentId,
                'collection_id' => $collectionId,
                'new_paid_amount' => $newPaid,
                'new_remaining_amount' => $newRemaining,
                'new_status' => $newStatus,
            ]);
            $this->repository->updatePaymentSettlement(
                $paymentId,
                $newPaid,
                $newRemaining,
                $newStatus,
                $actorId
            );

            RuntimeInspector::stage('settlement.record.transaction.commit', [
                'payment_id' => $paymentId,
                'collection_id' => $collectionId,
            ]);
            $this->repository->commitTransaction();
        } catch (Throwable $error) {
            RuntimeInspector::stage('settlement.record.transaction.rollback', [
                'payment_id' => $paymentId,
                'payment_method_id' => $paymentMethodId,
                'amount' => $amount,
            ]);
            $this->repository->rollbackTransaction();
            throw $error;
        }

        RuntimeInspector::stage('settlement.record.events', [
            'payment_id' => $paymentId,
            'collection_id' => $collectionId,
        ]);
        // Preserve the historical hook payload exactly for existing audit and integrations.
        do_action(
            'safecontracts_collection_recorded',
            $collectionId,
            $paymentId,
            $amount,
            $collectionDate,
            $paymentMethodId,
            $proofMediaId,
            $actorId
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
            $storedStatus
        );

        // P11 emits a separate event instead of mutating established hook signatures.
        do_action(
            'safecontracts_financial_settlement_recorded',
            $collectionId,
            $paymentId,
            $payment['financial_direction'],
            $payment['currency_code'],
            $amount,
            $newPaid,
            $newRemaining,
            $newStatus,
            $actorId
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

    /** @param array<string,mixed> $payment */
    private function assertContractSettlementCapacity(array $payment, string $amount): void
    {
        if (($payment['contract_base_value'] ?? null) === null || ($payment['contract_settled_total'] ?? null) === null) {
            return;
        }
        $contractValue = ContractMoney::normalizeNonNegative((string) $payment['contract_base_value']);
        $settled = ContractMoney::normalizeNonNegative((string) $payment['contract_settled_total']);
        $projected = ContractMoney::add($settled, $amount);
        if (ContractMoney::compare($projected, $contractValue) <= 0) {
            return;
        }
        $available = ContractMoney::compare($settled, $contractValue) >= 0
            ? '0.0000'
            : ContractMoney::subtract($contractValue, $settled);
        throw new DomainException(
            "Total collections/payments cannot exceed the contract value. Contract value: {$contractValue}; already settled: {$settled}; maximum additional settlement: {$available}."
        );
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
