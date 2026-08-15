<?php

declare(strict_types=1);

namespace SafeContracts\Lifecycle;

use SafeContracts\Notifications\NotificationScheduler;

final class Deactivator
{
    public static function deactivate(): void
    {
        // Deactivation is intentionally non-destructive. Business and audit data,
        // roles and configuration survive so reactivation is safe.
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(NotificationScheduler::HOOK);
        }
        do_action('safecontracts_deactivated');
    }
}
