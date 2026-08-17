<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0054EnterpriseContractFinancialPenaltyRuleRevisions;
use SafeContracts\Finance\ContractFinancialPenaltyRulePolicy;
use SafeContracts\Finance\ContractFinancialPenaltyRuleRevisionRepository;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;
function esc_p9_010_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @param class-string<Throwable> $expected */
function esc_p9_010_expect_throw(callable $callback, string $expected, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p9_010_assert($error instanceof $expected, $message . ' (received ' . $error::class . ')');
        return;
    }
    esc_p9_010_assert(false, $message . ' (no exception)');
}

/** @return array<string,mixed> */
function esc_p9_010_row(
    string $ruleUuid,
    int $revisionNumber,
    string $label = 'Delay penalty',
    string $mode = 'fixed_amount',
    string $value = '100.0000',
    string $currency = 'USD',
    string $state = 'configured',
    int $id = 1001,
    int $profileId = 31
): array {
    return [
        'id' => (string) $id,
        'revision_uuid' => sprintf('20000000-0000-4000-8000-%012x', $id),
        'contract_id' => '55',
        'financial_currency_profile_id' => (string) $profileId,
        'penalty_rule_uuid' => $ruleUuid,
        'revision_number' => (string) $revisionNumber,
        'label' => $label,
        'calculation_mode' => $mode,
        'configured_value' => $value,
        'currency_code' => $currency,
        'penalty_rule_state' => $state,
        'created_by' => '42',
        'created_at' => '2026-08-17 19:00:00',
    ];
}

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0054EnterpriseContractFinancialPenaltyRuleRevisions.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialPenaltyRulePolicy.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialPenaltyRuleRevisionRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialPenaltyRuleRevisionService.php');
$moneySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/Money.php');
$rateSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/PercentageRate.php');
$reconciliationRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialReconciliationRepository.php');
$reconciliationServiceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialReconciliationService.php');
$routerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

// Historical schema boundary and exact historical mappings.
esc_p9_010_assert(version_compare(Migrator::LATEST_VERSION, '1.53.0', '>='), 'P9-010 historical schema boundary remains at or beyond 1.53.0');
esc_p9_010_assert(str_contains($migratorSource, "'1.52.0' => Migration0053EnterpriseContractFinancialRetentionRuleRevisions::class"), 'Migration0053 remains historically mapped to 1.52.0');
esc_p9_010_assert(str_contains($migratorSource, "'1.53.0' => Migration0054EnterpriseContractFinancialPenaltyRuleRevisions::class"), 'Migration0054 remains historically mapped to 1.53.0');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0054EnterpriseContractFinancialPenaltyRuleRevisions())->up($GLOBALS['wpdb']);
esc_p9_010_assert(count($GLOBALS['sc_test_dbdelta']) === 1, 'P9-010 emits one additive table definition');
$schema = (string) ($GLOBALS['sc_test_dbdelta'][0] ?? '');
foreach ([
    'safecontracts_contract_financial_penalty_rule_revisions',
    'tenant_id bigint(20) unsigned NOT NULL',
    'revision_uuid char(36) NOT NULL',
    'contract_id bigint(20) unsigned NOT NULL',
    'financial_currency_profile_id bigint(20) unsigned NOT NULL',
    'penalty_rule_uuid char(36) NOT NULL',
    'revision_number bigint(20) unsigned NOT NULL',
    'label varchar(120) NOT NULL',
    'calculation_mode varchar(20) NOT NULL',
    'configured_value decimal(20,4) NOT NULL',
    'currency_code char(3) NOT NULL',
    "penalty_rule_state varchar(16) NOT NULL DEFAULT 'configured'",
    'created_by bigint(20) unsigned NOT NULL',
    'created_at datetime NOT NULL',
    'UNIQUE KEY financial_penalty_rule_revision_uuid (revision_uuid)',
    'UNIQUE KEY tenant_contract_penalty_rule_revision (tenant_id, contract_id, penalty_rule_uuid, revision_number)',
    'KEY tenant_contract_penalty_rule_latest (tenant_id, contract_id, penalty_rule_uuid, revision_number, id)',
] as $marker) {
    esc_p9_010_assert(str_contains($schema, $marker), 'P9-010 schema contains ' . $marker);
}
esc_p9_010_assert(! str_contains(strtoupper($migrationSource), 'DROP TABLE'), 'P9-010 migration is non-destructive');
esc_p9_010_assert(! str_contains(strtoupper($migrationSource), 'ALTER TABLE'), 'P9-010 migration does not rewrite existing tables');
esc_p9_010_assert(! str_contains($schema, 'updated_at') && ! str_contains($schema, 'updated_by'), 'penalty revisions contain no mutable update columns');

