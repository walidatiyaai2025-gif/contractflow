<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Roles\Capabilities;

final class ContractArchiveService
{
    public function __construct(private ?ContractArchiveRepository $repository = null)
    {
        $this->repository ??= new ContractArchiveRepository();
    }

    public function archive(int $contractId): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            throw new DomainException('You do not have permission to delete contracts from the dashboard.');
        }
        if ($contractId <= 0) {
            throw new InvalidArgumentException('Contract ID must be positive.');
        }

        $contract = $this->repository->findState($contractId);
        if ($contract === null) {
            throw new InvalidArgumentException('Contract was not found.');
        }
        $this->assertScope($contract);
        if ($contract['is_archived']) {
            return;
        }

        $actorId = get_current_user_id();
        $this->repository->archive($contractId, $actorId);
        do_action('safecontracts_contract_archived', $contractId, $actorId);
    }

    /** @param array{accountant_user_id:?int} $contract */
    private function assertScope(array $contract): void
    {
        if (current_user_can(Capabilities::VIEW_ALL)) {
            return;
        }
        if (
            current_user_can(Capabilities::VIEW_ASSIGNED)
            && $contract['accountant_user_id'] !== null
            && $contract['accountant_user_id'] === get_current_user_id()
        ) {
            return;
        }
        throw new DomainException('Contract is outside the current user data scope.');
    }
}
