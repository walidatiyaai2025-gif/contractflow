<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use DateTimeImmutable;
use InvalidArgumentException;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\RoleRegistrar;

final class NotificationRule
{
    public const TRIGGER_BEFORE_DUE = 'before_due';
    public const TRIGGER_DUE_DAY = 'due_day';
    public const TRIGGER_OVERDUE = 'overdue';

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

    /** @return list<string> */
    public static function allowedTriggers(): array
    {
        return [self::TRIGGER_BEFORE_DUE, self::TRIGGER_DUE_DAY, self::TRIGGER_OVERDUE];
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
        if (! in_array($trigger, self::allowedTriggers(), true)) {
            throw new InvalidArgumentException('Unsupported notification trigger type.');
        }
        return $trigger;
    }

    public static function normalizeDaysBefore(mixed $value): int
    {
        return self::normalizeBoundedInt($value, 1, 365, 'Notification days-before value');
    }

    public static function normalizeDaysAfter(mixed $value): int
    {
        return self::normalizeBoundedInt($value, 1, 365, 'Notification days-after value');
    }

    public static function normalizeRepeatInterval(mixed $value): int
    {
        return self::normalizeBoundedInt($value, 0, 365, 'Notification repeat interval');
    }

    public static function normalizeMaxRepeats(mixed $value): int
    {
        return self::normalizeBoundedInt($value, 0, 50, 'Notification max repeats');
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

    /** @return list<int> */
    public static function normalizeRecipientUserIds(mixed $value): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('Notification recipient users must be an array.');
        }
        $ids = [];
        foreach ($value as $userId) {
            if (filter_var($userId, FILTER_VALIDATE_INT) === false || (int) $userId <= 0) {
                throw new InvalidArgumentException('Notification recipient user IDs must be positive integers.');
            }
            $ids[(int) $userId] = (int) $userId;
        }
        ksort($ids, SORT_NUMERIC);
        return array_values($ids);
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
        $trigger = self::normalizeTrigger($input['trigger_type'] ?? self::TRIGGER_BEFORE_DUE);
        $roles = self::normalizeRecipientRoles($input['recipient_roles'] ?? []);
        $userIds = self::normalizeRecipientUserIds($input['recipient_user_ids'] ?? []);
        $escalationRoles = self::normalizeRecipientRoles($input['escalation_roles'] ?? []);
        $assigned = self::normalizeBool($input['target_assigned_accountant'] ?? false);
        if ($roles === [] && $userIds === [] && ! $assigned) {
            throw new InvalidArgumentException('Notification rule must target at least one role, selected user or the assigned Accountant.');
        }

        $pushEnabled = self::normalizeBool($input['push_enabled'] ?? true);
        $emailEnabled = self::normalizeBool($input['email_enabled'] ?? false);
        if (! $pushEnabled && ! $emailEnabled) {
            throw new InvalidArgumentException('Notification rule must enable Push, Email or both.');
        }

        $daysBefore = 0;
        $daysAfter = 0;
        if ($trigger === self::TRIGGER_BEFORE_DUE) {
            $daysBefore = self::normalizeDaysBefore($input['days_before'] ?? 0);
        } elseif ($trigger === self::TRIGGER_OVERDUE) {
            $daysAfter = self::normalizeDaysAfter($input['days_after'] ?? 0);
        }

        $repeatInterval = self::normalizeRepeatInterval($input['repeat_interval_days'] ?? 0);
        $maxRepeats = self::normalizeMaxRepeats($input['max_repeats'] ?? 0);
        if (($repeatInterval === 0) !== ($maxRepeats === 0)) {
            throw new InvalidArgumentException('Notification repeat interval and max repeats must either both be zero or both be configured.');
        }

        $templateCode = self::normalizeCode($input['template_code'] ?? self::defaultTemplateForTrigger($trigger));

