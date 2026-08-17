<?php

declare(strict_types=1);

namespace SafeContracts\Notices;

use InvalidArgumentException;

final class ContractNoticePeriodRulePolicy
{
    public const PURPOSE_RENEWAL_ELECTION = 'renewal_election';
    public const PURPOSE_NON_RENEWAL = 'non_renewal';
    public const PURPOSE_TERMINATION = 'termination';
    public const PURPOSE_OTHER = 'other';

    public const DIRECTION_OUTBOUND = 'outbound';
    public const DIRECTION_INBOUND = 'inbound';
    public const DIRECTION_EITHER = 'either';

    public const UNIT_DAY = 'day';
    public const UNIT_MONTH = 'month';
    public const UNIT_YEAR = 'year';

    /** @return list<string> */
    public static function purposes(): array
    {
        return [
            self::PURPOSE_RENEWAL_ELECTION,
            self::PURPOSE_NON_RENEWAL,
            self::PURPOSE_TERMINATION,
            self::PURPOSE_OTHER,
        ];
    }

    /** @return list<string> */
    public static function directions(): array
    {
        return [self::DIRECTION_OUTBOUND, self::DIRECTION_INBOUND, self::DIRECTION_EITHER];
    }

    /** @return list<string> */
    public static function periodUnits(): array
    {
        return [self::UNIT_DAY, self::UNIT_MONTH, self::UNIT_YEAR];
    }

    public static function normalizeCode(string $code): string
    {
        $code = strtolower(trim($code));
        $code = preg_replace('/\s+/', '_', $code) ?? '';
        if ($code === '' || strlen($code) > 64 || preg_match('/^[a-z0-9][a-z0-9._-]*$/', $code) !== 1) {
            throw new InvalidArgumentException('Notice period code must be 1-64 lowercase machine-code characters using letters, numbers, dot, underscore or hyphen.');
        }
        return $code;
    }

    public static function normalizePurpose(string $purpose): string
    {
        $purpose = strtolower(trim($purpose));
        if (! in_array($purpose, self::purposes(), true)) {
            throw new InvalidArgumentException('Contract notice period purpose is not supported.');
        }
        return $purpose;
    }

    public static function normalizeDirection(string $direction): string
    {
        $direction = strtolower(trim($direction));
        if (! in_array($direction, self::directions(), true)) {
            throw new InvalidArgumentException('Contract notice period direction is not supported.');
        }
        return $direction;
    }

    /** @return array{period_value:int,period_unit:string} */
    public static function normalizePeriod(int $value, string $unit): array
    {
        if ($value < 1 || $value > 10000) {
            throw new InvalidArgumentException('Contract notice period value must be between 1 and 10000.');
        }
        $unit = strtolower(trim($unit));
        if (! in_array($unit, self::periodUnits(), true)) {
            throw new InvalidArgumentException('Contract notice period unit is not supported.');
        }
        return ['period_value' => $value, 'period_unit' => $unit];
    }

    public static function normalizeActive(bool|int $active): int
    {
        return (int) ((bool) $active);
    }

    public static function normalizeNotes(?string $notes): ?string
    {
        if ($notes === null) {
            return null;
        }
        $notes = trim(strip_tags($notes));
        if ($notes === '') {
            return null;
        }
        if (strlen($notes) > 4000) {
            throw new InvalidArgumentException('Contract notice period notes must not exceed 4000 characters.');
        }
        return $notes;
    }

    public static function normalizeExpectedRevision(int $revision): int
    {
        if ($revision < 1) {
            throw new InvalidArgumentException('Contract notice period expected revision must be positive.');
        }
        return $revision;
    }
}
