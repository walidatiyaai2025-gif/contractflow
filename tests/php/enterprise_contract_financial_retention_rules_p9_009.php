<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0053EnterpriseContractFinancialRetentionRuleRevisions;
use SafeContracts\Finance\ContractFinancialRetentionRulePolicy;
use SafeContracts\Finance\ContractFinancialRetentionRuleRevisionRepository;
use SafeContracts\Finance\PercentageRate;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;
function esc_p9_009_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @param class-string<Throwable> $expected */
function esc_p9_009_expect_throw(callable $callback, string $expected, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p9_009_assert($error instanceof $expected, $message . ' (received ' . $error::class . ')');
        return;
    }
    esc_p9_009_assert(false, $message . ' (no exception)');
}

/** @return array<string,mixed> */
function esc_p9_009_row(
    string $ruleUuid,
    int $revisionNumber,
    string $label = 'Standard retention',
    string $rate = '10.0000',
    string $state = 'configured',
    int $id = 1001,
    int $profileId = 31
): array {
    return [
        'id' => (string) $id,
        'revision_uuid' => sprintf('20000000-0000-4000-8000-%012x', $id),
        'contract_id' => '55',
        'financial_currency_profile_id' => (string) $profileId,
        'retention_rule_uuid' => $ruleUuid,
        'revision_number' => (string) $revisionNumber,
        'label' => $label,
        'rate_percent' => $rate,
        'retention_rule_state' => $state,
        'created_by' => '42',
        'created_at' => '2026-08-17 18:30:00',
    ];
}

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0053EnterpriseContractFinancialRetentionRuleRevisions.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialRetentionRulePolicy.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialRetentionRuleRevisionRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialRetentionRuleRevisionService.php');
$reconciliationRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialReconciliationRepository.php');
$reconciliationServiceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialReconciliationService.php');
$routerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

esc_p9_009_assert(Migrator::LATEST_VERSION === '1.52.0', 'P9-009 advances Enterprise schema to 1.52.0');
esc_p9_009_assert(str_contains($migratorSource, "'1.51.0' => Migration0052EnterpriseContractFinancialTaxRuleRevisions::class"), 'Migration0052 remains historically mapped to 1.51.0');
esc_p9_009_assert(str_contains($migratorSource, "'1.52.0' => Migration0053EnterpriseContractFinancialRetentionRuleRevisions::class"), 'Migration0053 is mapped to 1.52.0');
esc_p9_009_assert(! str_contains($migratorSource, 'Migration0054'), 'P9-009 does not invent a later migration');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0053EnterpriseContractFinancialRetentionRuleRevisions())->up($GLOBALS['wpdb']);
esc_p9_009_assert(count($GLOBALS['sc_test_dbdelta']) === 1, 'P9-009 emits one additive table definition');
$schema = (string) ($GLOBALS['sc_test_dbdelta'][0] ?? '');
foreach ([
    'safecontracts_contract_financial_retention_rule_revisions',
    'tenant_id bigint(20) unsigned NOT NULL',
    'revision_uuid char(36) NOT NULL',
    'contract_id bigint(20) unsigned NOT NULL',
    'financial_currency_profile_id bigint(20) unsigned NOT NULL',
    'retention_rule_uuid char(36) NOT NULL',
    'revision_number bigint(20) unsigned NOT NULL',
    'label varchar(120) NOT NULL',
    'rate_percent decimal(7,4) NOT NULL',
    "retention_rule_state varchar(16) NOT NULL DEFAULT 'configured'",
    'created_by bigint(20) unsigned NOT NULL',
    'created_at datetime NOT NULL',
    'UNIQUE KEY financial_retention_rule_revision_uuid (revision_uuid)',
    'UNIQUE KEY tenant_contract_retention_rule_revision (tenant_id, contract_id, retention_rule_uuid, revision_number)',
    'KEY tenant_contract_retention_rule_latest (tenant_id, contract_id, retention_rule_uuid, revision_number, id)',
] as $marker) {
    esc_p9_009_assert(str_contains($schema, $marker), 'P9-009 schema contains ' . $marker);
}
esc_p9_009_assert(! str_contains(strtoupper($migrationSource), 'DROP TABLE'), 'P9-009 migration is non-destructive');
esc_p9_009_assert(! str_contains(strtoupper($migrationSource), 'ALTER TABLE'), 'P9-009 migration does not rewrite existing tables');
esc_p9_009_assert(! str_contains($schema, 'updated_at') && ! str_contains($schema, 'updated_by'), 'retention revisions contain no mutable update columns');

