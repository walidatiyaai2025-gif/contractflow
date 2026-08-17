<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use InvalidArgumentException;
use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0046EnterpriseContractNoticePeriodRules;
use SafeContracts\Notices\ContractNoticePeriodRulePolicy;

$assertions = 0;
function esc_p8_notice_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p8_notice_expect_invalid(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        esc_p8_notice_assert(true, $message);
        return;
    }
    esc_p8_notice_assert(false, $message);
}

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0046EnterpriseContractNoticePeriodRules.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Notices/ContractNoticePeriodRulePolicy.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Notices/ContractNoticePeriodRuleRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Notices/ContractNoticePeriodRuleService.php');
$routerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

// Historical migration mapping remains stable while later ESC migrations may advance LATEST_VERSION.
esc_p8_notice_assert(version_compare(Migrator::LATEST_VERSION, '1.45.0', '>='), 'Enterprise schema remains at or beyond P8-004 version 1.45.0');
esc_p8_notice_assert(str_contains($migratorSource, 'Migration0046EnterpriseContractNoticePeriodRules'), 'Migrator registers Migration0046');
esc_p8_notice_assert(str_contains($migratorSource, "'1.45.0' => Migration0046EnterpriseContractNoticePeriodRules::class"), 'Migration0046 is historically mapped to schema 1.45.0');
$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0046EnterpriseContractNoticePeriodRules())->up($GLOBALS['wpdb']);
esc_p8_notice_assert(count($GLOBALS['sc_test_dbdelta']) === 1, 'P8-004 emits exactly one additive table definition');
$schema = (string) ($GLOBALS['sc_test_dbdelta'][0] ?? '');
foreach ([
    'safecontracts_contract_notice_period_rules',
    'tenant_id bigint(20) unsigned NOT NULL',
    'uuid char(36) NOT NULL',
    'contract_id bigint(20) unsigned NOT NULL',
    'notice_code varchar(64) NOT NULL',
    'purpose varchar(32) NOT NULL',
    "direction varchar(20) NOT NULL DEFAULT 'outbound'",
    'period_value int(10) unsigned NOT NULL',
    'period_unit varchar(10) NOT NULL',
    'is_active tinyint(1) NOT NULL DEFAULT 1',
    'revision bigint(20) unsigned NOT NULL DEFAULT 1',
    'UNIQUE KEY notice_period_rule_uuid (uuid)',
    'UNIQUE KEY tenant_contract_notice_code (tenant_id, contract_id, notice_code)',
    'KEY tenant_contract_active_purpose (tenant_id, contract_id, is_active, purpose, id)',
    'KEY tenant_purpose_active (tenant_id, purpose, is_active, id)',
] as $marker) {
    esc_p8_notice_assert(str_contains($schema, $marker), 'P8-004 schema contains ' . $marker);
}
esc_p8_notice_assert(! str_contains(strtoupper($migrationSource), 'DROP TABLE'), 'P8-004 migration is non-destructive');
esc_p8_notice_assert(! str_contains(strtoupper($migrationSource), 'ALTER TABLE'), 'P8-004 migration does not rewrite legacy tables');
foreach (['notice_date', 'scheduled_for', 'effective_date', 'expiry_date', 'end_date'] as $forbiddenDateField) {
    esc_p8_notice_assert(! str_contains($schema, $forbiddenDateField), 'Notice Period Rules do not persist computed date field ' . $forbiddenDateField);
}

