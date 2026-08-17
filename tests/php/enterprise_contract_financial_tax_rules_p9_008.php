<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0052EnterpriseContractFinancialTaxRuleRevisions;
use SafeContracts\Finance\ContractFinancialTaxRulePolicy;
use SafeContracts\Finance\ContractFinancialTaxRuleRevisionRepository;
use SafeContracts\Finance\PercentageRate;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;
function esc_p9_008_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @param class-string<Throwable> $expected */
function esc_p9_008_expect_throw(callable $callback, string $expected, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p9_008_assert($error instanceof $expected, $message . ' (received ' . $error::class . ')');
        return;
    }
    esc_p9_008_assert(false, $message . ' (no exception)');
}

/** @return array<string,mixed> */
function esc_p9_008_row(
    string $ruleUuid,
    int $revisionNumber,
    string $kind = 'vat',
    string $label = 'VAT',
    string $rate = '5.0000',
    string $state = 'configured',
    int $id = 1001,
    int $profileId = 31
): array {
    return [
        'id' => (string) $id,
        'revision_uuid' => sprintf('20000000-0000-4000-8000-%012x', $id),
        'contract_id' => '55',
        'financial_currency_profile_id' => (string) $profileId,
        'tax_rule_uuid' => $ruleUuid,
        'revision_number' => (string) $revisionNumber,
        'tax_kind' => $kind,
        'label' => $label,
        'rate_percent' => $rate,
        'tax_rule_state' => $state,
        'created_by' => '42',
        'created_at' => '2026-08-17 18:00:00',
    ];
}

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0052EnterpriseContractFinancialTaxRuleRevisions.php');
$rateSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/PercentageRate.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialTaxRulePolicy.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialTaxRuleRevisionRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialTaxRuleRevisionService.php');
$reconciliationRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialReconciliationRepository.php');
$reconciliationServiceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialReconciliationService.php');
$routerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

// Additive schema boundary.
esc_p9_008_assert(Migrator::LATEST_VERSION === '1.51.0', 'P9-008 advances Enterprise schema to 1.51.0');
esc_p9_008_assert(str_contains($migratorSource, "'1.50.0' => Migration0051EnterpriseContractFinancialVariationRevisions::class"), 'Migration0051 remains historically mapped to 1.50.0');
esc_p9_008_assert(str_contains($migratorSource, "'1.51.0' => Migration0052EnterpriseContractFinancialTaxRuleRevisions::class"), 'Migration0052 is mapped to 1.51.0');
esc_p9_008_assert(! str_contains($migratorSource, 'Migration0053'), 'P9-008 does not invent a later migration');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0052EnterpriseContractFinancialTaxRuleRevisions())->up($GLOBALS['wpdb']);
esc_p9_008_assert(count($GLOBALS['sc_test_dbdelta']) === 1, 'P9-008 emits one additive table definition');
$schema = (string) ($GLOBALS['sc_test_dbdelta'][0] ?? '');
foreach ([
    'safecontracts_contract_financial_tax_rule_revisions',
    'tenant_id bigint(20) unsigned NOT NULL',
    'revision_uuid char(36) NOT NULL',
    'contract_id bigint(20) unsigned NOT NULL',
    'financial_currency_profile_id bigint(20) unsigned NOT NULL',
    'tax_rule_uuid char(36) NOT NULL',
    'revision_number bigint(20) unsigned NOT NULL',
    'tax_kind varchar(16) NOT NULL',
    'label varchar(120) NOT NULL',
    'rate_percent decimal(7,4) NOT NULL',
    "tax_rule_state varchar(16) NOT NULL DEFAULT 'configured'",
    'created_by bigint(20) unsigned NOT NULL',
    'created_at datetime NOT NULL',
    'UNIQUE KEY financial_tax_rule_revision_uuid (revision_uuid)',
    'UNIQUE KEY tenant_contract_tax_rule_revision (tenant_id, contract_id, tax_rule_uuid, revision_number)',
    'KEY tenant_contract_tax_rule_latest (tenant_id, contract_id, tax_rule_uuid, revision_number, id)',
] as $marker) {
    esc_p9_008_assert(str_contains($schema, $marker), 'P9-008 schema contains ' . $marker);
}
esc_p9_008_assert(! str_contains(strtoupper($migrationSource), 'DROP TABLE'), 'P9-008 migration is non-destructive');
esc_p9_008_assert(! str_contains(strtoupper($migrationSource), 'ALTER TABLE'), 'P9-008 migration does not rewrite existing tables');
esc_p9_008_assert(! str_contains($schema, 'updated_at') && ! str_contains($schema, 'updated_by'), 'tax rule revisions contain no mutable update columns');

