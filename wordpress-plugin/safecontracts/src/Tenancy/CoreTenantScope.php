<?php

declare(strict_types=1);

namespace SafeContracts\Tenancy;

final class CoreTenantScope
{
    public static function tenantId(): ?int
    {
        $context = TenantContextStore::context();
        if (CoreTenantEnforcement::isEnabled()) {
            return $context->requireTenantId();
        }
        return $context->tenantId();
    }

    public static function condition(string $column): string
    {
        $tenantId = self::tenantId();
        return $tenantId === null ? '' : ' AND ' . $column . ' = ' . $tenantId;
    }
}
