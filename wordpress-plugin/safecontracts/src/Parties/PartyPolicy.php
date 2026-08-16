<?php

declare(strict_types=1);

namespace SafeContracts\Parties;

final class PartyPolicy
{
    public const KIND_ORGANIZATION = 'organization';
    public const KIND_INDIVIDUAL = 'individual';
    public const KIND_GOVERNMENT = 'government';
    public const KIND_OTHER = 'other';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    /** @return list<string> */
    public static function kinds(): array
    {
        return [
            self::KIND_ORGANIZATION,
            self::KIND_INDIVIDUAL,
            self::KIND_GOVERNMENT,
            self::KIND_OTHER,
        ];
    }

    /** @return list<string> */
    public static function statuses(): array
    {
        return [self::STATUS_ACTIVE, self::STATUS_INACTIVE];
    }

    public static function isKind(string $kind): bool
    {
        return in_array($kind, self::kinds(), true);
    }

    public static function isStatus(string $status): bool
    {
        return in_array($status, self::statuses(), true);
    }
}
