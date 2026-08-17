<?php

declare(strict_types=1);

namespace SafeContracts\Payments;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;
use SafeContracts\Finance\FinancialDirection;

final class PaymentStatus
{
    public const UPCOMING = 'upcoming';
    public const DUE_SOON = 'due_soon';
    public const DUE = 'due';
    public const OVERDUE = 'overdue';
    public const PARTIALLY_PAID = 'partially_paid';
    public const PAID = 'paid';
    public const PARTIALLY_RECEIVED = 'partially_received';
    public const RECEIVED = 'received';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::UPCOMING,
            self::DUE_SOON,
            self::DUE,
            self::OVERDUE,
            self::PARTIALLY_PAID,
            self::PAID,
            self::PARTIALLY_RECEIVED,
            self::RECEIVED,
        ];
    }

    public static function normalize(mixed $value): string
    {
        $status = strtolower(trim((string) $value));
        if (! in_array($status, self::all(), true)) {
            throw new InvalidArgumentException('Unsupported payment status.');
        }

        return $status;
    }

    public static function partialForDirection(string $direction): string
    {
        return FinancialDirection::normalize($direction) === FinancialDirection::PAYABLE
            ? self::PARTIALLY_PAID
            : self::PARTIALLY_RECEIVED;
    }

    public static function settledForDirection(string $direction): string
    {
        return FinancialDirection::normalize($direction) === FinancialDirection::PAYABLE
            ? self::PAID
            : self::RECEIVED;
    }

    public static function isSettlementStatus(string $status): bool
    {
        return in_array(self::normalize($status), [
            self::PARTIALLY_PAID,
            self::PAID,
            self::PARTIALLY_RECEIVED,
            self::RECEIVED,
        ], true);
    }

    public static function isTerminalSettlement(string $status): bool
    {
        return in_array(self::normalize($status), [self::PAID, self::RECEIVED], true);
    }

    public static function assertTransition(string $current, string $target): void
    {
        $current = self::normalize($current);
        $target = self::normalize($target);

        if ($current === $target) {
            return;
        }

        if (self::isTerminalSettlement($current)) {
            throw new DomainException('Settled obligations cannot leave their final state without an explicit reversal workflow.');
        }
    }

    public static function temporalForDueDate(
        mixed $dueDate,
        ?DateTimeImmutable $today = null,
        int $dueSoonDays = 10
    ): string {
        if ($dueSoonDays < 0) {
            throw new InvalidArgumentException('Due-soon window cannot be negative.');
        }

        $today = self::today($today);
        $due = self::parseDate($dueDate, $today->getTimezone());
        $dueKey = $due->format('Y-m-d');
        $todayKey = $today->format('Y-m-d');

        if ($dueKey < $todayKey) {
            return self::OVERDUE;
        }
        if ($dueKey === $todayKey) {
            return self::DUE;
        }

        $days = (int) $today->diff($due)->format('%a');
        return $days <= $dueSoonDays ? self::DUE_SOON : self::UPCOMING;
    }

    public static function isDueSoon(mixed $dueDate, ?DateTimeImmutable $today = null, int $dueSoonDays = 10): bool
    {
        return self::temporalForDueDate($dueDate, $today, $dueSoonDays) === self::DUE_SOON;
    }

    public static function isOverdue(mixed $dueDate, ?DateTimeImmutable $today = null): bool
    {
        return self::temporalForDueDate($dueDate, $today) === self::OVERDUE;
    }

    private static function parseDate(mixed $value, DateTimeZone $timezone): DateTimeImmutable
    {
        $date = trim((string) $value);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Payment due date must use YYYY-MM-DD and be a valid calendar date.');
        }

        return $parsed;
    }

    private static function today(?DateTimeImmutable $today): DateTimeImmutable
    {
        if ($today !== null) {
            return $today->setTime(0, 0, 0);
        }

        if (function_exists('current_datetime')) {
            /** @var DateTimeImmutable $current */
            $current = current_datetime();
            return $current->setTime(0, 0, 0);
        }

        return new DateTimeImmutable('today', new DateTimeZone('UTC'));
    }
}
