<?php

declare(strict_types=1);

namespace SafeContracts\Audit;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Roles\Capabilities;

final class AuditService
{
    public function __construct(private ?AuditRepository $repository = null)
    {
        $this->repository ??= new AuditRepository();
    }

    /** @return list<array<string, mixed>> */
    public function forEntity(string $entityType, ?int $entityId = null, int $limit = 100): array
    {
        if (! current_user_can(Capabilities::VIEW_AUDIT)) {
            throw new DomainException('You do not have permission to view the SafeContracts audit log.');
        }
        $entityType = strtolower(trim($entityType));
        if ($entityType === '' || ! preg_match('/^[a-z0-9_]{1,32}$/', $entityType)) {
            throw new InvalidArgumentException('Audit entity type is invalid.');
        }
        if ($entityId !== null && $entityId <= 0) {
            throw new InvalidArgumentException('Audit entity ID must be positive when supplied.');
        }
        return $this->repository->forEntity($entityType, $entityId, max(1, min(500, $limit)));
    }
}
