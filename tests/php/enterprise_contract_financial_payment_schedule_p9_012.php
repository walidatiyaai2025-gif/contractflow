<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0056EnterpriseContractFinancialPaymentScheduleEntryRevisions;
use SafeContracts\Finance\ContractFinancialPaymentSchedulePolicy;
use SafeContracts\Finance\ContractFinancialPaymentScheduleRevisionRepository;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;
function esc_p9_012_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @param class-string<Throwable> $expected */
function esc_p9_012_expect_throw(callable $callback, string $expected, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p9_012_assert($error instanceof $expected, $message . ' (received ' . $error::class . ')');
        return;
    }
    esc_p9_012_assert(false, $message . ' (no exception)');
}

/** @return array<string,mixed> */
function esc_p9_012_row(
    string $entryUuid,
    int $revisionNumber,
    int|string $sequence = 1,
    mixed $reference = 'INV-1',
    mixed $dueDate = '2026-09-30',
    mixed $amount = '100.0000',
    mixed $currency = 'USD',
    mixed $state = 'scheduled',
    int $id = 1001,
    int $profileId = 31
): array {
    return [
        'id' => (string) $id,
        'revision_uuid' => sprintf('20000000-0000-4000-8000-%012x', $id),
        'contract_id' => '55',
        'financial_currency_profile_id' => (string) $profileId,
        'schedule_entry_uuid' => $entryUuid,
        'revision_number' => (string) $revisionNumber,
        'sequence_no' => (string) $sequence,
        'reference' => $reference,
        'due_date' => $dueDate,
        'amount' => $amount,
        'currency_code' => $currency,
        'schedule_entry_state' => $state,
        'created_by' => '42',
        'created_at' => '2026-08-17 20:00:00',
    ];
}

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0056EnterpriseContractFinancialPaymentScheduleEntryRevisions.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialPaymentSchedulePolicy.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialPaymentScheduleRevisionRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialPaymentScheduleRevisionService.php');
$reconciliationRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialReconciliationRepository.php');
$reconciliationServiceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/ContractFinancialReconciliationService.php');
$legacyPaymentRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Payments/PaymentRepository.php');
$legacyCollectionRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Collections/CollectionRepository.php');
$routerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

// Historical additive schema boundary.
esc_p9_012_assert(version_compare(Migrator::LATEST_VERSION, '1.55.0', '>='), 'P9-012 historical schema boundary remains at or beyond 1.55.0');
esc_p9_012_assert(str_contains($migratorSource, "'1.54.0' => Migration0055EnterpriseContractFinancialCreditRevisions::class"), 'Migration0055 remains historically mapped to 1.54.0');
esc_p9_012_assert(str_contains($migratorSource, "'1.55.0' => Migration0056EnterpriseContractFinancialPaymentScheduleEntryRevisions::class"), 'Migration0056 remains historically mapped to 1.55.0');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0056EnterpriseContractFinancialPaymentScheduleEntryRevisions())->up($GLOBALS['wpdb']);
esc_p9_012_assert(count($GLOBALS['sc_test_dbdelta']) === 1, 'P9-012 emits one additive table definition');
$schema = (string) ($GLOBALS['sc_test_dbdelta'][0] ?? '');
foreach ([
    'safecontracts_contract_financial_payment_schedule_entry_revisions',
    'tenant_id bigint(20) unsigned NOT NULL',
    'revision_uuid char(36) NOT NULL',
    'contract_id bigint(20) unsigned NOT NULL',
    'financial_currency_profile_id bigint(20) unsigned NOT NULL',
    'schedule_entry_uuid char(36) NOT NULL',
    'revision_number bigint(20) unsigned NOT NULL',
    'sequence_no int(11) unsigned NOT NULL',
    'reference varchar(100) NULL',
    'due_date date NOT NULL',
    'amount decimal(20,4) NOT NULL',
    'currency_code char(3) NOT NULL',
    "schedule_entry_state varchar(16) NOT NULL DEFAULT 'scheduled'",
    'created_by bigint(20) unsigned NOT NULL',
    'created_at datetime NOT NULL',
    'UNIQUE KEY financial_payment_schedule_revision_uuid (revision_uuid)',
    'UNIQUE KEY tenant_contract_payment_schedule_revision (tenant_id, contract_id, schedule_entry_uuid, revision_number)',
    'KEY tenant_contract_payment_schedule_latest (tenant_id, contract_id, schedule_entry_uuid, revision_number, id)',
    'KEY tenant_contract_payment_schedule_sequence (tenant_id, contract_id, sequence_no, schedule_entry_uuid)',
    'KEY tenant_contract_payment_schedule_due_state (tenant_id, contract_id, due_date, schedule_entry_state, id)',
] as $marker) {
    esc_p9_012_assert(str_contains($schema, $marker), 'P9-012 schema contains ' . $marker);
}
esc_p9_012_assert(! str_contains(strtoupper($migrationSource), 'DROP TABLE') && ! str_contains(strtoupper($migrationSource), 'ALTER TABLE'), 'P9-012 migration is additive/non-destructive');
esc_p9_012_assert(! str_contains($schema, 'updated_at') && ! str_contains($schema, 'updated_by'), 'schedule revisions contain no mutable update columns');
esc_p9_012_assert(! str_contains($schema, 'paid_amount') && ! str_contains($schema, 'remaining_amount'), 'Enterprise schedule schema contains no settlement balances');

