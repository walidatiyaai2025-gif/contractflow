<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0051EnterpriseContractFinancialVariationRevisions;
use SafeContracts\Finance\ContractFinancialVariationPolicy;
use SafeContracts\Finance\ContractFinancialVariationRevisionRepository;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;
function esc_p9_007_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @param class-string<Throwable> $expected */
function esc_p9_007_expect_throw(callable $callback, string $expected, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p9_007_assert($error instanceof $expected, $message . ' (received ' . $error::class . ')');
        return;
    }
    esc_p9_007_assert(false, $message . ' (no exception)');
}

/** @return array<string,mixed> */
function esc_p9_007_row(
    string $variationUuid,
    int $revisionNumber,
    string $direction = 'addition',
    string $description = 'Approved scope proposal',
    string $amount = '100.0000',
    string $currency = 'USD',
    string $state = 'proposed',
    int $id = 1001,
    int $profileId = 31
): array {
    return [
        'id' => (string) $id,
        'revision_uuid' => sprintf('20000000-0000-4000-8000-%012x', $id),
        'contract_id' => '55',
        'financial_currency_profile_id' => (string) $profileId,
        'variation_uuid' => $variationUuid,
        'revision_number' => (string) $revisionNumber,
        'variation_direction' => $direction,
        'description' => $description,
        'amount' => $amount,
        'currency_code' => $currency,
        'variation_state' => $state,
        'created_by' => '42',
        'created_at' => '2026-08-17 17:00:00',
    ];
}

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0051EnterpriseContractFinancialVariationRevisions.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialVariationRevisionRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialVariationRevisionService.php');
$reconciliationRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialReconciliationRepository.php');
$reconciliationServiceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialReconciliationService.php');
$routerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

// Historical schema boundary; later additive migrations may advance the current version.
esc_p9_007_assert(version_compare(Migrator::LATEST_VERSION, '1.50.0', '>='), 'current Enterprise schema is at or beyond the P9-007 1.50.0 boundary');
esc_p9_007_assert(str_contains($migratorSource, "'1.49.0' => Migration0050EnterpriseContractFinancialAdjustmentRevisions::class"), 'Migration0050 remains historically mapped to 1.49.0');
esc_p9_007_assert(str_contains($migratorSource, "'1.50.0' => Migration0051EnterpriseContractFinancialVariationRevisions::class"), 'Migration0051 remains historically mapped to 1.50.0');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0051EnterpriseContractFinancialVariationRevisions())->up($GLOBALS['wpdb']);
esc_p9_007_assert(count($GLOBALS['sc_test_dbdelta']) === 1, 'P9-007 emits one additive table definition');
$schema = (string) ($GLOBALS['sc_test_dbdelta'][0] ?? '');
foreach ([
    'safecontracts_contract_financial_variation_revisions',
    'tenant_id bigint(20) unsigned NOT NULL',
    'revision_uuid char(36) NOT NULL',
    'contract_id bigint(20) unsigned NOT NULL',
    'financial_currency_profile_id bigint(20) unsigned NOT NULL',
    'variation_uuid char(36) NOT NULL',
    'revision_number bigint(20) unsigned NOT NULL',
    'variation_direction varchar(16) NOT NULL',
    'description varchar(191) NOT NULL',
    'amount decimal(20,4) NOT NULL',
    'currency_code char(3) NOT NULL',
    "variation_state varchar(16) NOT NULL DEFAULT 'proposed'",
    'created_by bigint(20) unsigned NOT NULL',
    'created_at datetime NOT NULL',
    'UNIQUE KEY financial_variation_revision_uuid (revision_uuid)',
    'UNIQUE KEY tenant_contract_variation_revision (tenant_id, contract_id, variation_uuid, revision_number)',
    'KEY tenant_contract_variation_latest (tenant_id, contract_id, variation_uuid, revision_number, id)',
] as $marker) {
    esc_p9_007_assert(str_contains($schema, $marker), 'P9-007 schema contains ' . $marker);
}
esc_p9_007_assert(! str_contains(strtoupper($migrationSource), 'DROP TABLE'), 'P9-007 migration is non-destructive');
esc_p9_007_assert(! str_contains(strtoupper($migrationSource), 'ALTER TABLE'), 'P9-007 migration does not rewrite existing tables');
esc_p9_007_assert(! str_contains($schema, 'updated_at') && ! str_contains($schema, 'updated_by'), 'variation revisions contain no mutable update columns');