// Exact percentage primitive: no floats, no rounding, fixed scale and hard 0..100 bound.
esc_p9_008_assert(PercentageRate::of('5')->value() === '5.0000', 'integer-like rate canonicalizes to four decimals');
esc_p9_008_assert(PercentageRate::of('7.25')->value() === '7.2500', 'fractional rate canonicalizes exactly');
esc_p9_008_assert(PercentageRate::of('000100.0000')->value() === '100.0000', '100 percent boundary canonicalizes');
esc_p9_008_assert(PercentageRate::of('0.0000')->value() === '0.0000', 'zero rate is valid configuration evidence');
esc_p9_008_assert(PercentageRate::of('7.25')->equals(PercentageRate::of('7.2500')), 'canonical rates compare exactly');
esc_p9_008_expect_throw(static fn (): PercentageRate => PercentageRate::of('100.0001'), OverflowException::class, 'rate above 100 percent fails closed');
esc_p9_008_expect_throw(static fn (): PercentageRate => PercentageRate::of('101'), OverflowException::class, 'whole rate above 100 percent fails closed');
esc_p9_008_expect_throw(static fn (): PercentageRate => PercentageRate::of('1.00001'), InvalidArgumentException::class, 'rate beyond four-decimal scale fails closed');
esc_p9_008_expect_throw(static fn (): PercentageRate => PercentageRate::of('-1'), InvalidArgumentException::class, 'negative rate fails closed');
esc_p9_008_expect_throw(static fn (): PercentageRate => PercentageRate::of(5.5), InvalidArgumentException::class, 'float rate input is rejected');
esc_p9_008_assert(! str_contains($rateSource, 'round(') && ! str_contains($rateSource, 'float'), 'PercentageRate defines no floating-point rounding path');

// Bounded allowlisted tax-rule policy.
esc_p9_008_assert(ContractFinancialTaxRulePolicy::normalizeKind(' VAT ') === 'vat', 'VAT kind canonicalizes');
esc_p9_008_assert(ContractFinancialTaxRulePolicy::normalizeKind('TAX') === 'tax', 'generic tax kind canonicalizes');
esc_p9_008_expect_throw(static fn (): string => ContractFinancialTaxRulePolicy::normalizeKind('withholding'), InvalidArgumentException::class, 'withholding semantics are not invented in P9-008');
esc_p9_008_assert(ContractFinancialTaxRulePolicy::normalizeState('CONFIGURED') === 'configured', 'configured state canonicalizes');
esc_p9_008_assert(ContractFinancialTaxRulePolicy::normalizeState('voided') === 'voided', 'voided state canonicalizes');
esc_p9_008_expect_throw(static fn (): string => ContractFinancialTaxRulePolicy::normalizeState('effective'), InvalidArgumentException::class, 'effective/posting state is not invented in P9-008');
esc_p9_008_assert(ContractFinancialTaxRulePolicy::MAX_RULES === 20, 'tax rules are bounded to 20 stable identities per Contract');
esc_p9_008_assert(ContractFinancialTaxRulePolicy::normalizeLabel(' VAT 5% ') === 'VAT 5%', 'tax rule label canonicalizes');
esc_p9_008_expect_throw(static fn (): string => ContractFinancialTaxRulePolicy::normalizeLabel(''), InvalidArgumentException::class, 'blank tax label fails closed');
esc_p9_008_assert(ContractFinancialTaxRulePolicy::normalizeUuid('AAAAAAAA-AAAA-4AAA-8AAA-AAAAAAAAAAAA') === 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'tax rule UUIDv4 canonicalizes');

