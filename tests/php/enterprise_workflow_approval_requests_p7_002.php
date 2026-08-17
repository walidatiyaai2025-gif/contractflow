<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Approvals\ApprovalRequestPolicy;
use SafeContracts\Approvals\ApprovalRequestRepository;
use SafeContracts\Approvals\ApprovalRequestService;
use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0040EnterpriseWorkflowApprovalRequests;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;
function esc_p7_req_assert(bool $condition, string $message): void { global $assertions; $assertions++; if (! $condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
function esc_p7_req_throws(callable $callback, string $class, string $message): void { try { $callback(); } catch (Throwable $error) { esc_p7_req_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ': ' . $error->getMessage() . ')'); return; } esc_p7_req_assert(false, $message . ' (no exception)'); }
function esc_p7_req_throws_contains(callable $callback, string $class, string $needle, string $message): void { try { $callback(); } catch (Throwable $error) { esc_p7_req_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ': ' . $error->getMessage() . ')'); esc_p7_req_assert(str_contains($error->getMessage(), $needle), $message . ' (message mismatch: ' . $error->getMessage() . ')'); return; } esc_p7_req_assert(false, $message . ' (no exception)'); }
function esc_p7_req_instance(): array { return [['contract_id'=>'71','accountant_user_id'=>'42','is_archived'=>'0','instance_id'=>'501','workflow_id'=>'81','workflow_version_id'=>'91','current_state_id'=>'301','current_state_code_snapshot'=>'draft']]; }
function esc_p7_req_transition(): array { return [['transition_id'=>'701','workflow_id'=>'81','workflow_version_id'=>'91','transition_code'=>'submit','source_state_id'=>'301','source_state_code'=>'draft','destination_state_id'=>'302','destination_state_code'=>'review']]; }
function esc_p7_req_route(bool $stale = false): array { return [['id'=>'1001','workflow_id'=>'81','workflow_version_id'=>'91','transition_id'=>'701','transition_code_snapshot'=>$stale?'old_submit':'submit','source_state_id_snapshot'=>'301','source_state_code_snapshot'=>'draft','destination_state_id_snapshot'=>'302','destination_state_code_snapshot'=>'review']]; }
function esc_p7_req_stage(string $policy='all', int $required=0): array { return [['id'=>'1101','position_no'=>'1','stage_code'=>'finance_review','name'=>'Finance review','decision_policy'=>$policy,'required_approvals'=>(string)$required]]; }
function esc_p7_req_selectors(bool $explicit = true, string $role = 'manager'): array {
    $rows=[]; $p=1;
    if ($explicit) { $rows[]=['id'=>'1201','position_no'=>(string)$p++,'selector_type'=>'tenant_user','selector_user_id'=>'55','selector_role_code'=>null,'selector_key'=>'user:55']; }
    $rows[]=['id'=>'1202','position_no'=>(string)$p,'selector_type'=>'tenant_role','selector_user_id'=>null,'selector_role_code'=>$role,'selector_key'=>'role:'.$role];
    return $rows;
}
function esc_p7_req_memberships(array $pairs): array { $rows=[]; foreach ($pairs as [$user,$role]) { $rows[]=['user_id'=>(string)$user,'role_code'=>$role]; } return $rows; }
function esc_p7_req_existing(string $code='submit', string $hash='hash'): array { return [['id'=>'2001','instance_id'=>'501','contract_id'=>'71','workflow_id'=>'81','workflow_version_id'=>'91','transition_id'=>'701','transition_code_snapshot'=>$code,'from_state_id'=>'301','from_state_code_snapshot'=>'draft','to_state_id'=>'302','to_state_code_snapshot'=>'review','route_id_snapshot'=>'1001','request_key_hash'=>$hash,'status'=>'pending','requester_user_id'=>'42','requested_at'=>'2026-08-17 03:45:00']]; }

