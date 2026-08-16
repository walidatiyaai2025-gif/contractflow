<?php

declare(strict_types=1);

namespace SafeContracts\Audit;

use Throwable;

final class NotificationCenterAuditRecorder
{
    private static bool $registered = false;

    /** @var list<string> */
    private const EVENTS = [
        'safecontracts_notification_rule_saved',
        'safecontracts_notification_template_saved',
        'safecontracts_notification_suppression_changed',
        'safecontracts_direct_notification_sent',
        'safecontracts_role_capabilities_changed',
        'safecontracts_user_role_changed',
    ];

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        foreach (self::EVENTS as $hook) {
            add_action($hook, static function (mixed ...$args) use ($hook): void {
                self::record($hook, $args);
            }, 10, 8);
        }
    }

    /** @param list<mixed> $args */
    private static function record(string $hook, array $args): void
    {
        [$entityType, $entityId, $eventType, $actorId, $after, $context] = self::map($hook, $args);
        try {
            (new AuditRepository())->append(
                $entityType,
                $entityId,
                $eventType,
                $actorId > 0 ? $actorId : null,
                null,
                self::sanitize($after),
                self::sanitize($context)
            );
        } catch (Throwable $error) {
            error_log('SafeContracts notification-center audit write failed for ' . $eventType . ': ' . $error->getMessage());
        }
    }

    /**
     * @param list<mixed> $args
     * @return array{0:string,1:?int,2:string,3:int,4:?array,5:?array}
     */
    private static function map(string $hook, array $args): array
    {
        return match ($hook) {
            'safecontracts_notification_rule_saved' => [
                'notification_rule',
                null,
                'notification_rule_saved',
                (int) ($args[1] ?? 0),
                [
                    'code' => (string) ($args[0] ?? ''),
                    'is_active' => (bool) (($args[2]['is_active'] ?? false)),
                    'push_enabled' => (bool) (($args[2]['push_enabled'] ?? false)),
                    'email_enabled' => (bool) (($args[2]['email_enabled'] ?? false)),
                    'recipient_roles' => is_array($args[2]['recipient_roles'] ?? null) ? $args[2]['recipient_roles'] : [],
                    'recipient_user_ids' => is_array($args[2]['recipient_user_ids'] ?? null) ? array_map('intval', $args[2]['recipient_user_ids']) : [],
                    'target_assigned_accountant' => (bool) (($args[2]['target_assigned_accountant'] ?? false)),
                ],
                null,
            ],
            'safecontracts_notification_template_saved' => [
                'notification_template',
                null,
                'notification_template_saved',
                (int) ($args[1] ?? 0),
                ['code' => (string) ($args[0] ?? '')],
                null,
            ],
            'safecontracts_notification_suppression_changed' => [
                (string) ($args[0] ?? 'notification'),
                (int) ($args[1] ?? 0),
                'notification_suppression_changed',
                (int) ($args[4] ?? 0),
                ['suppressed' => (bool) ($args[2] ?? false)],
                ['reason' => (string) ($args[3] ?? '')],
            ],
            'safecontracts_direct_notification_sent' => [
                'user',
                (int) ($args[0] ?? 0),
                'direct_notification_sent',
                (int) ($args[2] ?? 0),
                is_array($args[1] ?? null) ? [
                    'push_sent' => (int) ($args[1]['push_sent'] ?? 0),
                    'push_failed' => (int) ($args[1]['push_failed'] ?? 0),
                    'email_sent' => (int) ($args[1]['email_sent'] ?? 0),
                    'email_failed' => (int) ($args[1]['email_failed'] ?? 0),
                ] : [],
                null,
            ],
            'safecontracts_role_capabilities_changed' => [
                'role',
                null,
                'role_capabilities_changed',
                (int) ($args[2] ?? 0),
                [
                    'role' => (string) ($args[0] ?? ''),
                    'capabilities' => is_array($args[1] ?? null) ? array_values(array_map('strval', $args[1])) : [],
                ],
                null,
            ],
            'safecontracts_user_role_changed' => [
                'user',
                (int) ($args[0] ?? 0),
                'user_role_changed',
                (int) ($args[2] ?? 0),
                ['role' => (string) ($args[1] ?? '')],
                null,
            ],
            default => ['system', null, $hook, get_current_user_id(), [], null],
        };
    }

    private static function sanitize(?array $context): ?array
    {
        if ($context === null) {
            return null;
        }
        $clean = [];
        foreach ($context as $key => $value) {
            $name = strtolower((string) $key);
            if (preg_match('/token|secret|password|credential|authorization|private[_-]?key|service[_-]?account|storage[_-]?key|sha256|tmp[_-]?name/', $name)) {
                continue;
            }
            $clean[$key] = is_array($value) ? self::sanitize($value) : $value;
        }
        return $clean;
    }
}
