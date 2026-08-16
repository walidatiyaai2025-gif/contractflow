<?php

declare(strict_types=1);

namespace SafeContracts\Lifecycle;

use SafeContracts\Notifications\NotificationScheduler;

final class Deactivator
{
    public static function deactivate(): void
    {
        NotificationScheduler::clear();
        // Deactivation is intentionally non-destructive. Business and audit data,
        // roles and configuration survive so reactivation is safe.
        do_action('safecontracts_deactivated');
    }
}
