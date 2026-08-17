<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0055EnterpriseContractFinancialCreditRevisions;
use SafeContracts\Finance\ContractFinancialCreditPolicy;
use SafeContracts\Finance\ContractFinancialCreditRevisionRepository;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;
function esc_p9_011_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @param class-string<Throwable> $expected */
function esc_p9_011_expect_throw(callable $callback, string $expected, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p9_011_assert($error instanceof $expected, $message . ' (received ' . $error::class . ')');
        return;
    }
    esc_p9_011_assert(false, $message . ' (no exception)');
}

/** @return array<string,mixed> */
function esc_p9_011_row(
    string $creditUuid,
    int $revisionNumber,
    string $reason = 'Commercial credit',
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
        'credit_uuid' => $creditUuid,
        'revision_number' => (string) $revisionNumber,
        'reason' => $reason,
        'amount' => $amount,
        'currency_code' => $currency,
        'credit_state' => $state,
        'created_by' => '42',
        'created_at' => '2026-08-17 19:30:00',
    ];
}

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0055EnterpriseContractFinancialCreditRevisions.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialCreditPolicy.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialCreditRevisionRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialCreditRevisionService.php');
$reconciliationRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialReconciliationRepository.php');
$reconciliationServiceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialReconciliationService.php');
$routerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

// Historical additive schema boundary.
esc_p9_011_assert(version_compare(Migrator::LATEST_VERSION, '1.54.0', '>='), 'P9-011 historical schema boundary remains at or beyond 1.54.0');
esc_p9_011_assert(str_contains($migratorSource, "'1.53.0' => Migration0054EnterpriseContractFinancialPenaltyRuleRevisions::class"), 'Migration0054 remains historically mapped to 1.53.0');
esc_p9_011_assert(str_contains($migratorSource, "'1.54.0' => Migration0055EnterpriseContractFinancialCreditRevisions::class"), 'Migration0055 remains historically mapped to 1.54.0');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0055EnterpriseContractFinancialCreditRevisions())->up($GLOBALS['wpdb']);
esc_p9_011_assert(count($GLOBALS['sc_test_dbdelta']) === 1, 'P9-011 emits one additive table definition');
$schema = (string) ($GLOBALS['sc_test_dbdelta'][0] ?? '');
foreach ([
    'safecontracts_contract_financial_credit_revisions',
    'tenant_id bigint(20) unsigned NOT NULL',
    'revision_uuid char(36) NOT NULL',
    'contract_id bigint(20) unsigned NOT NULL',
    'financial_currency_profile_id bigint(20) unsigned NOT NULL',
    'credit_uuid char(36) NOT NULL',
    'revision_number bigint(20) unsigned NOT NULL',
    'reason varchar(191) NOT NULL',
    'amount decimal(20,4) NOT NULL',
    'currency_code char(3) NOT NULL',
    "credit_state varchar(16) NOT NULL DEFAULT 'proposed'",
    'created_by bigint(20) unsigned NOT NULL',
    'created_at datetime NOT NULL',
    'UNIQUE KEY financial_credit_revision_uuid (revision_uuid)',
    'UNIQUE KEY tenant_contract_credit_revision (tenant_id, contract_id, credit_uuid, revision_number)',
    'KEY tenant_contract_credit_latest (tenant_id, contract_id, credit_uuid, revision_number, id)',
] as $marker) {
    esc_p9_011_assert(str_contains($schema, $marker), 'P9-011 schema contains ' . $marker);
}
esc_p9_011_assert(! str_contains(strtoupper($migrationSource), 'DROP TABLE') && ! str_contains(strtoupper($migrationSource), 'ALTER TABLE'), 'P9-011 migration is additive/non-destructive');
esc_p9_011_assert(! str_contains($schema, 'updated_at') && ! str_contains($schema, 'updated_by'), 'credit evidence contains no mutable update columns');

