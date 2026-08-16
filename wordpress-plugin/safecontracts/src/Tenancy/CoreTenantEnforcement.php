<?php

declare(strict_types=1);

namespace SafeContracts\Tenancy;

final class CoreTenantEnforcement
{
    public const OPTION = 'safecontracts_esc_core_tenant_enforcement';

    public static function isEnabled(): bool
    {
        $value = get_option(self::OPTION, '0');
        return $value === true || $value === 1 || $value === '1';
    }

    public static function enable(): void
    {
        (new CoreTenantOwnershipBackfill())->assertReadyForEnforcement();
        update_option(self::OPTION, '1', false);
    }

    public static function disable(): void
    {
        update_option(self::OPTION, '0', false);
    }
}
