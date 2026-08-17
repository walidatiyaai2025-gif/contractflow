<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use InvalidArgumentException;
use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0044EnterpriseContractMilestones;
use SafeContracts\Milestones\ContractMilestonePolicy;

$assertions = 0;
function esc_p8_milestone_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p8_milestone_expect_invalid(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        esc_p8_milestone_assert(true, $message);
        return;
    }
    esc_p8_milestone_assert(false, $message);
}

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0044EnterpriseContractMilestones.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Milestones/ContractMilestonePolicy.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Milestones/ContractMilestoneRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Milestones/ContractMilestoneService.php');
$routerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

// Additive schema/version registration. The global latest version may advance after P8-002;
// this regression pins Migration0044's historical mapping instead of blocking later migrations.
esc_p8_milestone_assert(version_compare(Migrator::LATEST_VERSION, '1.43.0', '>='), 'Enterprise schema remains at or beyond P8-002 version 1.43.0');
esc_p8_milestone_assert(str_contains($migratorSource, 'Migration0044EnterpriseContractMilestones'), 'Migrator registers Migration0044');
esc_p8_milestone_assert(str_contains($migratorSource, "'1.43.0' => Migration0044EnterpriseContractMilestones::class"), 'Migration0044 is mapped to schema 1.43.0');
$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0044EnterpriseContractMilestones())->up($GLOBALS['wpdb']);
esc_p8_milestone_assert(count($GLOBALS['sc_test_dbdelta']) === 1, 'P8-002 migration emits exactly one additive table definition');
$schema = (string) ($GLOBALS['sc_test_dbdelta'][0] ?? '');
foreach ([
    'safecontracts_contract_milestones',
    'tenant_id bigint(20) unsigned NOT NULL',
    'uuid char(36) NOT NULL',
    'contract_id bigint(20) unsigned NOT NULL',
    'milestone_code varchar(64) NOT NULL',
    'target_date date NULL',
    "status varchar(20) NOT NULL DEFAULT 'planned'",
    'achieved_at datetime NULL',
    'achieved_by bigint(20) unsigned NULL',
    'cancelled_at datetime NULL',
    'cancelled_by bigint(20) unsigned NULL',
    'UNIQUE KEY milestone_uuid (uuid)',
    'UNIQUE KEY tenant_contract_milestone_code (tenant_id, contract_id, milestone_code)',
    'KEY tenant_contract_status_target (tenant_id, contract_id, status, target_date, id)',
    'KEY tenant_target_status (tenant_id, target_date, status, id)',
] as $marker) {
    esc_p8_milestone_assert(str_contains($schema, $marker), 'P8-002 schema contains ' . $marker);
}
esc_p8_milestone_assert(! str_contains(strtoupper($migrationSource), 'DROP TABLE'), 'P8-002 migration is non-destructive');
esc_p8_milestone_assert(! str_contains($schema, 'obligation_id'), 'P8-002 does not force a premature Obligation relationship');

// Policy is bounded and deterministic.
esc_p8_milestone_assert(ContractMilestonePolicy::normalizeCode(' Design Review ') === 'design_review', 'milestone code normalization is deterministic');
esc_p8_milestone_assert(ContractMilestonePolicy::normalizeTitle('  Design accepted  ') === 'Design accepted', 'milestone title is normalized');
esc_p8_milestone_assert(ContractMilestonePolicy::normalizeDescription('  Evidence  ') === 'Evidence', 'milestone description is normalized');
esc_p8_milestone_assert(ContractMilestonePolicy::normalizeDescription('   ') === null, 'blank milestone description canonicalizes to null');
esc_p8_milestone_assert(ContractMilestonePolicy::normalizeTargetDate('2027-01-31') === '2027-01-31', 'valid contractual target date is accepted');
esc_p8_milestone_assert(ContractMilestonePolicy::normalizeTargetDate(null) === null, 'missing contractual target date remains null');
esc_p8_milestone_expect_invalid(static fn (): string => ContractMilestonePolicy::normalizeCode('../escape'), 'invalid machine code is rejected');
esc_p8_milestone_expect_invalid(static fn (): string => ContractMilestonePolicy::normalizeTitle(''), 'missing title is rejected');
esc_p8_milestone_expect_invalid(static fn (): ?string => ContractMilestonePolicy::normalizeTargetDate('2027-02-29'), 'invalid calendar date is rejected');
esc_p8_milestone_expect_invalid(static fn (): string => ContractMilestonePolicy::normalizeTerminalStatus('planned'), 'planned cannot be caller-selected as a terminal transition');
esc_p8_milestone_assert(str_contains($policySource, "STATUS_PLANNED = 'planned'") && str_contains($policySource, "STATUS_ACHIEVED = 'achieved'") && str_contains($policySource, "STATUS_CANCELLED = 'cancelled'"), 'P8-002 lifecycle is explicitly allowlisted');

