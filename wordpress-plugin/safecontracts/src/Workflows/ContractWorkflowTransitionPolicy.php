<?php

declare(strict_types=1);

namespace SafeContracts\Workflows;

use InvalidArgumentException;

final class ContractWorkflowTransitionPolicy
{
    public const MAX_IDEMPOTENCY_KEY_BYTES = 191;

    public static function normalizeTransitionCode(string $code): string
    {
        return WorkflowDefinitionPolicy::normalizeCode($code);
    }

    public static function normalizeIdempotencyKey(string $key): string
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > self::MAX_IDEMPOTENCY_KEY_BYTES) {
            throw new InvalidArgumentException('Workflow transition idempotency key must be 1-191 bytes.');
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $key) === 1) {
            throw new InvalidArgumentException('Workflow transition idempotency key contains control characters.');
        }
        return $key;
    }

    public static function requestKeyHash(string $normalizedKey): string
    {
        if ($normalizedKey === '') {
            throw new InvalidArgumentException('Workflow transition idempotency key is required before hashing.');
        }
        return hash('sha256', $normalizedKey);
    }
}