        return [
            'code' => self::normalizeCode($input['code'] ?? ''),
            'name' => self::normalizeName($input['name'] ?? ''),
            'trigger_type' => $trigger,
            'days_before' => $daysBefore,
            'days_after' => $daysAfter,
            'repeat_interval_days' => $repeatInterval,
            'max_repeats' => $maxRepeats,
            'recipient_roles' => $roles,
            'recipient_user_ids' => $userIds,
            'escalation_roles' => $escalationRoles,
            'target_assigned_accountant' => $assigned,
            'push_enabled' => $pushEnabled,
            'email_enabled' => $emailEnabled,
            'template_code' => $templateCode,
            'is_active' => self::normalizeBool($input['is_active'] ?? true),
        ];
    }

    /** @param array<string, mixed> $rule */
    public static function matchesContractualDueDate(array $rule, mixed $dueDate, DateTimeImmutable $today): bool
    {
        $legacy = [
            'trigger_type' => self::TRIGGER_BEFORE_DUE,
            'days_before' => $rule['days_before'] ?? 0,
            'days_after' => 0,
            'repeat_interval_days' => 0,
            'max_repeats' => 0,
        ];
        return self::targetDate($legacy, $dueDate, 0)->format('Y-m-d') === $today->format('Y-m-d');
    }

    /** @param array<string, mixed> $rule @param array<string, mixed> $payment */
    public static function matchesPayment(array $rule, array $payment, DateTimeImmutable $today, int $attemptNo = 0): bool
    {
        if (! self::normalizeBool($rule['is_active'] ?? true)) {
            return false;
        }
        if ($attemptNo < 0) {
            throw new InvalidArgumentException('Notification attempt number cannot be negative.');
        }

        $status = PaymentStatus::normalize((string) ($payment['status'] ?? ''));
        $remaining = ContractMoney::normalizeNonNegative($payment['remaining_amount'] ?? '');
        if ($status === PaymentStatus::PAID || $remaining === '0.0000') {
            return false;
        }

        $repeatInterval = self::normalizeRepeatInterval($rule['repeat_interval_days'] ?? 0);
        $maxRepeats = self::normalizeMaxRepeats($rule['max_repeats'] ?? 0);
        if ($attemptNo > 0 && ($repeatInterval === 0 || $maxRepeats === 0 || $attemptNo > $maxRepeats)) {
            return false;
        }

        $target = self::targetDate($rule, $payment['due_date'] ?? '', $attemptNo);
        return $target->format('Y-m-d') === $today->format('Y-m-d');
    }

    /** @param array<string, mixed> $rule */
    public static function targetDate(array $rule, mixed $dueDate, int $attemptNo = 0): DateTimeImmutable
    {
        $due = self::normalizeDate($dueDate);
        $trigger = self::normalizeTrigger($rule['trigger_type'] ?? '');

        $base = match ($trigger) {
            self::TRIGGER_BEFORE_DUE => $due->modify('-' . self::normalizeDaysBefore($rule['days_before'] ?? 0) . ' days'),
            self::TRIGGER_DUE_DAY => $due,
            self::TRIGGER_OVERDUE => $due->modify('+' . self::normalizeDaysAfter($rule['days_after'] ?? 0) . ' days'),
        };

        if ($attemptNo <= 0) {
            return $base;
        }
        $repeatInterval = self::normalizeRepeatInterval($rule['repeat_interval_days'] ?? 0);
        $maxRepeats = self::normalizeMaxRepeats($rule['max_repeats'] ?? 0);
        if ($repeatInterval === 0 || $maxRepeats === 0 || $attemptNo > $maxRepeats) {
            throw new InvalidArgumentException('Notification repeat attempt is outside the configured cadence.');
        }
        return $base->modify('+' . ($attemptNo * $repeatInterval) . ' days');
    }

    public static function daysOverdue(mixed $dueDate, DateTimeImmutable $today): int
    {
        $due = self::normalizeDate($dueDate);
        if ($today <= $due) {
            return 0;
        }
        return (int) $due->diff($today)->format('%a');
    }

    /** @return array<string, mixed> */
    public static function fromRow(array $row): array
    {
        $roles = self::decodeRoles($row['recipient_roles_json'] ?? '[]');
        $recipientUserIds = self::decodeIds($row['recipient_user_ids_json'] ?? '[]');
        $escalationRoles = self::decodeRoles($row['escalation_roles_json'] ?? '[]');
        $trigger = (string) ($row['trigger_type'] ?? self::TRIGGER_BEFORE_DUE);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'code' => (string) ($row['code'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'trigger_type' => $trigger,
            'days_before' => (int) ($row['days_before'] ?? 0),
            'days_after' => (int) ($row['days_after'] ?? 0),
            'repeat_interval_days' => (int) ($row['repeat_interval_days'] ?? 0),
            'max_repeats' => (int) ($row['max_repeats'] ?? 0),
            'recipient_roles' => $roles,
            'recipient_user_ids' => $recipientUserIds,
            'escalation_roles' => $escalationRoles,
            'target_assigned_accountant' => (bool) ($row['target_assigned_accountant'] ?? false),
            'push_enabled' => ! array_key_exists('push_enabled', $row) || (bool) $row['push_enabled'],
            'email_enabled' => (bool) ($row['email_enabled'] ?? false),
            'template_code' => (string) ($row['template_code'] ?? self::defaultTemplateForTrigger($trigger)),
            'is_active' => (bool) ($row['is_active'] ?? false),
            'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
            'updated_by' => isset($row['updated_by']) ? (int) $row['updated_by'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private static function defaultTemplateForTrigger(string $trigger): string
    {
        return match ($trigger) {
            self::TRIGGER_DUE_DAY => 'payment_due_today',
            self::TRIGGER_OVERDUE => 'payment_overdue',
            default => 'payment_due_soon',
        };
    }

    private static function normalizeBoundedInt(mixed $value, int $min, int $max, string $field): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException("{$field} must be an integer.");
        }
        $number = (int) $value;
        if ($number < $min || $number > $max) {
            throw new InvalidArgumentException("{$field} must be between {$min} and {$max}.");
        }
        return $number;
    }

    private static function normalizeDate(mixed $value): DateTimeImmutable
    {
        $date = trim((string) $value);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Notification contractual due date must be a valid YYYY-MM-DD date.');
        }
        return $parsed;
    }

    /** @return list<string> */
    private static function decodeRoles(mixed $value): array
    {
        $decoded = json_decode((string) $value, true);
        if (! is_array($decoded)) {
            return [];
        }
        return array_values(array_map('strval', $decoded));
    }

    /** @return list<int> */
    private static function decodeIds(mixed $value): array
    {
        $decoded = json_decode((string) $value, true);
        if (! is_array($decoded)) {
            return [];
        }
        $ids = [];
        foreach ($decoded as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        ksort($ids, SORT_NUMERIC);
        return array_values($ids);
    }
}
