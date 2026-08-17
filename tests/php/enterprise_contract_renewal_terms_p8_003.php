<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use InvalidArgumentException;
use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0045EnterpriseContractRenewalTerms;
use SafeContracts\Renewals\ContractRenewalTermsPolicy;

$assertions = 0;
function esc_p8_renewal_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p8_renewal_expect_invalid(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        esc_p8_renewal_assert(true, $message);
        return;
    }
    esc_p8_renewal_assert(false, $message);
}

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0045EnterpriseContractRenewalTerms.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Renewals/ContractRenewalTermsPolicy.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Renewals/ContractRenewalTermsRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Renewals/ContractRenewalTermsService.php');
$routerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

// Historical migration mapping remains stable while later ESC migrations may advance LATEST_VERSION.
esc_p8_renewal_assert(version_compare(Migrator::LATEST_VERSION, '1.44.0', '>='), 'Enterprise schema remains at or beyond P8-003 version 1.44.0');
esc_p8_renewal_assert(str_contains($migratorSource, 'Migration0045EnterpriseContractRenewalTerms'), 'Migrator registers Migration0045');
esc_p8_renewal_assert(str_contains($migratorSource, "'1.44.0' => Migration0045EnterpriseContractRenewalTerms::class"), 'Migration0045 is historically mapped to schema 1.44.0');
$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0045EnterpriseContractRenewalTerms())->up($GLOBALS['wpdb']);
esc_p8_renewal_assert(count($GLOBALS['sc_test_dbdelta']) === 1, 'P8-003 emits exactly one additive table definition');
$schema = (string) ($GLOBALS['sc_test_dbdelta'][0] ?? '');
foreach ([
    'safecontracts_contract_renewal_terms',
    'tenant_id bigint(20) unsigned NOT NULL',
    'uuid char(36) NOT NULL',
    'contract_id bigint(20) unsigned NOT NULL',
    "renewal_mode varchar(20) NOT NULL DEFAULT 'none'",
    'interval_value int(10) unsigned NULL',
    'interval_unit varchar(10) NULL',
    'max_occurrences int(10) unsigned NULL',
    'revision bigint(20) unsigned NOT NULL DEFAULT 1',
    'UNIQUE KEY renewal_terms_uuid (uuid)',
    'UNIQUE KEY tenant_contract_renewal_terms (tenant_id, contract_id)',
    'KEY tenant_renewal_mode (tenant_id, renewal_mode, contract_id)',
] as $marker) {
    esc_p8_renewal_assert(str_contains($schema, $marker), 'P8-003 schema contains ' . $marker);
}
esc_p8_renewal_assert(! str_contains(strtoupper($migrationSource), 'DROP TABLE'), 'P8-003 migration is non-destructive');
esc_p8_renewal_assert(! str_contains(strtoupper($migrationSource), 'ALTER TABLE'), 'P8-003 migration does not rewrite legacy Contract tables');
esc_p8_renewal_assert(! str_contains($schema, 'end_date') && ! str_contains($schema, 'expiry_date'), 'Renewal Terms do not duplicate the Contract current-term boundary');

