<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use SafeContracts\Payments\CurrencyCode;
use SafeContracts\Roles\Capabilities;

final class ContractService
{
    public function __construct(private ?ContractRepository $repository = null)
    {
        $this->repository ??= new ContractRepository();
    }

    /** @param array<string,mixed> $input */
    public function create(array $input): int
    {
        if (array_key_exists('counterparty_type', $input) || array_key_exists('counterparty_id', $input)) {
            return (new CounterpartyContractService($this->repository))->create($input);
        }

        $this->requireCapability(Capabilities::CREATE_CONTRACTS, 'You do not have permission to create contracts.');
        $contractNumber = $this->normalizeContractNumber($input['contract_number'] ?? '');
        $customerId = (int) ($input['customer_id'] ?? 0);
        $notes = trim((string) ($input['notes'] ?? ''));
        $baseValue = ContractMoney::normalizeNonNegative($input['base_value'] ?? '');
        if ($baseValue === '0.0000') {
            throw new InvalidArgumentException('Contract base value must be greater than zero.');
        }
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
        $contractId = $this->repository->create($contractNumber, $customerId, $accountantUserId, $notes, $actorId, $baseValue);
        do_action('safecontracts_contract_created', $contractId, $actorId, $customerId, $accountantUserId);
        do_action('safecontracts_contract_base_value_changed', $contractId, $baseValue, $actorId, '0.0000');
        return $contractId;
    }

    /** @param array{contract_number?:mixed, notes?:mixed} $changes */
    public function edit(int $contractId, array $changes): void
    {
        $this->requireCapability(Capabilities::EDIT_CONTRACTS, 'You do not have permission to edit contracts.');
        $contract = $this->editableContract($contractId);
        $contractNumber = array_key_exists('contract_number', $changes) ? $this->normalizeContractNumber($changes['contract_number']) : $contract['contract_number'];
        $notes = array_key_exists('notes', $changes) ? trim((string) $changes['notes']) : $contract['notes'];
        $actorId = get_current_user_id();
        $this->repository->updateDetails($contractId, $contractNumber, $notes, $actorId);
        do_action('safecontracts_contract_edited', $contractId, $actorId);
    }

    public function updateDates(int $contractId, mixed $startDate, mixed $endDate): void
    {
        $this->requireCapability(Capabilities::EDIT_CONTRACTS, 'You do not have permission to edit contract dates.');
        $contract = $this->editableContract($contractId);
        $start = $this->normalizeDate($startDate, 'start date');
        $end = $this->normalizeDate($endDate, 'end date');
        if ($start !== null && $end !== null && $end < $start) {
            throw new InvalidArgumentException('Contract end date cannot be earlier than start date.');
        }
        $actorId = get_current_user_id();
        $this->repository->updateDates($contractId, $start, $end, $actorId);
        do_action('safecontracts_contract_dates_changed', $contractId, $start, $end, $actorId, $contract['start_date'], $contract['end_date']);
    }

    public function updateBaseValue(int $contractId, mixed $amount): void
    {
        $this->requireCapability(Capabilities::EDIT_CONTRACTS, 'You do not have permission to edit contract value.');
        $contract = $this->editableContract($contractId);
        $amount = ContractMoney::normalizeNonNegative($amount);
        if ($amount === '0.0000') {
            throw new InvalidArgumentException('Contract base value must be greater than zero.');
        }
        $actorId = get_current_user_id();
        $this->repository->updateBaseValue($contractId, $amount, $actorId);
        do_action('safecontracts_contract_base_value_changed', $contractId, $amount, $actorId, $contract['base_value']);
    }

    public function updateCurrency(int $contractId, mixed $currency): void
    {
        $this->requireCapability(Capabilities::EDIT_CONTRACTS, 'You do not have permission to edit contract currency.');
        $contract = $this->editableContract($contractId);
        $currencyCode = CurrencyCode::normalize($currency);
        if ($contract['currency_code'] === $currencyCode) {
            return;
        }
        $actorId = get_current_user_id();
        (new ContractCurrencyRepository())->update($contractId, $currencyCode, $actorId);
        do_action('safecontracts_contract_currency_changed', $contractId, $currencyCode, $actorId, $contract['currency_code']);
    }

    public function addFinancialItem(int $contractId, mixed $description, mixed $amount, mixed $displayOrder = 0): int
    {
        $this->requireCapability(Capabilities::EDIT_CONTRACTS, 'You do not have permission to edit contract financials.');
        $this->editableContract($contractId);
        $description = $this->normalizeDescription($description);
        $amount = ContractMoney::normalizeNonNegative($amount);
        $displayOrder = max(0, (int) $displayOrder);
        $actorId = get_current_user_id();
        $itemId = $this->repository->addFinancialItem($contractId, $description, $amount, $displayOrder, $actorId);
        do_action('safecontracts_contract_financial_item_added', $contractId, $itemId, $amount, $actorId);
        return $itemId;
    }

