<?php

declare(strict_types=1);

namespace SafeContracts\Approvals;

use InvalidArgumentException;

final class ApprovalReleasePolicy
{
    public const MAX_IDEMPOTENCY_KEY_BYTES = 191;
    private const TRANSITION_KEY_NAMESPACE = 'esc-approval-release:v1:';

    public static function normalizeIdempotencyKey(string $key): string
    {
        $key = trim($key);
        $length = strlen($key);
        if ($length < 1 || $length > self::MAX_IDEMPOTENCY_KEY_BYTES) {
            throw new InvalidArgumentException('Approval Release idempotency key must be between 1 and 191 bytes.');
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $key) === 1) {
            throw new InvalidArgumentException('Approval Release idempotency key contains unsupported control characters.');
        }
        return $key;
    }

    public static function releaseKeyHash(string $normalizedKey): string
    {
        return hash('sha256', $normalizedKey);
    }

    /**
     * Derive a domain-separated P6 transition request identity from the normalized release key.
     * The raw client key is never persisted and cannot collide with ordinary P6 caller keys.
     */
    public static function transitionRequestKeyHash(string $normalizedKey): string
    {
        return hash('sha256', self::TRANSITION_KEY_NAMESPACE . $normalizedKey);
    }
}