// Bounded, allowlisted domain policy.
esc_p9_007_assert(ContractFinancialVariationPolicy::normalizeDirection(' Addition ') === 'addition', 'addition direction canonicalizes');
esc_p9_007_assert(ContractFinancialVariationPolicy::normalizeDirection('DISCOUNT') === 'discount', 'discount direction canonicalizes');
esc_p9_007_expect_throw(static fn (): string => ContractFinancialVariationPolicy::normalizeDirection('tax'), InvalidArgumentException::class, 'unsupported direction fails closed');
esc_p9_007_assert(ContractFinancialVariationPolicy::normalizeState('PROPOSED') === 'proposed', 'proposed state canonicalizes');
esc_p9_007_assert(ContractFinancialVariationPolicy::normalizeState('VOIDED') === 'voided', 'voided state canonicalizes');
esc_p9_007_expect_throw(static fn (): string => ContractFinancialVariationPolicy::normalizeState('approved'), InvalidArgumentException::class, 'approval/effect state is not invented in P9-007');
esc_p9_007_assert(ContractFinancialVariationPolicy::MAX_VARIATIONS === 200, 'variation identities are bounded to 200 per Contract');
esc_p9_007_assert(ContractFinancialVariationPolicy::normalizeDescription(' Scope change ') === 'Scope change', 'description canonicalizes');
esc_p9_007_expect_throw(static fn (): string => ContractFinancialVariationPolicy::normalizeDescription(''), InvalidArgumentException::class, 'blank description fails closed');
esc_p9_007_assert(ContractFinancialVariationPolicy::normalizeUuid('AAAAAAAA-AAAA-4AAA-8AAA-AAAAAAAAAAAA') === 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'UUIDv4 canonicalizes');

// Architectural invariants.
esc_p9_007_assert(str_contains($repositorySource, 'TenantContextStore::context()->requireTenantId()'), 'repository derives tenant only from locked context');
esc_p9_007_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()'), 'repository requires core tenant enforcement');
esc_p9_007_assert(str_contains($repositorySource, 'COUNT(DISTINCT variation_uuid)'), 'create path bounds stable variation identities');
esc_p9_007_assert(str_contains($repositorySource, 'ContractFinancialVariationPolicy::MAX_VARIATIONS + 1'), 'current read uses a 201st overflow sentinel');
esc_p9_007_assert(str_contains($repositorySource, 'NOT EXISTS ('), 'current read derives only latest immutable revisions');
esc_p9_007_assert(str_contains($repositorySource, '$authorizeLockedContract($contract)'), 'repository authorizes against the exact locked Contract row');
esc_p9_007_assert(str_contains($repositorySource, "c.status = 'active' AND c.is_archived = 0"), 'final append revalidates active/unarchived lifecycle');
esc_p9_007_assert(str_contains($repositorySource, 'p.contract_currency = %s'), 'final append revalidates P9-003 currency authority');
esc_p9_007_assert(str_contains($repositorySource, '$storedMoney->equals($money)'), 'exact proposed revision retry is idempotent');
esc_p9_007_assert(str_contains($repositorySource, 'cannot be revised or reactivated'), 'voided variation is terminal');
esc_p9_007_assert(! str_contains(strtoupper($repositorySource), 'UPDATE '), 'variation evidence has no UPDATE path');
esc_p9_007_assert(! str_contains(strtoupper($repositorySource), 'DELETE FROM'), 'variation evidence has no DELETE path');
foreach (['ContractMoney', 'GeneralSettings', 'safecontracts_contract_adjustments', 'exchange_rate', 'currency_convert'] as $forbidden) {
    esc_p9_007_assert(! str_contains($repositorySource, $forbidden) && ! str_contains($serviceSource, $forbidden), 'P9-007 avoids legacy/FX coupling: ' . $forbidden);
}

// P9-006 remains the authority for base/addition/discount totals and does not observe proposed variations.
esc_p9_007_assert(! str_contains($reconciliationRepositorySource, 'financial_variation'), 'P9-006 reconciliation does not query the P9-007 variation table');
esc_p9_007_assert(! str_contains($reconciliationServiceSource, 'ContractFinancialVariation'), 'P9-006 reconciliation has no implicit variation effect');
esc_p9_007_assert(! str_contains($routerSource, 'ContractFinancialVariationRevision'), 'P9-007 exposes no REST route');

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(7);
$repository = new ContractFinancialVariationRevisionRepository();
$contractActive = [['id' => '55', 'accountant_user_id' => '42', 'status' => 'active', 'is_archived' => '0']];
$profile = [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']];
$variationUuid = '11111111-1111-4111-8111-111111111111';

// Reads lock and authorize the Contract before any profile/variation financial read.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_007_row($variationUuid, 2, 'ADDITION', '  Scope change  ', '125.5', 'usd')]];
$authorized = false;
$current = $repository->listCurrentForContract(55, static function (array $contract) use (&$authorized): void {
    esc_p9_007_assert((int) ($contract['id'] ?? 0) === 55, 'authorization receives the locked Contract identity');
    esc_p9_007_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'authorization runs before profile or variation reads');
    esc_p9_007_assert(str_contains((string) $GLOBALS['sc_test_read_queries'][0], 'FOR UPDATE'), 'authorization boundary is protected by Contract FOR UPDATE');
    $authorized = true;
});
esc_p9_007_assert($authorized, 'locked-row authorization callback executes');
esc_p9_007_assert(count($current) === 1 && $current[0]['variation_direction'] === 'addition', 'current variation read returns one normalized latest revision');
esc_p9_007_assert($current[0]['amount'] === '125.5000' && $current[0]['currency_code'] === 'USD', 'current variation Money canonicalizes');
esc_p9_007_assert(implode("\n", $GLOBALS['sc_test_queries']) === "START TRANSACTION\nCOMMIT", 'current read is one coherent transaction');
$listSql = (string) end($GLOBALS['sc_test_read_queries']);
esc_p9_007_assert(str_contains($listSql, 'r.tenant_id = 7') && str_contains($listSql, 'r.contract_id = 55') && str_contains($listSql, 'LIMIT 201'), 'current read is tenant/Contract scoped and bounded');