// Schedule policy: bounded state, stable positive sequence, optional reference and contractual due date.
esc_p9_012_assert(ContractFinancialPaymentSchedulePolicy::normalizeState('SCHEDULED') === 'scheduled', 'scheduled state canonicalizes');
esc_p9_012_assert(ContractFinancialPaymentSchedulePolicy::normalizeState('voided') === 'voided', 'voided state canonicalizes');
esc_p9_012_expect_throw(static fn (): string => ContractFinancialPaymentSchedulePolicy::normalizeState('paid'), InvalidArgumentException::class, 'settlement status is not invented in P9-012');
esc_p9_012_assert(ContractFinancialPaymentSchedulePolicy::MAX_ENTRIES === 500, 'schedule is bounded to 500 stable identities');
esc_p9_012_assert(ContractFinancialPaymentSchedulePolicy::normalizeSequence('0007') === 7, 'sequence canonicalizes without losing integer identity');
esc_p9_012_expect_throw(static fn (): int => ContractFinancialPaymentSchedulePolicy::normalizeSequence(0), InvalidArgumentException::class, 'zero sequence fails closed');
esc_p9_012_expect_throw(static fn (): int => ContractFinancialPaymentSchedulePolicy::normalizeSequence(1.5), InvalidArgumentException::class, 'float sequence fails closed');
esc_p9_012_expect_throw(static fn (): int => ContractFinancialPaymentSchedulePolicy::normalizeSequence('9999999999'), OverflowException::class, 'oversized sequence fails closed');
esc_p9_012_assert(ContractFinancialPaymentSchedulePolicy::normalizeReference(' INV-1 ') === 'INV-1', 'reference canonicalizes');
esc_p9_012_assert(ContractFinancialPaymentSchedulePolicy::normalizeReference('   ') === null, 'blank reference canonicalizes to null');
esc_p9_012_assert(ContractFinancialPaymentSchedulePolicy::normalizeDueDate('2028-02-29') === '2028-02-29', 'valid leap-day due date is accepted');
esc_p9_012_expect_throw(static fn (): string => ContractFinancialPaymentSchedulePolicy::normalizeDueDate('2027-02-29'), InvalidArgumentException::class, 'invalid calendar due date fails closed');
esc_p9_012_assert(ContractFinancialPaymentSchedulePolicy::normalizeUuid('AAAAAAAA-AAAA-4AAA-8AAA-AAAAAAAAAAAA') === 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'schedule UUID canonicalizes');

