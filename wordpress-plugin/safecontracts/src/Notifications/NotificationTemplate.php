<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use InvalidArgumentException;

final class NotificationTemplate
{
    /** @var list<string> */
    private const PLACEHOLDERS = [
        'client_name',
        'contract_number',
        'payment_reference',
        'due_date',
        'original_amount',
        'remaining_amount',
        'days_overdue',
    ];

    /** @return list<string> */
    public static function allowedPlaceholders(): array
    {
        return self::PLACEHOLDERS;
    }

    /** @return array<string, mixed> */
    public static function normalizeInput(array $input): array
    {
        $code = NotificationRule::normalizeTemplateCode($input['code'] ?? '');
        $name = trim(strip_tags((string) ($input['name'] ?? '')));
        if ($name === '' || strlen($name) > 191) {
            throw new InvalidArgumentException('Notification template name is required and must not exceed 191 characters.');
        }

        $title = self::normalizeTemplateText($input['title_template'] ?? '', 255, 'title');
        $body = self::normalizeTemplateText($input['body_template'] ?? '', 5000, 'body');

        return [
            'code' => $code,
            'name' => $name,
            'title_template' => $title,
            'body_template' => $body,
            'is_active' => NotificationRule::normalizeBool($input['is_active'] ?? true),
        ];
    }

    /** @param array<string, mixed> $template @param array<string, mixed> $context @return array{title:string,body:string} */
    public static function render(array $template, array $context): array
    {
        $title = self::normalizeTemplateText($template['title_template'] ?? '', 255, 'title');
        $body = self::normalizeTemplateText($template['body_template'] ?? '', 5000, 'body');
        $replacements = [];
        foreach (self::PLACEHOLDERS as $placeholder) {
            $value = $context[$placeholder] ?? '';
            if (is_array($value) || is_object($value) || is_resource($value)) {
                throw new InvalidArgumentException('Notification placeholder values must be scalar.');
            }
            $text = trim(strip_tags((string) $value));
            $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
            if (strlen($text) > 1000) {
                $text = substr($text, 0, 1000);
            }
            $replacements['{' . $placeholder . '}'] = $text;
        }

        return [
            'title' => strtr($title, $replacements),
            'body' => strtr($body, $replacements),
        ];
    }

    /** @return array<string, mixed> */
    public static function fromRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'code' => (string) ($row['code'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'title_template' => (string) ($row['title_template'] ?? ''),
            'body_template' => (string) ($row['body_template'] ?? ''),
            'is_active' => (bool) ($row['is_active'] ?? false),
            'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
            'updated_by' => isset($row['updated_by']) ? (int) $row['updated_by'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private static function normalizeTemplateText(mixed $value, int $maxLength, string $field): string
    {
        $text = trim(strip_tags((string) $value));
        if ($text === '' || strlen($text) > $maxLength) {
            throw new InvalidArgumentException("Notification template {$field} is required and exceeds the allowed length.");
        }

        preg_match_all('/\{([a-z_]+)\}/', $text, $matches);
        foreach ($matches[1] ?? [] as $placeholder) {
            if (! in_array($placeholder, self::PLACEHOLDERS, true)) {
                throw new InvalidArgumentException('Unknown notification template placeholder: ' . $placeholder);
            }
        }

        if (preg_match('/\{[^}]*\}|\{[^\s]*$/', preg_replace('/\{(?:' . implode('|', self::PLACEHOLDERS) . ')\}/', '', $text) ?? '')) {
            throw new InvalidArgumentException('Notification template contains a malformed or unsupported placeholder.');
        }
        return $text;
    }
}
