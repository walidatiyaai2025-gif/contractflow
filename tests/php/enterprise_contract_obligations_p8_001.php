<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Obligations\ObligationPolicy;
use SafeContracts\Obligations\ObligationRepository;
use SafeContracts\Obligations\ObligationService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

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
function esc_p8_obligation_throws(callable $callback, string $class, string $needle, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p8_obligation_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ': ' . $error->getMessage() . ')');
        esc_p8_obligation_assert(str_contains($error->getMessage(), $needle), $message . ' (message mismatch: ' . $error->getMessage() . ')');
        return;
    }
    esc_p8_obligation_assert(false, $message . ' (no exception)');
}
function esc_p8_membership(string $role = 'manager'): array
{
    return [['id'=>'1','tenant_id'=>'17','user_id'=>'42','role_code'=>$role,'is_owner'=>'0']];
}
function esc_p8_contract(int $accountant = 42, int $archived = 0): array
{
    return [['id'=>'71','accountant_user_id'=>(string)$accountant,'status'=>'active','is_archived'=>(string)$archived]];
}
function esc_p8_obligation_row(string $status = 'open'): array
{
    return [[
        'id'=>'901','uuid'=>'123e4567-e89b-42d3-a456-426614174000','contract_id'=>'71','obligation_code'=>'notice-01',
        'title'=>'Renewal notice','description'=>null,'due_date'=>'2027-02-28','status'=>$status,
        'completed_at'=>$status === 'completed' ? '2026-08-17 12:00:00' : null,
        'completed_by'=>$status === 'completed' ? '42' : null,
        'cancelled_at'=>$status === 'cancelled' ? '2026-08-17 12:00:00' : null,
        'cancelled_by'=>$status === 'cancelled' ? '42' : null,
        'created_by'=>'42','updated_by'=>'42','created_at'=>'2026-08-17 11:00:00','updated_at'=>'2026-08-17 12:00:00',
    ]];
}

update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(17);
$repo = new ObligationRepository();

// Every read is tenant+Contract scoped; object ID alone is never authorization.
$GLOBALS['sc_test_result_queue'] = [esc_p8_contract()[0] ? esc_p8_contract() : []];
$contract = $repo->findContract(71);
esc_p8_obligation_assert(is_array($contract), 'current-tenant Contract is readable');
$contractSql = (string) end($GLOBALS['sc_test_read_queries']);
esc_p8_obligation_assert(str_contains($contractSql, 'tenant_id = 17') && str_contains($contractSql, 'id = 71'), 'Contract lookup binds tenant and Contract identity');

$GLOBALS['sc_test_result_queue'] = [esc_p8_obligation_row()];
$found = $repo->find(71, 901);
esc_p8_obligation_assert(is_array($found) && (int)$found['id'] === 901, 'obligation read returns exact row');
$findSql = (string) end($GLOBALS['sc_test_read_queries']);
esc_p8_obligation_assert(str_contains($findSql, 'tenant_id = 17') && str_contains($findSql, 'contract_id = 71') && str_contains($findSql, 'id = 901'), 'obligation lookup cannot cross tenant or Contract boundary');

$GLOBALS['sc_test_result_queue'] = [esc_p8_obligation_row()];
$filters = ObligationPolicy::normalizeSearch(['status'=>'open','due_from'=>'2027-01-01','due_to'=>'2027-12-31','obligation_code'=>'NOTICE-01']);
$list = $repo->search(71, $filters, 500, -1);
esc_p8_obligation_assert(count($list) === 1, 'bounded obligation search returns tenant rows');
$searchSql = (string) end($GLOBALS['sc_test_read_queries']);
esc_p8_obligation_assert(str_contains($searchSql, 'tenant_id = 17') && str_contains($searchSql, 'contract_id = 71'), 'search is tenant+Contract scoped');
esc_p8_obligation_assert(str_contains($searchSql, "status = 'open'") && str_contains($searchSql, "obligation_code = 'notice-01'"), 'search filters use normalized policy values');
esc_p8_obligation_assert(str_contains($searchSql, 'LIMIT 100 OFFSET 0'), 'search limit/offset are bounded server-side');

// Creation is INSERT..SELECT from the same current-tenant, non-archived Contract.
$GLOBALS['wpdb']->insert_id = 0;
$GLOBALS['sc_test_queries'] = [];
$created = $repo->create(71, '123e4567-e89b-42d3-a456-426614174001', [
    'obligation_code'=>'notice-02','title'=>'Notice','description'=>null,'due_date'=>null,
], 42);
$createSql = (string) end($GLOBALS['sc_test_queries']);
esc_p8_obligation_assert((int)$created['id'] === 1001 && $created['status'] === 'open', 'create returns server-owned open identity');
esc_p8_obligation_assert(str_contains($createSql, 'FROM wp_safecontracts_contracts c') && str_contains($createSql, 'c.tenant_id = 17') && str_contains($createSql, 'c.id = 71') && str_contains($createSql, 'c.is_archived = 0'), 'create revalidates current-tenant mutable Contract in persistence query');
esc_p8_obligation_assert(str_contains($createSql, "'notice-02'") && str_contains($createSql, "'open'"), 'create persists immutable code and server lifecycle start');

