<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use SafeContracts\Roles\Capabilities;

final class ContractService
{
    public function __construct(
        private ?ContractRepository $repository = null,
        private ?ContractFinancialRepository $financialRepository = null
    ) {
        $this->repository ??= new ContractRepository();
        $this->financialRepository ??= new ContractFinancialRepository();
    }

    /** @param array{contract_number:mixed, customer_id:mixed, accountant_user_id?:mixed, notes?:mixed} $input */
    public function create(array $input): int
    {
        $this->requireCapability(Capabilities::CREATE_CONTRACTS, 'You do not have permission to create contracts.');

        $contractNumber = $this->normalizeContractNumber($input['contract_number'] ?? '');
        $customerId = (int) ($input['customer_id'] ?? 0);
        $notes = trim((string) ($input['notes'] ?? ''));
        if ($customerId <= 0 || ! $this->repository->customerIsActive($customerId)) {
            throw new InvalidArgumentException('Contract customer must be an active SafeContracts customer.');
        }

        $accountantUserId = $this->normalizeOptionalUserId($input['accountant_user_id'] ?? null);
        if ($accountantUserId === null && current_user_can(Capabilities::VIEW_ASSIGNED) && ! current_user_can(Capabilities::VIEW_ALL)) {
            $accountantUserId = get_current_user_id();
        }

        if ($accountantUserId !== null) {
            if ($accountantUserId !== get_current_user_id()) {
                $this->requireCapability(Capabilities::ASSIGN_CONTRACTS, 'You do not have permission to assign contracts.');
            }
            $this->assertEligibleAccountant($accountantUserId);
        }

        $actorId = get_current_user_id();
        $contractId = $this->repository->create($contractNumber, $customerId, $accountantUserId, $notes, $actorId);
        do_action('safecontracts_contract_created', $contractId, $actorId, $customerId, $accountantUserId);

        return $contractId;
    }

    /** @param array{contract_number?:mixed, notes?:mixed} $changes */
    public function edit(int $contractId, array $changes): void
    {
        $this->requireCapability(Capabilities::EDIT_CONTRACTS, 'You do not have permission to edit contracts.');
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);

        $contractNumber = array_key_exists('contract_number', $changes)
            ? $this->normalizeContractNumber($changes['contract_number'])
            : $contract['contract_number'];
        $notes = array_key_exists('notes', $changes) ? trim((string) $changes['notes']) : $contract['notes'];
        $actorId = get_current_user_id();

