<?php

declare(strict_types=1);

namespace SafeContracts\Lifecycle;

final class Deactivator
{
    public static function deactivate(): void
    {
        // Deactivation is intentionally non-destructive. Business and audit data,
        // roles and configuration survive so reactivation is safe.
        do_action('safecontracts_deactivated');
    }
}
