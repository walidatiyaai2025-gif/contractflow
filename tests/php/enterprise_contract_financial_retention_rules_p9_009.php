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

// Historical P9-009 schema boundary. Later additive migrations may advance latest.
esc_p9_009_assert(version_compare(Migrator::LATEST_VERSION, '1.52.0', '>='), 'current Enterprise schema is at or beyond the P9-009 1.52.0 boundary');
esc_p9_009_assert(str_contains($migratorSource, "'1.51.0' => Migration0052EnterpriseContractFinancialTaxRuleRevisions::class"), 'Migration0052 remains historically mapped to 1.51.0');
esc_p9_009_assert(str_contains($migratorSource, "'1.52.0' => Migration0053EnterpriseContractFinancialRetentionRuleRevisions::class"), 'Migration0053 remains historically mapped to 1.52.0');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0053EnterpriseContractFinancialRetentionRuleRevisions())->up($GLOBALS['wpdb']);
esc_p9_009_assert(count($GLOBALS['sc_test_dbdelta']) === 1, 'P9-009 emits one additive table definition');
$schema = (string) ($GLOBALS['sc_test_dbdelta'][0] ?? '');
foreach ([
    'safecontracts_contract_financial_retention_rule_revisions',
    'financial_currency_profile_id bigint(20) unsigned NOT NULL',
    'retention_rule_uuid char(36) NOT NULL',
    'revision_number bigint(20) unsigned NOT NULL',
    'label varchar(120) NOT NULL',
    'rate_percent decimal(7,4) NOT NULL',
    "retention_rule_state varchar(16) NOT NULL DEFAULT 'configured'",
    'UNIQUE KEY financial_retention_rule_revision_uuid (revision_uuid)',
    'UNIQUE KEY tenant_contract_retention_rule_revision (tenant_id, contract_id, retention_rule_uuid, revision_number)',
    'KEY tenant_contract_retention_rule_latest (tenant_id, contract_id, retention_rule_uuid, revision_number, id)',
] as $marker) {
    esc_p9_009_assert(str_contains($schema, $marker), 'P9-009 schema contains ' . $marker);
}
esc_p9_009_assert(! str_contains(strtoupper($migrationSource), 'DROP TABLE') && ! str_contains(strtoupper($migrationSource), 'ALTER TABLE'), 'P9-009 migration is additive/non-destructive');
esc_p9_009_assert(! str_contains($schema, 'updated_at') && ! str_contains($schema, 'updated_by'), 'retention history has no mutable update columns');

// Exact rate primitive remains P9-008 PercentageRate.
esc_p9_009_assert(PercentageRate::of('10')->value() === '10.0000', 'retention rate canonicalizes through PercentageRate');
esc_p9_009_assert(PercentageRate::of('7.5')->equals(PercentageRate::of('7.5000')), 'retention rate equality is exact');
esc_p9_009_expect_throw(static fn (): PercentageRate => PercentageRate::of(7.5), InvalidArgumentException::class, 'float retention rate fails closed');
esc_p9_009_expect_throw(static fn (): PercentageRate => PercentageRate::of('100.0001'), OverflowException::class, 'retention rate above 100 fails closed');
esc_p9_009_assert(! str_contains($policySource, 'class PercentageRate'), 'retention policy does not duplicate PercentageRate');

