<?php

declare(strict_types=1);

namespace SafeContracts\Approvals;

use InvalidArgumentException;
use SafeContracts\Workflows\WorkflowDefinitionPolicy;

final class ApprovalRequestPolicy
{
    public const STATUS_PENDING = 'pending';
    public const MAX_IDEMPOTENCY_KEY_BYTES = 191;
    public const MAX_CANDIDATES_PER_STAGE = 256;
    public const MAX_CANDIDATES_PER_REQUEST = 1024;

    public static function normalizeTransitionCode(string $code): string
    {
        return WorkflowDefinitionPolicy::normalizeCode($code, 'Workflow transition code');
    }

    public static function normalizeIdempotencyKey(string $key): string
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > self::MAX_IDEMPOTENCY_KEY_BYTES) {
            throw new InvalidArgumentException('Approval Request idempotency key must be 1-191 bytes.');
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $key) === 1) {
            throw new InvalidArgumentException('Approval Request idempotency key contains control characters.');
        }
        return $key;
    }

    public static function requestKeyHash(string $normalizedKey): string
    {
        if ($normalizedKey === '') {
            throw new InvalidArgumentException('Approval Request idempotency key is required before hashing.');
        }
        return hash('sha256', $normalizedKey);
    }
}
