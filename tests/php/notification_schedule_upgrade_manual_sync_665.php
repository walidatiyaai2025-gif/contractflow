<?php

declare(strict_types=1);

$tests = 0;

function sc_665_sync_assert(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
$scheduler = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Notifications/NotificationScheduler.php');
$bootstrap = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/safecontracts.php');

sc_665_sync_assert(
    str_contains($scheduler, "public const MANUAL_SYNC_ACTION = 'safecontracts_notification_schedule_sync_now'"),
    'manual schedule synchronization has a stable governed admin action'
);
sc_665_sync_assert(
    str_contains($scheduler, "add_action('admin_post_' . self::MANUAL_SYNC_ACTION, [self::class, 'handleManualSync'])"),
    'manual schedule synchronization is wired through admin-post'
);
sc_665_sync_assert(
    str_contains($scheduler, 'current_user_can(Capabilities::MANAGE_NOTIFICATIONS)'),
    'manual schedule synchronization requires notification-management capability'
);
sc_665_sync_assert(
    str_contains($scheduler, 'check_admin_referer(self::MANUAL_SYNC_ACTION)'),
    'manual schedule synchronization is nonce protected'
);
sc_665_sync_assert(
    str_contains($scheduler, "self::adminText('Sync schedule now', 'مزامنة الجدولة الآن')"),
    'notification schedule page exposes the requested Arabic manual synchronization control'
);
sc_665_sync_assert(
    str_contains($scheduler, "public const SEEDED_VERSION_OPTION = 'safecontracts_notification_schedule_seeded_version'"),
    'full schedule reconciliation is version scoped rather than one-time forever'
);
sc_665_sync_assert(
    str_contains($scheduler, '$seededVersion === $version'),
    'same-version requests do not repeat a successful upgrade reconciliation'
);
sc_665_sync_assert(
    str_contains($scheduler, '(new NotificationScheduleService())->sync()'),
    'upgrade reconciliation executes the authoritative full schedule sync service'
);
sc_665_sync_assert(
    str_contains($scheduler, "self::recordFullSync(\$count, 'upgrade')"),
    'successful upgrade reconciliation records auditable full-sync evidence'
);
sc_665_sync_assert(
    str_contains($scheduler, "self::recordFullSync(\$count, 'manual')"),
    'manual reconciliation records auditable full-sync evidence'
);
sc_665_sync_assert(
    str_contains($scheduler, "self::recordFullSync(\$count, 'cron')"),
    'cron reconciliation records the same auditable full-sync evidence'
);
sc_665_sync_assert(
    strpos($scheduler, 'self::syncAfterUpgrade();') > strpos($scheduler, "if (function_exists('wp_next_scheduled')"),
    'upgrade reconciliation remains outside the cron-registration guard and therefore does not wait for WP-Cron availability'
);
sc_665_sync_assert(
    str_contains($bootstrap, 'Version: 0.3.23') && str_contains($bootstrap, "SAFECONTRACTS_VERSION', '0.3.23'"),
    'upgrade/manual reconciliation ships only in forward release 0.3.23'
);

echo "SafeContracts notification upgrade/manual sync #665 passed ({$tests} assertions).\n";
