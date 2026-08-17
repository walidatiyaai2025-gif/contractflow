<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Payments\CurrencyCode;
use SafeContracts\Roles\Capabilities;

final class CounterpartyContractService
{
    public function __construct(
        private ?ContractRepository $repository = null,
        private ?CounterpartyAssignmentRepository $assignmentRepository = null
    ) {
        $this->repository ??= new ContractRepository();
        $this->assignmentRepository ??= new CounterpartyAssignmentRepository();
    }

    /**
     * @param array{
     *   contract_number:mixed,
     *   counterparty_type:mixed,
     *   counterparty_id:mixed,
     *   currency_code?:mixed,
     *   accountant_user_id?:mixed,
     *   notes?:mixed
     * } $input
     */
    public function create(array $input): int
    {
        $this->requireCapability(Capabilities::CREATE_CONTRACTS, 'You do not have permission to create contracts.');
        $type = Counterparty::normalize($input['counterparty_type'] ?? '');
        $counterpartyId = (int) ($input['counterparty_id'] ?? 0);
        if ($counterpartyId <= 0 || ! $this->repository->counterpartyIsActive($type, $counterpartyId)) {
            throw new InvalidArgumentException('Contract counterparty must reference an active SafeContracts customer or supplier.');
        }
        if ($type === Counterparty::SUPPLIER) {
            $this->requireAny(
                [Capabilities::VIEW_SUPPLIERS, Capabilities::MANAGE_SUPPLIERS],
                'You do not have permission to use SafeContracts suppliers as contract counterparties.'
            );
        }

        $contractNumber = $this->contractNumber($input['contract_number'] ?? '');
        $currencyCode = CurrencyCode::fromInputOrSettings($input['currency_code'] ?? null);
        $direction = Counterparty::defaultFinancialDirection($type);
        $accountantUserId = $this->optionalUserId($input['accountant_user_id'] ?? null);
        if ($accountantUserId === null && current_user_can(Capabilities::VIEW_ASSIGNED) && ! current_user_can(Capabilities::VIEW_ALL)) {
            $accountantUserId = get_current_user_id();
        }
        if ($accountantUserId !== null) {
            if ($accountantUserId !== get_current_user_id()) {
                $this->requireCapability(Capabilities::ASSIGN_CONTRACTS, 'You do not have permission to assign contracts.');
            }
            $this->assertEligibleAccountant($accountantUserId);
        }

        $notes = trim(strip_tags((string) ($input['notes'] ?? '')));
        if (strlen($notes) > 5000) {
            throw new InvalidArgumentException('Contract notes must not exceed 5000 characters.');
        }
        $actorId = get_current_user_id();
        $contractId = $this->repository->createForCounterparty(
            $contractNumber,
            $type,
            $counterpartyId,
            $direction,
            $currencyCode,
            $accountantUserId,
            $notes,
            $actorId
        );
        $legacyCustomerId = $type === Counterparty::CUSTOMER ? $counterpartyId : null;
        do_action('safecontracts_contract_created', $contractId, $actorId, $legacyCustomerId, $accountantUserId);
        do_action(
            'safecontracts_contract_counterparty_created',
            $contractId,
            $type,
            $counterpartyId,
            $direction,
            $currencyCode,
            $actorId
        );
        return $contractId;
    }

    public function assign(int $contractId, mixed $counterpartyType, mixed $counterpartyId): void
    {
        $this->requireCapability(Capabilities::ASSIGN_CONTRACTS, 'You do not have permission to assign contracts.');
        if ($contractId <= 0) {
            throw new InvalidArgumentException('Contract ID must be positive.');
        }
        $contract = $this->repository->find($contractId);
        if ($contract === null) {
            throw new InvalidArgumentException('Contract was not found.');
        }
        $this->assertScope($contract['accountant_user_id']);
        if ($contract['is_archived']) {
            throw new DomainException('Archived contracts cannot change counterparty.');
        }

        $type = Counterparty::normalize($counterpartyType);
        $counterpartyId = (int) $counterpartyId;
        if ($counterpartyId <= 0) {
            throw new InvalidArgumentException('Contract counterparty ID must be positive.');
        }

        // Re-saving the existing counterparty is a no-op. This must remain
        // possible even after obligations exist or the Supplier master is later
        // archived, because neither action changes historical financial truth.
        if ($type === $contract['counterparty_type'] && $counterpartyId === $contract['counterparty_id']) {
            return;
        }

        if ($this->assignmentRepository->hasScheduledObligations($contractId)) {
            throw new DomainException('Contract counterparty cannot change after financial obligations exist.');
        }
        if (! $this->repository->counterpartyIsActive($type, $counterpartyId)) {
            throw new InvalidArgumentException('Contract counterparty must reference an active SafeContracts customer or supplier.');
        }
        if ($type === Counterparty::SUPPLIER) {
            $this->requireAny(
                [Capabilities::VIEW_SUPPLIERS, Capabilities::MANAGE_SUPPLIERS],
                'You do not have permission to assign SafeContracts suppliers.'
            );
        }

        $actorId = get_current_user_id();
        $this->repository->assignCounterparty($contractId, $type, $counterpartyId, $actorId);
        do_action(
            'safecontracts_contract_counterparty_assigned',
            $contractId,
            $type,
            $counterpartyId,
            Counterparty::defaultFinancialDirection($type),
            $actorId,
            $contract['counterparty_type'],
            $contract['counterparty_id']
        );
    }

    private function contractNumber(mixed $value): string
    {
        $number = trim(strip_tags((string) $value));
        if ($number === '' || strlen($number) > 100 || preg_match('/[\r\n\x00]/', $number)) {
            throw new InvalidArgumentException('Contract number is required and must not exceed 100 characters.');
        }
        return $number;
    }

    private function optionalUserId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $userId = (int) $value;
        if ($userId <= 0) {
            throw new InvalidArgumentException('Accountant user ID must be positive.');
        }
        return $userId;
    }

    private function assertScope(?int $accountantUserId): void
    {
        if (current_user_can(Capabilities::VIEW_ALL)) {
            return;
        }
        if (current_user_can(Capabilities::VIEW_ASSIGNED)
            && $accountantUserId !== null
            && $accountantUserId === get_current_user_id()) {
            return;
        }
        throw new DomainException('Contract is outside the current user data scope.');
    }

    private function assertEligibleAccountant(int $userId): void
    {
        $isCurrent = $userId === get_current_user_id();
        $hasAccess = $isCurrent ? current_user_can(Capabilities::ACCESS) : user_can($userId, Capabilities::ACCESS);
        $canCreate = $isCurrent ? current_user_can(Capabilities::CREATE_CONTRACTS) : user_can($userId, Capabilities::CREATE_CONTRACTS);
        $hasScope = $isCurrent ? current_user_can(Capabilities::VIEW_ASSIGNED) : user_can($userId, Capabilities::VIEW_ASSIGNED);
        if (! $hasAccess || ! $canCreate || ! $hasScope) {
            throw new InvalidArgumentException('Assigned user must be an eligible SafeContracts Accountant.');
        }
    }

    private function requireCapability(string $capability, string $message): void
    {
        if (! current_user_can($capability)) {
            throw new DomainException($message);
        }
    }

    /** @param list<string> $capabilities */
    private function requireAny(array $capabilities, string $message): void
    {
        foreach ($capabilities as $capability) {
            if (current_user_can($capability)) {
                return;
            }
        }
        throw new DomainException($message);
    }
}