// Renewal Terms policy is explicit, bounded and canonical.
esc_p8_renewal_assert(ContractRenewalTermsPolicy::normalizeMode(' Automatic ') === 'automatic', 'renewal mode normalization is deterministic');
esc_p8_renewal_assert(ContractRenewalTermsPolicy::normalizeInterval('manual', 12, ' MONTH ') === ['interval_value' => 12, 'interval_unit' => 'month'], 'enabled renewal interval is normalized');
esc_p8_renewal_assert(ContractRenewalTermsPolicy::normalizeInterval('none', 12, 'month') === ['interval_value' => null, 'interval_unit' => null], 'disabled renewal canonicalizes stale interval fields to null');
esc_p8_renewal_assert(ContractRenewalTermsPolicy::normalizeMaxOccurrences('none', 4) === null, 'disabled renewal canonicalizes stale occurrence cap to null');
esc_p8_renewal_assert(ContractRenewalTermsPolicy::normalizeMaxOccurrences('automatic', null) === null, 'enabled renewal may have no configured occurrence cap');
esc_p8_renewal_assert(ContractRenewalTermsPolicy::normalizeMaxOccurrences('automatic', 5) === 5, 'bounded occurrence cap is accepted');
esc_p8_renewal_assert(ContractRenewalTermsPolicy::normalizeNotes('  Renewal clause  ') === 'Renewal clause', 'renewal notes are normalized');
esc_p8_renewal_assert(ContractRenewalTermsPolicy::normalizeNotes('   ') === null, 'blank renewal notes canonicalize to null');
esc_p8_renewal_assert(ContractRenewalTermsPolicy::normalizeExpectedRevision(1) === 1, 'positive expected revision is accepted');
esc_p8_renewal_expect_invalid(static fn (): string => ContractRenewalTermsPolicy::normalizeMode('rolling'), 'unknown renewal mode is rejected');
esc_p8_renewal_expect_invalid(static fn (): array => ContractRenewalTermsPolicy::normalizeInterval('manual', null, 'month'), 'enabled renewal requires interval value');
esc_p8_renewal_expect_invalid(static fn (): array => ContractRenewalTermsPolicy::normalizeInterval('automatic', 1, 'week'), 'unknown interval unit is rejected');
esc_p8_renewal_expect_invalid(static fn (): array => ContractRenewalTermsPolicy::normalizeInterval('manual', 10001, 'day'), 'excessive interval value is rejected');
esc_p8_renewal_expect_invalid(static fn (): ?int => ContractRenewalTermsPolicy::normalizeMaxOccurrences('automatic', 0), 'zero occurrence cap is rejected');
esc_p8_renewal_expect_invalid(static fn (): int => ContractRenewalTermsPolicy::normalizeExpectedRevision(0), 'non-positive expected revision is rejected');
esc_p8_renewal_assert(str_contains($policySource, "MODE_NONE = 'none'") && str_contains($policySource, "MODE_MANUAL = 'manual'") && str_contains($policySource, "MODE_AUTOMATIC = 'automatic'"), 'renewal modes are explicitly allowlisted');
esc_p8_renewal_assert(str_contains($policySource, "UNIT_DAY = 'day'") && str_contains($policySource, "UNIT_MONTH = 'month'") && str_contains($policySource, "UNIT_YEAR = 'year'"), 'renewal interval units are explicitly allowlisted');

// Repository owns tenant-safe persistence and stale-write protection.
esc_p8_renewal_assert(str_contains($repositorySource, 'TenantContextStore::context()->requireTenantId()'), 'repository derives tenant identity from TenantContextStore');
esc_p8_renewal_assert(! str_contains($repositorySource, 'public function tenantId('), 'repository exposes no caller tenant selector');
esc_p8_renewal_assert(substr_count($repositorySource, 'tenant_id = %d') >= 6, 'repository tenant-scopes reads, locks and writes');
esc_p8_renewal_assert(str_contains($repositorySource, 'c.tenant_id = r.tenant_id'), 'renewal terms lock enforces same-tenant Contract ownership');
esc_p8_renewal_assert(substr_count($repositorySource, 'START TRANSACTION') >= 2, 'create and update are transactional');
esc_p8_renewal_assert(substr_count($repositorySource, 'FOR UPDATE') >= 3, 'Contract/existing Terms/update identities are locked before writes');
esc_p8_renewal_assert(str_contains($repositorySource, 'revision = revision + 1'), 'update increments revision atomically');
esc_p8_renewal_assert(str_contains($repositorySource, 'AND revision = %d'), 'update performs SQL revision compare-and-set');
esc_p8_renewal_assert(str_contains($repositorySource, "['revision']") && str_contains($repositorySource, '$expectedRevision'), 'locked revision is checked before mutation');
esc_p8_renewal_assert(str_contains($repositorySource, 'if ($updated !== 1)'), 'revisioned updates require exactly one affected row');
esc_p8_renewal_assert(str_contains($repositorySource, 'is_archived = 0') && str_contains($repositorySource, "['is_archived']"), 'archived Contract mutation is blocked before and during persistence');
esc_p8_renewal_assert(! str_contains(strtoupper($repositorySource), 'DELETE FROM'), 'P8-003 exposes no physical Renewal Terms delete');
esc_p8_renewal_assert(! str_contains($repositorySource, 'SET end_date') && ! str_contains($repositorySource, 'SET start_date'), 'P8-003 never mutates authoritative Contract dates');
esc_p8_renewal_assert(! str_contains($repositorySource, 'safecontracts_contract_obligations') && ! str_contains($repositorySource, 'safecontracts_contract_milestones'), 'Renewal Terms persistence is not coupled to Obligation/Milestone storage');
esc_p8_renewal_assert(! str_contains($repositorySource, 'safecontracts_contract_workflow_instances') && ! str_contains($repositorySource, 'safecontracts_workflow_approval'), 'Renewal Terms persistence does not mutate Workflow/Approval runtime');

