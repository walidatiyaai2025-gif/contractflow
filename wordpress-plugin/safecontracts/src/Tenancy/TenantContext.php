<?php

declare(strict_types=1);

namespace SafeContracts\Tenancy;

use InvalidArgumentException;
use LogicException;
use RuntimeException;

final class TenantContext
{
    private ?int $tenantId = null;

    public function setTenantId(int $tenantId): void
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant id must be a positive integer.');
        }
        if ($this->tenantId !== null && $this->tenantId !== $tenantId) {
            throw new LogicException('Tenant context is already locked for this request.');
        }
        $this->tenantId = $tenantId;
    }

    public function hasTenant(): bool
    {
        return $this->tenantId !== null;
    }

    public function tenantId(): ?int
    {
        return $this->tenantId;
    }

    public function requireTenantId(): int
    {
        if ($this->tenantId === null) {
            throw new RuntimeException('Enterprise tenant context is required.');
        }
        return $this->tenantId;
    }

    public function clear(): void
    {
        $this->tenantId = null;
    }
}