// Repository owns persistence and derives tenant identity from locked server context.
esc_p8_milestone_assert(str_contains($repositorySource, 'TenantContextStore::context()->requireTenantId()'), 'repository derives tenant identity from TenantContextStore');
esc_p8_milestone_assert(! str_contains($repositorySource, 'public function tenantId('), 'repository exposes no caller tenant selector');
esc_p8_milestone_assert(substr_count($repositorySource, 'tenant_id = %d') >= 7, 'repository scopes reads, locks and mutations by tenant');
esc_p8_milestone_assert(str_contains($repositorySource, 'c.tenant_id = m.tenant_id'), 'milestone-to-contract lock enforces same-tenant parent ownership');
esc_p8_milestone_assert(substr_count($repositorySource, 'START TRANSACTION') >= 3, 'create/update/lifecycle mutations are transactional');
esc_p8_milestone_assert(substr_count($repositorySource, 'FOR UPDATE') >= 3, 'mutable contract/milestone identities are locked before writes');
esc_p8_milestone_assert(str_contains($repositorySource, 'ORDER BY CASE WHEN target_date IS NULL THEN 1 ELSE 0 END ASC, target_date ASC, id ASC'), 'contract milestone listing is deterministic by target date then id');
esc_p8_milestone_assert(str_contains($repositorySource, 'AND status = %s'), 'lifecycle/update writes use status compare-and-set predicates');
esc_p8_milestone_assert(str_contains($repositorySource, '$updated === false || ($updated !== 0 && $updated !== 1)'), 'metadata updates allow exact MySQL no-op while rejecting failure/cardinality anomalies');
esc_p8_milestone_assert(str_contains($repositorySource, 'is_archived = 0') && str_contains($repositorySource, "['is_archived']"), 'archived contract mutation is blocked before and during persistence');
esc_p8_milestone_assert(str_contains($repositorySource, 'achieved_at = UTC_TIMESTAMP()') && str_contains($repositorySource, 'achieved_by = %d'), 'achievement evidence is server-derived');
esc_p8_milestone_assert(str_contains($repositorySource, 'cancelled_at = UTC_TIMESTAMP()') && str_contains($repositorySource, 'cancelled_by = %d'), 'cancellation evidence is server-derived');
esc_p8_milestone_assert(! str_contains(strtoupper($repositorySource), 'DELETE FROM'), 'P8-002 performs no physical milestone delete');
esc_p8_milestone_assert(! str_contains($repositorySource, 'safecontracts_contract_obligations'), 'P8-002 repository does not couple milestone persistence to Obligation storage');
esc_p8_milestone_assert(! str_contains($repositorySource, 'safecontracts_contract_history') && ! str_contains($repositorySource, 'safecontracts_contract_workflow_instances'), 'P8-002 does not mutate legacy status or Workflow runtime');

// Service preserves existing Contract capability and data-scope boundaries.
esc_p8_milestone_assert(str_contains($serviceSource, 'authorize(Capabilities::ACCESS)'), 'milestone reads require ACCESS');
esc_p8_milestone_assert(str_contains($serviceSource, 'authorize(Capabilities::EDIT_CONTRACTS)'), 'milestone mutations require EDIT_CONTRACTS');
esc_p8_milestone_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'tenant role remains a narrowing authorization ceiling');
esc_p8_milestone_assert(str_contains($serviceSource, 'assertScope($contract)'), 'every service path retains Contract data scope checks');
esc_p8_milestone_assert(str_contains($serviceSource, 'Capabilities::VIEW_ALL') && str_contains($serviceSource, 'Capabilities::VIEW_ASSIGNED'), 'service preserves VIEW_ALL / own VIEW_ASSIGNED semantics');
esc_p8_milestone_assert(str_contains($serviceSource, '$accountantUserId === get_current_user_id()'), 'assigned scope is restricted to the current accountant');
esc_p8_milestone_assert(str_contains($serviceSource, 'assertContractMutable($contract)'), 'new mutations reject archived Contracts');
esc_p8_milestone_assert(str_contains($serviceSource, 'public function create(') && str_contains($serviceSource, 'public function update(') && str_contains($serviceSource, 'public function achieve(') && str_contains($serviceSource, 'public function cancel('), 'P8-002 exposes explicit service commands only');
esc_p8_milestone_assert(! str_contains($serviceSource, "'tenant_id'") && ! str_contains($serviceSource, '$tenantId'), 'service accepts no caller tenant identity');
esc_p8_milestone_assert(str_contains($serviceSource, 'currentStatus === $targetStatus'), 'exact terminal retry returns idempotently without duplicate transition event');
esc_p8_milestone_assert(str_contains($serviceSource, 'currentStatus !== ContractMilestonePolicy::STATUS_PLANNED'), 'different terminal retry fails closed');
esc_p8_milestone_assert(str_contains($serviceSource, 'random_bytes(16)') && str_contains($serviceSource, 'wp_generate_uuid4'), 'UUIDv4 identity has WordPress and cryptographic fallback generation');
esc_p8_milestone_assert(! str_contains($serviceSource, 'ContractObligation'), 'Milestone service is not coupled to the Obligation runtime');

// Foundation deliberately has no exposed API/UI or later P8 execution engines.
esc_p8_milestone_assert(! str_contains($routerSource, 'ContractMilestone'), 'P8-002 adds no REST milestone route');
esc_p8_milestone_assert(str_contains($gateSource, 'enterprise_contract_milestones_p8_002.php'), 'P8-002 regression is wired into the global backend gate');
esc_p8_milestone_assert(! str_contains($serviceSource, 'Renewal') && ! str_contains($serviceSource, 'Notice') && ! str_contains($serviceSource, 'Notification'), 'later P8/P11 execution domains are not coupled into the milestone foundation');

echo "P8-002 Contract Milestone foundation passed ({$assertions} assertions).\n";