// Evidence remains readable after lifecycle completion; only mutation is ACTIVE-only.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'accountant_user_id' => '42', 'status' => 'completed', 'is_archived' => '0']],
    $profile,
    [esc_p9_007_row($variationUuid, 2)],
];
$completedCurrent = $repository->listCurrentForContract(55, static function (array $contract): void {});
esc_p9_007_assert(count($completedCurrent) === 1, 'completed Contract retains readable immutable variation evidence');

// 201st current identity is an overflow sentinel, never silent truncation.
$overflow = [];
for ($i = 1; $i <= ContractFinancialVariationPolicy::MAX_VARIATIONS + 1; $i++) {
    $overflow[] = esc_p9_007_row(sprintf('30000000-0000-4000-8000-%012x', $i), 1, 'addition', 'Variation ' . $i, '1.0000', 'USD', 'proposed', 2000 + $i);
}
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, $overflow];
esc_p9_007_expect_throw(
    static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}),
    RuntimeException::class,
    '201 current variations fail closed'
);

// Create revision 1: Contract lock -> authorization -> profile -> bounded identity -> guarded append.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [['total' => '0']], []];
$GLOBALS['wpdb']->insert_id = 0;
$createAuthorized = false;
$created = $repository->createVariation(
    55,
    'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    'addition',
    'Post-award scope change',
    '100',
    42,
    static function (array $contract) use (&$createAuthorized): void {
        esc_p9_007_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'create authorization occurs immediately after Contract lock');
        $createAuthorized = true;
    }
);
esc_p9_007_assert($createAuthorized && $created === 1001, 'active Contract variation creation succeeds after locked authorization');
$createReads = implode("\n", array_map('strval', $GLOBALS['sc_test_read_queries']));
$createWrites = implode("\n", array_map('strval', $GLOBALS['sc_test_queries']));
esc_p9_007_assert(strpos($createReads, 'safecontracts_contracts') < strpos($createReads, 'safecontracts_contract_financial_currency_profiles'), 'create locks Contract before profile');
esc_p9_007_assert(str_contains($createReads, 'variation_uuid = \'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa\'') && str_contains($createReads, 'FOR UPDATE'), 'create proves generated variation identity unused under lock');
esc_p9_007_assert(str_contains($createWrites, "c.status = 'active' AND c.is_archived = 0"), 'guarded insert repeats active Contract predicate');
esc_p9_007_assert(str_contains($createWrites, "'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 1, 'addition', 'Post-award scope change', '100.0000', p.contract_currency, 'proposed', 42"), 'create persists revision 1 with P9-003 currency and proposed state');

// Draft/completed/cancelled/archived Contracts cannot mutate variations.
foreach ([
    ['draft', '0'],
    ['completed', '0'],
    ['cancelled', '0'],
    ['active', '1'],
] as [$status, $archived]) {
    $GLOBALS['sc_test_queries'] = [];
    $GLOBALS['sc_test_read_queries'] = [];
    $GLOBALS['sc_test_result_queue'] = [[['id' => '55', 'accountant_user_id' => '42', 'status' => $status, 'is_archived' => $archived]]];
    esc_p9_007_expect_throw(
        static fn (): int => $repository->createVariation(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'addition', 'Invalid lifecycle', '1', 42, static function (array $contract): void {}),
        RuntimeException::class,
        "{$status}/archived lifecycle cannot create a variation"
    );
    esc_p9_007_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'lifecycle failure occurs before financial reads');
    esc_p9_007_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'ROLLBACK'), 'lifecycle failure rolls back');
}