// Architecture: tenant context, locked authorization, bounded latest-only immutable evidence.
esc_p9_008_assert(str_contains($repositorySource, 'TenantContextStore::context()->requireTenantId()'), 'repository derives tenant only from locked context');
esc_p9_008_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()'), 'repository requires core tenant enforcement');
esc_p9_008_assert(str_contains($repositorySource, 'COUNT(DISTINCT tax_rule_uuid)'), 'create path bounds stable tax-rule identities');
esc_p9_008_assert(str_contains($repositorySource, 'ContractFinancialTaxRulePolicy::MAX_RULES + 1'), 'current read uses a 21st overflow sentinel');
esc_p9_008_assert(str_contains($repositorySource, 'NOT EXISTS ('), 'current read derives latest immutable revisions only');
esc_p9_008_assert(str_contains($repositorySource, '$authorizeLockedContract($contract)'), 'repository authorizes against the exact locked Contract row');
esc_p9_008_assert(str_contains($repositorySource, "c.status IN ('draft', 'active') AND c.is_archived = 0"), 'guarded append revalidates draft/active lifecycle');
esc_p9_008_assert(str_contains($repositorySource, 'AND p.id = %d'), 'guarded append revalidates exact P9-003 profile identity');
esc_p9_008_assert(str_contains($repositorySource, '$storedRate->equals($rate)'), 'exact configured revision retry is idempotent');
esc_p9_008_assert(str_contains($repositorySource, 'cannot be revised or reactivated'), 'voided tax rule is terminal');
esc_p9_008_assert(! str_contains(strtoupper($repositorySource), 'UPDATE '), 'tax rule evidence has no UPDATE path');
esc_p9_008_assert(! str_contains(strtoupper($repositorySource), 'DELETE FROM'), 'tax rule evidence has no DELETE path');
foreach (['ContractMoney', 'safecontracts_contract_adjustments', 'exchange_rate', 'currency_convert', 'round(', 'tax_amount'] as $forbidden) {
    esc_p9_008_assert(! str_contains($repositorySource, $forbidden) && ! str_contains($serviceSource, $forbidden), 'P9-008 avoids legacy/calculation coupling: ' . $forbidden);
}

// P9-006 remains monetary authority; P9-008 does not calculate or post tax.
esc_p9_008_assert(! str_contains($reconciliationRepositorySource, 'financial_tax_rule'), 'P9-006 reconciliation does not query tax-rule evidence');
esc_p9_008_assert(! str_contains($reconciliationServiceSource, 'ContractFinancialTaxRule'), 'P9-006 service has no implicit tax effect');
esc_p9_008_assert(! str_contains($routerSource, 'ContractFinancialTaxRuleRevision'), 'P9-008 exposes no REST route');

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(7);
$repository = new ContractFinancialTaxRuleRevisionRepository();
$contractActive = [['id' => '55', 'accountant_user_id' => '42', 'status' => 'active', 'is_archived' => '0']];
$contractDraft = [['id' => '55', 'accountant_user_id' => '42', 'status' => 'draft', 'is_archived' => '0']];
$profile = [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']];
$ruleUuid = '11111111-1111-4111-8111-111111111111';

// Current read locks and authorizes Contract before profile/tax-rule state.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_008_row($ruleUuid, 2, ' VAT ', '  VAT 5.5%  ', '5.5', 'CONFIGURED')]];
$authorized = false;
$current = $repository->listCurrentForContract(55, static function (array $contract) use (&$authorized): void {
    esc_p9_008_assert((int) ($contract['id'] ?? 0) === 55, 'authorization receives locked Contract identity');
    esc_p9_008_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'authorization runs before profile/tax-rule reads');
    esc_p9_008_assert(str_contains((string) $GLOBALS['sc_test_read_queries'][0], 'FOR UPDATE'), 'authorization boundary is protected by Contract FOR UPDATE');
    $authorized = true;
});
esc_p9_008_assert($authorized, 'locked-row authorization callback executes');
esc_p9_008_assert(count($current) === 1 && $current[0]['tax_kind'] === 'vat', 'current tax rule returns normalized kind');
esc_p9_008_assert($current[0]['label'] === 'VAT 5.5%' && $current[0]['rate_percent'] === '5.5000', 'current tax rule returns canonical label/rate');
esc_p9_008_assert($current[0]['tax_rule_state'] === 'configured', 'current tax rule state canonicalizes');
esc_p9_008_assert(implode("\n", $GLOBALS['sc_test_queries']) === "START TRANSACTION\nCOMMIT", 'current read is one coherent transaction');
$listSql = (string) end($GLOBALS['sc_test_read_queries']);
esc_p9_008_assert(str_contains($listSql, 'r.tenant_id = 7') && str_contains($listSql, 'r.contract_id = 55') && str_contains($listSql, 'LIMIT 21'), 'current read is tenant/Contract scoped and bounded');

// Evidence remains readable after lifecycle completion/cancellation.
foreach (['completed', 'cancelled'] as $status) {
    $GLOBALS['sc_test_result_queue'] = [
        [['id' => '55', 'accountant_user_id' => '42', 'status' => $status, 'is_archived' => '0']],
        $profile,
        [esc_p9_008_row($ruleUuid, 2)],
    ];
    $historical = $repository->listCurrentForContract(55, static function (array $contract): void {});
    esc_p9_008_assert(count($historical) === 1, "{$status} Contract retains readable immutable tax-rule evidence");
}