// Policy and architectural invariants.
esc_p9_009_assert(ContractFinancialRetentionRulePolicy::normalizeState('CONFIGURED') === 'configured', 'configured retention state canonicalizes');
esc_p9_009_assert(ContractFinancialRetentionRulePolicy::normalizeState('VOIDED') === 'voided', 'voided retention state canonicalizes');
esc_p9_009_expect_throw(static fn (): string => ContractFinancialRetentionRulePolicy::normalizeState('released'), InvalidArgumentException::class, 'release semantics remain outside P9-009');
esc_p9_009_assert(ContractFinancialRetentionRulePolicy::MAX_RULES === 10, 'retention identities remain bounded to 10');
esc_p9_009_assert(ContractFinancialRetentionRulePolicy::normalizeLabel(' Retention ') === 'Retention', 'retention label canonicalizes');
esc_p9_009_assert(str_contains($repositorySource, 'TenantContextStore::context()->requireTenantId()'), 'repository derives tenant from locked context');
esc_p9_009_assert(str_contains($repositorySource, 'ContractFinancialRetentionRulePolicy::MAX_RULES + 1'), 'current read retains 11th overflow sentinel');
esc_p9_009_assert(str_contains($repositorySource, 'NOT EXISTS ('), 'current read remains latest-only');
esc_p9_009_assert(str_contains($repositorySource, '$authorizeLockedContract($contract)'), 'authorization remains inside Contract lock');
esc_p9_009_assert(str_contains($repositorySource, "c.status IN ('draft', 'active') AND c.is_archived = 0"), 'mutations remain draft/active and unarchived only');
esc_p9_009_assert(str_contains($repositorySource, '$storedRate->equals($rate)'), 'exact revision retry remains idempotent');
esc_p9_009_assert(str_contains($repositorySource, 'cannot be revised or reactivated'), 'void remains terminal');
esc_p9_009_assert(! str_contains(strtoupper($repositorySource), 'UPDATE ') && ! str_contains(strtoupper($repositorySource), 'DELETE FROM'), 'retention evidence remains immutable append-only');
foreach (['ContractMoney', 'Money::', 'retained_amount', 'withheld_amount', 'release_amount', 'exchange_rate', 'currency_convert', 'round('] as $forbidden) {
    esc_p9_009_assert(! str_contains($repositorySource, $forbidden) && ! str_contains($serviceSource, $forbidden), 'P9-009 still avoids calculation/legacy coupling: ' . $forbidden);
}
esc_p9_009_assert(! str_contains($reconciliationRepositorySource, 'financial_retention_rule'), 'P9-006 still ignores retention configuration');
esc_p9_009_assert(! str_contains($reconciliationServiceSource, 'ContractFinancialRetentionRule'), 'P9-006 still has no retention dependency');
esc_p9_009_assert(! str_contains($routerSource, 'ContractFinancialRetentionRuleRevision'), 'P9-009 still exposes no REST route');

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(7);
$repository = new ContractFinancialRetentionRuleRevisionRepository();
$contractActive = [['id' => '55', 'accountant_user_id' => '42', 'status' => 'active', 'is_archived' => '0']];
$contractDraft = [['id' => '55', 'accountant_user_id' => '42', 'status' => 'draft', 'is_archived' => '0']];
$profile = [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']];
$ruleUuid = '11111111-1111-4111-8111-111111111111';

// Locked current read and canonicalization.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_009_row($ruleUuid, 2, '  Main retention ', '7.5', 'CONFIGURED')]];
$authorized = false;
$current = $repository->listCurrentForContract(55, static function (array $contract) use (&$authorized): void {
    esc_p9_009_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'retention authorization runs before financial reads');
    esc_p9_009_assert(str_contains((string) $GLOBALS['sc_test_read_queries'][0], 'FOR UPDATE'), 'retention authorization uses locked Contract row');
    $authorized = true;
});
esc_p9_009_assert($authorized, 'retention authorization callback executes');
esc_p9_009_assert(count($current) === 1 && $current[0]['label'] === 'Main retention', 'retention current row canonicalizes label');
esc_p9_009_assert($current[0]['rate_percent'] === '7.5000' && $current[0]['retention_rule_state'] === 'configured', 'retention current row canonicalizes rate/state');
esc_p9_009_assert(str_contains((string) end($GLOBALS['sc_test_read_queries']), 'LIMIT 11'), 'retention current query remains bounded');

// Historical evidence is readable after completion.
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'accountant_user_id' => '42', 'status' => 'completed', 'is_archived' => '0']],
    $profile,
    [esc_p9_009_row($ruleUuid, 2)],
];
esc_p9_009_assert(count($repository->listCurrentForContract(55, static function (array $contract): void {})) === 1, 'completed Contract retains retention evidence');