// Bounded configuration policy; no trigger/effect state is invented.
esc_p9_010_assert(ContractFinancialPenaltyRulePolicy::normalizeMode(' FIXED_AMOUNT ') === 'fixed_amount', 'fixed amount mode canonicalizes');
esc_p9_010_assert(ContractFinancialPenaltyRulePolicy::normalizeMode('PERCENTAGE') === 'percentage', 'percentage mode canonicalizes');
esc_p9_010_expect_throw(static fn (): string => ContractFinancialPenaltyRulePolicy::normalizeMode('per_day'), InvalidArgumentException::class, 'per-day trigger semantics are not invented');
esc_p9_010_assert(ContractFinancialPenaltyRulePolicy::normalizeState('CONFIGURED') === 'configured', 'configured penalty state canonicalizes');
esc_p9_010_assert(ContractFinancialPenaltyRulePolicy::normalizeState('voided') === 'voided', 'voided penalty state canonicalizes');
esc_p9_010_expect_throw(static fn (): string => ContractFinancialPenaltyRulePolicy::normalizeState('accrued'), InvalidArgumentException::class, 'accrual/effect state is not invented');
esc_p9_010_assert(ContractFinancialPenaltyRulePolicy::MAX_RULES === 20, 'penalty rules are bounded to 20 stable identities');
esc_p9_010_assert(ContractFinancialPenaltyRulePolicy::normalizeLabel(' Delay penalty ') === 'Delay penalty', 'penalty label canonicalizes');
esc_p9_010_expect_throw(static fn (): string => ContractFinancialPenaltyRulePolicy::normalizeLabel(''), InvalidArgumentException::class, 'blank penalty label fails closed');
esc_p9_010_assert(ContractFinancialPenaltyRulePolicy::normalizeUuid('AAAAAAAA-AAAA-4AAA-8AAA-AAAAAAAAAAAA') === 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'penalty UUID canonicalizes');

// Reuse P9-001 Money and P9-008 PercentageRate; no duplicate arithmetic primitive.
esc_p9_010_assert(str_contains($repositorySource, 'Money::of($value, $currency)'), 'fixed amount uses P9-001 Money after profile currency is known');
esc_p9_010_assert(str_contains($repositorySource, 'PercentageRate::of($value)'), 'percentage mode uses P9-008 PercentageRate');
esc_p9_010_assert(! str_contains($policySource, 'class Money') && ! str_contains($policySource, 'class PercentageRate'), 'penalty policy duplicates no financial primitive');
esc_p9_010_assert(! str_contains($repositorySource, 'float') && ! str_contains($repositorySource, 'round('), 'penalty repository defines no float/rounding path');
esc_p9_010_assert(str_contains($moneySource, 'SCALE = 4') && str_contains($rateSource, 'SCALE = 4'), 'penalty operands retain existing exact four-decimal primitives');

// Architecture and explicit non-goals.
esc_p9_010_assert(str_contains($repositorySource, 'TenantContextStore::context()->requireTenantId()'), 'repository derives tenant only from locked context');
esc_p9_010_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()'), 'repository requires core tenant enforcement');
esc_p9_010_assert(str_contains($repositorySource, 'COUNT(DISTINCT penalty_rule_uuid)'), 'create path bounds stable penalty identities');
esc_p9_010_assert(str_contains($repositorySource, 'ContractFinancialPenaltyRulePolicy::MAX_RULES + 1'), 'current read uses a 21st overflow sentinel');
esc_p9_010_assert(str_contains($repositorySource, 'NOT EXISTS ('), 'current read derives latest immutable revisions only');
esc_p9_010_assert(str_contains($repositorySource, '$authorizeLockedContract($contract)'), 'repository authorizes against exact locked Contract row');
esc_p9_010_assert(str_contains($repositorySource, "c.status IN ('draft', 'active') AND c.is_archived = 0"), 'guarded append revalidates mutable lifecycle');
esc_p9_010_assert(str_contains($repositorySource, 'p.contract_currency = %s'), 'guarded append revalidates exact P9-003 currency');
esc_p9_010_assert(str_contains($repositorySource, 'cannot be revised or reactivated'), 'voided penalty rule is terminal');
esc_p9_010_assert(! str_contains(strtoupper($repositorySource), 'UPDATE '), 'penalty evidence has no UPDATE path');
esc_p9_010_assert(! str_contains(strtoupper($repositorySource), 'DELETE FROM'), 'penalty evidence has no DELETE path');
foreach (['safecontracts_contract_adjustments', 'exchange_rate', 'currency_convert', 'accrual', 'grace_period', 'trigger_date', 'per_day', 'milestone_id', 'deliverable_id', 'penalty_amount'] as $forbidden) {
    esc_p9_010_assert(! str_contains($repositorySource, $forbidden) && ! str_contains($serviceSource, $forbidden), 'P9-010 avoids trigger/legacy coupling: ' . $forbidden);
}

