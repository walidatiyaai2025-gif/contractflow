<?php

declare(strict_types=1);

namespace SafeContracts\Payments;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use SafeContracts\Contracts\ContractMoney;

final class PaymentState
{
    public const UPCOMING = 'upcoming';
    public const DUE_SOON = 'due_soon';
    public const DUE = 'due';
    public const OVERDUE = 'overdue';
    public const PARTIALLY_PAID = 'partially_paid';
    public const PAID = 'paid';
    public const CANCELLED = 'cancelled';

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
            self::CANCELLED,
        ];
    }

    public static function derive(
        string $dueDate,
        mixed $originalAmount,
        mixed $paidAmount = '0',
        ?DateTimeImmutable $today = null,
        int $dueSoonDays = 10,
        bool $cancelled = false
    ): string {
        $original = ContractMoney::normalizeNonNegative($originalAmount);
        $paid = ContractMoney::normalizeNonNegative($paidAmount);
        if ($original === '0.0000') {
            throw new InvalidArgumentException('Scheduled payment amount must be greater than zero.');
        }

        $comparison = self::compareMoney($paid, $original);
        if ($comparison > 0) {
            throw new InvalidArgumentException('Paid amount cannot exceed the scheduled payment amount.');
        }
        if ($cancelled) {
            return self::CANCELLED;
        }
        if ($comparison === 0) {
            return self::PAID;
        }
        if ($paid !== '0.0000') {
            return self::PARTIALLY_PAID;
        }

        return self::temporal($dueDate, $today, $dueSoonDays);
    }

    public static function temporal(string $dueDate, ?DateTimeImmutable $today = null, int $dueSoonDays = 10): string
    {
        if ($dueSoonDays < 0) {
            throw new InvalidArgumentException('Due-soon window cannot be negative.');
        }

        $due = self::parseDate($dueDate, 'due date');
        $today = self::normalizeToday($today);
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

    private static function normalizeToday(?DateTimeImmutable $today): DateTimeImmutable
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

    private static function parseDate(string $value, string $field): DateTimeImmutable
    {
        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (! $date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("Payment {$field} must use YYYY-MM-DD and be a valid calendar date.");
        }

        return $date;
    }

    private static function compareMoney(string $left, string $right): int
    {
        $leftScaled = ltrim(str_replace('.', '', $left), '0') ?: '0';
        $rightScaled = ltrim(str_replace('.', '', $right), '0') ?: '0';
        if (strlen($leftScaled) !== strlen($rightScaled)) {
            return strlen($leftScaled) <=> strlen($rightScaled);
        }

        return strcmp($leftScaled, $rightScaled) <=> 0;
    }
}
