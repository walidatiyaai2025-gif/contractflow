<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

/**
 * Kept as a compatibility hook for existing bootstrap registrations.
 *
 * Alkenzy ADV 0.3.2 removes the generic white summary-card strip from admin
 * pages. Page-specific operational content remains owned by each page class.
 */
final class AdminPageSummaryInjector
{
    public static function register(): void
    {
        // Intentionally no admin_notices injection in the premium 0.3.2 UI.
    }

    public static function render(): void
    {
        // Compatibility no-op. Do not reintroduce global summary cards here.
    }
}
