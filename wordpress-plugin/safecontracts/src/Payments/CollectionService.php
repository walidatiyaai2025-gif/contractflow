<?php

declare(strict_types=1);

namespace SafeContracts\Payments;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Roles\Capabilities;

final class CollectionService
{
    public function __construct(private ?CollectionRepository $repository = null)
    {
        $this->repository ??= new CollectionRepository();
    }

    /** @param array{payment_id:mixed, amount:mixed, collection_date:mixed, payment_method_id:mixed, reference?:mixed, details?:mixed, proof_media_id?:mixed} $input */
    public function create(array $input): int
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

        $collectionDate = $this->normalizeRequiredDate($input['collection_date'] ?? null);
        $paymentMethodId = (int) ($input['payment_method_id'] ?? 0);
        if ($paymentMethodId <= 0) {
            throw new InvalidArgumentException('Collection payment method is required.');
        }

        $reference = $this->normalizeOptionalText($input['reference'] ?? null, 191, 'Collection reference');
        $details = $this->normalizeOptionalText($input['details'] ?? null, 5000, 'Collection details');
        $proofMediaId = $this->normalizeProofMediaId($input['proof_media_id'] ?? null);

        $payment = $this->repository->paymentContext($paymentId);
        if ($payment === null) {
            throw new InvalidArgumentException('Collection payment was not found.');
        }
        $this->assertScope($payment['accountant_user_id']);
        if ($payment['contract_is_archived']) {
            throw new DomainException('Collections cannot be recorded against payments on archived contracts.');
        }

        if (! $this->repository->paymentMethodIsActive($paymentMethodId)) {
            throw new InvalidArgumentException('Collection payment method must reference an active SafeContracts payment method.');
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

    private function normalizeRequiredDate(mixed $value): string
    {
        $date = trim((string) $value);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Collection date must use YYYY-MM-DD and be a valid calendar date.');
        }

        return $date;
    }

    private function normalizeOptionalText(mixed $value, int $maxLength, string $field): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $normalized = trim((string) $value);
        if (strlen($normalized) > $maxLength) {
            throw new InvalidArgumentException("{$field} must not exceed {$maxLength} characters.");
        }

        return $normalized;
    }

    private function normalizeProofMediaId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $mediaId = (int) $value;
        if ($mediaId <= 0 || ! function_exists('get_post_type') || get_post_type($mediaId) !== 'attachment') {
            throw new InvalidArgumentException('Collection proof must reference a WordPress Media attachment.');
        }

        return $mediaId;
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

        throw new DomainException('Collection payment is outside the current user data scope.');
    }

    private function requireCapability(string $capability, string $message): void
    {
        if (! current_user_can($capability)) {
            throw new DomainException($message);
        }
    }
}