// Bounded proposal-only policy.
esc_p9_011_assert(ContractFinancialCreditPolicy::normalizeState('PROPOSED') === 'proposed', 'proposed credit state canonicalizes');
esc_p9_011_assert(ContractFinancialCreditPolicy::normalizeState('voided') === 'voided', 'voided credit state canonicalizes');
esc_p9_011_expect_throw(static fn (): string => ContractFinancialCreditPolicy::normalizeState('applied'), InvalidArgumentException::class, 'application/effect state is not invented');
esc_p9_011_assert(ContractFinancialCreditPolicy::MAX_CREDITS === 100, 'credits are bounded to 100 stable identities');
esc_p9_011_assert(ContractFinancialCreditPolicy::normalizeReason(' Commercial credit ') === 'Commercial credit', 'credit reason canonicalizes');
esc_p9_011_expect_throw(static fn (): string => ContractFinancialCreditPolicy::normalizeReason(''), InvalidArgumentException::class, 'blank credit reason fails closed');
esc_p9_011_assert(ContractFinancialCreditPolicy::normalizeUuid('AAAAAAAA-AAAA-4AAA-8AAA-AAAAAAAAAAAA') === 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'credit UUID canonicalizes');

// Architecture: Contract-first authorization, exact Money/currency, immutable evidence, no posting.
esc_p9_011_assert(str_contains($repositorySource, 'TenantContextStore::context()->requireTenantId()'), 'repository derives tenant only from locked context');
esc_p9_011_assert(str_contains($repositorySource, '$authorizeLockedContract($contract)'), 'authorization occurs against exact locked Contract');
esc_p9_011_assert(str_contains($repositorySource, 'ContractFinancialCreditPolicy::MAX_CREDITS + 1'), 'current read uses 101st overflow sentinel');
esc_p9_011_assert(str_contains($repositorySource, 'NOT EXISTS ('), 'current read derives latest immutable revisions only');
esc_p9_011_assert(str_contains($repositorySource, "c.status = 'active' AND c.is_archived = 0"), 'guarded append revalidates active lifecycle');
esc_p9_011_assert(str_contains($repositorySource, 'p.contract_currency = %s'), 'guarded append revalidates P9-003 currency');
esc_p9_011_assert(str_contains($repositorySource, 'Money::of($amount, $currency)'), 'credit amount uses P9-001 Money after profile lock');
esc_p9_011_assert(str_contains($repositorySource, 'cannot be negative'), 'negative stored/caller credit amounts fail closed');
esc_p9_011_assert(str_contains($repositorySource, 'cannot be revised or reactivated'), 'voided credit is terminal');
esc_p9_011_assert(! str_contains(strtoupper($repositorySource), 'UPDATE ') && ! str_contains(strtoupper($repositorySource), 'DELETE FROM'), 'credit evidence is append-only');
foreach (['safecontracts_contract_adjustments', 'exchange_rate', 'currency_convert', 'invoice_id', 'payment_id', 'refund', 'applied_amount', 'settled'] as $forbidden) {
    esc_p9_011_assert(! str_contains($repositorySource, $forbidden) && ! str_contains($serviceSource, $forbidden), 'P9-011 avoids legacy/application coupling: ' . $forbidden);
}
esc_p9_011_assert(! str_contains($reconciliationRepositorySource, 'financial_credit'), 'P9-006 reconciliation does not query credit evidence');
esc_p9_011_assert(! str_contains($reconciliationServiceSource, 'ContractFinancialCredit'), 'P9-006 service has no implicit credit effect');
esc_p9_011_assert(! str_contains($routerSource, 'ContractFinancialCreditRevision'), 'P9-011 exposes no REST route');

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(7);
$repository = new ContractFinancialCreditRevisionRepository();
$contractActive = [['id' => '55', 'accountant_user_id' => '42', 'status' => 'active', 'is_archived' => '0']];
$profile = [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']];
$creditUuid = '11111111-1111-4111-8111-111111111111';

// Read locks and authorizes before profile/credit reads, canonicalizes Money.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_011_row($creditUuid, 2, '  Goodwill credit  ', '125.5', 'usd', 'PROPOSED')]];
$authorized = false;
$current = $repository->listCurrentForContract(55, static function (array $contract) use (&$authorized): void {
    esc_p9_011_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'credit authorization runs before profile/credit reads');
    esc_p9_011_assert(str_contains((string) $GLOBALS['sc_test_read_queries'][0], 'FOR UPDATE'), 'credit authorization boundary is protected by Contract lock');
    $authorized = true;
});
esc_p9_011_assert($authorized, 'credit locked authorization callback executes');
esc_p9_011_assert(count($current) === 1 && $current[0]['reason'] === 'Goodwill credit', 'current credit reason canonicalizes');
esc_p9_011_assert($current[0]['amount'] === '125.5000' && $current[0]['currency_code'] === 'USD', 'current credit Money canonicalizes');
esc_p9_011_assert($current[0]['credit_state'] === 'proposed', 'current credit state canonicalizes');
esc_p9_011_assert(str_contains((string) end($GLOBALS['sc_test_read_queries']), 'LIMIT 101'), 'current credit read is bounded');