// Architecture: Contract-first authorization, Money/profile authority, immutable evidence and permanent sequence ownership.
esc_p9_012_assert(str_contains($repositorySource, 'TenantContextStore::context()->requireTenantId()'), 'repository derives tenant only from locked context');
esc_p9_012_assert(str_contains($repositorySource, '$authorizeLockedContract($contract)'), 'authorization occurs against exact locked Contract');
esc_p9_012_assert(str_contains($repositorySource, 'ContractFinancialPaymentSchedulePolicy::MAX_ENTRIES + 1'), 'current read uses 501st overflow sentinel');
esc_p9_012_assert(str_contains($repositorySource, 'NOT EXISTS ('), 'current read derives latest immutable revisions only');
esc_p9_012_assert(str_contains($repositorySource, 'ORDER BY r.sequence_no ASC, r.schedule_entry_uuid ASC'), 'current read is deterministic by stable sequence then identity');
esc_p9_012_assert(str_contains($repositorySource, 'COUNT(DISTINCT schedule_entry_uuid)'), 'create path bounds stable schedule identities');
esc_p9_012_assert(str_contains($repositorySource, 'sequence_no = %d ORDER BY id ASC LIMIT 2 FOR UPDATE'), 'creation proves sequence has never been assigned before');
esc_p9_012_assert(str_contains($repositorySource, "c.status = 'active' AND c.is_archived = 0"), 'guarded append revalidates active lifecycle');
esc_p9_012_assert(str_contains($repositorySource, 'p.contract_currency = %s'), 'guarded append revalidates P9-003 currency');
esc_p9_012_assert(str_contains($repositorySource, 'Money::of($amount, $currency)'), 'schedule amount uses P9-001 Money after profile lock');
esc_p9_012_assert(str_contains($repositorySource, 'must be greater than zero'), 'zero/negative schedule Money fails closed');
esc_p9_012_assert(str_contains($repositorySource, 'cannot be revised or reactivated'), 'voided schedule entry is terminal');
esc_p9_012_assert(! str_contains(strtoupper($repositorySource), 'UPDATE ') && ! str_contains(strtoupper($repositorySource), 'DELETE FROM'), 'schedule evidence is append-only');

// Explicit isolation from mutable legacy Payments/Collections and from Contract-value reconciliation.
foreach (['safecontracts_scheduled_payments', 'safecontracts_payment_collections', 'paid_amount', 'remaining_amount', 'expected_payment_date', 'payment_method_id', 'proof_media_id', 'PaymentStatus', 'CollectionRepository'] as $forbidden) {
    esc_p9_012_assert(! str_contains($repositorySource, $forbidden) && ! str_contains($serviceSource, $forbidden), 'P9-012 avoids legacy settlement coupling: ' . $forbidden);
}
esc_p9_012_assert(! str_contains($legacyPaymentRepositorySource, 'ContractFinancialPaymentScheduleRevision'), 'legacy PaymentRepository has no reverse Enterprise coupling');
esc_p9_012_assert(! str_contains($legacyCollectionRepositorySource, 'ContractFinancialPaymentScheduleRevision'), 'legacy CollectionRepository has no reverse Enterprise coupling');
esc_p9_012_assert(! str_contains($reconciliationRepositorySource, 'financial_payment_schedule'), 'P9-006 reconciliation does not query schedule evidence');
esc_p9_012_assert(! str_contains($reconciliationServiceSource, 'ContractFinancialPaymentSchedule'), 'P9-006 service has no implicit schedule effect');
esc_p9_012_assert(! str_contains($routerSource, 'ContractFinancialPaymentScheduleRevision'), 'P9-012 exposes no REST route');

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(7);
$repository = new ContractFinancialPaymentScheduleRevisionRepository();
$contractActive = [['id' => '55', 'accountant_user_id' => '42', 'status' => 'active', 'is_archived' => '0']];
$profile = [['id' => '31', 'contract_id' => '55', 'contract_currency' => 'USD']];
$entryUuid = '11111111-1111-4111-8111-111111111111';