// Service preserves Contract authorization/data scope and exposes configuration only.
esc_p8_renewal_assert(str_contains($serviceSource, 'authorize(Capabilities::ACCESS)'), 'renewal reads require ACCESS');
esc_p8_renewal_assert(str_contains($serviceSource, 'authorize(Capabilities::EDIT_CONTRACTS)'), 'renewal mutations require EDIT_CONTRACTS');
esc_p8_renewal_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'tenant role remains a narrowing authorization ceiling');
esc_p8_renewal_assert(str_contains($serviceSource, 'assertScope($contract)'), 'service retains Contract data scope checks');
esc_p8_renewal_assert(str_contains($serviceSource, 'Capabilities::VIEW_ALL') && str_contains($serviceSource, 'Capabilities::VIEW_ASSIGNED'), 'service preserves VIEW_ALL / own VIEW_ASSIGNED semantics');
esc_p8_renewal_assert(str_contains($serviceSource, '$accountantUserId === get_current_user_id()'), 'assigned scope is restricted to the current accountant');
esc_p8_renewal_assert(str_contains($serviceSource, 'assertContractMutable($contract)'), 'Renewal Terms writes reject archived Contracts');
esc_p8_renewal_assert(str_contains($serviceSource, 'public function find(') && str_contains($serviceSource, 'public function findForContract(') && str_contains($serviceSource, 'public function create(') && str_contains($serviceSource, 'public function update('), 'P8-003 exposes explicit configuration commands');
esc_p8_renewal_assert(! str_contains($serviceSource, 'public function delete(') && ! str_contains($serviceSource, 'public function renew(') && ! str_contains($serviceSource, 'public function execute('), 'P8-003 exposes no delete or renewal execution command');
esc_p8_renewal_assert(! str_contains($serviceSource, "'tenant_id'") && ! str_contains($serviceSource, '$tenantId'), 'service accepts no caller tenant identity');
esc_p8_renewal_assert(str_contains($serviceSource, 'normalizeExpectedRevision') && str_contains($serviceSource, "['revision']"), 'service rejects stale revisions before repository mutation');
esc_p8_renewal_assert(str_contains($serviceSource, 'random_bytes(16)') && str_contains($serviceSource, 'wp_generate_uuid4'), 'UUIDv4 identity has WordPress and cryptographic fallback generation');
esc_p8_renewal_assert(! str_contains($serviceSource, 'Notice') && ! str_contains($serviceSource, 'Notification') && ! str_contains($serviceSource, 'Milestone') && ! str_contains($serviceSource, 'Obligation'), 'Renewal Terms service has no later P8/P11 or adjacent-domain coupling');

// No exposed surface or execution engine is introduced by the foundation.
esc_p8_renewal_assert(! str_contains($routerSource, 'ContractRenewalTerms'), 'P8-003 adds no REST Renewal Terms route');
esc_p8_renewal_assert(str_contains($gateSource, 'enterprise_contract_renewal_terms_p8_003.php'), 'P8-003 regression is wired into the global backend gate');

echo "P8-003 Contract Renewal Terms foundation passed ({$assertions} assertions).\n";