// Historical evidence remains readable after completion.
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'accountant_user_id' => '42', 'status' => 'completed', 'is_archived' => '0']],
    $profile,
    [esc_p9_011_row($creditUuid, 2)],
];
esc_p9_011_assert(count($repository->listCurrentForContract(55, static function (array $contract): void {})) === 1, 'completed Contract retains credit evidence');

// Overflow, duplicate, cross-profile/currency and invalid amounts fail closed.
$overflow = [];
for ($i = 1; $i <= 101; $i++) {
    $overflow[] = esc_p9_011_row(sprintf('30000000-0000-4000-8000-%012x', $i), 1, 'Credit ' . $i, '1.0000', 'USD', 'proposed', 2000 + $i);
}
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, $overflow];
esc_p9_011_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), RuntimeException::class, '101 current credits fail closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_011_row($creditUuid, 1, 'A', '1', 'USD', 'proposed', 2101), esc_p9_011_row($creditUuid, 2, 'B', '2', 'USD', 'proposed', 2102)]];
esc_p9_011_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'duplicate latest credit identities fail closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_011_row($creditUuid, 1, 'Credit', '1', 'USD', 'proposed', 2201, 99)]];
esc_p9_011_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'cross-profile credit fails closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_011_row($creditUuid, 1, 'Credit', '1', 'EUR')]];
esc_p9_011_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'cross-currency credit fails closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_011_row($creditUuid, 1, 'Credit', '-1.0000', 'USD')]];
esc_p9_011_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'negative persisted credit fails closed');

// Create only on ACTIVE Contract; amount canonicalizes after profile lock.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [['total' => '0']], []];
$GLOBALS['wpdb']->insert_id = 0;
$created = $repository->createCredit(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'Commercial credit', '50.5', 42, static function (array $contract): void {
    esc_p9_011_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'create authorization runs immediately after Contract lock');
});
esc_p9_011_assert($created === 1001, 'active Contract credit creation succeeds');
$createReads = implode("\n", array_map('strval', $GLOBALS['sc_test_read_queries']));
$createWrites = implode("\n", array_map('strval', $GLOBALS['sc_test_queries']));
esc_p9_011_assert(strpos($createReads, 'safecontracts_contracts') < strpos($createReads, 'safecontracts_contract_financial_currency_profiles'), 'credit create locks Contract before profile');
esc_p9_011_assert(str_contains($createReads, 'credit_uuid = \'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa\'') && str_contains($createReads, 'FOR UPDATE'), 'credit create proves generated identity unused');
esc_p9_011_assert(str_contains($createWrites, "'Commercial credit', '50.5000', p.contract_currency, 'proposed', 42"), 'credit create persists positive canonical Money and proposed state');

// Draft/completed/cancelled/archived Contracts cannot mutate credits.
foreach ([['draft', '0'], ['completed', '0'], ['cancelled', '0'], ['active', '1']] as [$status, $archived]) {
    $GLOBALS['sc_test_queries'] = [];
    $GLOBALS['sc_test_read_queries'] = [];
    $GLOBALS['sc_test_result_queue'] = [[['id' => '55', 'accountant_user_id' => '42', 'status' => $status, 'is_archived' => $archived]]];
    esc_p9_011_expect_throw(static fn (): int => $repository->createCredit(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'Credit', '1', 42, static function (array $contract): void {}), RuntimeException::class, "{$status}/archived Contract cannot mutate credit");
    esc_p9_011_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'credit lifecycle failure occurs before profile read');
}

