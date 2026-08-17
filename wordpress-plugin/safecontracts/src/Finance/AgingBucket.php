<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class AgingBucket
{
    public const CURRENT = 'current';
    public const DAYS_1_30 = '1_30';
    public const DAYS_31_60 = '31_60';
    public const DAYS_61_90 = '61_90';
    public const DAYS_90_PLUS = '90_plus';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::CURRENT,
            self::DAYS_1_30,
            self::DAYS_31_60,
            self::DAYS_61_90,
            self::DAYS_90_PLUS,
        ];
    }

    public static function normalize(mixed $value): string
    {
        $bucket = strtolower(trim((string) $value));
        if (! in_array($bucket, self::all(), true)) {
            throw new InvalidArgumentException('Unsupported financial aging bucket.');
        }
        return $bucket;
    }

    public static function forDueDate(mixed $dueDate, ?DateTimeImmutable $today = null): string
    {
        $today = self::today($today);
        $due = self::date($dueDate, $today->getTimezone());
        if ($due >= $today) {
            return self::CURRENT;
        }

        $days = (int) $due->diff($today)->format('%a');
        if ($days <= 30) {
            return self::DAYS_1_30;
        }
        if ($days <= 60) {
            return self::DAYS_31_60;
        }
        if ($days <= 90) {
            return self::DAYS_61_90;
        }
        return self::DAYS_90_PLUS;
    }

    public static function sqlCase(string $dueExpression, string $todaySql): string
    {
        return "CASE
            WHEN {$dueExpression} >= {$todaySql} THEN '" . self::CURRENT . "'
            WHEN DATEDIFF({$todaySql}, {$dueExpression}) BETWEEN 1 AND 30 THEN '" . self::DAYS_1_30 . "'
            WHEN DATEDIFF({$todaySql}, {$dueExpression}) BETWEEN 31 AND 60 THEN '" . self::DAYS_31_60 . "'
            WHEN DATEDIFF({$todaySql}, {$dueExpression}) BETWEEN 61 AND 90 THEN '" . self::DAYS_61_90 . "'
            ELSE '" . self::DAYS_90_PLUS . "'
        END";
    }

    private static function today(?DateTimeImmutable $today): DateTimeImmutable
    {
        if ($today !== null) {
            return $today->setTime(0, 0, 0);
        }
        if (function_exists('current_datetime')) {
            /** @var DateTimeImmutable $current */
            $current = current_datetime();
            return $current->setTime(0, 0, 0);
        }
        return new DateTimeImmutable('today', new DateTimeZone('UTC'));
    }

    private static function date(mixed $value, DateTimeZone $timezone): DateTimeImmutable
    {
        $raw = trim((string) $value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, $timezone);
        if (! $date || $date->format('Y-m-d') !== $raw) {
            throw new InvalidArgumentException('Financial due date must use YYYY-MM-DD and be valid.');
        }
        return $date;
    }
}