$root=dirname(__DIR__,2);
$migrationSource=(string)file_get_contents($root.'/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0040EnterpriseWorkflowApprovalRequests.php');
$policySource=(string)file_get_contents($root.'/wordpress-plugin/safecontracts/src/Approvals/ApprovalRequestPolicy.php');
$repositorySource=(string)file_get_contents($root.'/wordpress-plugin/safecontracts/src/Approvals/ApprovalRequestRepository.php');
$serviceSource=(string)file_get_contents($root.'/wordpress-plugin/safecontracts/src/Approvals/ApprovalRequestService.php');
$transitionRepositorySource=(string)file_get_contents($root.'/wordpress-plugin/safecontracts/src/Workflows/ContractWorkflowTransitionRepository.php');
$gateSource=(string)file_get_contents($root.'/scripts/test-php.sh');
$statusSource=(string)file_get_contents($root.'/wordpress-plugin/safecontracts/src/Contracts/ContractStatus.php');
$migratorSource=(string)file_get_contents($root.'/wordpress-plugin/safecontracts/src/Database/Migrator.php');

$GLOBALS['sc_test_dbdelta']=[];
(new Migration0040EnterpriseWorkflowApprovalRequests())->up($GLOBALS['wpdb']);
$schema=implode("\n",$GLOBALS['sc_test_dbdelta']);
esc_p7_req_assert(str_contains($schema,'CREATE TABLE wp_safecontracts_workflow_approval_requests'),'P7-002 creates request table');
esc_p7_req_assert(str_contains($schema,'CREATE TABLE wp_safecontracts_workflow_approval_request_stages'),'P7-002 creates request stage table');
esc_p7_req_assert(str_contains($schema,'CREATE TABLE wp_safecontracts_workflow_approval_request_selectors'),'P7-002 creates selector snapshot table');
esc_p7_req_assert(str_contains($schema,'CREATE TABLE wp_safecontracts_workflow_approval_request_candidates'),'P7-002 creates candidate snapshot table');
esc_p7_req_assert(str_contains($schema,'UNIQUE KEY tenant_instance_request_key'),'Approval Request has separate instance idempotency identity');
esc_p7_req_assert(str_contains($schema,"status varchar(20) NOT NULL DEFAULT 'pending'"),'Approval Request starts pending');
esc_p7_req_assert(str_contains($schema,'UNIQUE KEY tenant_request_stage_user'),'resolved candidates de-duplicate per stage');
esc_p7_req_assert(str_contains($schema,'request_key_hash char(64) NOT NULL')&&!str_contains($schema,'request_key varchar'),'only the SHA-256 request identity is persisted');
esc_p7_req_assert(!str_contains($migrationSource,'ALTER TABLE'),'P7-002 migration is additive');
esc_p7_req_assert(version_compare(Migrator::LATEST_VERSION,'1.39.0','>='),'P7-002 migration remains at or before the current schema version');
esc_p7_req_assert(str_contains($migratorSource,"'1.39.0' => Migration0040EnterpriseWorkflowApprovalRequests::class"),'P7-002 migration registered');

esc_p7_req_assert(ApprovalRequestPolicy::normalizeTransitionCode(' Submit ')==='submit','transition code canonicalized');
esc_p7_req_assert(ApprovalRequestPolicy::normalizeIdempotencyKey('  request-1  ')==='request-1','request key trimmed');
esc_p7_req_assert(strlen(ApprovalRequestPolicy::requestKeyHash('request-1'))===64,'request key hashes to sha256');
esc_p7_req_throws(static fn()=>ApprovalRequestPolicy::normalizeIdempotencyKey(''),InvalidArgumentException::class,'empty request key rejected');
esc_p7_req_throws(static fn()=>ApprovalRequestPolicy::normalizeIdempotencyKey(str_repeat('x',192)),InvalidArgumentException::class,'oversized request key rejected');
esc_p7_req_throws(static fn()=>ApprovalRequestPolicy::normalizeIdempotencyKey("bad\nkey"),InvalidArgumentException::class,'control characters rejected');

update_option(CoreTenantEnforcement::OPTION,'1',false); TenantContextStore::reset(); TenantContextStore::context()->setTenantId(17);
$repository=new ApprovalRequestRepository();