// Contractual duration policy is explicit, bounded and deterministic.
esc_p8_notice_assert(ContractNoticePeriodRulePolicy::normalizeCode(' Non Renewal 90d ') === 'non_renewal_90d', 'notice code normalization is deterministic');
esc_p8_notice_assert(ContractNoticePeriodRulePolicy::normalizePurpose(' Non_Renewal ') === 'non_renewal', 'notice purpose normalization is deterministic');
esc_p8_notice_assert(ContractNoticePeriodRulePolicy::normalizeDirection(' OUTBOUND ') === 'outbound', 'notice direction normalization is deterministic');
esc_p8_notice_assert(ContractNoticePeriodRulePolicy::normalizePeriod(90, ' DAY ') === ['period_value' => 90, 'period_unit' => 'day'], 'notice duration is normalized');
esc_p8_notice_assert(ContractNoticePeriodRulePolicy::normalizeActive(true) === 1 && ContractNoticePeriodRulePolicy::normalizeActive(false) === 0, 'active flag canonicalizes to 1/0');
esc_p8_notice_assert(ContractNoticePeriodRulePolicy::normalizeNotes('  Written notice  ') === 'Written notice', 'notice notes are normalized');
esc_p8_notice_assert(ContractNoticePeriodRulePolicy::normalizeNotes('   ') === null, 'blank notice notes canonicalize to null');
esc_p8_notice_assert(ContractNoticePeriodRulePolicy::normalizeExpectedRevision(1) === 1, 'positive expected revision is accepted');
esc_p8_notice_expect_invalid(static fn (): string => ContractNoticePeriodRulePolicy::normalizeCode('../escape'), 'invalid notice code is rejected');
esc_p8_notice_expect_invalid(static fn (): string => ContractNoticePeriodRulePolicy::normalizePurpose('payment_due'), 'delivery/payment purpose is rejected');
esc_p8_notice_expect_invalid(static fn (): string => ContractNoticePeriodRulePolicy::normalizeDirection('broadcast'), 'unknown notice direction is rejected');
esc_p8_notice_expect_invalid(static fn (): array => ContractNoticePeriodRulePolicy::normalizePeriod(0, 'day'), 'zero notice period is rejected');
esc_p8_notice_expect_invalid(static fn (): array => ContractNoticePeriodRulePolicy::normalizePeriod(10001, 'day'), 'excessive notice period is rejected');
esc_p8_notice_expect_invalid(static fn (): array => ContractNoticePeriodRulePolicy::normalizePeriod(30, 'week'), 'unknown notice period unit is rejected');
esc_p8_notice_expect_invalid(static fn (): int => ContractNoticePeriodRulePolicy::normalizeExpectedRevision(0), 'non-positive expected revision is rejected');
esc_p8_notice_assert(str_contains($policySource, "PURPOSE_RENEWAL_ELECTION = 'renewal_election'") && str_contains($policySource, "PURPOSE_NON_RENEWAL = 'non_renewal'") && str_contains($policySource, "PURPOSE_TERMINATION = 'termination'") && str_contains($policySource, "PURPOSE_OTHER = 'other'"), 'notice purposes are explicitly allowlisted');
esc_p8_notice_assert(str_contains($policySource, "DIRECTION_OUTBOUND = 'outbound'") && str_contains($policySource, "DIRECTION_INBOUND = 'inbound'") && str_contains($policySource, "DIRECTION_EITHER = 'either'"), 'notice directions are explicitly allowlisted');
esc_p8_notice_assert(str_contains($policySource, "UNIT_DAY = 'day'") && str_contains($policySource, "UNIT_MONTH = 'month'") && str_contains($policySource, "UNIT_YEAR = 'year'"), 'notice period units are explicitly allowlisted');

// Repository owns tenant-safe persistence, immutable code identity and stale-write protection.
esc_p8_notice_assert(str_contains($repositorySource, 'TenantContextStore::context()->requireTenantId()'), 'repository derives tenant identity from TenantContextStore');
esc_p8_notice_assert(! str_contains($repositorySource, 'public function tenantId('), 'repository exposes no caller tenant selector');
esc_p8_notice_assert(substr_count($repositorySource, 'tenant_id = %d') >= 6, 'repository tenant-scopes reads, locks and writes');
esc_p8_notice_assert(str_contains($repositorySource, 'c.tenant_id = n.tenant_id'), 'notice rule lock enforces same-tenant Contract ownership');
esc_p8_notice_assert(substr_count($repositorySource, 'START TRANSACTION') >= 2, 'create and update are transactional');
esc_p8_notice_assert(substr_count($repositorySource, 'FOR UPDATE') >= 3, 'Contract/code/update identities are locked before writes');
esc_p8_notice_assert(str_contains($repositorySource, 'notice_code = %s') && str_contains($repositorySource, 'Notice period code already exists for this contract.'), 'creation protects immutable Contract-local notice code uniqueness');
esc_p8_notice_assert(str_contains($repositorySource, 'ORDER BY is_active DESC, purpose ASC, notice_code ASC, id ASC'), 'notice rule listing is deterministic');
esc_p8_notice_assert(str_contains($repositorySource, 'revision = revision + 1'), 'update increments revision atomically');
esc_p8_notice_assert(str_contains($repositorySource, 'AND revision = %d'), 'update performs SQL revision compare-and-set');
esc_p8_notice_assert(str_contains($repositorySource, "['revision']") && str_contains($repositorySource, '$expectedRevision'), 'locked revision is checked before mutation');
esc_p8_notice_assert(str_contains($repositorySource, 'if ($updated !== 1)'), 'revisioned update requires exactly one affected row');
esc_p8_notice_assert(str_contains($repositorySource, 'is_archived = 0') && str_contains($repositorySource, "['is_archived']"), 'archived Contract mutation is blocked before and during persistence');
esc_p8_notice_assert(! str_contains(strtoupper($repositorySource), 'DELETE FROM'), 'P8-004 exposes no physical Notice Period Rule delete');
esc_p8_notice_assert(! str_contains($repositorySource, 'SET notice_code'), 'notice code is immutable after creation');
esc_p8_notice_assert(! str_contains($repositorySource, 'SET end_date') && ! str_contains($repositorySource, 'SET start_date'), 'P8-004 never mutates authoritative Contract dates');
esc_p8_notice_assert(! str_contains($repositorySource, 'safecontracts_contract_renewal_terms'), 'P8-004 does not mutate Renewal Terms storage');
esc_p8_notice_assert(! str_contains($repositorySource, 'safecontracts_notification_rules') && ! str_contains($repositorySource, 'safecontracts_notification_schedule'), 'P8-004 does not write legacy notification delivery configuration/schedule');
esc_p8_notice_assert(! str_contains($repositorySource, 'safecontracts_contract_workflow_instances') && ! str_contains($repositorySource, 'safecontracts_workflow_approval'), 'P8-004 does not mutate Workflow/Approval runtime');