esc_p9_009_assert(PercentageRate::of('10')->value() === '10.0000', 'retention rate uses P9-008 PercentageRate');
esc_p9_009_assert(PercentageRate::of('7.5')->equals(PercentageRate::of('7.5000')), 'retention rate equality is exact');
esc_p9_009_expect_throw(static fn (): PercentageRate => PercentageRate::of(7.5), InvalidArgumentException::class, 'float retention rate fails closed');
esc_p9_009_expect_throw(static fn (): PercentageRate => PercentageRate::of('100.0001'), OverflowException::class, 'retention rate above 100 fails closed');
esc_p9_009_assert(! str_contains($policySource, 'class PercentageRate'), 'retention policy does not duplicate PercentageRate');

esc_p9_009_assert(ContractFinancialRetentionRulePolicy::normalizeState('CONFIGURED') === 'configured', 'configured retention state canonicalizes');
esc_p9_009_assert(ContractFinancialRetentionRulePolicy::normalizeState('voided') === 'voided', 'voided retention state canonicalizes');
esc_p9_009_expect_throw(static fn (): string => ContractFinancialRetentionRulePolicy::normalizeState('released'), InvalidArgumentException::class, 'release semantics are not invented in P9-009');
esc_p9_009_assert(ContractFinancialRetentionRulePolicy::MAX_RULES === 10, 'retention rules are bounded to 10 stable identities');
esc_p9_009_assert(ContractFinancialRetentionRulePolicy::normalizeLabel(' Standard retention ') === 'Standard retention', 'retention label canonicalizes');
esc_p9_009_expect_throw(static fn (): string => ContractFinancialRetentionRulePolicy::normalizeLabel(''), InvalidArgumentException::class, 'blank retention label fails closed');
esc_p9_009_assert(ContractFinancialRetentionRulePolicy::normalizeUuid('AAAAAAAA-AAAA-4AAA-8AAA-AAAAAAAAAAAA') === 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'retention UUID canonicalizes');

esc_p9_009_assert(str_contains($repositorySource, 'TenantContextStore::context()->requireTenantId()'), 'repository derives tenant only from locked context');
esc_p9_009_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()'), 'repository requires core tenant enforcement');
esc_p9_009_assert(str_contains($repositorySource, 'COUNT(DISTINCT retention_rule_uuid)'), 'create path bounds stable retention identities');
esc_p9_009_assert(str_contains($repositorySource, 'ContractFinancialRetentionRulePolicy::MAX_RULES + 1'), 'current read uses an 11th overflow sentinel');
esc_p9_009_assert(str_contains($repositorySource, 'NOT EXISTS ('), 'current read derives latest immutable revisions only');
esc_p9_009_assert(str_contains($repositorySource, '$authorizeLockedContract($contract)'), 'repository authorizes against exact locked Contract row');
esc_p9_009_assert(str_contains($repositorySource, "c.status IN ('draft', 'active') AND c.is_archived = 0"), 'guarded append revalidates mutable lifecycle');
esc_p9_009_assert(str_contains($repositorySource, 'AND p.id = %d'), 'guarded append revalidates exact P9-003 profile');
esc_p9_009_assert(str_contains($repositorySource, '$storedRate->equals($rate)'), 'exact configured retry is idempotent');
esc_p9_009_assert(str_contains($repositorySource, 'cannot be revised or reactivated'), 'voided retention rule is terminal');
esc_p9_009_assert(! str_contains(strtoupper($repositorySource), 'UPDATE '), 'retention evidence has no UPDATE path');
esc_p9_009_assert(! str_contains(strtoupper($repositorySource), 'DELETE FROM'), 'retention evidence has no DELETE path');
foreach (['ContractMoney', 'Money::', 'retained_amount', 'withheld_amount', 'release_amount', 'payment_schedule', 'exchange_rate', 'currency_convert', 'round('] as $forbidden) {
    esc_p9_009_assert(! str_contains($repositorySource, $forbidden) && ! str_contains($serviceSource, $forbidden), 'P9-009 avoids calculation/legacy coupling: ' . $forbidden);
}