// Happy path: explicit user + role resolve to two distinct candidates; callback runs before persistence.
$GLOBALS['wpdb']->insert_id=0; $GLOBALS['sc_test_queries']=[]; $GLOBALS['sc_test_read_queries']=[];
$GLOBALS['sc_test_result_queue']=[esc_p7_req_instance(),[],esc_p7_req_transition(),[],esc_p7_req_route(),esc_p7_req_stage(),esc_p7_req_selectors(),esc_p7_req_memberships([[55,'manager'],[66,'manager']])];
$callbackCount=0;
$result=$repository->createRequest(71,501,'submit',ApprovalRequestPolicy::requestKeyHash('request-1'),42,static function(array $transition) use (&$callbackCount): void { $callbackCount++; esc_p7_req_assert((int)($transition['transition_id']??0)===701,'guard callback receives exact transition'); });
esc_p7_req_assert($callbackCount===1,'guard callback runs once for new routed request');
esc_p7_req_assert($result['approval_required']===true&&$result['created']===true,'routed transition creates approval request');
esc_p7_req_assert(($result['request']['status']??'')==='pending','new request is pending');
$writes=implode("\n",$GLOBALS['sc_test_queries']); $reads=implode("\n",$GLOBALS['sc_test_read_queries']);
esc_p7_req_assert(($GLOBALS['sc_test_queries'][0]??'')==='START TRANSACTION'&&end($GLOBALS['sc_test_queries'])==='COMMIT','request creation is transactional');
esc_p7_req_assert(str_contains($reads,'safecontracts_contract_workflow_instances')&&str_contains($reads,'LIMIT 1 FOR UPDATE'),'request locks exact contract instance first');
esc_p7_req_assert(str_contains($reads,'safecontracts_workflow_transition_approval_routes')&&str_contains($reads,'FOR UPDATE'),'request locks published route snapshot');
esc_p7_req_assert(str_contains($reads,'ORDER BY m.user_id ASC')&&str_contains($reads,"m.status = 'active'")&&str_contains($reads,"t.status = 'active'"),'candidate memberships are active and canonically locked');
esc_p7_req_assert(str_contains($writes,'INSERT INTO wp_safecontracts_workflow_approval_requests'),'request row persisted');
esc_p7_req_assert(str_contains($writes,'INSERT INTO wp_safecontracts_workflow_approval_request_stages'),'stage snapshot persisted');
esc_p7_req_assert(substr_count($writes,'INSERT INTO wp_safecontracts_workflow_approval_request_selectors')===2,'selector snapshots persisted');
esc_p7_req_assert(substr_count($writes,'INSERT INTO wp_safecontracts_workflow_approval_request_candidates')===2,'distinct candidate snapshots persisted');
esc_p7_req_assert(str_contains($writes,'VALUES (17, 1001, 1001, 55)')&&str_contains($writes,'VALUES (17, 1001, 1001, 66)'),'candidate users persist deterministically');
esc_p7_req_assert(!str_contains($writes,'UPDATE wp_safecontracts_contract_workflow_instances'),'approval request does not move workflow state');
esc_p7_req_assert(!str_contains($writes,'safecontracts_contract_workflow_transition_history'),'approval request does not create P6 transition history');

// No route: explicit result, no request persistence, no guard callback.
$GLOBALS['wpdb']->insert_id=0; $GLOBALS['sc_test_queries']=[]; $GLOBALS['sc_test_read_queries']=[];
$GLOBALS['sc_test_result_queue']=[esc_p7_req_instance(),[],esc_p7_req_transition(),[],[]]; $noRouteCallback=0;
$noRoute=$repository->createRequest(71,501,'submit',ApprovalRequestPolicy::requestKeyHash('no-route'),42,static function() use (&$noRouteCallback): void { $noRouteCallback++; });
esc_p7_req_assert($noRoute['approval_required']===false&&$noRoute['request']===null&&$noRoute['created']===false,'no route returns approval_not_required semantics');
esc_p7_req_assert($noRouteCallback===0,'no-route result does not evaluate approval-request guards');
esc_p7_req_assert($GLOBALS['sc_test_queries']===['START TRANSACTION','COMMIT'],'no-route path performs no persistence or P6 execution');

// Exact retry returns original request before transition/route/guard lookup.
$retryHash=ApprovalRequestPolicy::requestKeyHash('retry-1');
$GLOBALS['sc_test_queries']=[]; $GLOBALS['sc_test_read_queries']=[]; $GLOBALS['sc_test_result_queue']=[esc_p7_req_instance(),esc_p7_req_existing('submit',$retryHash)]; $retryCallback=0;
$retry=$repository->createRequest(71,501,'submit',$retryHash,42,static function() use (&$retryCallback): void { $retryCallback++; });
esc_p7_req_assert($retry['approval_required']===true&&$retry['created']===false&&($retry['request']['id']??'')==='2001','exact retry returns original immutable request');
esc_p7_req_assert($retryCallback===0,'exact retry does not re-run route/guards');
esc_p7_req_assert($GLOBALS['sc_test_queries']===['START TRANSACTION','COMMIT'],'exact retry has no duplicate persistence');

