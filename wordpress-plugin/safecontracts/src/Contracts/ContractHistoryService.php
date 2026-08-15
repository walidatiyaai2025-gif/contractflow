<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Roles\Capabilities;

final class ContractHistoryService
{
    public function __construct(
        private ?ContractRepository $contracts = null,
        private ?ContractHistoryRepository $history = null
    ) {
        $this->contracts ??= new ContractRepository();
        $this->history ??= new ContractHistoryRepository();
    }

    /** @return list<array{id:int, action:string, actor_user_id:?int, context:array<string, mixed>, created_at:string}> */
    public function forContract(int $contractId, int $limit = 100): array
    {
        if ($contractId <= 0) {
            throw new InvalidArgumentException('Contract ID must be positive.');
        }

        $contract = $this->contracts->find($contractId);
        if ($contract === null) {
            throw new InvalidArgumentException('Contract was not found.');
        }

        if (! current_user_can(Capabilities::ACCESS)) {
            throw new DomainException('You do not have permission to access SafeContracts.');
        }

        if (current_user_can(Capabilities::VIEW_ALL)) {
            return $this->history->forContract($contractId, $limit);
        }

        if (
            current_user_can(Capabilities::VIEW_ASSIGNED)
            && $contract['accountant_user_id'] !== null
            && $contract['accountant_user_id'] === get_current_user_id()
        ) {
            return $this->history->forContract($contractId, $limit);
        }

        throw new DomainException('Contract history is outside the current user data scope.');
    }
}
