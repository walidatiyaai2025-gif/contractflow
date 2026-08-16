<?php

declare(strict_types=1);

namespace SafeContracts\Workflows;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\ContractTypes\ContractTypePolicy;
use SafeContracts\ContractTypes\ContractTypeRepository;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantAuthorization;
use SafeContracts\Tenancy\TenantContextStore;

final class WorkflowDefinitionService
{
    /** @var list<string> */
    private const CREATE_FIELDS = ['contract_type_id', 'workflow_code', 'name', 'description', 'status'];
    /** @var list<string> */
    private const UPDATE_FIELDS = ['name', 'description'];

    public function __construct(
        private ?ContractTypeRepository $contractTypes = null,
        private ?WorkflowDefinitionRepository $repository = null
    ) {
        $this->contractTypes ??= new ContractTypeRepository();
        $this->repository ??= new WorkflowDefinitionRepository();
    }

    public function findWorkflow(int $workflowId): ?array
    {
        $this->authorize(Capabilities::ACCESS);
        $this->requirePositive($workflowId, 'Workflow ID');
        return $this->repository->findWorkflow($workflowId);
    }

    /** @return list<array<string,mixed>> */
    public function searchWorkflows(string $search = '', string $status = '', int $contractTypeId = 0, int $limit = 50, int $offset = 0): array
    {
        $this->authorize(Capabilities::ACCESS);
        $search = trim(strip_tags($search));
        if (strlen($search) > 191) {
            throw new InvalidArgumentException('Workflow search must not exceed 191 characters.');
        }
        $status = trim($status);
        if ($status !== '') {
            $status = WorkflowDefinitionPolicy::normalizeStatus($status);
        }
        if ($contractTypeId > 0) {
            $this->requireContractType($contractTypeId, false);
        }
        return $this->repository->searchWorkflows($search, $status, $contractTypeId, $limit, $offset);
    }

