<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use InvalidArgumentException;
use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0047EnterpriseContractDeliverables;
use SafeContracts\Deliverables\ContractDeliverablePolicy;

$assertions = 0;
function esc_p8_deliverable_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p8_deliverable_expect_invalid(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        esc_p8_deliverable_assert(true, $message);
        return;
    }
    esc_p8_deliverable_assert(false, $message);
}

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0047EnterpriseContractDeliverables.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Deliverables/ContractDeliverablePolicy.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Deliverables/ContractDeliverableRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Deliverables/ContractDeliverableService.php');
$routerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

// Historical migration mapping stays stable while later migrations may advance LATEST_VERSION.
esc_p8_deliverable_assert(version_compare(Migrator::LATEST_VERSION, '1.46.0', '>='), 'Enterprise schema remains at or beyond P8-005 version 1.46.0');
esc_p8_deliverable_assert(str_contains($migratorSource, 'Migration0047EnterpriseContractDeliverables'), 'Migrator registers Migration0047');
esc_p8_deliverable_assert(str_contains($migratorSource, "'1.46.0' => Migration0047EnterpriseContractDeliverables::class"), 'Migration0047 is historically mapped to schema 1.46.0');
$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0047EnterpriseContractDeliverables())->up($GLOBALS['wpdb']);
esc_p8_deliverable_assert(count($GLOBALS['sc_test_dbdelta']) === 1, 'P8-005 emits exactly one additive table definition');
$schema = (string) ($GLOBALS['sc_test_dbdelta'][0] ?? '');
foreach ([
    'safecontracts_contract_deliverables',
    'tenant_id bigint(20) unsigned NOT NULL',
    'uuid char(36) NOT NULL',
    'contract_id bigint(20) unsigned NOT NULL',
    'deliverable_code varchar(64) NOT NULL',
    'due_date date NULL',
    "status varchar(20) NOT NULL DEFAULT 'pending'",
    'delivered_at datetime NULL',
    'delivered_by bigint(20) unsigned NULL',
    'cancelled_at datetime NULL',
    'cancelled_by bigint(20) unsigned NULL',
    'UNIQUE KEY deliverable_uuid (uuid)',
    'UNIQUE KEY tenant_contract_deliverable_code (tenant_id, contract_id, deliverable_code)',
    'KEY tenant_contract_status_due (tenant_id, contract_id, status, due_date, id)',
    'KEY tenant_due_status (tenant_id, due_date, status, id)',
] as $marker) {
    esc_p8_deliverable_assert(str_contains($schema, $marker), 'P8-005 schema contains ' . $marker);
}
esc_p8_deliverable_assert(! str_contains(strtoupper($migrationSource), 'DROP TABLE'), 'P8-005 migration is non-destructive');
esc_p8_deliverable_assert(! str_contains(strtoupper($migrationSource), 'ALTER TABLE'), 'P8-005 migration does not rewrite legacy tables');
foreach (['document_id', 'attachment_id', 'file_id', 'blob', 'obligation_id', 'milestone_id'] as $forbiddenField) {
    esc_p8_deliverable_assert(! str_contains($schema, $forbiddenField), 'Deliverable schema avoids deferred/coupled field ' . $forbiddenField);
}

// Policy is bounded and deterministic.
esc_p8_deliverable_assert(ContractDeliverablePolicy::normalizeCode(' Final Report ') === 'final_report', 'deliverable code normalization is deterministic');
esc_p8_deliverable_assert(ContractDeliverablePolicy::normalizeTitle('  Final report  ') === 'Final report', 'deliverable title is normalized');
esc_p8_deliverable_assert(ContractDeliverablePolicy::normalizeDescription('  Work product  ') === 'Work product', 'deliverable description is normalized');
esc_p8_deliverable_assert(ContractDeliverablePolicy::normalizeDescription('   ') === null, 'blank deliverable description canonicalizes to null');
esc_p8_deliverable_assert(ContractDeliverablePolicy::normalizeDueDate('2027-03-31') === '2027-03-31', 'valid contractual due date is accepted');
esc_p8_deliverable_assert(ContractDeliverablePolicy::normalizeDueDate(null) === null, 'missing contractual due date remains null');
esc_p8_deliverable_expect_invalid(static fn (): string => ContractDeliverablePolicy::normalizeCode('../escape'), 'invalid deliverable code is rejected');
esc_p8_deliverable_expect_invalid(static fn (): string => ContractDeliverablePolicy::normalizeTitle(''), 'missing title is rejected');
esc_p8_deliverable_expect_invalid(static fn (): ?string => ContractDeliverablePolicy::normalizeDueDate('2027-02-29'), 'invalid calendar date is rejected');
esc_p8_deliverable_expect_invalid(static fn (): string => ContractDeliverablePolicy::normalizeTerminalStatus('pending'), 'pending cannot be selected as terminal status');
esc_p8_deliverable_assert(str_contains($policySource, "STATUS_PENDING = 'pending'") && str_contains($policySource, "STATUS_DELIVERED = 'delivered'") && str_contains($policySource, "STATUS_CANCELLED = 'cancelled'"), 'P8-005 lifecycle is explicitly allowlisted');

