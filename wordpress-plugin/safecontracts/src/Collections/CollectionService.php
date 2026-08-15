<?php

declare(strict_types=1);

namespace SafeContracts\Collections;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Payments\PaymentRepository;
use SafeContracts\Roles\Capabilities;

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

        $payment = $this->requirePayment($paymentId);
        $this->assertScope($payment['accountant_user_id']);
        if ($payment['contract_is_archived']) {
            throw new DomainException('Collections cannot be recorded against archived contracts.');
        }

        if (! $this->repository->paymentMethodIsActive($paymentMethodId)) {
            throw new InvalidArgumentException('Collection payment method must be an active SafeContracts payment method.');
        }

        $actorId = get_current_user_id();
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

    /** @return array{id:int, contract_id:int, sequence_no:int, reference:?string, due_date:string, expected_payment_date:?string, original_amount:string, paid_amount:string, remaining_amount:string, status:string, accountant_user_id:?int, contract_is_archived:bool} */
    private function requirePayment(int $paymentId): array
    {
        if ($paymentId <= 0) {
            throw new InvalidArgumentException('Collection payment ID must be positive.');
        }
        $payment = $this->payments->find($paymentId);
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
