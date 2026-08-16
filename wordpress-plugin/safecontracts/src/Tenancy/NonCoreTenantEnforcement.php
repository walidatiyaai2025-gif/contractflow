<?php

declare(strict_types=1);

namespace SafeContracts\Tenancy;

final class NonCoreTenantEnforcement
{
    public const OPTION = 'safecontracts_esc_noncore_tenant_enforcement';

    public static function isEnabled(): bool
    {
        return get_option(self::OPTION, '0') === '1';
    }

    public static function enable(): void
    {
        // Runtime enforcement deliberately precedes destructive/non-null schema
        // hardening. Ownership must be verified first so ESC can prove tenant
        // isolation while the expand migration is still reversible.
        (new NonCoreTenantOwnershipBackfill())->assertReadyForEnforcement();
        update_option(self::OPTION, '1', false);
    }

    public static function disable(): void
    {
        update_option(self::OPTION, '0', false);
    }
}
