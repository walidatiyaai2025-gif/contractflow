<?php

declare(strict_types=1);

namespace SafeContracts\Expiry;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class ContractTermExpiryPolicy
{
    public const STATE_UNDATED = 'undated';
    public const STATE_NOT_EXPIRED = 'not_expired';
    public const STATE_ENDS_TODAY = 'ends_today';
    public const STATE_EXPIRED = 'expired';

    public static function normalizeDate(string $date, string $label = 'Date'): string
    {
        $date = trim($date);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed === false
            || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
            || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("{$label} must be a valid YYYY-MM-DD calendar date.");
        }
        return $date;
    }

    /**
     * @return array{expiry_state:string,days_until_end:?int,days_past_end:?int}
     */
    public static function evaluate(?string $endDate, string $asOfDate): array
    {
        $asOfDate = self::normalizeDate($asOfDate, 'As-of date');
        if ($endDate === null || trim($endDate) === '') {
            return [
                'expiry_state' => self::STATE_UNDATED,
                'days_until_end' => null,
                'days_past_end' => null,
            ];
        }

        $endDate = self::normalizeDate($endDate, 'Contract end date');
        $timezone = new DateTimeZone('UTC');
        $asOf = DateTimeImmutable::createFromFormat('!Y-m-d', $asOfDate, $timezone);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate, $timezone);
        if ($asOf === false || $end === false) {
            throw new InvalidArgumentException('Unable to evaluate Contract term dates.');
        }

        $signedDaysUntilEnd = (int) $asOf->diff($end)->format('%r%a');
        if ($signedDaysUntilEnd > 0) {
            return [
                'expiry_state' => self::STATE_NOT_EXPIRED,
                'days_until_end' => $signedDaysUntilEnd,
                'days_past_end' => null,
            ];
        }
        if ($signedDaysUntilEnd === 0) {
            return [
                'expiry_state' => self::STATE_ENDS_TODAY,
                'days_until_end' => 0,
                'days_past_end' => null,
            ];
        }

        return [
            'expiry_state' => self::STATE_EXPIRED,
            'days_until_end' => null,
            'days_past_end' => abs($signedDaysUntilEnd),
        ];
    }
}
