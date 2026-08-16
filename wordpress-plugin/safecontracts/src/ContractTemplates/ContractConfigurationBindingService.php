<?php

declare(strict_types=1);

namespace SafeContracts\ContractTemplates;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Contracts\ContractStatus;
use SafeContracts\Roles\Capabilities;

final class ContractConfigurationBindingService
{
    public function __construct(private ?ContractConfigurationBindingRepository $repository = null)
    {
        $this->repository ??= new ContractConfigurationBindingRepository();
    }

    public function findForContract(int $contractId): ?array
    {
        $this->requireCapability(Capabilities::ACCESS, 'You do not have permission to view Enterprise contract configuration.');
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        return $this->repository->findBinding($contractId);
    }

    public function bind(int $contractId, int $contractTypeId, ?int $templateId = null, ?int $templateVersionId = null): void
    {
        $this->requireCapability(Capabilities::EDIT_CONTRACTS, 'You do not have permission to configure Enterprise contracts.');
        $this->requirePositive($contractId, 'Contract ID');
        $this->requirePositive($contractTypeId, 'Contract Type ID');
        if (($templateId === null) !== ($templateVersionId === null)) {
            throw new InvalidArgumentException('Contract Template and Template Version must be supplied together.');
        }
        if ($templateId !== null) {
            $this->requirePositive($templateId, 'Contract Template ID');
            $this->requirePositive($templateVersionId ?? 0, 'Contract Template Version ID');
        }

        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        if ((bool) ($contract['is_archived'] ?? false)) {
            throw new DomainException('Archived contracts cannot change Enterprise configuration binding.');
        }
        if ((string) ($contract['status'] ?? '') !== ContractStatus::DRAFT) {
            throw new DomainException('Enterprise contract configuration binding is immutable after the contract leaves draft.');
        }

        $type = $this->repository->findContractType($contractTypeId);
        if ($type === null || (string) ($type['status'] ?? '') !== 'active') {
            throw new InvalidArgumentException('Contract Type must be an active current-tenant Enterprise Contract Type.');
        }

        if ($templateId !== null && $templateVersionId !== null) {
            $template = $this->repository->findTemplate($templateId);
            if ($template === null || (string) ($template['status'] ?? '') !== 'active') {
                throw new InvalidArgumentException('Contract Template must be an active current-tenant Enterprise Contract Template.');
            }
            if ((int) ($template['contract_type_id'] ?? 0) !== $contractTypeId) {
                throw new InvalidArgumentException('Contract Template does not belong to the selected Contract Type.');
            }

            $version = $this->repository->findTemplateVersion($templateId, $templateVersionId);
            if ($version === null || (string) ($version['version_status'] ?? '') !== 'published') {
                throw new InvalidArgumentException('Contract Template Version must be a published current-tenant version of the selected Template.');
            }
        }

        $existing = $this->repository->findBinding($contractId);
        if ($existing !== null
            && (int) ($existing['contract_type_id'] ?? 0) === $contractTypeId
            && $this->nullableInt($existing['template_id'] ?? null) === $templateId
            && $this->nullableInt($existing['template_version_id'] ?? null) === $templateVersionId) {
            return;
        }

        $actorId = get_current_user_id();
        $this->repository->saveBinding($contractId, $contractTypeId, $templateId, $templateVersionId, $actorId);
        do_action('safecontracts_enterprise_contract_configuration_bound', $contractId, $contractTypeId, $templateId, $templateVersionId, $actorId);
    }

    private function requireContract(int $contractId): array
    {
        $this->requirePositive($contractId, 'Contract ID');
        $contract = $this->repository->findContract($contractId);
        if ($contract === null) {
            throw new InvalidArgumentException('Contract was not found in the current Enterprise tenant.');
        }
        return $contract;
    }

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

    private function requireCapability(string $capability, string $message): void
    {
        if (! current_user_can($capability)) {
            throw new DomainException($message);
        }
    }

    private function requirePositive(int $value, string $label): void
    {
        if ($value <= 0) {
            throw new InvalidArgumentException($label . ' must be positive.');
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
}