// Read locks/authorizes before profile/schedule state and canonicalizes stored values.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    $contractActive,
    $profile,
    [
        esc_p9_012_row($entryUuid, 2, '1', ' INV-1 ', '2026-09-30', '125.5', 'usd', 'SCHEDULED', 1001),
        esc_p9_012_row('22222222-2222-4222-8222-222222222222', 1, '2', null, '2026-10-31', '75', 'USD', 'scheduled', 1002),
    ],
];
$authorized = false;
$current = $repository->listCurrentForContract(55, static function (array $contract) use (&$authorized): void {
    esc_p9_012_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'schedule authorization runs before profile/schedule reads');
    esc_p9_012_assert(str_contains((string) $GLOBALS['sc_test_read_queries'][0], 'FOR UPDATE'), 'schedule authorization boundary is protected by Contract lock');
    $authorized = true;
});
esc_p9_012_assert($authorized, 'schedule locked authorization callback executes');
esc_p9_012_assert(count($current) === 2, 'current schedule returns latest entries');
esc_p9_012_assert($current[0]['sequence_no'] === 1 && $current[0]['reference'] === 'INV-1', 'schedule sequence/reference canonicalize');
esc_p9_012_assert($current[0]['amount'] === '125.5000' && $current[0]['currency_code'] === 'USD', 'schedule Money/currency canonicalize');
esc_p9_012_assert($current[1]['sequence_no'] === 2 && $current[1]['reference'] === null, 'nullable reference remains null');
$listSql = (string) end($GLOBALS['sc_test_read_queries']);
esc_p9_012_assert(str_contains($listSql, 'LIMIT 501') && str_contains($listSql, 'ORDER BY r.sequence_no ASC'), 'current schedule read is ordered and bounded');
esc_p9_012_assert(implode("\n", $GLOBALS['sc_test_queries']) === "START TRANSACTION\nCOMMIT", 'current schedule read uses one transaction');

// Completed/cancelled Contracts retain readable immutable schedule evidence.
foreach (['completed', 'cancelled'] as $status) {
    $GLOBALS['sc_test_result_queue'] = [
        [['id' => '55', 'accountant_user_id' => '42', 'status' => $status, 'is_archived' => '0']],
        $profile,
        [esc_p9_012_row($entryUuid, 2)],
    ];
    esc_p9_012_assert(count($repository->listCurrentForContract(55, static function (array $contract): void {})) === 1, "{$status} Contract retains payment schedule evidence");
}

// 501st latest identity is an overflow sentinel.
$overflow = [];
for ($i = 1; $i <= 501; $i++) {
    $overflow[] = esc_p9_012_row(sprintf('30000000-0000-4000-8000-%012x', $i), 1, $i, 'P-' . $i, '2026-12-31', '1.0000', 'USD', 'scheduled', 2000 + $i);
}
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, $overflow];
esc_p9_012_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), RuntimeException::class, '501 current schedule entries fail closed');

// Duplicate latest identity/sequence and corrupted profile/currency/date/amount fail closed.
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [
    esc_p9_012_row($entryUuid, 1, 1, 'A', '2026-09-30', '1', 'USD', 'scheduled', 2101),
    esc_p9_012_row($entryUuid, 2, 2, 'B', '2026-10-31', '2', 'USD', 'scheduled', 2102),
]];
esc_p9_012_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'duplicate latest schedule identities fail closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [
    esc_p9_012_row($entryUuid, 1, 1, 'A', '2026-09-30', '1', 'USD', 'scheduled', 2111),
    esc_p9_012_row('22222222-2222-4222-8222-222222222222', 1, 1, 'B', '2026-10-31', '2', 'USD', 'scheduled', 2112),
]];
esc_p9_012_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'duplicate latest schedule sequence fails closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_012_row($entryUuid, 1, 1, 'A', '2026-09-30', '1', 'USD', 'scheduled', 2201, 99)]];
esc_p9_012_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'cross-profile schedule evidence fails closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_012_row($entryUuid, 1, 1, 'A', '2026-09-30', '1', 'EUR')]];
esc_p9_012_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'cross-currency schedule evidence fails closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_012_row($entryUuid, 1, 1, 'A', '2026-02-30', '1', 'USD')]];
esc_p9_012_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'invalid persisted due date fails closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_012_row($entryUuid, 1, 1, 'A', '2026-09-30', '0.0000', 'USD')]];
esc_p9_012_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'zero persisted schedule amount fails closed');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_012_row($entryUuid, 1, 1, 'A', '2026-09-30', '-1.0000', 'USD')]];
esc_p9_012_expect_throw(static fn (): array => $repository->listCurrentForContract(55, static function (array $contract): void {}), UnexpectedValueException::class, 'negative persisted schedule amount fails closed');