// Service preserves Contract authorization/data scope and exposes configuration only.
esc_p8_notice_assert(str_contains($serviceSource, 'authorize(Capabilities::ACCESS)'), 'notice rule reads require ACCESS');
esc_p8_notice_assert(str_contains($serviceSource, 'authorize(Capabilities::EDIT_CONTRACTS)'), 'notice rule mutations require EDIT_CONTRACTS');
esc_p8_notice_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'tenant role remains a narrowing authorization ceiling');
esc_p8_notice_assert(str_contains($serviceSource, 'assertScope($contract)'), 'service retains Contract data scope checks');
esc_p8_notice_assert(str_contains($serviceSource, 'Capabilities::VIEW_ALL') && str_contains($serviceSource, 'Capabilities::VIEW_ASSIGNED'), 'service preserves VIEW_ALL / own VIEW_ASSIGNED semantics');
esc_p8_notice_assert(str_contains($serviceSource, '$accountantUserId === get_current_user_id()'), 'assigned scope is restricted to current accountant');
esc_p8_notice_assert(str_contains($serviceSource, 'assertContractMutable($contract)'), 'Notice Period Rule writes reject archived Contracts');
esc_p8_notice_assert(str_contains($serviceSource, 'public function find(') && str_contains($serviceSource, 'public function listForContract(') && str_contains($serviceSource, 'public function create(') && str_contains($serviceSource, 'public function update('), 'P8-004 exposes explicit configuration commands');
esc_p8_notice_assert(! str_contains($serviceSource, 'public function delete(') && ! str_contains($serviceSource, 'public function send(') && ! str_contains($serviceSource, 'public function schedule(') && ! str_contains($serviceSource, 'public function execute('), 'P8-004 exposes no delete/delivery/execution command');
esc_p8_notice_assert(! str_contains($serviceSource, "'tenant_id'") && ! str_contains($serviceSource, '$tenantId'), 'service accepts no caller tenant identity');
esc_p8_notice_assert(str_contains($serviceSource, 'normalizeExpectedRevision') && str_contains($serviceSource, "['revision']"), 'service rejects stale revisions before repository mutation');
esc_p8_notice_assert(str_contains($serviceSource, 'random_bytes(16)') && str_contains($serviceSource, 'wp_generate_uuid4'), 'UUIDv4 identity has WordPress and cryptographic fallback generation');
esc_p8_notice_assert(! str_contains($serviceSource, 'ContractRenewalTerms') && ! str_contains($serviceSource, 'NotificationSchedule'), 'Notice Period service is not coupled to renewal or notification execution domains');

// Foundation deliberately exposes no API/UI or date/execution engine.
esc_p8_notice_assert(! str_contains($routerSource, 'ContractNoticePeriodRule'), 'P8-004 adds no REST Notice Period Rule route');
esc_p8_notice_assert(str_contains($gateSource, 'enterprise_contract_notice_period_rules_p8_004.php'), 'P8-004 regression is wired into global backend gate');

echo "P8-004 Contract Notice Period Rules foundation passed ({$assertions} assertions).\n";