// Repository owns tenant-safe persistence and lifecycle concurrency.
esc_p8_deliverable_assert(str_contains($repositorySource, 'TenantContextStore::context()->requireTenantId()'), 'repository derives tenant identity from TenantContextStore');
esc_p8_deliverable_assert(! str_contains($repositorySource, 'public function tenantId('), 'repository exposes no caller tenant selector');
esc_p8_deliverable_assert(substr_count($repositorySource, 'tenant_id = %d') >= 7, 'repository scopes reads, locks and mutations by tenant');
esc_p8_deliverable_assert(str_contains($repositorySource, 'c.tenant_id = d.tenant_id'), 'deliverable-to-contract lock enforces same-tenant parent ownership');
esc_p8_deliverable_assert(substr_count($repositorySource, 'START TRANSACTION') >= 3, 'create/update/lifecycle mutations are transactional');
esc_p8_deliverable_assert(substr_count($repositorySource, 'FOR UPDATE') >= 3, 'mutable Contract/Deliverable identities are locked before writes');
esc_p8_deliverable_assert(str_contains($repositorySource, 'ORDER BY CASE WHEN due_date IS NULL THEN 1 ELSE 0 END ASC, due_date ASC, id ASC'), 'Deliverable listing is deterministic by due date then ID');
esc_p8_deliverable_assert(str_contains($repositorySource, 'AND status = %s'), 'update/lifecycle writes use status compare-and-set predicates');
esc_p8_deliverable_assert(str_contains($repositorySource, '$updated === false || ($updated !== 0 && $updated !== 1)'), 'metadata update allows exact MySQL no-op while rejecting failure/cardinality anomalies');
esc_p8_deliverable_assert(str_contains($repositorySource, 'is_archived = 0') && str_contains($repositorySource, "['is_archived']"), 'archived Contract mutation is blocked before and during persistence');
esc_p8_deliverable_assert(str_contains($repositorySource, 'delivered_at = UTC_TIMESTAMP()') && str_contains($repositorySource, 'delivered_by = %d'), 'delivery evidence is server-derived');
esc_p8_deliverable_assert(str_contains($repositorySource, 'cancelled_at = UTC_TIMESTAMP()') && str_contains($repositorySource, 'cancelled_by = %d'), 'cancellation evidence is server-derived');
esc_p8_deliverable_assert(! str_contains(strtoupper($repositorySource), 'DELETE FROM'), 'P8-005 performs no physical Deliverable delete');
esc_p8_deliverable_assert(! str_contains($repositorySource, 'safecontracts_contract_obligations') && ! str_contains($repositorySource, 'safecontracts_contract_milestones'), 'Deliverable persistence is independent from Obligation/Milestone storage');
esc_p8_deliverable_assert(! str_contains($repositorySource, 'safecontracts_documents') && ! str_contains($repositorySource, 'safecontracts_attachments'), 'Deliverable persistence does not invent document storage coupling');
esc_p8_deliverable_assert(! str_contains($repositorySource, 'safecontracts_contract_workflow_instances') && ! str_contains($repositorySource, 'safecontracts_workflow_approval'), 'Deliverables do not mutate Workflow/Approval runtime');

// Service preserves Contract authorization/data scope and explicit commands.
esc_p8_deliverable_assert(str_contains($serviceSource, 'authorize(Capabilities::ACCESS)'), 'Deliverable reads require ACCESS');
esc_p8_deliverable_assert(str_contains($serviceSource, 'authorize(Capabilities::EDIT_CONTRACTS)'), 'Deliverable mutations require EDIT_CONTRACTS');
esc_p8_deliverable_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'tenant role remains a narrowing authorization ceiling');
esc_p8_deliverable_assert(str_contains($serviceSource, 'assertScope($contract)'), 'service retains Contract data scope checks');
esc_p8_deliverable_assert(str_contains($serviceSource, 'Capabilities::VIEW_ALL') && str_contains($serviceSource, 'Capabilities::VIEW_ASSIGNED'), 'service preserves VIEW_ALL / own VIEW_ASSIGNED semantics');
esc_p8_deliverable_assert(str_contains($serviceSource, '$accountantUserId === get_current_user_id()'), 'assigned scope is restricted to current accountant');
esc_p8_deliverable_assert(str_contains($serviceSource, 'assertContractMutable($contract)'), 'Deliverable writes reject archived Contracts');
esc_p8_deliverable_assert(str_contains($serviceSource, 'public function create(') && str_contains($serviceSource, 'public function update(') && str_contains($serviceSource, 'public function deliver(') && str_contains($serviceSource, 'public function cancel('), 'P8-005 exposes explicit service commands');
esc_p8_deliverable_assert(! str_contains($serviceSource, 'public function accept(') && ! str_contains($serviceSource, 'public function reject('), 'P8-005 does not invent acceptance/rejection workflow');
esc_p8_deliverable_assert(! str_contains($serviceSource, "'tenant_id'") && ! str_contains($serviceSource, '$tenantId'), 'service accepts no caller tenant identity');
esc_p8_deliverable_assert(str_contains($serviceSource, 'currentStatus === $targetStatus'), 'exact terminal retry is idempotent');
esc_p8_deliverable_assert(str_contains($serviceSource, 'currentStatus !== ContractDeliverablePolicy::STATUS_PENDING'), 'different terminal retry fails closed');
esc_p8_deliverable_assert(str_contains($serviceSource, 'random_bytes(16)') && str_contains($serviceSource, 'wp_generate_uuid4'), 'UUIDv4 identity has WordPress and cryptographic fallback generation');
esc_p8_deliverable_assert(! str_contains($serviceSource, 'ContractObligation') && ! str_contains($serviceSource, 'ContractMilestone') && ! str_contains($serviceSource, 'Document') && ! str_contains($serviceSource, 'Notification'), 'Deliverable service remains decoupled from adjacent/deferred domains');

// Foundation deliberately exposes no UI/API/public surface.
esc_p8_deliverable_assert(! str_contains($routerSource, 'ContractDeliverable'), 'P8-005 adds no REST Deliverable route');
esc_p8_deliverable_assert(str_contains($gateSource, 'enterprise_contract_deliverables_p8_005.php'), 'P8-005 regression is wired into global backend gate');

echo "P8-005 Contract Deliverables foundation passed ({$assertions} assertions).\n";