// Create only on ACTIVE Contract, after profile authority, proving identity and permanent sequence availability.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [['total' => '0']], [], []];
$GLOBALS['wpdb']->insert_id = 0;
$created = $repository->createEntry(
    55,
    'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    '1',
    ' INV-001 ',
    '2026-09-30',
    '50.5',
    42,
    static function (array $contract): void {
        esc_p9_012_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'create authorization runs immediately after Contract lock');
    }
);
esc_p9_012_assert($created === 1001, 'active Contract schedule creation succeeds');
$createReads = implode("\n", array_map('strval', $GLOBALS['sc_test_read_queries']));
$createWrites = implode("\n", array_map('strval', $GLOBALS['sc_test_queries']));
esc_p9_012_assert(strpos($createReads, 'safecontracts_contracts') < strpos($createReads, 'safecontracts_contract_financial_currency_profiles'), 'schedule create locks Contract before profile');
esc_p9_012_assert(str_contains($createReads, 'schedule_entry_uuid = \'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa\'') && str_contains($createReads, 'FOR UPDATE'), 'schedule create proves generated identity unused');
esc_p9_012_assert(str_contains($createReads, 'sequence_no = 1') && str_contains($createReads, 'ORDER BY id ASC LIMIT 2 FOR UPDATE'), 'schedule create proves sequence has never been used');
esc_p9_012_assert(str_contains($createWrites, "1, 'INV-001', '2026-09-30', '50.5000', p.contract_currency, 'scheduled', 42"), 'schedule create persists canonical sequence/reference/date/Money');

// Non-active/archived Contracts cannot mutate schedule entries and fail before profile state is read.
foreach ([['draft', '0'], ['completed', '0'], ['cancelled', '0'], ['active', '1']] as [$status, $archived]) {
    $GLOBALS['sc_test_queries'] = [];
    $GLOBALS['sc_test_read_queries'] = [];
    $GLOBALS['sc_test_result_queue'] = [[['id' => '55', 'accountant_user_id' => '42', 'status' => $status, 'is_archived' => $archived]]];
    esc_p9_012_expect_throw(static fn (): int => $repository->createEntry(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 1, null, '2026-09-30', '1', 42, static function (array $contract): void {}), RuntimeException::class, "{$status}/archived Contract cannot mutate payment schedule");
    esc_p9_012_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'schedule lifecycle failure occurs before profile read');
}

// Strict-positive caller Money validates only after Contract/profile authority is known.
foreach (['0', '-0.0001'] as $invalidAmount) {
    $GLOBALS['sc_test_queries'] = [];
    $GLOBALS['sc_test_read_queries'] = [];
    $GLOBALS['sc_test_result_queue'] = [$contractActive, $profile];
    esc_p9_012_expect_throw(static fn (): int => $repository->createEntry(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 1, null, '2026-09-30', $invalidAmount, 42, static function (array $contract): void {}), InvalidArgumentException::class, 'non-positive caller schedule amount fails closed');
    esc_p9_012_assert(count($GLOBALS['sc_test_read_queries']) === 2, 'schedule amount validates only after Contract/profile authority is known');
    esc_p9_012_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'ROLLBACK'), 'invalid schedule amount rolls back');
}
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile];
esc_p9_012_expect_throw(static fn (): int => $repository->createEntry(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 1, null, '2026-09-30', 1.5, 42, static function (array $contract): void {}), InvalidArgumentException::class, 'float schedule amount fails closed');

// Max 500 stable identities and permanent sequence non-reuse.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [['total' => '500']]];
esc_p9_012_expect_throw(static fn (): int => $repository->createEntry(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 501, null, '2026-09-30', '1', 42, static function (array $contract): void {}), RuntimeException::class, '500-entry Contract cannot create a 501st schedule identity');
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [['total' => '1']], [], [['id' => '777', 'schedule_entry_uuid' => '99999999-9999-4999-8999-999999999999']]];
esc_p9_012_expect_throw(static fn (): int => $repository->createEntry(55, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 1, null, '2026-09-30', '1', 42, static function (array $contract): void {}), RuntimeException::class, 'historically used sequence cannot be reused by a new identity');

