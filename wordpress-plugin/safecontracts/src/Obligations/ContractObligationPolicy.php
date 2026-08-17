<?php

declare(strict_types=1);

namespace SafeContracts\Obligations;

use DateTimeImmutable;
use InvalidArgumentException;

final class ContractObligationPolicy
{
    public const STATUS_OPEN = 'open';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /** @return list<string> */
    public static function statuses(): array
    {
        return [self::STATUS_OPEN, self::STATUS_COMPLETED, self::STATUS_CANCELLED];
    }

    /** @return list<string> */
    public static function terminalStatuses(): array
    {
        return [self::STATUS_COMPLETED, self::STATUS_CANCELLED];
    }

    public static function normalizeCode(string $code): string
    {
        $code = strtolower(trim($code));
        $code = preg_replace('/\s+/', '_', $code) ?? '';
        if ($code === '' || strlen($code) > 64 || preg_match('/^[a-z0-9][a-z0-9._-]*$/', $code) !== 1) {
            throw new InvalidArgumentException('Obligation code must be 1-64 lowercase machine-code characters using letters, numbers, dot, underscore or hyphen.');
        }
        return $code;
    }

    public static function normalizeTitle(string $title): string
    {
        $title = trim(strip_tags($title));
        if ($title === '') {
            throw new InvalidArgumentException('Obligation title is required.');
        }
        if (strlen($title) > 191) {
            throw new InvalidArgumentException('Obligation title must not exceed 191 characters.');
        }
        return $title;
    }

    public static function normalizeDescription(?string $description): ?string
    {
        if ($description === null) {
            return null;
        }
        $description = trim(strip_tags($description));
        if ($description === '') {
            return null;
        }
        if (strlen($description) > 4000) {
            throw new InvalidArgumentException('Obligation description must not exceed 4000 characters.');
        }
        return $description;
    }

    public static function normalizeDueDate(?string $dueDate): ?string
    {
        if ($dueDate === null || trim($dueDate) === '') {
            return null;
        }
        $dueDate = trim($dueDate);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false
            || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
            || $date->format('Y-m-d') !== $dueDate) {
            throw new InvalidArgumentException('Obligation due date must be a valid YYYY-MM-DD contractual date.');
        }
        return $dueDate;
    }

    public static function normalizeTerminalStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if (! in_array($status, self::terminalStatuses(), true)) {
            throw new InvalidArgumentException('Obligation terminal status is not supported.');
        }
        return $status;
    }
}
