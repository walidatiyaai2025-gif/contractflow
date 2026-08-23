<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Diagnostics\RuntimeInspector;
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
     *   base_value?:mixed,
     *   accountant_user_id?:mixed,
     *   notes?:mixed
     * } $input
     */
    public function create(array $input): int
    {
        RuntimeInspector::begin('contract.create', [
            'counterparty_type' => is_scalar($input['counterparty_type'] ?? null) ? (string) $input['counterparty_type'] : '',
            'counterparty_id' => (int) ($input['counterparty_id'] ?? 0),
            'currency_code' => is_scalar($input['currency_code'] ?? null) ? (string) $input['currency_code'] : '',
            'accountant_user_id' => ($input['accountant_user_id'] ?? '') === '' ? null : (int) ($input['accountant_user_id'] ?? 0),
        ]);
        try {
            return $this->createTraced($input);
        } catch (\Throwable $error) {
            RuntimeInspector::capture($error);
            throw $error;
        } finally {
            RuntimeInspector::finish();
        }
    }

    private function createTraced(array $input): int
    {
        RuntimeInspector::stage('contract.create.authorization');
        $this->requireCapability(Capabilities::CREATE_CONTRACTS, 'You do not have permission to create contracts.');
        RuntimeInspector::stage('contract.create.counterparty.normalize');
        $type = Counterparty::normalize($input['counterparty_type'] ?? '');
        $counterpartyId = (int) ($input['counterparty_id'] ?? 0);
        RuntimeInspector::stage('contract.create.counterparty.active', ['counterparty_type' => $type, 'counterparty_id' => $counterpartyId]);
        if ($counterpartyId <= 0 || ! $this->repository->counterpartyIsActive($type, $counterpartyId)) {
            throw new InvalidArgumentException('Contract counterparty must reference an active SafeContracts customer or supplier.');
        }
        if ($type === Counterparty::SUPPLIER) {
            RuntimeInspector::stage('contract.create.supplier.permission');
            $this->requireSupplierReadAccess(
                'You do not have permission to use SafeContracts suppliers as contract counterparties.'
            );
        }

        RuntimeInspector::stage('contract.create.contract_number');
        $contractNumber = $this->contractNumber($input['contract_number'] ?? '');
        RuntimeInspector::stage('contract.create.currency');
        $currencyCode = CurrencyCode::fromInputOrSettings($input['currency_code'] ?? null);
        RuntimeInspector::stage('contract.create.base_value');
        $baseValue = ContractMoney::normalizeNonNegative($input['base_value'] ?? '');
        if ($baseValue === '0.0000') {
            throw new InvalidArgumentException('Contract base value must be greater than zero.');
        }
        $direction = Counterparty::defaultFinancialDirection($type);
        RuntimeInspector::stage('contract.create.accountant.normalize');
        $accountantUserId = $this->optionalUserId($input['accountant_user_id'] ?? null);
        if ($accountantUserId === null && current_user_can(Capabilities::VIEW_ASSIGNED) && ! current_user_can(Capabilities::VIEW_ALL)) {
            $accountantUserId = get_current_user_id();
        }
        if ($accountantUserId !== null) {
            RuntimeInspector::stage('contract.create.accountant.authorization', ['accountant_user_id' => $accountantUserId]);
            if ($accountantUserId !== get_current_user_id()) {
                $this->requireCapability(Capabilities::ASSIGN_CONTRACTS, 'You do not have permission to assign contracts.');
            }
            RuntimeInspector::stage('contract.create.accountant.eligibility', ['accountant_user_id' => $accountantUserId]);
            $this->assertEligibleAccountant($accountantUserId);
        }

        RuntimeInspector::stage('contract.create.notes');
        $notes = trim(strip_tags((string) ($input['notes'] ?? '')));
        if (strlen($notes) > 5000) {
            throw new InvalidArgumentException('Contract notes must not exceed 5000 characters.');
        }
        $actorId = get_current_user_id();
        RuntimeInspector::stage('contract.create.database.insert', [
            'counterparty_type' => $type,
            'counterparty_id' => $counterpartyId,
            'currency_code' => $currencyCode,
            'accountant_user_id' => $accountantUserId,
        ]);
        $contractId = $this->repository->createForCounterparty(
            $contractNumber,
            $type,
            $counterpartyId,
            $direction,
            $currencyCode,
            $accountantUserId,
            $notes,
            $actorId,
            $baseValue
        );
        RuntimeInspector::stage('contract.create.events', ['contract_id' => $contractId]);
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
        do_action('safecontracts_contract_base_value_changed', $contractId, $baseValue, $actorId, '0.0000');
        return $contractId;
    }

    public function assign(int $contractId, mixed $counterpartyType, mixed $counterpartyId): void
    {
        RuntimeInspector::begin('contract.assign', [
            'contract_id' => $contractId,
            'counterparty_type' => is_scalar($counterpartyType) ? (string) $counterpartyType : '',
            'counterparty_id' => (int) $counterpartyId,
        ]);
        try {
            $this->assignTraced($contractId, $counterpartyType, $counterpartyId);
        } catch (\Throwable $error) {
            RuntimeInspector::capture($error);
            throw $error;
        } finally {
            RuntimeInspector::finish();
        }
    }

    private function assignTraced(int $contractId, mixed $counterpartyType, mixed $counterpartyId): void
    {
        RuntimeInspector::stage('contract.assign.authorization', ['contract_id' => $contractId]);
        $this->requireCapability(Capabilities::ASSIGN_CONTRACTS, 'You do not have permission to assign contracts.');
        if ($contractId <= 0) {
            throw new InvalidArgumentException('Contract ID must be positive.');
        }
        RuntimeInspector::stage('contract.assign.load', ['contract_id' => $contractId]);
        $contract = $this->repository->find($contractId);
        if ($contract === null) {
            throw new InvalidArgumentException('Contract was not found.');
        }
        $this->assertScope($contract['accountant_user_id']);
        if ($contract['is_archived']) {
            throw new DomainException('Archived contracts cannot change counterparty.');
        }
        if ($this->assignmentRepository->hasScheduledObligations($contractId)) {
            throw new DomainException('Contract counterparty cannot change after financial obligations exist.');
        }

        RuntimeInspector::stage('contract.assign.counterparty.normalize');
        $type = Counterparty::normalize($counterpartyType);
        $counterpartyId = (int) $counterpartyId;
        RuntimeInspector::stage('contract.assign.counterparty.active', ['counterparty_type' => $type, 'counterparty_id' => $counterpartyId]);
        if ($counterpartyId <= 0 || ! $this->repository->counterpartyIsActive($type, $counterpartyId)) {
            throw new InvalidArgumentException('Contract counterparty must reference an active SafeContracts customer or supplier.');
        }
        if ($type === Counterparty::SUPPLIER) {
            RuntimeInspector::stage('contract.assign.supplier.permission');
            $this->requireSupplierReadAccess(
                'You do not have permission to assign SafeContracts suppliers.'
            );
        }

        $actorId = get_current_user_id();
        RuntimeInspector::stage('contract.assign.database.update', ['counterparty_type' => $type, 'counterparty_id' => $counterpartyId]);
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

    private function requireSupplierReadAccess(string $message): void
    {
        $this->requireAny(
            [
                Capabilities::VIEW_SUPPLIERS,
                Capabilities::MANAGE_SUPPLIERS,
                Capabilities::VIEW_ALL,
                Capabilities::MANAGE_REFERENCE_DATA,
            ],
            $message
        );
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