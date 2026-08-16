<?php

declare(strict_types=1);

namespace SafeContracts\Tenancy;

use RuntimeException;

final class NonCoreTenantEnforcement
{
    public const OPTION = 'safecontracts_esc_noncore_tenant_enforcement';

    public static function isEnabled(): bool
    {
        return get_option(self::OPTION, '0') === '1';
    }

    public static function enable(): void
    {
        (new NonCoreTenantOwnershipBackfill())->assertReadyForEnforcement();
        $hardener = new NonCoreTenantSchemaHardener();
        $verification = $hardener->verify();
        if (! $hardener->isHardened() || ! ($verification['ready'] ?? false)) {
            throw new RuntimeException('Non-core Enterprise tenant schema must be hardened and verified before runtime enforcement can be enabled.');
        }
        update_option(self::OPTION, '1', false);
    }

    public static function disable(): void
    {
        update_option(self::OPTION, '0', false);
    }
}