// Exact revise retry is idempotent; changed revision retains stable sequence and appends.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_012_row($entryUuid, 1, 1, 'INV-1', '2026-09-30', '100')]];
$retry = $repository->reviseEntry(55, $entryUuid, 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'INV-1', '2026-09-30', '100', 42, static function (array $contract): void {});
esc_p9_012_assert($retry === 1001 && ! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'exact schedule revise retry is idempotent');
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_012_row($entryUuid, 1, 1, 'INV-1', '2026-09-30', '100')]];
$GLOBALS['wpdb']->insert_id = 0;
$repository->reviseEntry(55, $entryUuid, 'dddddddd-dddd-4ddd-8ddd-dddddddddddd', 'INV-2', '2026-10-15', '75', 42, static function (array $contract): void {});
$reviseWrites = implode("\n", $GLOBALS['sc_test_queries']);
esc_p9_012_assert(str_contains($reviseWrites, "'11111111-1111-4111-8111-111111111111', 2, 1, 'INV-2', '2026-10-15', '75.0000', p.contract_currency, 'scheduled', 42"), 'changed schedule appends revision 2 while retaining sequence 1');

// Void copies immutable schedule snapshot; repeated void is idempotent and voided entry cannot reactivate.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_012_row($entryUuid, 1, 1, 'INV-1', '2026-09-30', '100')]];
$GLOBALS['wpdb']->insert_id = 0;
$repository->voidEntry(55, $entryUuid, 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee', 42, static function (array $contract): void {});
$voidWrites = implode("\n", $GLOBALS['sc_test_queries']);
esc_p9_012_assert(str_contains($voidWrites, "2, 1, 'INV-1', '2026-09-30', '100.0000', p.contract_currency, 'voided', 42"), 'schedule void preserves sequence/date/Money snapshot and appends terminal state');
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_012_row($entryUuid, 2, 1, 'INV-1', '2026-09-30', '100', 'USD', 'voided', 1002)]];
esc_p9_012_assert($repository->voidEntry(55, $entryUuid, 'ffffffff-ffff-4fff-8fff-ffffffffffff', 42, static function (array $contract): void {}) === 1002, 'repeated schedule void returns terminal revision');
esc_p9_012_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO'), 'repeated schedule void writes nothing');
$GLOBALS['sc_test_result_queue'] = [$contractActive, $profile, [esc_p9_012_row($entryUuid, 2, 1, 'INV-1', '2026-09-30', '100', 'USD', 'voided', 1002)]];
esc_p9_012_expect_throw(static fn (): int => $repository->reviseEntry(55, $entryUuid, '12345678-1234-4234-8234-123456789abc', 'Reactivate', '2026-10-01', '100', 42, static function (array $contract): void {}), RuntimeException::class, 'voided schedule entry cannot reactivate');

// Service owns authorization/scope; mutation capability is MANAGE_PAYMENTS and caller supplies no tenant/currency.
esc_p9_012_assert(str_contains($serviceSource, 'authorize(Capabilities::ACCESS)'), 'schedule reads require ACCESS');
esc_p9_012_assert(substr_count($serviceSource, 'authorize(Capabilities::MANAGE_PAYMENTS)') >= 3, 'schedule mutations require MANAGE_PAYMENTS');
esc_p9_012_assert(! str_contains($serviceSource, 'authorize(Capabilities::EDIT_CONTRACTS)'), 'schedule mutation does not borrow EDIT_CONTRACTS capability');
esc_p9_012_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'tenant role narrows schedule capabilities');
esc_p9_012_assert(str_contains($serviceSource, 'Capabilities::VIEW_ALL') && str_contains($serviceSource, 'Capabilities::VIEW_ASSIGNED'), 'schedule service preserves locked Contract scope');
esc_p9_012_assert(! str_contains($serviceSource, 'mixed $currency') && ! str_contains($serviceSource, '$tenantId'), 'schedule service exposes no caller currency/tenant');
esc_p9_012_assert(str_contains($serviceSource, 'random_bytes(16)') && str_contains($serviceSource, 'wp_generate_uuid4'), 'schedule identities are server-generated UUIDv4');
esc_p9_012_assert(str_contains($gateSource, 'enterprise_contract_financial_payment_schedule_p9_012.php'), 'P9-012 regression is wired into global backend gate');

fwrite(STDOUT, "P9-012 Enterprise Contract payment schedule revisions passed ({$assertions} assertions).\n");
