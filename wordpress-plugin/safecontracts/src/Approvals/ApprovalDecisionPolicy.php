<?php

declare(strict_types=1);

namespace SafeContracts\Approvals;

use InvalidArgumentException;

final class ApprovalDecisionPolicy
{
    public const ACTION_APPROVE = 'approve';
    public const ACTION_REJECT = 'reject';

    public const REQUEST_STATUS_PENDING = 'pending';
    public const REQUEST_STATUS_APPROVED = 'approved';
    public const REQUEST_STATUS_REJECTED = 'rejected';

    public const MAX_IDEMPOTENCY_KEY_BYTES = 191;
    public const MAX_COMMENT_BYTES = 2000;

    public static function normalizeAction(string $action): string
    {
        $action = strtolower(trim($action));
        if (! in_array($action, [self::ACTION_APPROVE, self::ACTION_REJECT], true)) {
            throw new InvalidArgumentException('Approval Decision action must be approve or reject.');
        }
        return $action;
    }

    public static function normalizeIdempotencyKey(string $key): string
    {
        $key = trim($key);
        $length = strlen($key);
        if ($length < 1 || $length > self::MAX_IDEMPOTENCY_KEY_BYTES) {
            throw new InvalidArgumentException('Approval Decision idempotency key must be between 1 and 191 bytes.');
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $key) === 1) {
            throw new InvalidArgumentException('Approval Decision idempotency key contains unsupported control characters.');
        }
        return $key;
    }

    public static function decisionKeyHash(string $normalizedKey): string
    {
        return hash('sha256', $normalizedKey);
    }

    public static function normalizeComment(?string $comment): ?string
    {
        if ($comment === null) {
            return null;
        }
        $comment = trim($comment);
        if ($comment === '') {
            return null;
        }
        if (strlen($comment) > self::MAX_COMMENT_BYTES) {
            throw new InvalidArgumentException('Approval Decision comment exceeds the supported 2000-byte limit.');
        }
        if (str_contains($comment, "\0")) {
            throw new InvalidArgumentException('Approval Decision comment contains an unsupported null byte.');
        }
        return $comment;
    }
}