// Negative amount is rejected after authoritative profile currency is known and before append.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile];
esc_p9_007_expect_throw(
    static fn (): int => $repository->createVariation(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'addition', 'Negative', '-0.0001', 42, static function (array $contract): void {}),
    InvalidArgumentException::class,
    'negative variation amount fails closed'
);
esc_p9_007_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'ROLLBACK'), 'negative amount rolls back');

// Exact proposed revision retry is idempotent; changed revision appends monotonically.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_007_row($variationUuid, 1)]];
$retry = $repository->reviseVariation(55, $variationUuid, 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'addition', 'Approved scope proposal', '100.0000', 42, static function (array $contract): void {});
esc_p9_007_assert($retry === 1001, 'exact proposed revision retry returns existing revision');
esc_p9_007_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'exact retry writes no duplicate revision');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_007_row($variationUuid, 1)]];
$GLOBALS['wpdb']->insert_id = 0;
$repository->reviseVariation(55, $variationUuid, 'dddddddd-dddd-4ddd-8ddd-dddddddddddd', 'discount', 'Commercial change', '25', 42, static function (array $contract): void {});
$reviseWrites = implode("\n", $GLOBALS['sc_test_queries']);
esc_p9_007_assert(str_contains($reviseWrites, "'11111111-1111-4111-8111-111111111111', 2, 'discount', 'Commercial change', '25.0000'"), 'changed proposed variation appends revision 2');
esc_p9_007_assert(substr_count($reviseWrites, 'INSERT INTO') === 1 && ! str_contains($reviseWrites, 'UPDATE '), 'revision is immutable append only');

// Void appends a terminal snapshot and repeated void is idempotent.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_007_row($variationUuid, 1)]];
$GLOBALS['wpdb']->insert_id = 0;
$repository->voidVariation(55, $variationUuid, 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee', 42, static function (array $contract): void {});
esc_p9_007_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), "'voided', 42"), 'void appends terminal voided revision preserving financial snapshot');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_007_row($variationUuid, 2, 'addition', 'Approved scope proposal', '100.0000', 'USD', 'voided', 1002)]];
$voidRetry = $repository->voidVariation(55, $variationUuid, 'ffffffff-ffff-4fff-8fff-ffffffffffff', 42, static function (array $contract): void {});
esc_p9_007_assert($voidRetry === 1002, 'repeated void returns the terminal revision');
esc_p9_007_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'repeated void emits no duplicate terminal revision');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_007_row($variationUuid, 2, 'addition', 'Approved scope proposal', '100.0000', 'USD', 'voided', 1002)]];
esc_p9_007_expect_throw(
    static fn (): int => $repository->reviseVariation(55, $variationUuid, '12345678-1234-4234-8234-123456789abc', 'addition', 'Reactivate', '100', 42, static function (array $contract): void {}),
    RuntimeException::class,
    'voided variation cannot be revised/reactivated'
);

// Profile drift/cross-currency persisted evidence fails closed.
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_007_row($variationUuid, 1, 'addition', 'Drift', '1.0000', 'EUR')]];
esc_p9_007_expect_throw(
    static fn (): int => $repository->reviseVariation(55, $variationUuid, '12345678-1234-4234-8234-123456789abc', 'addition', 'Drift', '1', 42, static function (array $contract): void {}),
    UnexpectedValueException::class,
    'cross-currency persisted variation fails closed'
);

// Service owns capability, tenant-role and locked Contract scope; callers provide no tenant/currency.
esc_p9_007_assert(str_contains($serviceSource, 'authorize(Capabilities::ACCESS)'), 'variation reads require ACCESS');
esc_p9_007_assert(substr_count($serviceSource, 'authorize(Capabilities::EDIT_CONTRACTS)') >= 3, 'all variation mutations require EDIT_CONTRACTS');
esc_p9_007_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'tenant role narrows WordPress capability grants');
esc_p9_007_assert(str_contains($serviceSource, 'Capabilities::VIEW_ALL') && str_contains($serviceSource, 'Capabilities::VIEW_ASSIGNED'), 'service preserves Contract VIEW_ALL/own VIEW_ASSIGNED scope');
esc_p9_007_assert(! str_contains($serviceSource, 'mixed $currency') && ! str_contains($serviceSource, 'string $currency'), 'service exposes no caller currency input');
esc_p9_007_assert(! str_contains($serviceSource, '$tenantId'), 'service exposes no caller tenant identity');
esc_p9_007_assert(str_contains($serviceSource, 'random_bytes(16)') && str_contains($serviceSource, 'wp_generate_uuid4'), 'variation/revision identities are server generated');
esc_p9_007_assert(str_contains($gateSource, 'enterprise_contract_financial_variations_p9_007.php'), 'P9-007 regression is wired into global backend gate');

fwrite(STDOUT, "P9-007 Enterprise Contract financial variation revisions passed ({$assertions} assertions).\n");