// Metadata mutation is open-only and cannot rewrite immutable code/lifecycle fields.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p8_obligation_row()];
$repo->updateMetadata(71, 901, ['title'=>'Updated','description'=>null,'due_date'=>'2027-03-01'], 42);
$updateSql = (string) $GLOBALS['sc_test_queries'][0];
esc_p8_obligation_assert(str_contains($updateSql, "o.status = 'open'") && str_contains($updateSql, 'c.is_archived = 0'), 'metadata update is CAS-bounded to open obligation and mutable Contract');
esc_p8_obligation_assert(! str_contains($updateSql, 'obligation_code =') && ! str_contains($updateSql, 'o.status = %'), 'metadata update cannot rewrite immutable code or lifecycle target');

// Terminal transition is one compare-and-set from open with server actor/time evidence.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p8_obligation_row('completed')];
$terminal = $repo->transition(71, 901, 'completed', 42);
$transitionSql = (string) $GLOBALS['sc_test_queries'][0];
esc_p8_obligation_assert($terminal['idempotent'] === false && $terminal['obligation']['status'] === 'completed', 'open obligation completes once');
esc_p8_obligation_assert(str_contains($transitionSql, "o.status = 'completed'") && str_contains($transitionSql, "o.status = 'open'"), 'terminal update uses explicit open-to-terminal compare-and-set');
esc_p8_obligation_assert(str_contains($transitionSql, 'o.completed_at = UTC_TIMESTAMP()') && str_contains($transitionSql, 'o.completed_by = 42'), 'completion actor/timestamp are server-derived');
esc_p8_obligation_assert(str_contains($transitionSql, 'c.tenant_id = o.tenant_id') && str_contains($transitionSql, 'c.is_archived = 0'), 'terminal update revalidates tenant Contract mutability');

// Service-level VIEW_ASSIGNED scope blocks another accountant before obligation lookup/mutation.
$service = new ObligationService();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ASSIGNED=>true];
$GLOBALS['sc_test_result_queue'] = [esc_p8_membership(), esc_p8_contract(99)];
$readsBefore = count($GLOBALS['sc_test_read_queries']);
esc_p8_obligation_throws(static fn () => $service->get(71, 901), DomainException::class, 'outside the current user data scope', 'VIEW_ASSIGNED cannot read another accountant Contract obligation');
esc_p8_obligation_assert(count($GLOBALS['sc_test_read_queries']) === $readsBefore + 2, 'scope denial occurs before obligation ID lookup');

$GLOBALS['sc_test_current_caps'] = [Capabilities::EDIT_CONTRACTS=>true, Capabilities::VIEW_ASSIGNED=>true];
$GLOBALS['sc_test_result_queue'] = [esc_p8_membership(), esc_p8_contract(99)];
$writesBefore = count($GLOBALS['sc_test_queries']);
esc_p8_obligation_throws(static fn () => $service->updateMetadata(71, 901, ['title'=>'Nope']), DomainException::class, 'outside the current user data scope', 'VIEW_ASSIGNED cannot mutate another accountant Contract obligation');
esc_p8_obligation_assert(count($GLOBALS['sc_test_queries']) === $writesBefore, 'scope denial performs no mutation');

// Tenant role narrows a global edit grant.
$GLOBALS['sc_test_current_caps'] = [Capabilities::EDIT_CONTRACTS=>true, Capabilities::VIEW_ASSIGNED=>true];
$GLOBALS['sc_test_result_queue'] = [esc_p8_membership('accountant')];
esc_p8_obligation_throws(static fn () => $service->create(71, ['obligation_code'=>'x','title'=>'X']), DomainException::class, 'tenant role does not allow', 'accountant tenant role cannot widen itself into EDIT_CONTRACTS');

// Archived Contracts reject new mutations, but an exact already-committed terminal retry is mutation-free/idempotent.
$GLOBALS['sc_test_current_caps'] = [Capabilities::EDIT_CONTRACTS=>true, Capabilities::VIEW_ASSIGNED=>true];
$GLOBALS['sc_test_result_queue'] = [esc_p8_membership(), esc_p8_contract(42, 1)];
esc_p8_obligation_throws(static fn () => $service->create(71, ['obligation_code'=>'x','title'=>'X']), DomainException::class, 'Archived Contracts', 'archived Contract rejects obligation creation');

$GLOBALS['sc_test_result_queue'] = [esc_p8_membership(), esc_p8_contract(42, 1), esc_p8_obligation_row('completed')];
$writesBeforeRetry = count($GLOBALS['sc_test_queries']);
$retry = $service->complete(71, 901);
esc_p8_obligation_assert($retry['idempotent'] === true && $retry['obligation']['status'] === 'completed', 'exact terminal retry is idempotent even after later archive');
esc_p8_obligation_assert(count($GLOBALS['sc_test_queries']) === $writesBeforeRetry, 'exact terminal retry performs no mutation');

$GLOBALS['sc_test_result_queue'] = [esc_p8_membership(), esc_p8_contract(), esc_p8_obligation_row('completed')];
esc_p8_obligation_throws(static fn () => $service->cancel(71, 901), DomainException::class, 'conflicting status', 'conflicting terminal retry fails closed');

$root = dirname(__DIR__, 2);
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Obligations/ObligationRepository.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');
esc_p8_obligation_assert(str_contains($repositorySource, "o.status = %s AND c.is_archived = 0") && str_contains($repositorySource, 'terminal compare-and-set failed'), 'stale/concurrent terminal CAS has explicit fail-closed path');
esc_p8_obligation_assert(str_contains($gateSource, 'enterprise_contract_obligation_foundation_p8_001.php') && str_contains($gateSource, 'enterprise_contract_obligations_p8_001.php'), 'P8 adversarial regression is explicitly wired into global ESC backend gate');

echo "P8-001 Contract Obligation adversarial checks passed ({$assertions} assertions).\n";