    public function createWorkflow(array $input): int
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $this->rejectUnsupportedFields($input, self::CREATE_FIELDS);
        $contractTypeId = (int) ($input['contract_type_id'] ?? 0);
        $this->requireContractType($contractTypeId, true);
        $data = [
            'contract_type_id' => $contractTypeId,
            'workflow_code' => WorkflowDefinitionPolicy::normalizeCode((string) ($input['workflow_code'] ?? ''), 'Workflow code'),
            'name' => $this->text($input['name'] ?? '', 191, true, 'Workflow name'),
            'description' => $this->text($input['description'] ?? '', 5000, false, 'Workflow description'),
            'status' => WorkflowDefinitionPolicy::normalizeStatus((string) ($input['status'] ?? WorkflowDefinitionPolicy::STATUS_ACTIVE)),
        ];
        $actorId = get_current_user_id();
        $workflowId = $this->repository->createWorkflow($data, $this->uuid(), $actorId);
        do_action('safecontracts_enterprise_workflow_created', $workflowId, $data['workflow_code'], $contractTypeId, $actorId);
        return $workflowId;
    }

    public function updateWorkflow(int $workflowId, array $changes): void
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $this->requirePositive($workflowId, 'Workflow ID');
        $this->rejectUnsupportedFields($changes, self::UPDATE_FIELDS);
        $workflow = $this->requireWorkflow($workflowId, false);
        $name = array_key_exists('name', $changes)
            ? $this->text($changes['name'], 191, true, 'Workflow name')
            : (string) ($workflow['name'] ?? '');
        $description = array_key_exists('description', $changes)
            ? $this->text($changes['description'], 5000, false, 'Workflow description')
            : (string) ($workflow['description'] ?? '');
        $actorId = get_current_user_id();
        $this->repository->updateWorkflowMetadata($workflowId, $name, $description, $actorId);
        do_action('safecontracts_enterprise_workflow_updated', $workflowId, $actorId);
    }

    public function deactivateWorkflow(int $workflowId): void
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $workflow = $this->requireWorkflow($workflowId, false);
        $this->requireContractType((int) ($workflow['contract_type_id'] ?? 0), false);
        $actorId = get_current_user_id();
        $this->repository->deactivateWorkflow($workflowId, $actorId);
        do_action('safecontracts_enterprise_workflow_deactivated', $workflowId, $actorId);
    }

    public function createDraftVersion(int $workflowId): int
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $workflow = $this->requireWorkflow($workflowId, true);
        $this->requireContractType((int) ($workflow['contract_type_id'] ?? 0), true);
        $actorId = get_current_user_id();
        $versionId = $this->repository->createDraftVersion($workflowId, $actorId);
        do_action('safecontracts_enterprise_workflow_draft_created', $workflowId, $versionId, $actorId);
        return $versionId;
    }

    public function findVersion(int $workflowId, int $versionId): array
    {
        $this->authorize(Capabilities::ACCESS);
        $this->requireWorkflow($workflowId, false);
        $this->requirePositive($versionId, 'Workflow version ID');
        $version = $this->repository->findVersion($workflowId, $versionId);
        if ($version === null) {
            throw new InvalidArgumentException('Workflow version was not found in the current tenant/workflow.');
        }
        return $version;
    }

    /** @return list<array<string,mixed>> */
    public function listVersions(int $workflowId, int $limit = 50, int $offset = 0): array
    {
        $this->authorize(Capabilities::ACCESS);
        $this->requireWorkflow($workflowId, false);
        return $this->repository->listVersions($workflowId, $limit, $offset);
    }

    /** @return array<string,mixed> */
    public function getVersionGraph(int $workflowId, int $versionId): array
    {
        $this->authorize(Capabilities::ACCESS);
        $this->requireWorkflow($workflowId, false);
        $this->requirePositive($versionId, 'Workflow version ID');
        $version = $this->repository->findVersion($workflowId, $versionId);
        if ($version === null) {
            throw new InvalidArgumentException('Workflow version was not found in the current tenant/workflow.');
        }
        $graph = $this->repository->getGraph($workflowId, $versionId);
        if (count($graph['states']) > WorkflowDefinitionPolicy::MAX_STATES || count($graph['transitions']) > WorkflowDefinitionPolicy::MAX_TRANSITIONS) {
            throw new RuntimeException('Stored Workflow graph exceeds bounded read limits.');
        }
        return [
            'workflow_id' => $workflowId,
            'workflow_version_id' => $versionId,
            'version_no' => (int) ($version['version_no'] ?? 0),
            'version_status' => (string) ($version['version_status'] ?? ''),
            'configured' => $graph['states'] !== [],
            'states' => $graph['states'],
            'transitions' => $graph['transitions'],
        ];
    }

    /** @param list<mixed> $states @param list<mixed> $transitions */
    public function replaceDraftGraph(int $workflowId, int $versionId, array $states, array $transitions): void
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $workflow = $this->requireWorkflow($workflowId, true);
        $this->requireContractType((int) ($workflow['contract_type_id'] ?? 0), true);
        $this->requirePositive($versionId, 'Workflow version ID');
        $version = $this->repository->findVersion($workflowId, $versionId);
        if ($version === null) {
            throw new InvalidArgumentException('Workflow version was not found in the current tenant/workflow.');
        }
        if ((string) ($version['version_status'] ?? '') !== WorkflowDefinitionPolicy::VERSION_DRAFT) {
            throw new InvalidArgumentException('Published Workflow versions are immutable.');
        }
        $graph = WorkflowDefinitionPolicy::normalizeGraph($states, $transitions);
        $actorId = get_current_user_id();
        $this->repository->replaceDraftGraph($workflowId, $versionId, $graph['states'], $graph['transitions'], $actorId);
        do_action('safecontracts_enterprise_workflow_graph_replaced', $workflowId, $versionId, count($graph['states']), count($graph['transitions']), $actorId);
    }

    public function publishVersion(int $workflowId, int $versionId): void
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $workflow = $this->requireWorkflow($workflowId, true);
        $this->requireContractType((int) ($workflow['contract_type_id'] ?? 0), true);
        $this->requirePositive($versionId, 'Workflow version ID');
        $version = $this->repository->findVersion($workflowId, $versionId);
        if ($version === null) {
            throw new InvalidArgumentException('Workflow version was not found in the current tenant/workflow.');
        }
        if ((string) ($version['version_status'] ?? '') !== WorkflowDefinitionPolicy::VERSION_DRAFT) {
            throw new InvalidArgumentException('Only draft Workflow versions can be published.');
        }
        $actorId = get_current_user_id();
        $this->repository->publishDraftVersion($workflowId, $versionId, $actorId);
        do_action('safecontracts_enterprise_workflow_version_published', $workflowId, $versionId, $actorId);
    }

    /** @return array<string,mixed> */
    private function requireContractType(int $contractTypeId, bool $requireActive): array
    {
        $this->requirePositive($contractTypeId, 'Contract Type ID');
        $type = $this->contractTypes->find($contractTypeId);
        if ($type === null) {
            throw new InvalidArgumentException('Contract Type was not found in the current tenant.');
        }
        if ($requireActive && (string) ($type['status'] ?? '') !== ContractTypePolicy::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Contract Type must be active for Workflow authoring/publishing.');
        }
        return $type;
    }

    /** @return array<string,mixed> */
    private function requireWorkflow(int $workflowId, bool $requireActive): array
    {
        $this->requirePositive($workflowId, 'Workflow ID');
        $workflow = $this->repository->findWorkflow($workflowId);
        if ($workflow === null) {
            throw new InvalidArgumentException('Workflow was not found in the current tenant.');
        }
        if ($requireActive && (string) ($workflow['status'] ?? '') !== WorkflowDefinitionPolicy::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Workflow must be active for version authoring/publishing.');
        }
        return $workflow;
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Workflow access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Workflow operation.');
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

    /** @param list<string> $allowed */
    private function rejectUnsupportedFields(array $input, array $allowed): void
    {
        foreach (array_keys($input) as $field) {
            if (! is_string($field) || ! in_array($field, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported Workflow field.');
            }
        }
    }

    private function text(mixed $value, int $max, bool $required, string $label): string
    {
        $value = trim(strip_tags((string) $value));
        if ($required && $value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }
        if (strlen($value) > $max) {
            throw new InvalidArgumentException("{$label} is too long.");
        }
        return $value;
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
            throw new RuntimeException('Unable to generate Workflow UUID.', 0, $error);
        }
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }
}
