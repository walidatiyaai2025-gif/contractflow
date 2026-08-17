<?php

declare(strict_types=1);

namespace SafeContracts\Renewals;

use InvalidArgumentException;

final class ContractRenewalTermsPolicy
{
    public const MODE_NONE = 'none';
    public const MODE_MANUAL = 'manual';
    public const MODE_AUTOMATIC = 'automatic';

    public const UNIT_DAY = 'day';
    public const UNIT_MONTH = 'month';
    public const UNIT_YEAR = 'year';

    /** @return list<string> */
    public static function modes(): array
    {
        return [self::MODE_NONE, self::MODE_MANUAL, self::MODE_AUTOMATIC];
    }

    /** @return list<string> */
    public static function intervalUnits(): array
    {
        return [self::UNIT_DAY, self::UNIT_MONTH, self::UNIT_YEAR];
    }

    public static function normalizeMode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        if (! in_array($mode, self::modes(), true)) {
            throw new InvalidArgumentException('Contract renewal mode is not supported.');
        }
        return $mode;
    }

    /** @return array{interval_value:?int, interval_unit:?string} */
    public static function normalizeInterval(string $mode, ?int $value, ?string $unit): array
    {
        $mode = self::normalizeMode($mode);
        if ($mode === self::MODE_NONE) {
            return ['interval_value' => null, 'interval_unit' => null];
        }
        if ($value === null || $value < 1 || $value > 10000) {
            throw new InvalidArgumentException('Enabled Contract renewal terms require interval value between 1 and 10000.');
        }
        if ($unit === null) {
            throw new InvalidArgumentException('Enabled Contract renewal terms require an interval unit.');
        }
        $unit = strtolower(trim($unit));
        if (! in_array($unit, self::intervalUnits(), true)) {
            throw new InvalidArgumentException('Contract renewal interval unit is not supported.');
        }
        return ['interval_value' => $value, 'interval_unit' => $unit];
    }

    public static function normalizeMaxOccurrences(string $mode, ?int $maxOccurrences): ?int
    {
        $mode = self::normalizeMode($mode);
        if ($mode === self::MODE_NONE) {
            return null;
        }
        if ($maxOccurrences === null) {
            return null;
        }
        if ($maxOccurrences < 1 || $maxOccurrences > 10000) {
            throw new InvalidArgumentException('Contract renewal max occurrences must be between 1 and 10000 when configured.');
        }
        return $maxOccurrences;
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
            throw new InvalidArgumentException('Contract renewal notes must not exceed 4000 characters.');
        }
        return $notes;
    }

    public static function normalizeExpectedRevision(int $revision): int
    {
        if ($revision < 1) {
            throw new InvalidArgumentException('Contract renewal expected revision must be positive.');
        }
        return $revision;
    }
}
