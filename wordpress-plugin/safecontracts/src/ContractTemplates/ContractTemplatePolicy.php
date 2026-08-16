<?php

declare(strict_types=1);

namespace SafeContracts\ContractTemplates;

use InvalidArgumentException;

final class ContractTemplatePolicy
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const VERSION_DRAFT = 'draft';
    public const VERSION_PUBLISHED = 'published';
    public const MAX_DEFINITION_BYTES = 100000;
    public const MAX_DEFINITION_DEPTH = 32;

    /** @return list<string> */
    public static function statuses(): array
    {
        return [self::STATUS_ACTIVE, self::STATUS_INACTIVE];
    }

    public static function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if (! in_array($status, self::statuses(), true)) {
            throw new InvalidArgumentException('Contract Template status is not supported.');
        }
        return $status;
    }

    public static function normalizeCode(string $code): string
    {
        $code = strtolower(trim($code));
        $code = preg_replace('/\s+/', '_', $code) ?? '';
        if ($code === '' || strlen($code) > 100 || preg_match('/^[a-z0-9][a-z0-9._-]*$/', $code) !== 1) {
            throw new InvalidArgumentException('Contract Template code must be 1-100 lowercase machine-code characters using letters, numbers, dot, underscore or hyphen.');
        }
        return $code;
    }

    public static function encodeDefinition(mixed $definition): string
    {
        if (! is_array($definition)) {
            throw new InvalidArgumentException('Contract Template definition must be a JSON-compatible object/array.');
        }
        self::assertJsonCompatible($definition, 0);
        $json = json_encode($definition, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new InvalidArgumentException('Contract Template definition could not be encoded.');
        }
        if (strlen($json) > self::MAX_DEFINITION_BYTES) {
            throw new InvalidArgumentException('Contract Template definition exceeds the maximum encoded size.');
        }
        return $json;
    }

    private static function assertJsonCompatible(mixed $value, int $depth): void
    {
        if ($depth > self::MAX_DEFINITION_DEPTH) {
            throw new InvalidArgumentException('Contract Template definition exceeds the maximum nesting depth.');
        }
        if (is_array($value)) {
            foreach ($value as $key => $child) {
                if (! is_int($key) && ! is_string($key)) {
                    throw new InvalidArgumentException('Contract Template definition contains an unsupported key type.');
                }
                self::assertJsonCompatible($child, $depth + 1);
            }
            return;
        }
        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            return;
        }
        if (is_float($value) && is_finite($value)) {
            return;
        }
        throw new InvalidArgumentException('Contract Template definition contains a non-JSON-compatible value.');
    }
}