// Same key for another transition fails closed.
$GLOBALS['sc_test_queries']=[]; $GLOBALS['sc_test_result_queue']=[esc_p7_req_instance(),esc_p7_req_existing('other',$retryHash)];
esc_p7_req_throws(static fn()=>$repository->createRequest(71,501,'submit',$retryHash,42),RuntimeException::class,'request key conflict rejected');
esc_p7_req_assert(in_array('ROLLBACK',$GLOBALS['sc_test_queries'],true),'request key conflict rolls back');

// Different key cannot open a second pending process for same transition/source.
$GLOBALS['sc_test_queries']=[]; $GLOBALS['sc_test_result_queue']=[esc_p7_req_instance(),[],esc_p7_req_transition(),[['id'=>'2002','request_key_hash'=>'otherhash']]];
esc_p7_req_throws(static fn()=>$repository->createRequest(71,501,'submit',ApprovalRequestPolicy::requestKeyHash('different'),42),RuntimeException::class,'different key cannot duplicate pending approval process');
esc_p7_req_assert(!array_filter($GLOBALS['sc_test_queries'],static fn(string $sql):bool=>str_starts_with(ltrim($sql),'INSERT INTO wp_safecontracts_workflow_approval_requests')),'pending conflict is detected before request insert');

// Stale route fails before guard/persistence.
$GLOBALS['sc_test_queries']=[]; $GLOBALS['sc_test_result_queue']=[esc_p7_req_instance(),[],esc_p7_req_transition(),[],esc_p7_req_route(true)];
esc_p7_req_throws(static fn()=>$repository->createRequest(71,501,'submit',ApprovalRequestPolicy::requestKeyHash('stale'),42),RuntimeException::class,'stale route snapshot rejected');

// Role selector with no active members resolves to zero candidates.
$GLOBALS['sc_test_queries']=[]; $GLOBALS['sc_test_result_queue']=[esc_p7_req_instance(),[],esc_p7_req_transition(),[],esc_p7_req_route(),esc_p7_req_stage(),esc_p7_req_selectors(false),[]];
esc_p7_req_throws(static fn()=>$repository->createRequest(71,501,'submit',ApprovalRequestPolicy::requestKeyHash('empty-role'),42),RuntimeException::class,'zero-candidate stage rejected');
esc_p7_req_assert(!array_filter($GLOBALS['sc_test_queries'],static fn(string $sql):bool=>str_starts_with(ltrim($sql),'INSERT INTO wp_safecontracts_workflow_approval_requests')),'zero-candidate failure occurs before request insert');

// Quorum is checked against distinct candidates after explicit+role de-duplication.
$GLOBALS['sc_test_queries']=[]; $GLOBALS['sc_test_result_queue']=[esc_p7_req_instance(),[],esc_p7_req_transition(),[],esc_p7_req_route(),esc_p7_req_stage('quorum',2),esc_p7_req_selectors(),esc_p7_req_memberships([[55,'manager']])];
esc_p7_req_throws(static fn()=>$repository->createRequest(71,501,'submit',ApprovalRequestPolicy::requestKeyHash('dedupe-quorum'),42),RuntimeException::class,'quorum exceeding distinct candidates rejected');

// Per-stage candidate expansion fails closed rather than truncating.
$overflow=[]; for($i=1;$i<=257;$i++){ $overflow[]=[$i,'manager']; }
$GLOBALS['sc_test_queries']=[]; $GLOBALS['sc_test_result_queue']=[esc_p7_req_instance(),[],esc_p7_req_transition(),[],esc_p7_req_route(),esc_p7_req_stage(),esc_p7_req_selectors(false),esc_p7_req_memberships($overflow)];
esc_p7_req_throws(static fn()=>$repository->createRequest(71,501,'submit',ApprovalRequestPolicy::requestKeyHash('overflow'),42),RuntimeException::class,'stage candidate overflow rejected');