// P9-006 remains authoritative; configured penalty rules have zero monetary effect.
esc_p9_010_assert(! str_contains($reconciliationRepositorySource, 'financial_penalty_rule'), 'P9-006 reconciliation does not query penalty evidence');
esc_p9_010_assert(! str_contains($reconciliationServiceSource, 'ContractFinancialPenaltyRule'), 'P9-006 service has no implicit penalty effect');
esc_p9_010_assert(! str_contains($routerSource, 'ContractFinancialPenaltyRuleRevision'), 'P9-010 exposes no REST route');

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(7);
$repository = new ContractFinancialPenaltyRuleRevisionRepository();
$contractActive = [['id' => '55', 'accountant_user_id' => '42', 'status' => 'active', 'is_archived' => '0']];
$contractDraft = [['id' => '55', 'accountant_user_id' => '42', 'status' => 'draft', 'is_archived' => '0']];
$profile = [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']];
$ruleUuid = '11111111-1111-4111-8111-111111111111';

// Current read normalizes both operand modes after Contract-first authorization.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [
        esc_p9_010_row($ruleUuid, 2, ' Fixed ', 'fixed_amount', '125.5', 'usd', 'CONFIGURED', 1001),
        esc_p9_010_row('22222222-2222-4222-8222-222222222222', 1, ' Rate ', 'PERCENTAGE', '7.5', 'USD', 'configured', 1002),
    ],
];
$authorized = false;
$current = $repository->listCurrentForContract(55, static function (array $contract) use (&$authorized): void {
    esc_p9_010_assert((int) ($contract['id'] ?? 0) === 55, 'authorization receives locked Contract identity');
    esc_p9_010_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'authorization runs before profile/penalty reads');
    esc_p9_010_assert(str_contains((string) $GLOBALS['sc_test_read_queries'][0], 'FOR UPDATE'), 'authorization is protected by Contract FOR UPDATE');
    $authorized = true;
});
esc_p9_010_assert($authorized, 'locked-row authorization callback executes');
esc_p9_010_assert(count($current) === 2, 'current penalty list returns normalized latest rules');
esc_p9_010_assert($current[0]['label'] === 'Fixed' && $current[0]['configured_value'] === '125.5000' && $current[0]['currency_code'] === 'USD', 'fixed penalty Money canonicalizes');
esc_p9_010_assert($current[1]['label'] === 'Rate' && $current[1]['configured_value'] === '7.5000' && $current[1]['calculation_mode'] === 'percentage', 'percentage penalty canonicalizes');
esc_p9_010_assert(implode("\n", $GLOBALS['sc_test_queries']) === "START TRANSACTION\nCOMMIT", 'current penalty read is one transaction');
$listSql = (string) end($GLOBALS['sc_test_read_queries']);
esc_p9_010_assert(str_contains($listSql, 'r.tenant_id = 7') && str_contains($listSql, 'r.contract_id = 55') && str_contains($listSql, 'LIMIT 21'), 'current penalty read is tenant/Contract scoped and bounded');

// Historical evidence remains readable after lifecycle completion/cancellation.
foreach (['completed', 'cancelled'] as $status) {
    $GLOBALS['sc_test_result_queue'] = [
        [['id' => '55', 'accountant_user_id' => '42', 'status' => $status, 'is_archived' => '0']],
        $profile,
        [esc_p9_010_row($ruleUuid, 2)],
    ];
    $historical = $repository->listCurrentForContract(55, static function (array $contract): void {});
    esc_p9_010_assert(count($historical) === 1, "{$status} Contract retains readable penalty evidence");
}

// 21st current identity is an overflow sentinel.
$overflow = [];
for ($i = 1; $i <= ContractFinancialPenaltyRulePolicy::MAX_RULES + 1; $i++) {
    $overflow[] = esc_p9_010_row(sprintf('30000000-0000-4000-8000-%012x', $i), 1, 'Penalty ' . $i, 'fixed_amount', '1.0000', 'USD', 'configured', 2000 + $i);
}
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, $overflow];
esc_p9_010_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), RuntimeException::class, '21 current penalty rules fail closed');