// 11th rule, duplicate latest, cross-profile and invalid percentage fail closed.
$overflow = [];
for ($i = 1; $i <= 11; $i++) {
    $overflow[] = esc_p9_009_row(sprintf('30000000-0000-4000-8000-%012x', $i), 1, 'Retention ' . $i, '5.0000', 'configured', 2000 + $i);
}
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, $overflow];
esc_p9_009_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), RuntimeException::class, '11 current retention rules fail closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_009_row($ruleUuid, 1, 'A', '5', 'configured', 2101), esc_p9_009_row($ruleUuid, 2, 'B', '6', 'configured', 2102)]];
esc_p9_009_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'duplicate latest retention identities fail closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_009_row($ruleUuid, 1, 'Retention', '5', 'configured', 2201, 99)]];
esc_p9_009_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'cross-profile retention evidence fails closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_009_row($ruleUuid, 1, 'Retention', '100.0001')]];
esc_p9_009_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'invalid persisted retention percentage fails closed');

// Create remains valid for draft and active; lifecycle violations fail before profile reads.
foreach ([$contractDraft, $contractActive] as $contractRows) {
    $GLOBALS['sc_test_queries'] = [];
    $GLOBALS['sc_test_read_queries'] = [];
    $GLOBALS['sc_test_result_queue'] = [$contractRows, $profile, [['total' => '0']], []];
    $GLOBALS['wpdb']->insert_id = 0;
    $id = $repository->createRule(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'Standard retention', PercentageRate::of('10'), 42, static function (array $contract): void {});
    esc_p9_009_assert($id === 1001, 'draft/active retention creation succeeds');
    esc_p9_009_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), "'10.0000', 'configured', 42"), 'retention creation persists canonical exact rate');
}
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [[['id' => '55', 'accountant_user_id' => '42', 'status' => 'completed', 'is_archived' => '0']]];
esc_p9_009_expect_throw(static fn (): int => $repository->createRule(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'Retention', PercentageRate::of('5'), 42, static function (array $contract): void {}), RuntimeException::class, 'completed Contract cannot mutate retention');
esc_p9_009_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'retention lifecycle failure occurs before profile read');

// Exact revise and void retries remain idempotent; void remains terminal.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_009_row($ruleUuid, 1)]];
$retry = $repository->reviseRule(55, $ruleUuid, 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'Standard retention', PercentageRate::of('10'), 42, static function (array $contract): void {});
esc_p9_009_assert($retry === 1001 && ! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'exact retention revision retry is idempotent');
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_009_row($ruleUuid, 1)]];
$GLOBALS['wpdb']->insert_id = 0;
$repository->voidRule(55, $ruleUuid, 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee', 42, static function (array $contract): void {});
esc_p9_009_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), "'10.0000', 'voided', 42"), 'retention void appends terminal snapshot');
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_009_row($ruleUuid, 2, 'Standard retention', '10', 'voided', 1002)]];
esc_p9_009_assert($repository->voidRule(55, $ruleUuid, 'ffffffff-ffff-4fff-8fff-ffffffffffff', 42, static function (array $contract): void {}) === 1002, 'repeated retention void returns terminal revision');
esc_p9_009_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'repeated retention void emits no insert');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_009_row($ruleUuid, 2, 'Standard retention', '10', 'voided', 1002)]];
esc_p9_009_expect_throw(static fn (): int => $repository->reviseRule(55, $ruleUuid, '12345678-1234-4234-8234-123456789abc', 'Reactivate', PercentageRate::of('10'), 42, static function (array $contract): void {}), RuntimeException::class, 'voided retention cannot reactivate');

// Service boundary remains unchanged.
esc_p9_009_assert(str_contains($serviceSource, 'authorize(Capabilities::ACCESS)'), 'retention reads require ACCESS');
esc_p9_009_assert(substr_count($serviceSource, 'authorize(Capabilities::EDIT_CONTRACTS)') >= 3, 'retention mutations require EDIT_CONTRACTS');
esc_p9_009_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'tenant role narrows retention capabilities');
esc_p9_009_assert(str_contains($serviceSource, 'PercentageRate::of($ratePercent)'), 'retention service still reuses PercentageRate');
esc_p9_009_assert(! str_contains($serviceSource, '$tenantId') && ! str_contains($serviceSource, 'mixed $currency'), 'retention service exposes no caller tenant/currency');
esc_p9_009_assert(str_contains($gateSource, 'enterprise_contract_financial_retention_rules_p9_009.php'), 'P9-009 remains wired into global backend gate');

fwrite(STDOUT, "P9-009 Enterprise Contract retention rule revisions passed ({$assertions} assertions).\n");
