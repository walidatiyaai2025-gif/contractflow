<?php

declare(strict_types=1);

namespace SafeContracts\Payments;

use DomainException;
use InvalidArgumentException;

final class PaymentStatus
{
    public const UPCOMING = 'upcoming';
    public const DUE_SOON = 'due_soon';
    public const DUE = 'due';
    public const OVERDUE = 'overdue';
    public const PARTIALLY_PAID = 'partially_paid';
    public const PAID = 'paid';

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

    public static function assertTransition(string $current, string $target): void
    {
        $current = self::normalize($current);
        $target = self::normalize($target);

        if ($current === $target) {
            return;
        }

        // Temporal states can move as due/expected dates are changed or recalculated.
        // PAID remains terminal until a later explicit reversal workflow is introduced.
        if ($current === self::PAID) {
            throw new DomainException('Paid payments cannot leave the paid state without an explicit reversal workflow.');
        }
    }
}