// Duplicate, cross-profile, cross-currency and malformed persisted operands fail closed.
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [
        esc_p9_010_row($ruleUuid, 1, 'Penalty', 'fixed_amount', '1.0000', 'USD', 'configured', 2101),
        esc_p9_010_row($ruleUuid, 2, 'Penalty changed', 'fixed_amount', '2.0000', 'USD', 'configured', 2102),
    ],
];
esc_p9_010_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'duplicate latest penalty identities fail closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_010_row($ruleUuid, 1, 'Penalty', 'fixed_amount', '1.0000', 'USD', 'configured', 2201, 99)]];
esc_p9_010_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'cross-profile penalty evidence fails closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_010_row($ruleUuid, 1, 'Penalty', 'fixed_amount', '1.0000', 'EUR')]];
esc_p9_010_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'cross-currency penalty evidence fails closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_010_row($ruleUuid, 1, 'Penalty', 'percentage', '100.0001', 'USD')]];
esc_p9_010_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'persisted percentage above 100 fails closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_010_row($ruleUuid, 1, 'Penalty', 'fixed_amount', '-1.0000', 'USD')]];
esc_p9_010_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'persisted negative fixed penalty fails closed');

// Create supports draft/active and both exact operand modes.
foreach ([
    [$contractDraft, 'draft', 'fixed_amount', '125.5', '125.5000'],
    [$contractActive, 'active', 'percentage', '7.25', '7.2500'],
] as [$contractRows, $status, $mode, $input, $canonical]) {
    $GLOBALS['sc_test_queries'] = [];
    $GLOBALS['sc_test_read_queries'] = [];
    $GLOBALS['sc_test_result_queue'] = [$contractRows, $profile, [['total' => '0']], []];
    $GLOBALS['wpdb']->insert_id = 0;
    $createAuthorized = false;
    $created = $repository->createRule(
        55,
        'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        'Configured penalty',
        $mode,
        $input,
        42,
        static function (array $contract) use (&$createAuthorized): void {
            esc_p9_010_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'create authorization occurs immediately after Contract lock');
            $createAuthorized = true;
        }
    );
    esc_p9_010_assert($createAuthorized && $created === 1001, "{$status} Contract {$mode} penalty creation succeeds");
    $reads = implode("\n", array_map('strval', $GLOBALS['sc_test_read_queries']));
    $writes = implode("\n", array_map('strval', $GLOBALS['sc_test_queries']));
    esc_p9_010_assert(strpos($reads, 'safecontracts_contracts') < strpos($reads, 'safecontracts_contract_financial_currency_profiles'), 'create locks Contract before profile');
    esc_p9_010_assert(str_contains($reads, 'penalty_rule_uuid = \'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa\'') && str_contains($reads, 'FOR UPDATE'), 'create proves generated penalty identity unused');
    esc_p9_010_assert(str_contains($writes, "c.status IN ('draft', 'active') AND c.is_archived = 0"), 'guarded insert repeats mutable lifecycle');
    esc_p9_010_assert(str_contains($writes, "'Configured penalty', '{$mode}', '{$canonical}', p.contract_currency, 'configured', 42"), 'create persists canonical operand and P9-003 currency snapshot');
}

// Caller operand validation occurs after authoritative profile lock.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile];
esc_p9_010_expect_throw(static fn (): int => $repository->createRule(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'Negative', 'fixed_amount', '-0.0001', 42, static function (array $contract): void {}), InvalidArgumentException::class, 'negative fixed penalty fails closed');
esc_p9_010_assert(count($GLOBALS['sc_test_read_queries']) === 2, 'fixed operand validates only after Contract and profile reads');
esc_p9_010_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'ROLLBACK'), 'invalid fixed operand rolls back');
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile];
esc_p9_010_expect_throw(static fn (): int => $repository->createRule(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'Float rate', 'percentage', 5.5, 42, static function (array $contract): void {}), InvalidArgumentException::class, 'float percentage penalty fails closed');

// Completed/cancelled/archived Contracts cannot mutate penalty configuration.
foreach ([['completed', '0'], ['cancelled', '0'], ['draft', '1'], ['active', '1']] as [$status, $archived]) {
    $GLOBALS['sc_test_queries'] = [];
    $GLOBALS['sc_test_read_queries'] = [];
    $GLOBALS['sc_test_result_queue'] = [[['id' => '55', 'accountant_user_id' => '42', 'status' => $status, 'is_archived' => $archived]]];
    esc_p9_010_expect_throw(static fn (): int => $repository->createRule(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'Penalty', 'fixed_amount', '1', 42, static function (array $contract): void {}), RuntimeException::class, "{$status}/archived Contract cannot mutate penalty");
    esc_p9_010_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'lifecycle failure occurs before profile/operand reads');
    esc_p9_010_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'ROLLBACK'), 'lifecycle failure rolls back');
}