esc_p9_009_assert(! str_contains($reconciliationRepositorySource, 'financial_retention_rule'), 'P9-006 reconciliation does not query retention evidence');
esc_p9_009_assert(! str_contains($reconciliationServiceSource, 'ContractFinancialRetentionRule'), 'P9-006 service has no implicit retention effect');
esc_p9_009_assert(! str_contains($routerSource, 'ContractFinancialRetentionRuleRevision'), 'P9-009 exposes no REST route');

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(7);
$repository = new ContractFinancialRetentionRuleRevisionRepository();
$contractActive = [['id' => '55', 'accountant_user_id' => '42', 'status' => 'active', 'is_archived' => '0']];
$contractDraft = [['id' => '55', 'accountant_user_id' => '42', 'status' => 'draft', 'is_archived' => '0']];
$profile = [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']];
$ruleUuid = '11111111-1111-4111-8111-111111111111';

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_009_row($ruleUuid, 2, '  Main retention  ', '7.5', 'CONFIGURED')]];
$authorized = false;
$current = $repository->listCurrentForContract(55, static function (array $contract) use (&$authorized): void {
    esc_p9_009_assert((int) ($contract['id'] ?? 0) === 55, 'authorization receives locked Contract identity');
    esc_p9_009_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'authorization runs before profile/retention reads');
    esc_p9_009_assert(str_contains((string) $GLOBALS['sc_test_read_queries'][0], 'FOR UPDATE'), 'authorization is protected by Contract FOR UPDATE');
    $authorized = true;
});
esc_p9_009_assert($authorized, 'locked-row authorization callback executes');
esc_p9_009_assert(count($current) === 1, 'current retention list returns latest rule');
esc_p9_009_assert($current[0]['label'] === 'Main retention' && $current[0]['rate_percent'] === '7.5000', 'current retention row canonicalizes label/rate');
esc_p9_009_assert($current[0]['retention_rule_state'] === 'configured', 'current retention state canonicalizes');
esc_p9_009_assert(implode("\n", $GLOBALS['sc_test_queries']) === "START TRANSACTION\nCOMMIT", 'current retention read is one transaction');
$listSql = (string) end($GLOBALS['sc_test_read_queries']);
esc_p9_009_assert(str_contains($listSql, 'r.tenant_id = 7') && str_contains($listSql, 'r.contract_id = 55') && str_contains($listSql, 'LIMIT 11'), 'current retention read is tenant/Contract scoped and bounded');

foreach (['completed', 'cancelled'] as $status) {
    $GLOBALS['sc_test_result_queue'] = [
        [['id' => '55', 'accountant_user_id' => '42', 'status' => $status, 'is_archived' => '0']],
        $profile,
        [esc_p9_009_row($ruleUuid, 2)],
    ];
    $historical = $repository->listCurrentForContract(55, static function (array $contract): void {});
    esc_p9_009_assert(count($historical) === 1, "{$status} Contract retains readable retention evidence");
}

$overflow = [];
for ($i = 1; $i <= ContractFinancialRetentionRulePolicy::MAX_RULES + 1; $i++) {
    $overflow[] = esc_p9_009_row(sprintf('30000000-0000-4000-8000-%012x', $i), 1, 'Retention ' . $i, '5.0000', 'configured', 2000 + $i);
}
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, $overflow];
esc_p9_009_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), RuntimeException::class, '11 current retention rules fail closed');

$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [
        esc_p9_009_row($ruleUuid, 1, 'Retention', '5.0000', 'configured', 2101),
        esc_p9_009_row($ruleUuid, 2, 'Retention changed', '6.0000', 'configured', 2102),
    ],
];
esc_p9_009_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'duplicate latest retention identities fail closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_009_row($ruleUuid, 1, 'Retention', '5.0000', 'configured', 2201, 99)]];
esc_p9_009_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'cross-profile retention evidence fails closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_009_row($ruleUuid, 1, 'Retention', '100.0001')]];
esc_p9_009_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'out-of-range persisted retention rate fails closed');

foreach ([[$contractDraft, 'draft'], [$contractActive, 'active']] as [$contractRows, $status]) {
    $GLOBALS['sc_test_queries'] = [];
    $GLOBALS['sc_test_read_queries'] = [];
    $GLOBALS['sc_test_result_queue'] = [$contractRows, $profile, [['total' => '0']], []];
    $GLOBALS['wpdb']->insert_id = 0;
    $createAuthorized = false;
    $created = $repository->createRule(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'Standard retention', PercentageRate::of('10'), 42, static function (array $contract) use (&$createAuthorized): void {
        esc_p9_009_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'create authorization occurs immediately after Contract lock');
        $createAuthorized = true;
    });
    esc_p9_009_assert($createAuthorized && $created === 1001, "{$status} Contract retention creation succeeds");
    $reads = implode("\n", array_map('strval', $GLOBALS['sc_test_read_queries']));
    $writes = implode("\n", array_map('strval', $GLOBALS['sc_test_queries']));
    esc_p9_009_assert(strpos($reads, 'safecontracts_contracts') < strpos($reads, 'safecontracts_contract_financial_currency_profiles'), 'create locks Contract before profile');
    esc_p9_009_assert(str_contains($reads, 'retention_rule_uuid = \'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa\'') && str_contains($reads, 'FOR UPDATE'), 'create proves generated retention identity unused');
    esc_p9_009_assert(str_contains($writes, "c.status IN ('draft', 'active') AND c.is_archived = 0"), 'guarded insert repeats mutable lifecycle');
    esc_p9_009_assert(str_contains($writes, "'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 1, 'Standard retention', '10.0000', 'configured', 42"), 'create persists configured revision 1 exactly');
}

