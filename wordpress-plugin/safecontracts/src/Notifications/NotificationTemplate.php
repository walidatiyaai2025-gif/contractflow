<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use InvalidArgumentException;

final class NotificationTemplate
{
    private const ICON_KEYS = ['contract_due', 'payment', 'warning', 'success', 'safe_contracts'];

    /** @return list<string> */
    public static function allowedPlaceholders(): array
    {
        return [
            'customer_name',
            'contract_number',
            'payment_reference',
            'due_date',
            'remaining_amount',
            'days_overdue',
        ];
    }

    /** @return list<string> */
    public static function allowedIconKeys(): array
    {
        return self::ICON_KEYS;
    }

    /** @return array<string,mixed> */
    public static function normalizeInput(array $input): array
    {
        $title = self::normalizeText($input['title_template'] ?? '', 191, 'Notification template title');
        $body = self::normalizeText($input['body_template'] ?? '', 4000, 'Notification template body');
        $emailSubject = self::normalizeText($input['email_subject_template'] ?? $title, 191, 'Notification email subject');
        $emailBody = self::normalizeText($input['email_body_template'] ?? $body, 12000, 'Notification email body');
        $icon = sanitize_key((string) ($input['icon_key'] ?? 'contract_due'));
        if (! in_array($icon, self::ICON_KEYS, true)) {
            throw new InvalidArgumentException('Notification icon selection is invalid.');
        }

        return [
            'code' => NotificationRule::normalizeCode($input['code'] ?? ''),
            'title_template' => $title,
            'body_template' => $body,
            'email_subject_template' => $emailSubject,
            'email_body_template' => $emailBody,
            'icon_key' => $icon,
            'is_active' => NotificationRule::normalizeBool($input['is_active'] ?? true),
        ];
    }

    /** @return array{title:string,body:string,email_subject:string,email_body:string,icon_key:string} */
    public static function render(array $template, array $context): array
    {
        return [
            'title' => self::renderText((string) ($template['title_template'] ?? ''), $context),
            'body' => self::renderText((string) ($template['body_template'] ?? ''), $context),
            'email_subject' => self::renderText((string) ($template['email_subject_template'] ?? $template['title_template'] ?? ''), $context),
            'email_body' => self::renderText((string) ($template['email_body_template'] ?? $template['body_template'] ?? ''), $context),
            'icon_key' => self::normalizeIconFromRow($template['icon_key'] ?? 'contract_due'),
        ];
    }

    /** @return array<string, mixed> */
    public static function fromRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'code' => (string) ($row['code'] ?? ''),
            'title_template' => (string) ($row['title_template'] ?? ''),
            'body_template' => (string) ($row['body_template'] ?? ''),
            'email_subject_template' => (string) ($row['email_subject_template'] ?? $row['title_template'] ?? ''),
            'email_body_template' => (string) ($row['email_body_template'] ?? $row['body_template'] ?? ''),
            'icon_key' => self::normalizeIconFromRow($row['icon_key'] ?? 'contract_due'),
            'is_active' => (bool) ($row['is_active'] ?? false),
            'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
            'updated_by' => isset($row['updated_by']) ? (int) $row['updated_by'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private static function normalizeText(mixed $value, int $maxLength, string $field): string
    {
        $text = trim(wp_strip_all_tags((string) $value));
        if ($text === '' || strlen($text) > $maxLength) {
            throw new InvalidArgumentException("{$field} is required and must not exceed {$maxLength} characters.");
        }
        self::assertPlaceholdersAllowed($text);
        return $text;
    }

    private static function renderText(string $text, array $context): string
    {
        self::assertPlaceholdersAllowed($text);
        $rendered = preg_replace_callback('/{{\s*([a-z0-9_]+)\s*}}/i', static function (array $match) use ($context): string {
            $key = strtolower($match[1]);
            if (! array_key_exists($key, $context)) {
                throw new InvalidArgumentException("Notification template context is missing {$key}.");
            }
            $value = $context[$key];
            if (! is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException("Notification template context {$key} must be scalar.");
            }
            return trim(wp_strip_all_tags((string) $value));
        }, $text);

        if (! is_string($rendered)) {
            throw new InvalidArgumentException('Notification template could not be rendered.');
        }
        return $rendered;
    }

    private static function assertPlaceholdersAllowed(string $text): void
    {
        preg_match_all('/{{\s*([a-z0-9_]+)\s*}}/i', $text, $matches);
        $allowed = array_flip(self::allowedPlaceholders());
        foreach ($matches[1] ?? [] as $placeholder) {
            if (! isset($allowed[strtolower((string) $placeholder)])) {
                throw new InvalidArgumentException('Notification template contains an unsupported placeholder.');
            }
        }
    }

    private static function normalizeIconFromRow(mixed $value): string
    {
        $icon = sanitize_key((string) $value);
        return in_array($icon, self::ICON_KEYS, true) ? $icon : 'contract_due';
    }
}