// Rule count is enforced under Contract lock.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [['total' => '20']]];
esc_p9_010_expect_throw(static fn (): int => $repository->createRule(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'Penalty', 'fixed_amount', '1', 42, static function (array $contract): void {}), RuntimeException::class, '20-rule Contract cannot create a 21st penalty identity');

// Exact retry is idempotent; changed mode/value appends revision 2.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_010_row($ruleUuid, 1, 'Delay penalty', 'fixed_amount', '100.0000')]];
$retry = $repository->reviseRule(55, $ruleUuid, 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'Delay penalty', 'fixed_amount', '100', 42, static function (array $contract): void {});
esc_p9_010_assert($retry === 1001, 'exact configured penalty retry returns current revision');
esc_p9_010_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'exact penalty retry emits no duplicate insert');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_010_row($ruleUuid, 1, 'Delay penalty', 'fixed_amount', '100.0000')]];
$GLOBALS['wpdb']->insert_id = 0;
$repository->reviseRule(55, $ruleUuid, 'dddddddd-dddd-4ddd-8ddd-dddddddddddd', 'Rate penalty', 'percentage', '5', 42, static function (array $contract): void {});
$reviseWrites = implode("\n", $GLOBALS['sc_test_queries']);
esc_p9_010_assert(str_contains($reviseWrites, "'11111111-1111-4111-8111-111111111111', 2, 'Rate penalty', 'percentage', '5.0000', p.contract_currency, 'configured', 42"), 'changed penalty appends canonical revision 2');
esc_p9_010_assert(substr_count($reviseWrites, 'INSERT INTO') === 1 && ! str_contains($reviseWrites, 'UPDATE '), 'penalty revision is immutable append only');

// Void preserves canonical operand, is terminal and idempotent.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_010_row($ruleUuid, 1, 'Rate penalty', 'percentage', '5.0000')]];
$GLOBALS['wpdb']->insert_id = 0;
$repository->voidRule(55, $ruleUuid, 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee', 42, static function (array $contract): void {});
esc_p9_010_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), "'Rate penalty', 'percentage', '5.0000', p.contract_currency, 'voided', 42"), 'void preserves penalty configuration and appends terminal state');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_010_row($ruleUuid, 2, 'Rate penalty', 'percentage', '5.0000', 'USD', 'voided', 1002)]];
$voidRetry = $repository->voidRule(55, $ruleUuid, 'ffffffff-ffff-4fff-8fff-ffffffffffff', 42, static function (array $contract): void {});
esc_p9_010_assert($voidRetry === 1002, 'repeated penalty void returns terminal revision');
esc_p9_010_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'repeated penalty void emits no insert');

$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_010_row($ruleUuid, 2, 'Rate penalty', 'percentage', '5.0000', 'USD', 'voided', 1002)]];
esc_p9_010_expect_throw(static fn (): int => $repository->reviseRule(55, $ruleUuid, '12345678-1234-4234-8234-123456789abc', 'Reactivate', 'percentage', '5', 42, static function (array $contract): void {}), RuntimeException::class, 'voided penalty rule cannot reactivate');

// Service owns capabilities, tenant-role narrowing and locked scope; caller supplies no currency.
esc_p9_010_assert(str_contains($serviceSource, 'authorize(Capabilities::ACCESS)'), 'penalty reads require ACCESS');
esc_p9_010_assert(substr_count($serviceSource, 'authorize(Capabilities::EDIT_CONTRACTS)') >= 3, 'penalty mutations require EDIT_CONTRACTS');
esc_p9_010_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'tenant role narrows WordPress grants');
esc_p9_010_assert(str_contains($serviceSource, 'Capabilities::VIEW_ALL') && str_contains($serviceSource, 'Capabilities::VIEW_ASSIGNED'), 'service preserves VIEW_ALL/own VIEW_ASSIGNED scope');
esc_p9_010_assert(! str_contains($serviceSource, 'mixed $currency') && ! str_contains($serviceSource, '$tenantId'), 'service exposes no caller currency/tenant identity');
esc_p9_010_assert(str_contains($serviceSource, 'random_bytes(16)') && str_contains($serviceSource, 'wp_generate_uuid4'), 'penalty identities are server-generated UUIDv4');
esc_p9_010_assert(str_contains($gateSource, 'enterprise_contract_financial_penalty_rules_p9_010.php'), 'P9-010 regression is wired into global backend gate');

fwrite(STDOUT, "P9-010 Enterprise Contract penalty rule revisions passed ({$assertions} assertions).\n");