// Negative caller amount is rejected after profile lock and rolls back.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile];
esc_p9_011_expect_throw(static fn (): int => $repository->createCredit(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'Negative', '-0.0001', 42, static function (array $contract): void {}), InvalidArgumentException::class, 'negative caller credit fails closed');
esc_p9_011_assert(count($GLOBALS['sc_test_read_queries']) === 2, 'credit amount validates only after Contract/profile authority is known');
esc_p9_011_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'ROLLBACK'), 'negative credit rolls back');

// Max 100 stable identities.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [['total' => '100']]];
esc_p9_011_expect_throw(static fn (): int => $repository->createCredit(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'Credit', '1', 42, static function (array $contract): void {}), RuntimeException::class, '100-credit Contract cannot create a 101st credit');

// Exact revise retry is idempotent; changed revision appends; void is terminal/idempotent.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_011_row($creditUuid, 1)]];
$retry = $repository->reviseCredit(55, $creditUuid, 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'Commercial credit', '100', 42, static function (array $contract): void {});
esc_p9_011_assert($retry === 1001 && ! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'exact credit retry is idempotent');
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_011_row($creditUuid, 1)]];
$GLOBALS['wpdb']->insert_id = 0;
$repository->reviseCredit(55, $creditUuid, 'dddddddd-dddd-4ddd-8ddd-dddddddddddd', 'Revised credit', '75', 42, static function (array $contract): void {});
esc_p9_011_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), "'11111111-1111-4111-8111-111111111111', 2, 'Revised credit', '75.0000', p.contract_currency, 'proposed', 42"), 'changed credit appends revision 2');
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_011_row($creditUuid, 1)]];
$GLOBALS['wpdb']->insert_id = 0;
$repository->voidCredit(55, $creditUuid, 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee', 42, static function (array $contract): void {});
esc_p9_011_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), "'100.0000', p.contract_currency, 'voided', 42"), 'credit void preserves Money snapshot and appends terminal state');
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_011_row($creditUuid, 2, 'Commercial credit', '100', 'USD', 'voided', 1002)]];
esc_p9_011_assert($repository->voidCredit(55, $creditUuid, 'ffffffff-ffff-4fff-8fff-ffffffffffff', 42, static function (array $contract): void {}) === 1002, 'repeated credit void returns terminal revision');
esc_p9_011_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'repeated credit void writes nothing');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_011_row($creditUuid, 2, 'Commercial credit', '100', 'USD', 'voided', 1002)]];
esc_p9_011_expect_throw(static fn (): int => $repository->reviseCredit(55, $creditUuid, '12345678-1234-4234-8234-123456789abc', 'Reactivate', '100', 42, static function (array $contract): void {}), RuntimeException::class, 'voided credit cannot reactivate');

// Service owns authorization/scope; caller provides no tenant/currency.
esc_p9_011_assert(str_contains($serviceSource, 'authorize(Capabilities::ACCESS)'), 'credit reads require ACCESS');
esc_p9_011_assert(substr_count($serviceSource, 'authorize(Capabilities::EDIT_CONTRACTS)') >= 3, 'credit mutations require EDIT_CONTRACTS');
esc_p9_011_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'tenant role narrows credit capabilities');
esc_p9_011_assert(str_contains($serviceSource, 'Capabilities::VIEW_ALL') && str_contains($serviceSource, 'Capabilities::VIEW_ASSIGNED'), 'credit service preserves locked Contract scope');
esc_p9_011_assert(! str_contains($serviceSource, 'mixed $currency') && ! str_contains($serviceSource, '$tenantId'), 'credit service exposes no caller currency/tenant');
esc_p9_011_assert(str_contains($serviceSource, 'random_bytes(16)') && str_contains($serviceSource, 'wp_generate_uuid4'), 'credit identities are server-generated UUIDv4');
esc_p9_011_assert(str_contains($gateSource, 'enterprise_contract_financial_credits_p9_011.php'), 'P9-011 regression is wired into global backend gate');

fwrite(STDOUT, "P9-011 Enterprise Contract credit revisions passed ({$assertions} assertions).\n");
