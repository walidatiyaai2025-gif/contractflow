<?php

declare(strict_types=1);

namespace SafeContracts\Obligations;

use InvalidArgumentException;

final class ObligationPolicy
{
    public const STATUS_OPEN = 'open';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const MAX_CODE_BYTES = 100;
    public const MAX_TITLE_BYTES = 191;
    public const MAX_DESCRIPTION_BYTES = 4000;
    public const MAX_SEARCH_LIMIT = 100;

    /** @return array{obligation_code:string,title:string,description:?string,due_date:?string} */
    public static function normalizeCreate(array $input): array
    {
        self::assertOnlyKeys($input, ['obligation_code', 'title', 'description', 'due_date']);
        if (! array_key_exists('obligation_code', $input) || ! array_key_exists('title', $input)) {
            throw new InvalidArgumentException('Contract Obligation code and title are required.');
        }
        return [
            'obligation_code' => self::normalizeCode((string) $input['obligation_code']),
            'title' => self::normalizeTitle((string) $input['title']),
            'description' => self::normalizeDescription($input['description'] ?? null),
            'due_date' => self::normalizeDueDate($input['due_date'] ?? null),
        ];
    }

    /** @return array{title:string,description:?string,due_date:?string} */
    public static function normalizeMetadataUpdate(array $input): array
    {
        self::assertOnlyKeys($input, ['title', 'description', 'due_date']);
        if (! array_key_exists('title', $input)) {
            throw new InvalidArgumentException('Contract Obligation metadata update requires title.');
        }
        return [
            'title' => self::normalizeTitle((string) $input['title']),
            'description' => self::normalizeDescription($input['description'] ?? null),
            'due_date' => self::normalizeDueDate($input['due_date'] ?? null),
        ];
    }

    /** @return array{status:?string,due_from:?string,due_to:?string,obligation_code:?string} */
    public static function normalizeSearch(array $input): array
    {
        self::assertOnlyKeys($input, ['status', 'due_from', 'due_to', 'obligation_code']);
        $status = null;
        if (array_key_exists('status', $input) && $input['status'] !== null && $input['status'] !== '') {
            $status = self::normalizeStatus((string) $input['status']);
        }
        $dueFrom = self::normalizeDueDate($input['due_from'] ?? null);
        $dueTo = self::normalizeDueDate($input['due_to'] ?? null);
        if ($dueFrom !== null && $dueTo !== null && $dueFrom > $dueTo) {
            throw new InvalidArgumentException('Contract Obligation due_from cannot be after due_to.');
        }
        $code = null;
        if (array_key_exists('obligation_code', $input) && $input['obligation_code'] !== null && $input['obligation_code'] !== '') {
            $code = self::normalizeCode((string) $input['obligation_code']);
        }
        return ['status' => $status, 'due_from' => $dueFrom, 'due_to' => $dueTo, 'obligation_code' => $code];
    }

    public static function normalizeCode(string $code): string
    {
        $code = strtolower(trim($code));
        if ($code === '' || strlen($code) > self::MAX_CODE_BYTES || preg_match('/^[a-z0-9][a-z0-9._-]*$/', $code) !== 1) {
            throw new InvalidArgumentException('Contract Obligation code must be 1-100 bytes of lowercase letters, digits, dot, underscore or hyphen.');
        }
        return $code;
    }

    public static function normalizeTitle(string $title): string
    {
        $title = trim($title);
        if ($title === '' || strlen($title) > self::MAX_TITLE_BYTES || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $title) === 1) {
            throw new InvalidArgumentException('Contract Obligation title must be 1-191 bytes and contain no unsupported control characters.');
        }
        return $title;
    }

    public static function normalizeDescription(mixed $description): ?string
    {
        if ($description === null || $description === '') {
            return null;
        }
        if (! is_string($description)) {
            throw new InvalidArgumentException('Contract Obligation description must be a string or null.');
        }
        $description = trim($description);
        if ($description === '') {
            return null;
        }
        if (strlen($description) > self::MAX_DESCRIPTION_BYTES || preg_match('/[\x00\x0B\x0C]/', $description) === 1) {
            throw new InvalidArgumentException('Contract Obligation description exceeds the supported bound or contains unsupported controls.');
        }
        return $description;
    }

    public static function normalizeDueDate(mixed $dueDate): ?string
    {
        if ($dueDate === null || $dueDate === '') {
            return null;
        }
        if (! is_string($dueDate) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dueDate, $parts) !== 1) {
            throw new InvalidArgumentException('Contract Obligation due_date must use YYYY-MM-DD DATE semantics.');
        }
        if (! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw new InvalidArgumentException('Contract Obligation due_date is not a valid calendar date.');
        }
        return $dueDate;
    }

    public static function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if (! in_array($status, [self::STATUS_OPEN, self::STATUS_COMPLETED, self::STATUS_CANCELLED], true)) {
            throw new InvalidArgumentException('Unsupported Contract Obligation status.');
        }
        return $status;
    }

    public static function normalizeTerminalTarget(string $status): string
    {
        $status = self::normalizeStatus($status);
        if (! in_array($status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true)) {
            throw new InvalidArgumentException('Contract Obligation terminal transition must target completed or cancelled.');
        }
        return $status;
    }

    public static function newUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    private static function assertOnlyKeys(array $input, array $allowed): void
    {
        $unsupported = array_diff(array_keys($input), $allowed);
        if ($unsupported !== []) {
            throw new InvalidArgumentException('Unsupported Contract Obligation field: ' . (string) reset($unsupported));
        }
    }
}
