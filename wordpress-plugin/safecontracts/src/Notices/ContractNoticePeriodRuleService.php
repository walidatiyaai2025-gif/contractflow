<?php

declare(strict_types=1);

namespace SafeContracts\Notices;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantAuthorization;
use SafeContracts\Tenancy\TenantContextStore;

final class ContractNoticePeriodRuleService
{
    public function __construct(private ?ContractNoticePeriodRuleRepository $repository = null)
    {
        $this->repository ??= new ContractNoticePeriodRuleRepository();
    }

    /** @return array<string,mixed>|null */
    public function find(int $ruleId): ?array
    {
        $this->authorize(Capabilities::ACCESS);
        $this->requirePositive($ruleId, 'Contract Notice Period Rule ID');
        $rule = $this->repository->find($ruleId);
        if ($rule === null) {
            return null;
        }
        $contract = $this->requireContract((int) ($rule['contract_id'] ?? 0));
        $this->assertScope($contract);
        return $rule;
    }

    /** @return list<array<string,mixed>> */
    public function listForContract(int $contractId, int $limit = 50, int $offset = 0): array
    {
        $this->authorize(Capabilities::ACCESS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Contract Notice Period Rule list limit must be between 1 and 100.');
        }
        if ($offset < 0 || $offset > 100000) {
            throw new InvalidArgumentException('Contract Notice Period Rule list offset must be between 0 and 100000.');
        }
        return $this->repository->listForContract($contractId, $limit, $offset);
    }

    public function create(
        int $contractId,
        string $noticeCode,
        string $purpose,
        string $direction,
        int $periodValue,
        string $periodUnit,
        bool|int $isActive = true,
        ?string $notes = null
    ): int {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $this->assertContractMutable($contract);

        $code = ContractNoticePeriodRulePolicy::normalizeCode($noticeCode);
        $purpose = ContractNoticePeriodRulePolicy::normalizePurpose($purpose);
        $direction = ContractNoticePeriodRulePolicy::normalizeDirection($direction);
        $period = ContractNoticePeriodRulePolicy::normalizePeriod($periodValue, $periodUnit);
        $active = ContractNoticePeriodRulePolicy::normalizeActive($isActive);
        $notes = ContractNoticePeriodRulePolicy::normalizeNotes($notes);
        $actorId = get_current_user_id();

        $ruleId = $this->repository->create(
            $contractId,
            $this->uuid(),
            $code,
            $purpose,
            $direction,
            $period['period_value'],
            $period['period_unit'],
            $active,
            $notes,
            $actorId
        );
        do_action('safecontracts_enterprise_contract_notice_period_rule_created', $contractId, $ruleId, $actorId);
        return $ruleId;
    }

    /** @return array<string,mixed> */
    public function update(
        int $ruleId,
        int $expectedRevision,
        string $purpose,
        string $direction,
        int $periodValue,
        string $periodUnit,
        bool|int $isActive,
        ?string $notes = null
    ): array {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $this->requirePositive($ruleId, 'Contract Notice Period Rule ID');
        $expectedRevision = ContractNoticePeriodRulePolicy::normalizeExpectedRevision($expectedRevision);
        $rule = $this->repository->find($ruleId);
        if ($rule === null) {
            throw new InvalidArgumentException('Contract Notice Period Rule was not found in the current Enterprise tenant.');
        }
        $contract = $this->requireContract((int) ($rule['contract_id'] ?? 0));
        $this->assertScope($contract);
        $this->assertContractMutable($contract);
        if ((int) ($rule['revision'] ?? 0) !== $expectedRevision) {
            throw new DomainException('Contract Notice Period Rule changed concurrently.');
        }

        $purpose = ContractNoticePeriodRulePolicy::normalizePurpose($purpose);
        $direction = ContractNoticePeriodRulePolicy::normalizeDirection($direction);
        $period = ContractNoticePeriodRulePolicy::normalizePeriod($periodValue, $periodUnit);
        $active = ContractNoticePeriodRulePolicy::normalizeActive($isActive);
        $notes = ContractNoticePeriodRulePolicy::normalizeNotes($notes);
        $actorId = get_current_user_id();
        $updated = $this->repository->update(
            $ruleId,
            $expectedRevision,
            $purpose,
            $direction,
            $period['period_value'],
            $period['period_unit'],
            $active,
            $notes,
            $actorId
        );
        do_action(
            'safecontracts_enterprise_contract_notice_period_rule_updated',
            (int) $rule['contract_id'],
            $ruleId,
            (int) ($updated['revision'] ?? ($expectedRevision + 1)),
            $actorId
        );
        return $updated;
    }

    /** @return array<string,mixed> */
    private function requireContract(int $contractId): array
    {
        $this->requirePositive($contractId, 'Contract ID');
        $contract = $this->repository->findContract($contractId);
        if ($contract === null) {
            throw new InvalidArgumentException('Contract was not found in the current Enterprise tenant.');
        }
        return $contract;
    }

    /** @param array<string,mixed> $contract */
    private function assertScope(array $contract): void
    {
        if (current_user_can(Capabilities::VIEW_ALL)) {
            return;
        }
        $accountantUserId = $this->nullableInt($contract['accountant_user_id'] ?? null);
        if (current_user_can(Capabilities::VIEW_ASSIGNED)
            && $accountantUserId !== null
            && $accountantUserId === get_current_user_id()) {
            return;
        }
        throw new DomainException('Contract is outside the current user data scope.');
    }

    /** @param array<string,mixed> $contract */
    private function assertContractMutable(array $contract): void
    {
        if ((int) ($contract['is_archived'] ?? 0) === 1) {
            throw new DomainException('Archived contracts cannot mutate Enterprise Contract Notice Period Rules.');
        }
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract Notice Period Rule access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Contract Notice Period Rule operation.');
        }
        if (get_current_user_id() <= 0) {
            throw new DomainException('An authenticated tenant user is required.');
        }
    }

    private function requirePositive(int $value, string $label): void
    {
        if ($value <= 0) {
            throw new InvalidArgumentException("{$label} must be positive.");
        }
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }

    private function uuid(): string
    {
        if (function_exists('wp_generate_uuid4')) {
            $uuid = (string) wp_generate_uuid4();
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1) {
                return strtolower($uuid);
            }
        }

        try {
            $bytes = random_bytes(16);
        } catch (\Throwable $error) {
            throw new RuntimeException('Unable to generate Contract Notice Period Rule UUID.', 0, $error);
        }
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
