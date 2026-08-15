<?php

declare(strict_types=1);

namespace SafeContracts\FollowUps;

use InvalidArgumentException;

final class FollowUpState
{
    public const CONTACTED = 'contacted';
    public const PROMISED_TO_PAY = 'promised_to_pay';
    public const ISSUE = 'issue';
    public const DEFERRED = 'deferred';
    public const NEEDS_ESCALATION = 'needs_escalation';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::CONTACTED,
            self::PROMISED_TO_PAY,
            self::ISSUE,
            self::DEFERRED,
            self::NEEDS_ESCALATION,
        ];
    }

    public static function normalize(mixed $state): string
    {
        $value = strtolower(trim((string) $state));
        if (! in_array($value, self::all(), true)) {
            throw new InvalidArgumentException('Unknown SafeContracts follow-up state.');
        }
        return $value;
    }
}
