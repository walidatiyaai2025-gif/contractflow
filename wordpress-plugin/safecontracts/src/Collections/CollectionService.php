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
        $this->requireCapability(Capabilities::MANAGE_COLLECTIONS, 'You do not have permission to record collections.');

        $paymentId = (int) ($input['payment_id'] ?? 0);
        if ($paymentId <= 0) {
            throw new InvalidArgumentException('Collection payment ID must be positive.');
        }

        $amount = ContractMoney::normalizeNonNegative($input['amount'] ?? '');
        if ($amount === '0.0000') {
            throw new InvalidArgumentException('Collection amount must be greater than zero.');
        }
        $collectionDate = $this->normalizeDate($input['collection_date'] ?? null);

        $paymentMethodId = (int) ($input['payment_method_id'] ?? 0);
        if ($paymentMethodId <= 0) {
            throw new InvalidArgumentException('Payment method is required for every collection transaction.');
        }

        $reference = $this->normalizeOptionalText($input['reference'] ?? null, 191, 'Collection reference');
        $details = $this->normalizeOptionalText($input['details'] ?? null, 5000, 'Collection details');
        $proofMediaId = $this->normalizeProofMediaId($input['proof_media_id'] ?? null);
        $actorId = get_current_user_id();
        $transactionStarted = false;

        try {
            $this->repository->beginTransaction();
            $transactionStarted = true;

            $payment = $this->requirePayment($paymentId, true);
            $this->assertScope($payment['accountant_user_id']);
            if ($payment['contract_is_archived']) {
                throw new DomainException('Collections cannot be recorded against archived contracts.');
            }

            if (! $this->repository->paymentMethodIsActive($paymentMethodId)) {
                throw new InvalidArgumentException('Collection payment method must be an active SafeContracts payment method.');
            }

            $ledgerBefore = ContractMoney::normalizeNonNegative($this->repository->totalForPayment($paymentId));
            $projectedCollected = ContractMoney::sum([$ledgerBefore, $amount]);
            if (ContractMoney::compareNonNegative($projectedCollected, $payment['original_amount']) > 0) {
                throw new DomainException('Collection amount exceeds the payment remaining balance.');
            }

            $collectionId = $this->repository->create(
                $paymentId,
                $amount,
                $collectionDate,
                $paymentMethodId,
                $reference,
                $details,
                $proofMediaId,
                $actorId
            );

            $balance = $this->deriveBalance($payment, $projectedCollected);
            $this->payments->updateBalance(
                $paymentId,
                $balance['paid_amount'],
                $balance['remaining_amount'],
                $balance['status'],
                $actorId
            );

            $this->repository->commit();
            $transactionStarted = false;
        } catch (Throwable $error) {
            if ($transactionStarted) {
                try {
                    $this->repository->rollBack();
                } catch (Throwable) {
                    // Preserve the original domain/database error.
                }
            }
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
            $actorId
        );
        do_action(
            'safecontracts_payment_balance_changed',
            $paymentId,
            $payment['paid_amount'],
            $balance['paid_amount'],
            $payment['remaining_amount'],
            $balance['remaining_amount'],
            $payment['status'],
            $balance['status'],
            $actorId
        );

        return $collectionId;
    }

    /** @return list<array<string, mixed>> */
    public function forPayment(int $paymentId): array
    {
        $this->requireCapability(Capabilities::ACCESS, 'You do not have access to SafeContracts collections.');
        $payment = $this->requirePayment($paymentId);
        $this->assertScope($payment['accountant_user_id']);

        return $this->repository->forPayment($paymentId);
    }

    /**
     * @return array{
     *   payment_id:int, original_amount:string, ledger_collected:string,
     *   stored_paid_amount:string, expected_paid_amount:string,
     *   stored_remaining_amount:string, expected_remaining_amount:string,
     *   stored_status:string, expected_status:string,
     *   over_collected:bool, is_consistent:bool
     * }
     */
    public function reconcilePayment(int $paymentId): array
    {
        $this->requireCapability(Capabilities::ACCESS, 'You do not have access to SafeContracts reconciliation.');
        $payment = $this->requirePayment($paymentId);
        $this->assertScope($payment['accountant_user_id']);
        $ledger = ContractMoney::normalizeNonNegative($this->repository->totalForPayment($paymentId));
        $balance = $this->deriveBalance($payment, $ledger);

        $storedPaid = ContractMoney::normalizeNonNegative($payment['paid_amount']);
        $storedRemaining = ContractMoney::normalizeNonNegative($payment['remaining_amount']);
        $storedStatus = PaymentStatus::normalize($payment['status']);
        $isConsistent = ! $balance['over_collected']
            && ContractMoney::compareNonNegative($storedPaid, $balance['paid_amount']) === 0
            && ContractMoney::compareNonNegative($storedRemaining, $balance['remaining_amount']) === 0
            && $storedStatus === $balance['status'];

        return [
            'payment_id' => $paymentId,
            'original_amount' => ContractMoney::normalizeNonNegative($payment['original_amount']),
            'ledger_collected' => $ledger,
            'stored_paid_amount' => $storedPaid,
            'expected_paid_amount' => $balance['paid_amount'],
            'stored_remaining_amount' => $storedRemaining,
            'expected_remaining_amount' => $balance['remaining_amount'],
            'stored_status' => $storedStatus,
            'expected_status' => $balance['status'],
            'over_collected' => $balance['over_collected'],
            'is_consistent' => $isConsistent,
        ];
    }

    /** @return array{paid_amount:string, remaining_amount:string, status:string} */
    public function repairPaymentBalance(int $paymentId): array
    {
        $this->requireCapability(Capabilities::MANAGE_COLLECTIONS, 'You do not have permission to repair payment balances.');
        $actorId = get_current_user_id();
        $transactionStarted = false;

        try {
            $this->repository->beginTransaction();
            $transactionStarted = true;
            $payment = $this->requirePayment($paymentId, true);
            $this->assertScope($payment['accountant_user_id']);
            if ($payment['contract_is_archived']) {
                throw new DomainException('Archived payment balances cannot be repaired automatically.');
            }

            $ledger = ContractMoney::normalizeNonNegative($this->repository->totalForPayment($paymentId));
            $balance = $this->deriveBalance($payment, $ledger);
            if ($balance['over_collected']) {
                throw new DomainException('Over-collected payments require an explicit reversal/correction workflow.');
            }

            $this->payments->updateBalance(
                $paymentId,
                $balance['paid_amount'],
                $balance['remaining_amount'],
                $balance['status'],
                $actorId
            );
            $this->repository->commit();
            $transactionStarted = false;
        } catch (Throwable $error) {
            if ($transactionStarted) {
                try {
                    $this->repository->rollBack();
                } catch (Throwable) {
                    // Preserve the original domain/database error.
                }
            }
            throw $error;
        }

        do_action(
            'safecontracts_payment_balance_repaired',
            $paymentId,
            $payment['paid_amount'],
            $balance['paid_amount'],
            $payment['remaining_amount'],
            $balance['remaining_amount'],
            $payment['status'],
            $balance['status'],
            $actorId
        );

        return [
            'paid_amount' => $balance['paid_amount'],
            'remaining_amount' => $balance['remaining_amount'],
            'status' => $balance['status'],
        ];
    }

    /**
     * @param array{id:int, contract_id:int, sequence_no:int, reference:?string, due_date:string, expected_payment_date:?string, original_amount:string, paid_amount:string, remaining_amount:string, status:string, accountant_user_id:?int, contract_is_archived:bool} $payment
     * @return array{paid_amount:string, remaining_amount:string, status:string, over_collected:bool}
     */
    private function deriveBalance(array $payment, string $ledgerCollected): array
    {
        $original = ContractMoney::normalizeNonNegative($payment['original_amount']);
        $collected = ContractMoney::normalizeNonNegative($ledgerCollected);
        $comparison = ContractMoney::compareNonNegative($collected, $original);
        $overCollected = $comparison > 0;
        $remaining = $comparison >= 0
            ? '0.0000'
            : ContractMoney::subtractNonNegative($original, $collected);

        if ($collected === '0.0000') {
            $current = PaymentStatus::normalize($payment['status']);
            $status = in_array($current, [PaymentStatus::PARTIALLY_PAID, PaymentStatus::PAID], true)
                ? PaymentStatus::temporalForDueDate($payment['due_date'])
                : $current;
        } elseif ($comparison < 0) {
            $status = PaymentStatus::PARTIALLY_PAID;
        } else {
            $status = PaymentStatus::PAID;
        }

        return [
            'paid_amount' => $collected,
            'remaining_amount' => $remaining,
            'status' => $status,
            'over_collected' => $overCollected,
        ];
    }

    /** @return array{id:int, contract_id:int, sequence_no:int, reference:?string, due_date:string, expected_payment_date:?string, original_amount:string, paid_amount:string, remaining_amount:string, status:string, accountant_user_id:?int, contract_is_archived:bool} */
    private function requirePayment(int $paymentId, bool $forUpdate = false): array
    {
        if ($paymentId <= 0) {
            throw new InvalidArgumentException('Collection payment ID must be positive.');
        }
        $payment = $forUpdate ? $this->payments->findForUpdate($paymentId) : $this->payments->find($paymentId);
        if ($payment === null) {
            throw new InvalidArgumentException('Collection payment was not found.');
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

        throw new DomainException('Collection is outside the current user data scope.');
    }

    private function normalizeDate(mixed $value): string
    {
        $date = trim((string) $value);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Collection date must use YYYY-MM-DD and be a valid calendar date.');
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
            throw new InvalidArgumentException('Collection proof must reference a WordPress Media attachment when supplied.');
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
}
