<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Roles\Capabilities;
use Throwable;

final class SettlementService
{
    public function __construct(private ?SettlementRepository $repository = null)
    {
        $this->repository ??= new SettlementRepository();
    }

    /** @param array<string,mixed> $input */
    public function record(array $input): int
    {
        $paymentId = (int) ($input['payment_id'] ?? 0);
        if ($paymentId <= 0) {
            throw new InvalidArgumentException('Financial obligation ID must be positive.');
        }
        $amount = ContractMoney::normalizeNonNegative($input['amount'] ?? '');
        if ($amount === '0.0000') {
            throw new InvalidArgumentException('Settlement amount must be greater than zero.');
        }
        $transactionDate = $this->normalizeDate($input['transaction_date'] ?? null);
        $paymentMethodId = $this->normalizeOptionalPositiveInt($input['payment_method_id'] ?? null, 'Payment method');
        $reference = $this->normalizeOptionalText($input['reference'] ?? null, 191, 'Settlement reference');
        $details = $this->normalizeOptionalText($input['details'] ?? null, 5000, 'Settlement details');
        $proofMediaId = $this->normalizeProofMediaId($input['proof_media_id'] ?? null);
        $idempotencyKey = $this->normalizeIdempotencyKey($input['idempotency_key'] ?? null);
        $actorId = get_current_user_id();

        $this->repository->beginTransaction();
        try {
            $obligation = $this->repository->lockObligation($paymentId);
            if ($obligation === null) {
                throw new InvalidArgumentException('Financial obligation was not found.');
            }
            $this->assertScope($obligation['accountant_user_id']);
            if ($obligation['payment_is_archived'] || $obligation['contract_is_archived']) {
                throw new DomainException('Archived obligations or contracts cannot be settled.');
            }

            $direction = FinancialDirection::normalize($obligation['financial_direction']);
            $currency = CurrencyCode::normalize($obligation['currency_code']);
            $this->requireDirectionCapability($direction);

            $existing = $this->repository->findByIdempotencyKey($idempotencyKey);
            if ($existing !== null) {
                if ((int) $existing['payment_id'] !== $paymentId
                    || ContractMoney::compare((string) $existing['amount'], $amount) !== 0
                    || (string) $existing['financial_direction'] !== $direction) {
                    throw new DomainException('Idempotency key was already used for a different financial operation.');
                }
                $this->repository->commitTransaction();
                return (int) $existing['id'];
            }

            if ($paymentMethodId !== null && ! $this->repository->paymentMethodIsActive($paymentMethodId)) {
                throw new InvalidArgumentException('Settlement payment method must be an active SafeContracts payment method.');
            }

            $original = ContractMoney::normalizeNonNegative($obligation['original_amount']);
            $settled = ContractMoney::normalizeNonNegative($obligation['paid_amount']);
            $remaining = ContractMoney::normalizeNonNegative($obligation['remaining_amount']);
            if (ContractMoney::add($settled, $remaining) !== $original) {
                throw new DomainException('Stored obligation balances do not reconcile to the original amount.');
            }

            $next = SettlementMath::apply($original, $settled, $amount, $direction);
            $transactionId = $this->repository->createTransaction(
                $paymentId,
                (int) $obligation['contract_id'],
                $direction,
                $amount,
                $currency,
                $transactionDate,
                $paymentMethodId,
                $reference,
                $details,
                $proofMediaId,
                $idempotencyKey,
                $actorId
            );
            $this->repository->updateObligationSettlement(
                $paymentId,
                $next['settled_amount'],
                $next['remaining_amount'],
                $next['status'],
                $actorId
            );
            $this->repository->commitTransaction();
        } catch (Throwable $error) {
            $this->repository->rollbackTransaction();
            throw $error;
        }

        do_action(
            'safecontracts_financial_transaction_recorded',
            $transactionId,
            $paymentId,
            $direction,
            $amount,
            $currency,
            $transactionDate,
            $actorId
        );
        do_action(
            'safecontracts_payment_settled',
            $paymentId,
            $amount,
            $next['settled_amount'],
            $next['remaining_amount'],
            $next['status'],
            $actorId,
            $settled,
            $remaining,
            (string) $obligation['status']
        );

        return $transactionId;
    }

    /** @return list<array<string,mixed>> */
    public function forPayment(int $paymentId): array
    {
        if ($paymentId <= 0) {
            throw new InvalidArgumentException('Financial obligation ID must be positive.');
        }
        $this->requireCapability(Capabilities::ACCESS, 'You do not have access to SafeContracts finance.');

        $this->repository->beginTransaction();
        try {
            $obligation = $this->repository->lockObligation($paymentId);
            if ($obligation === null) {
                throw new InvalidArgumentException('Financial obligation was not found.');
            }
            $this->assertScope($obligation['accountant_user_id']);
            $direction = FinancialDirection::normalize($obligation['financial_direction']);
            $this->requireDirectionViewCapability($direction);
            $rows = $this->repository->forPayment($paymentId);
            $this->repository->commitTransaction();
            return $rows;
        } catch (Throwable $error) {
            $this->repository->rollbackTransaction();
            throw $error;
        }
    }

    private function requireDirectionCapability(string $direction): void
    {
        $capability = $direction === FinancialDirection::PAYABLE
            ? Capabilities::RECORD_PAYMENT
            : Capabilities::RECORD_RECEIPT;
        $this->requireCapability(
            $capability,
            $direction === FinancialDirection::PAYABLE
                ? 'You do not have permission to record Accounts Payable payments.'
                : 'You do not have permission to record Accounts Receivable receipts.'
        );
    }

    private function requireDirectionViewCapability(string $direction): void
    {
        $capability = $direction === FinancialDirection::PAYABLE
            ? Capabilities::VIEW_PAYABLES
            : Capabilities::VIEW_RECEIVABLES;
        $this->requireCapability($capability, 'You do not have permission to view this financial direction.');
    }

    private function assertScope(?int $accountantUserId): void
    {
        if (current_user_can(Capabilities::VIEW_ALL)) {
            return;
        }
        if (current_user_can(Capabilities::VIEW_ASSIGNED)
            && $accountantUserId !== null
            && $accountantUserId === get_current_user_id()) {
            return;
        }
        throw new DomainException('Financial obligation is outside the current user data scope.');
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

    private function normalizeOptionalPositiveInt(mixed $value, string $field): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $id = (int) $value;
        if ($id <= 0) {
            throw new InvalidArgumentException("{$field} ID must be positive.");
        }
        return $id;
    }

    private function normalizeOptionalText(mixed $value, int $maxLength, string $field): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim(strip_tags((string) $value));
        if ($text === '') {
            return null;
        }
        if (strlen($text) > $maxLength || preg_match('/[\x00]/', $text)) {
            throw new InvalidArgumentException("{$field} is invalid.");
        }
        return $text;
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

    private function normalizeIdempotencyKey(mixed $value): string
    {
        $key = trim((string) $value);
        if (! preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $key)) {
            throw new InvalidArgumentException('Settlement idempotency key must be 8-128 safe characters.');
        }
        return $key;
    }

    private function requireCapability(string $capability, string $message): void
    {
        if (! current_user_can($capability)) {
            throw new DomainException($message);
        }
    }
}
