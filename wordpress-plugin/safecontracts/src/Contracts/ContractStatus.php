<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

use DomainException;
use InvalidArgumentException;

final class ContractStatus
{
    public const DRAFT = 'draft';
    public const ACTIVE = 'active';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::DRAFT, self::ACTIVE, self::COMPLETED, self::CANCELLED];
    }

    public static function normalize(string $status): string
    {
        $status = strtolower(trim($status));
        if (! in_array($status, self::all(), true)) {
            throw new InvalidArgumentException('Unsupported contract status.');
        }

        return $status;
    }

    /** @return list<string> */
    public static function allowedTargets(string $from): array
    {
        $from = self::normalize($from);
        $allowed = [
            self::DRAFT => [self::ACTIVE, self::CANCELLED],
            self::ACTIVE => [self::COMPLETED, self::CANCELLED],
            self::COMPLETED => [],
            self::CANCELLED => [],
        ];

        return $allowed[$from];
    }

    public static function assertTransition(string $from, string $to): void
    {
        $from = self::normalize($from);
        $to = self::normalize($to);

        if ($from === $to) {
            return;
        }

        if (! in_array($to, self::allowedTargets($from), true)) {
            throw new DomainException("Invalid contract status transition: {$from} -> {$to}.");
        }
    }
}
