<?php

declare(strict_types=1);

namespace SafeContracts\FollowUps;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use SafeContracts\Payments\PaymentRepository;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;

final class FollowUpService
{
    public function __construct(
        private ?FollowUpRepository $repository = null,
        private ?PaymentRepository $payments = null
    ) {
        $this->repository ??= new FollowUpRepository();
        $this->payments ??= new PaymentRepository();
    }

    /** @return list<array<string, mixed>> */
    public function queue(int $limit = 100): array
    {
        $this->requireCapability(Capabilities::ACCESS, 'You do not have access to SafeContracts follow-up.');
        $limit = max(1, min(500, $limit));

        if (current_user_can(Capabilities::VIEW_ALL)) {
            return $this->repository->queue(null, $limit);
        }
        if (current_user_can(Capabilities::VIEW_ASSIGNED)) {
            return $this->repository->queue(get_current_user_id(), $limit);
        }

        throw new DomainException('Follow-up queue is outside the current user data scope.');
    }

    public function addNote(int $paymentId, mixed $note): int
    {
        $text = $this->normalizeRequiredNote($note);
        return $this->record($paymentId, FollowUpState::CONTACTED, $text, null, null);
    }

    public function promiseToPay(int $paymentId, mixed $promisedDate, mixed $note = null): int
    {
        return $this->record(
            $paymentId,
            FollowUpState::PROMISED_TO_PAY,
            $this->normalizeOptionalNote($note),
            $this->normalizeRequiredDate($promisedDate, 'promised payment date'),
            null
        );
    }

    public function markIssue(int $paymentId, mixed $note): int
    {
        return $this->record($paymentId, FollowUpState::ISSUE, $this->normalizeRequiredNote($note), null, null);
    }

    public function defer(int $paymentId, mixed $until, mixed $note = null): int
    {
        return $this->record(
            $paymentId,
            FollowUpState::DEFERRED,
            $this->normalizeOptionalNote($note),
            null,
            $this->normalizeRequiredDate($until, 'deferred-until date')
        );
    }

    public function escalate(int $paymentId, mixed $note): int
    {
        return $this->record($paymentId, FollowUpState::NEEDS_ESCALATION, $this->normalizeRequiredNote($note), null, null);
    }

    /** @return list<array<string, mixed>> */
    public function history(int $paymentId, int $limit = 100): array
    {
        $this->requireCapability(Capabilities::ACCESS, 'You do not have access to SafeContracts follow-up history.');
        $payment = $this->requirePayment($paymentId);
        $this->assertScope($payment['accountant_user_id']);
        return $this->repository->history($paymentId, max(1, min(500, $limit)));
    }

    private function record(
        int $paymentId,
        string $state,
        ?string $note,
        ?string $promisedDate,
        ?string $deferredUntil
    ): int {
        $this->requireCapability(Capabilities::MANAGE_FOLLOWUPS, 'You do not have permission to manage follow-up.');
        $payment = $this->requirePayment($paymentId);
        $this->assertScope($payment['accountant_user_id']);
        if ($payment['contract_is_archived']) {
            throw new DomainException('Archived contracts cannot receive follow-up updates.');
        }
        if (PaymentStatus::normalize($payment['status']) === PaymentStatus::PAID || $payment['remaining_amount'] === '0.0000') {
            throw new DomainException('Paid payments do not require operational follow-up.');
        }

        $state = FollowUpState::normalize($state);
        $actorId = get_current_user_id();
        $id = $this->repository->append($paymentId, $state, $note, $promisedDate, $deferredUntil, $actorId);
        do_action('safecontracts_followup_recorded', $id, $paymentId, $state, $actorId, $promisedDate, $deferredUntil);
        return $id;
    }

    /** @return array{id:int, contract_id:int, sequence_no:int, reference:?string, due_date:string, expected_payment_date:?string, original_amount:string, paid_amount:string, remaining_amount:string, status:string, accountant_user_id:?int, contract_is_archived:bool} */
    private function requirePayment(int $paymentId): array
    {
        if ($paymentId <= 0) {
            throw new InvalidArgumentException('Follow-up payment ID must be positive.');
        }
        $payment = $this->payments->find($paymentId);
        if ($payment === null) {
            throw new InvalidArgumentException('Follow-up payment was not found.');
        }
        return $payment;
    }

    private function assertScope(?int $accountantUserId): void
    {
        if (current_user_can(Capabilities::VIEW_ALL)) {
            return;
        }
        if (current_user_can(Capabilities::VIEW_ASSIGNED) && $accountantUserId !== null && $accountantUserId === get_current_user_id()) {
            return;
        }
        throw new DomainException('Follow-up is outside the current user data scope.');
    }

    private function normalizeRequiredNote(mixed $value): string
    {
        $note = $this->normalizeOptionalNote($value);
        if ($note === null) {
            throw new InvalidArgumentException('Follow-up note is required.');
        }
        return $note;
    }

    private function normalizeOptionalNote(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $note = trim((string) $value);
        if ($note === '') {
            return null;
        }
        if (strlen($note) > 5000) {
            throw new InvalidArgumentException('Follow-up note must not exceed 5000 characters.');
        }
        return $note;
    }

    private function normalizeRequiredDate(mixed $value, string $field): string
    {
        $date = trim((string) $value);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Follow-up {$field} must use YYYY-MM-DD and be a valid calendar date.");
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