        $this->repository->updateDetails($contractId, $contractNumber, $notes, $actorId);
        do_action('safecontracts_contract_edited', $contractId, $actorId);
    }

    public function updateDates(int $contractId, mixed $startDate, mixed $endDate): void
    {
        $this->requireCapability(Capabilities::EDIT_CONTRACTS, 'You do not have permission to edit contract dates.');
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $this->assertMutable($contract);

        $start = $this->normalizeNullableDate($startDate, 'start');
        $end = $this->normalizeNullableDate($endDate, 'end');
        if ($start !== null && $end !== null && $end < $start) {
            throw new InvalidArgumentException('Contract end date cannot be before start date.');
        }

        $actorId = get_current_user_id();
        $this->repository->updateDates($contractId, $start, $end, $actorId);
        do_action(
            'safecontracts_contract_dates_changed',
            $contractId,
            $contract['start_date'],
            $contract['end_date'],
            $start,
            $end,
            $actorId
        );
    }

    public function setBaseValue(int $contractId, mixed $baseValue): void
    {
        $this->requireCapability(Capabilities::EDIT_CONTRACTS, 'You do not have permission to edit contract value.');
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $this->assertMutable($contract);

        $normalized = DecimalAmount::normalize($baseValue);
        $actorId = get_current_user_id();
        $this->repository->updateBaseValue($contractId, $normalized, $actorId);
        do_action('safecontracts_contract_base_value_changed', $contractId, $contract['base_value'], $normalized, $actorId);
    }

    public function addFinancialItem(
        int $contractId,
        string $type,
        mixed $amount,
        string $description,
        int $displayOrder = 0
    ): int {
        $this->requireCapability(Capabilities::EDIT_CONTRACTS, 'You do not have permission to edit contract finance.');
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $this->assertMutable($contract);

        $type = FinancialItemType::normalize($type);
        $amount = DecimalAmount::normalize($amount);
        if (DecimalAmount::isZero($amount)) {
            throw new InvalidArgumentException('Financial item amount must be greater than zero.');
        }

        $description = trim($description);
        if ($description === '' || strlen($description) > 191) {
            throw new InvalidArgumentException('Financial item description is required and must not exceed 191 characters.');
        }
        if ($displayOrder < 0 || $displayOrder > 1000000) {
            throw new InvalidArgumentException('Financial item display order is out of range.');
        }

        $actorId = get_current_user_id();
        $itemId = $this->financialRepository->createItem(
            $contractId,
            $type,
            $description,
            $amount,
            $displayOrder,
            $actorId
        );
        do_action('safecontracts_contract_financial_item_added', $contractId, $itemId, $type, $amount, $actorId);

        return $itemId;
    }

    public function deactivateFinancialItem(int $contractId, int $itemId): void
    {
        $this->requireCapability(Capabilities::EDIT_CONTRACTS, 'You do not have permission to edit contract finance.');
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $this->assertMutable($contract);
        if ($itemId <= 0) {
            throw new InvalidArgumentException('Financial item ID must be positive.');
        }

        $actorId = get_current_user_id();
        $this->financialRepository->deactivateItem($contractId, $itemId, $actorId);
        do_action('safecontracts_contract_financial_item_deactivated', $contractId, $itemId, $actorId);
    }

    /** @return array{base_value:string, line_items:string, additions:string, discounts:string, gross_value:string, net_value:string} */
    public function reconcile(int $contractId): array
    {
        $this->requireCapability(Capabilities::ACCESS, 'You do not have permission to view contract finance.');
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);

        $totals = $this->financialRepository->totals($contractId);
        $gross = DecimalAmount::add(
            $contract['base_value'],
            $totals[FinancialItemType::LINE],
            $totals[FinancialItemType::ADDITION]
        );
        $net = DecimalAmount::subtractNonNegative($gross, $totals[FinancialItemType::DISCOUNT]);

        return [
            'base_value' => $contract['base_value'],
            'line_items' => $totals[FinancialItemType::LINE],
            'additions' => $totals[FinancialItemType::ADDITION],
            'discounts' => $totals[FinancialItemType::DISCOUNT],
            'gross_value' => $gross,
            'net_value' => $net,
        ];
    }

    public function attachMedia(int $contractId, int $mediaId, string $label = ''): int
    {
        $this->requireCapability(Capabilities::EDIT_CONTRACTS, 'You do not have permission to edit contract attachments.');
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $this->assertMutable($contract);
        if ($mediaId <= 0) {
            throw new InvalidArgumentException('Attachment media ID must be positive.');
        }
        if (function_exists('get_post_type') && get_post_type($mediaId) !== 'attachment') {
            throw new InvalidArgumentException('Contract attachment must reference WordPress Media Library attachment.');
        }

        $label = trim($label);
        if (strlen($label) > 191) {
            throw new InvalidArgumentException('Attachment label must not exceed 191 characters.');
        }

        $actorId = get_current_user_id();
        $attachmentId = $this->financialRepository->attachMedia($contractId, $mediaId, $label, $actorId);
        do_action('safecontracts_contract_attachment_added', $contractId, $mediaId, $attachmentId, $actorId);

        return $attachmentId;
    }

    public function detachMedia(int $contractId, int $mediaId): void
    {
        $this->requireCapability(Capabilities::EDIT_CONTRACTS, 'You do not have permission to edit contract attachments.');
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $this->assertMutable($contract);
        if ($mediaId <= 0) {
            throw new InvalidArgumentException('Attachment media ID must be positive.');
        }

        $actorId = get_current_user_id();
        $this->financialRepository->detachMedia($contractId, $mediaId, $actorId);
        do_action('safecontracts_contract_attachment_removed', $contractId, $mediaId, $actorId);
    }

    public function assignCustomer(int $contractId, int $customerId): void
    {
        $this->requireCapability(Capabilities::ASSIGN_CONTRACTS, 'You do not have permission to assign contracts.');
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        if ($customerId <= 0 || ! $this->repository->customerIsActive($customerId)) {
            throw new InvalidArgumentException('Contract customer must be an active SafeContracts customer.');
        }

        $actorId = get_current_user_id();
        $this->repository->assignCustomer($contractId, $customerId, $actorId);
        do_action('safecontracts_contract_customer_assigned', $contractId, $customerId, $actorId);
    }

    public function assignAccountant(int $contractId, ?int $accountantUserId): void
    {
        $this->requireCapability(Capabilities::ASSIGN_CONTRACTS, 'You do not have permission to assign contracts.');
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);

        if ($accountantUserId !== null) {
            if ($accountantUserId <= 0) {
                throw new InvalidArgumentException('Accountant user ID must be positive.');
            }
            $this->assertEligibleAccountant($accountantUserId);
        }

        $actorId = get_current_user_id();
        $this->repository->assignAccountant($contractId, $accountantUserId, $actorId);
        do_action('safecontracts_contract_accountant_assigned', $contractId, $accountantUserId, $actorId);
    }

    public function changeStatus(int $contractId, string $targetStatus): void
    {
        $this->requireCapability(Capabilities::EDIT_CONTRACTS, 'You do not have permission to edit contracts.');
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        if ($contract['is_archived']) {
            throw new DomainException('Archived contracts cannot change lifecycle status.');
        }

        $targetStatus = ContractStatus::normalize($targetStatus);
        ContractStatus::assertTransition($contract['status'], $targetStatus);
        $actorId = get_current_user_id();
        $this->repository->updateStatus($contractId, $targetStatus, $actorId);
        do_action('safecontracts_contract_status_changed', $contractId, $contract['status'], $targetStatus, $actorId);
    }

    /** @return array{id:int, contract_number:string, customer_id:int, accountant_user_id:?int, status:string, start_date:?string, end_date:?string, base_value:string, notes:string, is_archived:bool} */
    private function requireContract(int $contractId): array
    {
        if ($contractId <= 0) {
            throw new InvalidArgumentException('Contract ID must be positive.');
        }

        $contract = $this->repository->find($contractId);
        if ($contract === null) {
            throw new InvalidArgumentException('Contract was not found.');
        }

        return $contract;
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

    /** @param array{is_archived:bool} $contract */
    private function assertMutable(array $contract): void
    {
        if ($contract['is_archived']) {
            throw new DomainException('Archived contracts cannot be modified.');
        }
    }

    private function assertEligibleAccountant(int $userId): void
    {
        $isCurrentUser = $userId === get_current_user_id();
        $hasAccess = $isCurrentUser
            ? current_user_can(Capabilities::ACCESS)
            : user_can($userId, Capabilities::ACCESS);
        $canCreate = $isCurrentUser
            ? current_user_can(Capabilities::CREATE_CONTRACTS)
            : user_can($userId, Capabilities::CREATE_CONTRACTS);
        $hasAssignedScope = $isCurrentUser
            ? current_user_can(Capabilities::VIEW_ASSIGNED)
            : user_can($userId, Capabilities::VIEW_ASSIGNED);

        if (! $hasAccess || ! $canCreate || ! $hasAssignedScope) {
            throw new InvalidArgumentException('Assigned user must be an eligible SafeContracts Accountant.');
        }
    }

    private function normalizeContractNumber(mixed $value): string
    {
        $contractNumber = trim((string) $value);
        if ($contractNumber === '' || strlen($contractNumber) > 100) {
            throw new InvalidArgumentException('Contract number is required and must not exceed 100 characters.');
        }

        return $contractNumber;
    }

    private function normalizeOptionalUserId(mixed $value): ?int
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

    private function normalizeNullableDate(mixed $value, string $field): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $value = trim((string) $value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (! $date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("Contract {$field} date must use YYYY-MM-DD.");
        }

        return $value;
    }

    private function requireCapability(string $capability, string $message): void
    {
        if (! current_user_can($capability)) {
            throw new DomainException($message);
        }
    }
}