// 21st current identity is overflow, not silent truncation.
$overflow = [];
for ($i = 1; $i <= ContractFinancialTaxRulePolicy::MAX_RULES + 1; $i++) {
    $overflow[] = esc_p9_008_row(sprintf('30000000-0000-4000-8000-%012x', $i), 1, 'vat', 'Rule ' . $i, '5.0000', 'configured', 2000 + $i);
}
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, $overflow];
esc_p9_008_expect_throw(
    static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}),
    RuntimeException::class,
    '21 current tax rules fail closed'
);

// Duplicate latest identities fail closed even under corrupted storage.
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [
        esc_p9_008_row($ruleUuid, 1, 'vat', 'VAT', '5.0000', 'configured', 2101),
        esc_p9_008_row($ruleUuid, 2, 'vat', 'VAT changed', '6.0000', 'configured', 2102),
    ],
];
esc_p9_008_expect_throw(
    static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}),
    UnexpectedValueException::class,
    'duplicate latest tax-rule identities fail closed'
);

// Cross-profile and malformed persisted rates fail closed.
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_008_row($ruleUuid, 1, 'vat', 'VAT', '5.0000', 'configured', 2201, 99)]];
esc_p9_008_expect_throw(
    static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}),
    UnexpectedValueException::class,
    'cross-profile tax-rule evidence fails closed'
);
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_008_row($ruleUuid, 1, 'vat', 'VAT', '100.0001')]];
esc_p9_008_expect_throw(
    static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}),
    UnexpectedValueException::class,
    'out-of-range persisted tax rate fails closed'
);

// Creation succeeds in both draft and active lifecycle states with Contract-first authorization.
foreach ([[$contractDraft, 'draft'], [$contractActive, 'active']] as [$contractRows, $status]) {
    $GLOBALS['sc_test_queries'] = [];
    $GLOBALS['sc_test_read_queries'] = [];
    $GLOBALS['sc_test_result_queue'] = [$contractRows, $profile, [['total' => '0']], []];
    $GLOBALS['wpdb']->insert_id = 0;
    $createAuthorized = false;
    $created = $repository->createRule(
        55,
        'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        'vat',
        'VAT 5%',
        PercentageRate::of('5'),
        42,
        static function (array $contract) use (&$createAuthorized): void {
            esc_p9_008_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'create authorization occurs immediately after Contract lock');
            $createAuthorized = true;
        }
    );
    esc_p9_008_assert($createAuthorized && $created === 1001, "{$status} Contract tax-rule creation succeeds after locked authorization");
    $createReads = implode("\n", array_map('strval', $GLOBALS['sc_test_read_queries']));
    $createWrites = implode("\n", array_map('strval', $GLOBALS['sc_test_queries']));
    esc_p9_008_assert(strpos($createReads, 'safecontracts_contracts') < strpos($createReads, 'safecontracts_contract_financial_currency_profiles'), 'create locks Contract before profile');
    esc_p9_008_assert(str_contains($createReads, 'tax_rule_uuid = \'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa\'') && str_contains($createReads, 'FOR UPDATE'), 'create proves generated tax-rule identity unused under lock');
    esc_p9_008_assert(str_contains($createWrites, "c.status IN ('draft', 'active') AND c.is_archived = 0"), 'guarded insert repeats mutable lifecycle predicate');
    esc_p9_008_assert(str_contains($createWrites, "'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 1, 'vat', 'VAT 5%', '5.0000', 'configured', 42"), 'create persists revision 1 configured tax rule exactly');
}

// Completed/cancelled/archived Contracts cannot mutate tax rules.
foreach ([
    ['completed', '0'],
    ['cancelled', '0'],
    ['draft', '1'],
    ['active', '1'],
] as [$status, $archived]) {
    $GLOBALS['sc_test_queries'] = [];
    $GLOBALS['sc_test_read_queries'] = [];
    $GLOBALS['sc_test_result_queue'] = [[['id' => '55', 'accountant_user_id' => '42', 'status' => $status, 'is_archived' => $archived]]];
    esc_p9_008_expect_throw(
        static fn (): int => $repository->createRule(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'vat', 'VAT', PercentageRate::of('5'), 42, static function (array $contract): void {}),
        RuntimeException::class,
        "{$status}/archived lifecycle cannot mutate a tax rule"
    );
    esc_p9_008_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'lifecycle mutation failure occurs before financial reads');
    esc_p9_008_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'ROLLBACK'), 'lifecycle mutation failure rolls back');
}