// Request-wide membership expansion is independently bounded at MAX+1 before any request snapshot write.
$requestOverflow=[]; for($i=1;$i<=ApprovalRequestPolicy::MAX_CANDIDATES_PER_REQUEST+1;$i++){ $requestOverflow[]=[$i,'manager']; }
$GLOBALS['sc_test_queries']=[]; $GLOBALS['sc_test_result_queue']=[esc_p7_req_instance(),[],esc_p7_req_transition(),[],esc_p7_req_route(),esc_p7_req_stage(),esc_p7_req_selectors(false),esc_p7_req_memberships($requestOverflow)];
esc_p7_req_throws_contains(static fn()=>$repository->createRequest(71,501,'submit',ApprovalRequestPolicy::requestKeyHash('request-overflow'),42),RuntimeException::class,'request bound','request-wide candidate membership overflow rejected before stage snapshotting');
esc_p7_req_assert(!array_filter($GLOBALS['sc_test_queries'],static fn(string $sql):bool=>str_starts_with(ltrim($sql),'INSERT INTO wp_safecontracts_workflow_approval_requests')),'request-wide candidate overflow occurs before request insert');

// Guard failure is before all runtime snapshot inserts and rolls back locks/readiness together.
$GLOBALS['sc_test_queries']=[]; $GLOBALS['sc_test_result_queue']=[esc_p7_req_instance(),[],esc_p7_req_transition(),[],esc_p7_req_route(),esc_p7_req_stage(),esc_p7_req_selectors(false),esc_p7_req_memberships([[66,'manager']])];
esc_p7_req_throws(static fn()=>$repository->createRequest(71,501,'submit',ApprovalRequestPolicy::requestKeyHash('guard-fail'),42,static function():void{ throw new DomainException('guard blocked'); }),DomainException::class,'guard failure blocks approval request');
esc_p7_req_assert(in_array('ROLLBACK',$GLOBALS['sc_test_queries'],true),'guard failure rolls back transaction');
esc_p7_req_assert(!array_filter($GLOBALS['sc_test_queries'],static fn(string $sql):bool=>str_starts_with(ltrim($sql),'INSERT INTO wp_safecontracts_workflow_approval_requests')),'guard failure occurs before request persistence');

$GLOBALS['sc_test_current_caps']=[Capabilities::ACCESS=>true,Capabilities::EDIT_CONTRACTS=>false,Capabilities::VIEW_ALL=>true,Capabilities::VIEW_ASSIGNED=>true];
$service=new ApprovalRequestService(); $GLOBALS['sc_test_queries']=[]; $GLOBALS['sc_test_read_queries']=[];
esc_p7_req_throws(static fn()=>$service->request(71,'submit','denied'),DomainException::class,'Approval Request creation requires EDIT_CONTRACTS');
esc_p7_req_assert($GLOBALS['sc_test_queries']===[]&&$GLOBALS['sc_test_read_queries']===[],'global capability denial occurs before data access');

esc_p7_req_assert(str_contains($serviceSource,'WorkflowTransitionGuardEvaluator')&&str_contains($serviceSource,'assertAllowed'),'P7-002 service enforces P6-004 guards');
esc_p7_req_assert(str_contains($repositorySource,'A different pending Approval Request already exists'),'repository prevents duplicate pending process');
esc_p7_req_assert(str_contains($repositorySource,'ORDER BY m.user_id ASC LIMIT %d FOR UPDATE'),'candidate memberships use deterministic lock order');
esc_p7_req_assert(str_contains($repositorySource,'MAX_CANDIDATES_PER_STAGE')&&str_contains($repositorySource,'MAX_CANDIDATES_PER_REQUEST'),'candidate expansion is bounded');
esc_p7_req_assert(!str_contains($repositorySource,'UPDATE {$instances}')&&!str_contains($repositorySource,'contract_workflow_transition_history'),'repository has no P6 state/history mutation path');
esc_p7_req_assert(str_contains($transitionRepositorySource,'safecontracts_contract_workflow_transition_history'),'P6 transition history remains owned by P6 repository only');
esc_p7_req_assert(str_contains($statusSource,'final class ContractStatus')&&!str_contains($statusSource,'ApprovalRequest'),'legacy ContractStatus remains independent');
esc_p7_req_assert(!str_contains($policySource,'eval(')&&!str_contains($policySource,'exec('),'request policy contains no executable expression engine');
esc_p7_req_assert(str_contains($gateSource,'enterprise_workflow_approval_requests_p7_002.php'),'P7-002 regression explicitly wired into backend Gate');

CoreTenantEnforcement::disable(); TenantContextStore::reset();
echo "P7-002 Enterprise Workflow approval request checks passed ({$assertions} assertions).\n";