foreach ([['completed', '0'], ['cancelled', '0'], ['draft', '1'], ['active', '1']] as [$status, $archived]) {
    $GLOBALS['sc_test_queries'] = [];
    $GLOBALS['sc_test_read_queries'] = [];
    $GLOBALS['sc_test_result_queue'] = [[['id' => '55', 'accountant_user_id' => '42', 'status' => $status, 'is_archived' => $archived]]];
    esc_p9_009_expect_throw(static fn (): int => $repository->createRule(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'Retention', PercentageRate::of('5'), 42, static function (array $contract): void {}), RuntimeException::class, "{$status}/archived Contract cannot mutate retention");
    esc_p9_009_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'lifecycle failure occurs before financial reads');
    esc_p9_009_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'ROLLBACK'), 'lifecycle failure rolls back');
}

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [['total' => '10']]];
esc_p9_009_expect_throw(static fn (): int => $repository->createRule(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'Retention', PercentageRate::of('5'), 42, static function (array $contract): void {}), RuntimeException::class, '10-rule Contract cannot create an 11th retention identity');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_009_row($ruleUuid, 1)]];
$retry = $repository->reviseRule(55, $ruleUuid, 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'Standard retention', PercentageRate::of('10'), 42, static function (array $contract): void {});
esc_p9_009_assert($retry === 1001, 'exact configured retention retry returns existing revision');
esc_p9_009_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'exact retention retry emits no duplicate insert');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_009_row($ruleUuid, 1)]];
$GLOBALS['wpdb']->insert_id = 0;
$repository->reviseRule(55, $ruleUuid, 'dddddddd-dddd-4ddd-8ddd-dddddddddddd', 'Reduced retention', PercentageRate::of('5'), 42, static function (array $contract): void {});
$reviseWrites = implode("\n", $GLOBALS['sc_test_queries']);
esc_p9_009_assert(str_contains($reviseWrites, "'11111111-1111-4111-8111-111111111111', 2, 'Reduced retention', '5.0000', 'configured', 42"), 'changed retention appends revision 2');
esc_p9_009_assert(substr_count($reviseWrites, 'INSERT INTO') === 1 && ! str_contains($reviseWrites, 'UPDATE '), 'retention revision is immutable append only');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_009_row($ruleUuid, 1)]];
$GLOBALS['wpdb']->insert_id = 0;
$repository->voidRule(55, $ruleUuid, 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee', 42, static function (array $contract): void {});
esc_p9_009_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), "'10.0000', 'voided', 42"), 'void preserves retention snapshot and appends terminal state');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_009_row($ruleUuid, 2, 'Standard retention', '10.0000', 'voided', 1002)]];
$voidRetry = $repository->voidRule(55, $ruleUuid, 'ffffffff-ffff-4fff-8fff-ffffffffffff', 42, static function (array $contract): void {});
esc_p9_009_assert($voidRetry === 1002, 'repeated retention void returns terminal revision');
esc_p9_009_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'repeated retention void emits no insert');

$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_009_row($ruleUuid, 2, 'Standard retention', '10.0000', 'voided', 1002)]];
esc_p9_009_expect_throw(static fn (): int => $repository->reviseRule(55, $ruleUuid, '12345678-1234-4234-8234-123456789abc', 'Reactivate', PercentageRate::of('10'), 42, static function (array $contract): void {}), RuntimeException::class, 'voided retention rule cannot reactivate');

esc_p9_009_assert(str_contains($serviceSource, 'authorize(Capabilities::ACCESS)'), 'retention reads require ACCESS');
esc_p9_009_assert(substr_count($serviceSource, 'authorize(Capabilities::EDIT_CONTRACTS)') >= 3, 'retention mutations require EDIT_CONTRACTS');
esc_p9_009_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'tenant role narrows WordPress grants');
esc_p9_009_assert(str_contains($serviceSource, 'Capabilities::VIEW_ALL') && str_contains($serviceSource, 'Capabilities::VIEW_ASSIGNED'), 'service preserves VIEW_ALL/own VIEW_ASSIGNED scope');
esc_p9_009_assert(str_contains($serviceSource, 'PercentageRate::of($ratePercent)'), 'service reuses exact PercentageRate');
esc_p9_009_assert(! str_contains($serviceSource, '$tenantId') && ! str_contains($serviceSource, 'mixed $currency'), 'service exposes no caller tenant/currency');
esc_p9_009_assert(str_contains($serviceSource, 'random_bytes(16)') && str_contains($serviceSource, 'wp_generate_uuid4'), 'retention identities are server-generated UUIDv4');
esc_p9_009_assert(str_contains($gateSource, 'enterprise_contract_financial_retention_rules_p9_009.php'), 'P9-009 regression is wired into global backend gate');

fwrite(STDOUT, "P9-009 Enterprise Contract retention rule revisions passed ({$assertions} assertions).\n");
