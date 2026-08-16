<?php

declare(strict_types=1);

namespace SafeContracts\Tenancy;

final class NonCoreTenantScope
{
    public static function tenantId(): ?int
    {
        $context = TenantContextStore::context();
        if (NonCoreTenantEnforcement::isEnabled()) {
            return $context->requireTenantId();
        }
        return $context->tenantId();
    }

    public static function condition(string $column = 'tenant_id'): string
    {
        $tenantId = self::tenantId();
        return $tenantId === null ? '' : ' AND ' . $column . ' = ' . $tenantId;
    }
}