// 20-rule create limit is enforced under Contract lock.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [['total' => '20']]];
esc_p9_008_expect_throw(
    static fn (): int => $repository->createRule(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'vat', 'VAT', PercentageRate::of('5'), 42, static function (array $contract): void {}),
    RuntimeException::class,
    '20-rule Contract cannot create a 21st stable tax-rule identity'
);
esc_p9_008_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'ROLLBACK'), 'tax-rule limit failure rolls back');

// Exact configured revision retry is idempotent; changed values append revision 2.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_008_row($ruleUuid, 1, 'vat', 'VAT', '5.0000')]];
$retry = $repository->reviseRule(55, $ruleUuid, 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'vat', 'VAT', PercentageRate::of('5'), 42, static function (array $contract): void {});
esc_p9_008_assert($retry === 1001, 'exact configured tax-rule retry returns existing revision');
esc_p9_008_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'exact configured retry writes no duplicate revision');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_008_row($ruleUuid, 1, 'vat', 'VAT', '5.0000')]];
$GLOBALS['wpdb']->insert_id = 0;
$repository->reviseRule(55, $ruleUuid, 'dddddddd-dddd-4ddd-8ddd-dddddddddddd', 'tax', 'Local Tax', PercentageRate::of('7.25'), 42, static function (array $contract): void {});
$reviseWrites = implode("\n", $GLOBALS['sc_test_queries']);
esc_p9_008_assert(str_contains($reviseWrites, "'11111111-1111-4111-8111-111111111111', 2, 'tax', 'Local Tax', '7.2500', 'configured', 42"), 'changed tax rule appends revision 2 exactly');
esc_p9_008_assert(substr_count($reviseWrites, 'INSERT INTO') === 1 && ! str_contains($reviseWrites, 'UPDATE '), 'tax-rule revision is immutable append only');

// Void appends terminal snapshot; repeated void is idempotent; voided cannot reactivate.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_008_row($ruleUuid, 1, 'vat', 'VAT', '5.0000')]];
$GLOBALS['wpdb']->insert_id = 0;
$repository->voidRule(55, $ruleUuid, 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee', 42, static function (array $contract): void {});
esc_p9_008_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), "'5.0000', 'voided', 42"), 'void appends terminal state while preserving tax snapshot');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_008_row($ruleUuid, 2, 'vat', 'VAT', '5.0000', 'voided', 1002)]];
$voidRetry = $repository->voidRule(55, $ruleUuid, 'ffffffff-ffff-4fff-8fff-ffffffffffff', 42, static function (array $contract): void {});
esc_p9_008_assert($voidRetry === 1002, 'repeated void returns existing terminal revision');
esc_p9_008_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'repeated void emits no duplicate terminal revision');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_008_row($ruleUuid, 2, 'vat', 'VAT', '5.0000', 'voided', 1002)]];
esc_p9_008_expect_throw(
    static fn (): int => $repository->reviseRule(55, $ruleUuid, '12345678-1234-4234-8234-123456789abc', 'vat', 'Reactivate', PercentageRate::of('5'), 42, static function (array $contract): void {}),
    RuntimeException::class,
    'voided tax rule cannot be revised/reactivated'
);

// Service owns capability, tenant-role and locked Contract scope; callers supply no tenant/profile/currency.
esc_p9_008_assert(str_contains($serviceSource, 'authorize(Capabilities::ACCESS)'), 'tax-rule reads require ACCESS');
esc_p9_008_assert(substr_count($serviceSource, 'authorize(Capabilities::EDIT_CONTRACTS)') >= 3, 'all tax-rule mutations require EDIT_CONTRACTS');
esc_p9_008_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'tenant role narrows WordPress capability grants');
esc_p9_008_assert(str_contains($serviceSource, 'Capabilities::VIEW_ALL') && str_contains($serviceSource, 'Capabilities::VIEW_ASSIGNED'), 'service preserves Contract VIEW_ALL/own VIEW_ASSIGNED scope');
esc_p9_008_assert(str_contains($serviceSource, 'PercentageRate::of($ratePercent)'), 'service normalizes exact rate without Money multiplication');
esc_p9_008_assert(! str_contains($serviceSource, '$tenantId') && ! str_contains($serviceSource, 'mixed $currency'), 'service exposes no caller tenant/currency identity');
esc_p9_008_assert(str_contains($serviceSource, 'random_bytes(16)') && str_contains($serviceSource, 'wp_generate_uuid4'), 'tax-rule/revision identities are server generated');
esc_p9_008_assert(str_contains($gateSource, 'enterprise_contract_financial_tax_rules_p9_008.php'), 'P9-008 regression is wired into global backend gate');

fwrite(STDOUT, "P9-008 Enterprise Contract tax/VAT rule revisions passed ({$assertions} assertions).\n");