    public function addAdjustment(int $contractId, mixed $type, mixed $description, mixed $amount, mixed $displayOrder = 0): int
    {
        $this->requireCapability(Capabilities::EDIT_CONTRACTS, 'You do not have permission to edit contract financials.');
        $this->editableContract($contractId);
        $type = strtolower(trim((string) $type));
        if (! in_array($type, ['addition', 'discount'], true)) {
            throw new InvalidArgumentException('Contract adjustment type must be addition or discount.');
        }
        $description = $this->normalizeDescription($description);
        $amount = ContractMoney::normalizeNonNegative($amount);
        $displayOrder = max(0, (int) $displayOrder);
        $actorId = get_current_user_id();
        $adjustmentId = $this->repository->addAdjustment($contractId, $type, $description, $amount, $displayOrder, $actorId);
        do_action('safecontracts_contract_adjustment_added', $contractId, $adjustmentId, $type, $amount, $actorId);
        return $adjustmentId;
    }

    /** @return array{base_value:string, financial_items:string, additions:string, discounts:string, net_value:string} */
    public function reconcile(int $contractId): array
    {
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $totals = $this->repository->financialTotals($contractId);
        $base = ContractMoney::normalizeNonNegative($contract['base_value']);
        $items = ContractMoney::normalizeNonNegative($totals['items']);
        $additions = ContractMoney::normalizeNonNegative($totals['additions']);
        $discounts = ContractMoney::normalizeNonNegative($totals['discounts']);
        return [
            'base_value' => $base,
            'financial_items' => $items,
            'additions' => $additions,
            'discounts' => $discounts,
            'net_value' => ContractMoney::reconcile($base, $items, $additions, $discounts),
        ];
    }

    public function attachMedia(int $contractId, int $mediaId, mixed $label = ''): int
    {
        $this->requireCapability(Capabilities::EDIT_CONTRACTS, 'You do not have permission to edit contract attachments.');
        $this->editableContract($contractId);
        if ($mediaId <= 0 || get_post_type($mediaId) !== 'attachment') {
            throw new InvalidArgumentException('Contract attachment must reference a WordPress Media attachment.');
        }
        $label = trim((string) $label);
        if (strlen($label) > 191) {
            throw new InvalidArgumentException('Attachment label must not exceed 191 characters.');
        }
        $actorId = get_current_user_id();
        $referenceId = $this->repository->attachMedia($contractId, $mediaId, $label, $actorId);
        do_action('safecontracts_contract_attachment_added', $contractId, $mediaId, $actorId);
        return $referenceId;
    }

    public function detachMedia(int $contractId, int $mediaId): void
    {
        $this->requireCapability(Capabilities::EDIT_CONTRACTS, 'You do not have permission to edit contract attachments.');
        $this->editableContract($contractId);
        if ($mediaId <= 0) {
            throw new InvalidArgumentException('Attachment media ID must be positive.');
        }
        $actorId = get_current_user_id();
        $this->repository->detachMedia($contractId, $mediaId);
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
        do_action('safecontracts_contract_customer_assigned', $contractId, $customerId, $actorId, $contract['customer_id']);
    }

    public function assignCounterparty(int $contractId, mixed $counterpartyType, mixed $counterpartyId): void
    {
        (new CounterpartyContractService($this->repository))->assign($contractId, $counterpartyType, $counterpartyId);
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
        do_action('safecontracts_contract_accountant_assigned', $contractId, $accountantUserId, $actorId, $contract['accountant_user_id']);
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

    /** @return array<string,mixed> */
    private function editableContract(int $contractId): array
    {
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        if ($contract['is_archived']) {
            throw new DomainException('Archived contracts cannot be edited.');
        }
        return $contract;
    }

    /** @return array<string,mixed> */
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
        if (current_user_can(Capabilities::VIEW_ASSIGNED) && $contract['accountant_user_id'] !== null && $contract['accountant_user_id'] === get_current_user_id()) {
            return;
        }
        throw new DomainException('Contract is outside the current user data scope.');
    }

    private function assertEligibleAccountant(int $userId): void
    {
        $isCurrentUser = $userId === get_current_user_id();
        $hasAccess = $isCurrentUser ? current_user_can(Capabilities::ACCESS) : user_can($userId, Capabilities::ACCESS);
        $canCreate = $isCurrentUser ? current_user_can(Capabilities::CREATE_CONTRACTS) : user_can($userId, Capabilities::CREATE_CONTRACTS);
        $hasAssignedScope = $isCurrentUser ? current_user_can(Capabilities::VIEW_ASSIGNED) : user_can($userId, Capabilities::VIEW_ASSIGNED);
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

    private function normalizeDescription(mixed $value): string
    {
        $description = trim((string) $value);
        if ($description === '' || strlen($description) > 191) {
            throw new InvalidArgumentException('Financial description is required and must not exceed 191 characters.');
        }
        return $description;
    }

    private function normalizeDate(mixed $value, string $field): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $date = trim((string) $value);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Contract {$field} must use YYYY-MM-DD and be a valid calendar date.");
        }
        return $date;
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

    private function requireCapability(string $capability, string $message): void
    {
        if (! current_user_can($capability)) {
            throw new DomainException($message);
        }
    }
}