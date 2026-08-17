<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use InvalidArgumentException;
use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0043EnterpriseContractObligations;
use SafeContracts\Obligations\ContractObligationPolicy;

$assertions = 0;
function esc_p8_obligation_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p8_expect_invalid(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        esc_p8_obligation_assert(true, $message);
        return;
    }
    esc_p8_obligation_assert(false, $message);
}

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0043EnterpriseContractObligations.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Obligations/ContractObligationPolicy.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Obligations/ContractObligationRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Obligations/ContractObligationService.php');
$routerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

// Additive schema/version registration.
esc_p8_obligation_assert(Migrator::LATEST_VERSION === '1.42.0', 'P8-001 advances Enterprise schema exactly to 1.42.0');
esc_p8_obligation_assert(str_contains($migratorSource, 'Migration0043EnterpriseContractObligations'), 'Migrator registers Migration0043');
esc_p8_obligation_assert(str_contains($migratorSource, "'1.42.0' => Migration0043EnterpriseContractObligations::class"), 'Migration0043 is mapped to schema 1.42.0');
$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0043EnterpriseContractObligations())->up($GLOBALS['wpdb']);
esc_p8_obligation_assert(count($GLOBALS['sc_test_dbdelta']) === 1, 'P8-001 migration emits exactly one additive table definition');
$schema = (string) ($GLOBALS['sc_test_dbdelta'][0] ?? '');
foreach ([
    'safecontracts_contract_obligations',
    'tenant_id bigint(20) unsigned NOT NULL',
    'uuid char(36) NOT NULL',
    'contract_id bigint(20) unsigned NOT NULL',
    'obligation_code varchar(64) NOT NULL',
    "status varchar(20) NOT NULL DEFAULT 'open'",
    'completed_at datetime NULL',
    'completed_by bigint(20) unsigned NULL',
    'cancelled_at datetime NULL',
    'cancelled_by bigint(20) unsigned NULL',
    'UNIQUE KEY obligation_uuid (uuid)',
    'UNIQUE KEY tenant_contract_obligation_code (tenant_id, contract_id, obligation_code)',
    'KEY tenant_contract_status_due (tenant_id, contract_id, status, due_date, id)',
    'KEY tenant_due_status (tenant_id, due_date, status, id)',
] as $marker) {
    esc_p8_obligation_assert(str_contains($schema, $marker), 'P8-001 schema contains ' . $marker);
}
esc_p8_obligation_assert(! str_contains(strtoupper($migrationSource), 'DROP TABLE'), 'P8-001 migration is non-destructive');
esc_p8_obligation_assert(! str_contains(strtoupper($migrationSource), 'ALTER TABLE') || str_contains($migrationSource, 'CREATE TABLE'), 'P8-001 does not rewrite legacy tables');

// Policy is bounded and deterministic.
esc_p8_obligation_assert(ContractObligationPolicy::normalizeCode(' Quarterly Report ') === 'quarterly_report', 'obligation code normalization is deterministic');
esc_p8_obligation_assert(ContractObligationPolicy::normalizeTitle('  Submit report  ') === 'Submit report', 'obligation title is normalized');
esc_p8_obligation_assert(ContractObligationPolicy::normalizeDescription('  Evidence  ') === 'Evidence', 'obligation description is normalized');
esc_p8_obligation_assert(ContractObligationPolicy::normalizeDescription('   ') === null, 'blank obligation description canonicalizes to null');
esc_p8_obligation_assert(ContractObligationPolicy::normalizeDueDate('2026-12-31') === '2026-12-31', 'valid contractual due date is accepted');
esc_p8_obligation_assert(ContractObligationPolicy::normalizeDueDate(null) === null, 'missing contractual due date remains null');
esc_p8_expect_invalid(static fn (): string => ContractObligationPolicy::normalizeCode('../escape'), 'invalid machine code is rejected');
esc_p8_expect_invalid(static fn (): string => ContractObligationPolicy::normalizeTitle(''), 'missing title is rejected');
esc_p8_expect_invalid(static fn (): ?string => ContractObligationPolicy::normalizeDueDate('2026-02-30'), 'invalid calendar date is rejected');
esc_p8_expect_invalid(static fn (): string => ContractObligationPolicy::normalizeTerminalStatus('open'), 'open cannot be caller-selected as a terminal transition');
esc_p8_obligation_assert(str_contains($policySource, "STATUS_OPEN = 'open'") && str_contains($policySource, "STATUS_COMPLETED = 'completed'") && str_contains($policySource, "STATUS_CANCELLED = 'cancelled'"), 'P8-001 lifecycle is explicitly allowlisted');

