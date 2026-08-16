<?php

declare(strict_types=1);

namespace SafeContracts\ContractTypes;

use InvalidArgumentException;

final class ContractTypePolicy
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    /** @return list<string> */
    public static function statuses(): array
    {
        return [self::STATUS_ACTIVE, self::STATUS_INACTIVE];
    }

    public static function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if (! in_array($status, self::statuses(), true)) {
            throw new InvalidArgumentException('Contract Type status is not supported.');
        }
        return $status;
    }

    public static function normalizeCode(string $code): string
    {
        $code = strtolower(trim($code));
        $code = preg_replace('/\s+/', '_', $code) ?? '';
        if ($code === '' || strlen($code) > 100 || preg_match('/^[a-z0-9][a-z0-9._-]*$/', $code) !== 1) {
            throw new InvalidArgumentException('Contract Type code must be 1-100 lowercase machine-code characters using letters, numbers, dot, underscore or hyphen.');
        }
        return $code;
    }
}
