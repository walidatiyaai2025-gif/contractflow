<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use InvalidArgumentException;
use SafeContracts\Roles\RoleRegistrar;

final class NotificationRule
{
    public const TRIGGER_BEFORE_DUE = 'before_due';

    /** @return list<string> */
    public static function allowedRecipientRoles(): array
    {
        return [
            RoleRegistrar::SYSTEM_ADMIN,
            RoleRegistrar::MANAGER,
            RoleRegistrar::ACCOUNTANT,
            RoleRegistrar::VIEWER,
        ];
    }

    public static function normalizeCode(mixed $value): string
    {
        $code = sanitize_key(trim((string) $value));
        if ($code === '' || strlen($code) > 100) {
            throw new InvalidArgumentException('Notification rule code is required and must not exceed 100 characters.');
        }
        return $code;
    }

    public static function normalizeName(mixed $value): string
    {
        $name = trim(strip_tags((string) $value));
        if ($name === '' || strlen($name) > 191) {
            throw new InvalidArgumentException('Notification rule name is required and must not exceed 191 characters.');
        }
        return $name;
    }

    public static function normalizeTrigger(mixed $value): string
    {
        $trigger = strtolower(trim((string) $value));
        if ($trigger !== self::TRIGGER_BEFORE_DUE) {
            throw new InvalidArgumentException('Unsupported notification trigger type for this delivery slice.');
        }
        return $trigger;
    }

    public static function normalizeDaysBefore(mixed $value): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('Notification days-before value must be an integer.');
        }
        $days = (int) $value;
        if ($days < 1 || $days > 365) {
            throw new InvalidArgumentException('Before-due notification rules must use 1 to 365 days.');
        }
        return $days;
    }

    /** @return list<string> */
    public static function normalizeRecipientRoles(mixed $value): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('Notification recipient roles must be an array.');
        }

        $allowed = array_flip(self::allowedRecipientRoles());
        $roles = [];
        foreach ($value as $role) {
            $slug = strtolower(trim((string) $role));
            if (! isset($allowed[$slug])) {
                throw new InvalidArgumentException('Notification recipient role is not a SafeContracts role.');
            }
            if (! in_array($slug, $roles, true)) {
                $roles[] = $slug;
            }
        }
        return $roles;
    }

    public static function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }
        if ($value === 0 || $value === '0' || $value === 'false' || $value === null) {
            return false;
        }
        throw new InvalidArgumentException('Notification boolean value is invalid.');
    }

    /** @return array<string, mixed> */
    public static function normalizeInput(array $input): array
    {
        $roles = self::normalizeRecipientRoles($input['recipient_roles'] ?? []);
        $assigned = self::normalizeBool($input['target_assigned_accountant'] ?? false);
        if ($roles === [] && ! $assigned) {
            throw new InvalidArgumentException('Notification rule must target at least one role or the assigned Accountant.');
        }

        return [
            'code' => self::normalizeCode($input['code'] ?? ''),
            'name' => self::normalizeName($input['name'] ?? ''),
            'trigger_type' => self::normalizeTrigger($input['trigger_type'] ?? self::TRIGGER_BEFORE_DUE),
            'days_before' => self::normalizeDaysBefore($input['days_before'] ?? 0),
            'recipient_roles' => $roles,
            'target_assigned_accountant' => $assigned,
            'is_active' => self::normalizeBool($input['is_active'] ?? true),
        ];
    }

    /** @return array<string, mixed> */
    public static function fromRow(array $row): array
    {
        $roles = json_decode((string) ($row['recipient_roles_json'] ?? '[]'), true);
        if (! is_array($roles)) {
            $roles = [];
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'code' => (string) ($row['code'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'trigger_type' => (string) ($row['trigger_type'] ?? ''),
            'days_before' => (int) ($row['days_before'] ?? 0),
            'recipient_roles' => array_values(array_map('strval', $roles)),
            'target_assigned_accountant' => (bool) ($row['target_assigned_accountant'] ?? false),
            'is_active' => (bool) ($row['is_active'] ?? false),
            'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
            'updated_by' => isset($row['updated_by']) ? (int) $row['updated_by'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}
