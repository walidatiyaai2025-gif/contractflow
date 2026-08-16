<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class NotificationScheduleSettings
{
    public const DISPATCH_TIME_OPTION = 'safecontracts_notification_dispatch_time';
    public const DEFAULT_DISPATCH_TIME = '09:00';

    public function dispatchTime(): string
    {
        $value = trim((string) get_option(self::DISPATCH_TIME_OPTION, self::DEFAULT_DISPATCH_TIME));
        try {
            return $this->normalizeTime($value);
        } catch (InvalidArgumentException $error) {
            unset($error);
            return self::DEFAULT_DISPATCH_TIME;
        }
    }

    public function saveDispatchTime(mixed $value): string
    {
        $time = $this->normalizeTime($value);
        update_option(self::DISPATCH_TIME_OPTION, $time, false);
        return $time;
    }

    public function scheduledUtc(string $date): string
    {
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $local = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $this->dispatchTime(), $timezone);
        if (! $local || $local->format('Y-m-d H:i') !== $date . ' ' . $this->dispatchTime()) {
            throw new InvalidArgumentException('Notification schedule date is invalid.');
        }
        return $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public function localDateFromUtc(string $utcDateTime): string
    {
        $utc = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $utcDateTime, new DateTimeZone('UTC'));
        if (! $utc) {
            throw new InvalidArgumentException('Notification scheduled timestamp is invalid.');
        }
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        return $utc->setTimezone($timezone)->format('Y-m-d');
    }

    private function normalizeTime(mixed $value): string
    {
        $time = trim((string) $value);
        if (! preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            throw new InvalidArgumentException('Notification dispatch time must use HH:MM in 24-hour format.');
        }
        return $time;
    }
}