// Repository owns all persistence and derives tenant identity from locked server context.
esc_p8_obligation_assert(str_contains($repositorySource, 'TenantContextStore::context()->requireTenantId()'), 'repository derives tenant identity from TenantContextStore');
esc_p8_obligation_assert(! str_contains($repositorySource, 'public function tenantId('), 'repository exposes no caller tenant selector');
esc_p8_obligation_assert(substr_count($repositorySource, 'tenant_id = %d') >= 7, 'repository scopes reads, locks and mutations by tenant');
esc_p8_obligation_assert(str_contains($repositorySource, 'c.tenant_id = o.tenant_id'), 'obligation-to-contract lock enforces same-tenant parent ownership');
esc_p8_obligation_assert(substr_count($repositorySource, 'START TRANSACTION') >= 3, 'create/update/lifecycle mutations are transactional');
esc_p8_obligation_assert(substr_count($repositorySource, 'FOR UPDATE') >= 3, 'mutable contract/obligation identities are locked before writes');
esc_p8_obligation_assert(str_contains($repositorySource, "AND status = %s"), 'lifecycle/update writes use status compare-and-set predicates');
esc_p8_obligation_assert(str_contains($repositorySource, '$updated === false || ($updated !== 0 && $updated !== 1)'), 'metadata updates accept exact MySQL no-op affected_rows=0 while rejecting query/cardinality failures');
esc_p8_obligation_assert(str_contains($repositorySource, 'is_archived = 0') && str_contains($repositorySource, "['is_archived']"), 'archived contract mutation is blocked before and during persistence');
esc_p8_obligation_assert(str_contains($repositorySource, 'completed_at = UTC_TIMESTAMP()') && str_contains($repositorySource, 'completed_by = %d'), 'completion evidence is server-derived');
esc_p8_obligation_assert(str_contains($repositorySource, 'cancelled_at = UTC_TIMESTAMP()') && str_contains($repositorySource, 'cancelled_by = %d'), 'cancellation evidence is server-derived');
esc_p8_obligation_assert(! str_contains(strtoupper($repositorySource), 'DELETE FROM'), 'P8-001 performs no physical obligation delete');
esc_p8_obligation_assert(! str_contains($repositorySource, 'safecontracts_contract_history') && ! str_contains($repositorySource, 'safecontracts_contract_workflow_instances'), 'P8-001 does not mutate legacy status or Workflow runtime');

// Service preserves existing Contract capability and data-scope boundaries.
esc_p8_obligation_assert(str_contains($serviceSource, 'authorize(Capabilities::ACCESS)'), 'obligation reads require ACCESS');
esc_p8_obligation_assert(str_contains($serviceSource, 'authorize(Capabilities::EDIT_CONTRACTS)'), 'obligation mutations require EDIT_CONTRACTS');
esc_p8_obligation_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'tenant role remains a narrowing authorization ceiling');
esc_p8_obligation_assert(str_contains($serviceSource, 'assertScope($contract)'), 'every service path retains Contract data scope checks');
esc_p8_obligation_assert(str_contains($serviceSource, 'Capabilities::VIEW_ALL') && str_contains($serviceSource, 'Capabilities::VIEW_ASSIGNED'), 'service preserves VIEW_ALL / own VIEW_ASSIGNED semantics');
esc_p8_obligation_assert(str_contains($serviceSource, '$accountantUserId === get_current_user_id()'), 'assigned scope is restricted to the current accountant');
esc_p8_obligation_assert(str_contains($serviceSource, 'assertContractMutable($contract)'), 'new mutations reject archived Contracts');
esc_p8_obligation_assert(str_contains($serviceSource, 'public function create(') && str_contains($serviceSource, 'public function update(') && str_contains($serviceSource, 'public function complete(') && str_contains($serviceSource, 'public function cancel('), 'P8-001 exposes explicit service commands only');
esc_p8_obligation_assert(! str_contains($serviceSource, "'tenant_id'") && ! str_contains($serviceSource, '$tenantId'), 'service accepts no caller tenant identity');
esc_p8_obligation_assert(str_contains($serviceSource, 'currentStatus === $targetStatus'), 'exact terminal retry returns idempotently without duplicate transition event');
esc_p8_obligation_assert(str_contains($serviceSource, "currentStatus !== ContractObligationPolicy::STATUS_OPEN"), 'different terminal retry fails closed');
esc_p8_obligation_assert(str_contains($serviceSource, 'random_bytes(16)') && str_contains($serviceSource, 'wp_generate_uuid4'), 'UUIDv4 identity has WordPress and cryptographic fallback generation');

// Foundation deliberately has no exposed UI/API/public execution surface yet.
esc_p8_obligation_assert(! str_contains($routerSource, 'ContractObligation'), 'P8-001 adds no REST obligation route');
esc_p8_obligation_assert(str_contains($gateSource, 'enterprise_contract_obligations_p8_001.php'), 'P8-001 regression is wired into the global backend gate');
esc_p8_obligation_assert(! str_contains($serviceSource, 'Notification') && ! str_contains($serviceSource, 'Renewal') && ! str_contains($serviceSource, 'Milestone'), 'later P8 execution domains are not coupled into the obligation foundation');

echo "P8-001 Contract Obligation foundation passed ({$assertions} assertions).\n";
