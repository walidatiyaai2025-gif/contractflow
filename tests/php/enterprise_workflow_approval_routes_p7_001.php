<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Approvals\ApprovalRoutePolicy;
use SafeContracts\Approvals\ApprovalRouteRepository;
use SafeContracts\Approvals\ApprovalRouteService;
use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0039EnterpriseWorkflowTransitionApprovalRoutes;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;
function esc_p7_route_assert(bool $condition, string $message): void { global $assertions; $assertions++; if (! $condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
function esc_p7_route_throws(callable $callback, string $class, string $message): void { try { $callback(); } catch (Throwable $error) { esc_p7_route_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ': ' . $error->getMessage() . ')'); return; } esc_p7_route_assert(false, $message . ' (no exception)'); }
function esc_p7_route_actor(string $role = 'tenant_admin'): array { return [['id'=>'1','tenant_id'=>'17','user_id'=>'42','role_code'=>$role,'is_owner'=>'0']]; }
function esc_p7_route_workflow(string $status = 'active'): array { return [['id'=>'81','uuid'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','contract_type_id'=>'31','workflow_code'=>'standard_flow','name'=>'Standard Flow','description'=>'','status'=>$status,'created_by'=>'42','updated_by'=>'42']]; }
function esc_p7_route_version(string $status = 'draft'): array { return [['id'=>'91','workflow_id'=>'81','version_no'=>'3','version_status'=>$status,'created_by'=>'42','updated_by'=>'42']]; }
function esc_p7_route_transition(): array { return [['id'=>'701','transition_code'=>'submit','source_state_id'=>'301','source_state_code'=>'draft','destination_state_id'=>'302','destination_state_code'=>'review']]; }
function esc_p7_route_locked_transition(): array { return [['id'=>'701','transition_code'=>'submit','source_state_id'=>'301','source_state_code'=>'draft','destination_state_id'=>'302','destination_state_code'=>'review']]; }
function esc_p7_route_membership(int $userId = 55): array { return [['id'=>'9','tenant_id'=>'17','user_id'=>(string)$userId,'role_code'=>'manager','is_owner'=>'0']]; }
function esc_p7_route_publish_row(bool $stale = false): array { return [[
    'id'=>'1001','workflow_id'=>'81','workflow_version_id'=>'91','transition_id'=>'701',
    'transition_code_snapshot'=>$stale?'old_submit':'submit','source_state_id_snapshot'=>'301','source_state_code_snapshot'=>'draft',
    'destination_state_id_snapshot'=>'302','destination_state_code_snapshot'=>'review',
    'current_transition_id'=>'701','current_transition_code'=>'submit','current_source_state_id'=>'301','current_source_state_code'=>'draft',
    'current_destination_state_id'=>'302','current_destination_state_code'=>'review'
]]; }
function esc_p7_route_stage(string $policy = 'all', int $required = 0): array { return [['id'=>'1101','position_no'=>'1','stage_code'=>'finance_review','name'=>'Finance review','decision_policy'=>$policy,'required_approvals'=>(string)$required]]; }
function esc_p7_route_stage_rows(int $count): array {
    $rows = [];
    for ($i = 1; $i <= $count; $i++) {
        $rows[] = ['id'=>(string)(1100+$i),'position_no'=>(string)$i,'stage_code'=>'stage_'.$i,'name'=>'Stage '.$i,'decision_policy'=>'all','required_approvals'=>'0'];
    }
    return $rows;
}
function esc_p7_route_user_selector(int $userId = 55): array { return [['position_no'=>'1','selector_type'=>'tenant_user','selector_user_id'=>(string)$userId,'selector_role_code'=>null,'selector_key'=>'user:'.$userId]]; }
function esc_p7_route_user_selectors(array $userIds): array {
    $rows = [];
    foreach (array_values($userIds) as $index => $userId) {
        $rows[] = ['position_no'=>(string)($index+1),'selector_type'=>'tenant_user','selector_user_id'=>(string)$userId,'selector_role_code'=>null,'selector_key'=>'user:'.$userId];
    }
    return $rows;
}
function esc_p7_route_role_selector(string $role = 'manager'): array { return [['position_no'=>'1','selector_type'=>'tenant_role','selector_user_id'=>null,'selector_role_code'=>$role,'selector_key'=>'role:'.$role]]; }
function esc_p7_route_membership_queries(): array {
    return array_values(array_filter($GLOBALS['sc_test_read_queries'], static fn(string $sql): bool => str_contains($sql, 'safecontracts_tenant_memberships')));
}

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0039EnterpriseWorkflowTransitionApprovalRoutes.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Approvals/ApprovalRoutePolicy.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Approvals/ApprovalRouteRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Approvals/ApprovalRouteService.php');
$workflowRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Workflows/WorkflowDefinitionRepository.php');
$transitionRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Workflows/ContractWorkflowTransitionRepository.php');
$transitionServiceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Workflows/ContractWorkflowTransitionService.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');
$statusSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Contracts/ContractStatus.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0039EnterpriseWorkflowTransitionApprovalRoutes())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);
esc_p7_route_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_workflow_transition_approval_routes'), 'P7-001 creates route table');
esc_p7_route_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_workflow_transition_approval_stages'), 'P7-001 creates stage table');
esc_p7_route_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_workflow_transition_approval_selectors'), 'P7-001 creates selector table');
esc_p7_route_assert(str_contains($schema, 'tenant_id bigint(20) unsigned NOT NULL'), 'approval schema is tenant owned');
esc_p7_route_assert(str_contains($schema, 'UNIQUE KEY tenant_version_transition'), 'one route per tenant version transition');
esc_p7_route_assert(str_contains($schema, 'UNIQUE KEY tenant_route_position') && str_contains($schema, 'UNIQUE KEY tenant_route_code'), 'stage position and code are unique');
esc_p7_route_assert(str_contains($schema, 'UNIQUE KEY tenant_stage_selector') && str_contains($schema, 'selector_key varchar(100) NOT NULL'), 'canonical selector uniqueness is persisted');
esc_p7_route_assert(! str_contains($migrationSource, 'ALTER TABLE'), 'P7-001 migration is additive');
esc_p7_route_assert(version_compare(Migrator::LATEST_VERSION, '1.38.0', '>='), 'P7-001 migration remains at or before the current schema version');
esc_p7_route_assert(str_contains($migratorSource, "'1.38.0' => Migration0039EnterpriseWorkflowTransitionApprovalRoutes::class"), 'P7-001 migration registered');

$allRoute = ApprovalRoutePolicy::normalizeRoute([[
    'stage_code' => ' Finance Review ', 'name' => 'Finance Review', 'decision_policy' => 'ALL', 'required_approvals' => 0,
    'selectors' => [
        ['selector_type' => 'tenant_user', 'user_id' => 55],
        ['selector_type' => 'tenant_role', 'role_code' => ' MANAGER '],
    ],
]]);
esc_p7_route_assert($allRoute[0]['stage_code'] === 'finance_review', 'stage code canonicalized');
esc_p7_route_assert($allRoute[0]['position_no'] === 1, 'stage position server controlled');
esc_p7_route_assert($allRoute[0]['decision_policy'] === 'all' && $allRoute[0]['required_approvals'] === 0, 'all policy canonicalized');
esc_p7_route_assert($allRoute[0]['selectors'][0]['selector_key'] === 'user:55', 'user selector key canonicalized');
esc_p7_route_assert($allRoute[0]['selectors'][1]['selector_key'] === 'role:manager', 'role selector key canonicalized');
esc_p7_route_assert(ApprovalRoutePolicy::normalizeRoute([]) === [], 'empty route means no approval route');
$quorum = ApprovalRoutePolicy::normalizeRoute([[
    'stage_code'=>'legal','name'=>'Legal','decision_policy'=>'quorum','required_approvals'=>1,
    'selectors'=>[['selector_type'=>'tenant_role','role_code'=>'manager'],['selector_type'=>'tenant_role','role_code'=>'tenant_admin']],
]]);
esc_p7_route_assert($quorum[0]['required_approvals'] === 1, 'valid quorum preserved');
esc_p7_route_throws(static fn()=>ApprovalRoutePolicy::normalizeRoute([['stage_code'=>'x','name'=>'X','decision_policy'=>'all','required_approvals'=>1,'selectors'=>[['selector_type'=>'tenant_role','role_code'=>'manager']]]]), InvalidArgumentException::class, 'all rejects caller threshold');
esc_p7_route_throws(static fn()=>ApprovalRoutePolicy::normalizeRoute([['stage_code'=>'x','name'=>'X','decision_policy'=>'quorum','required_approvals'=>2,'selectors'=>[['selector_type'=>'tenant_role','role_code'=>'manager']]]]), InvalidArgumentException::class, 'quorum cannot exceed selectors');
esc_p7_route_throws(static fn()=>ApprovalRoutePolicy::normalizeRoute([['stage_code'=>'x','name'=>'X','decision_policy'=>'first','required_approvals'=>0,'selectors'=>[['selector_type'=>'tenant_role','role_code'=>'manager']]]]), InvalidArgumentException::class, 'unsupported decision policy rejected');
esc_p7_route_throws(static fn()=>ApprovalRoutePolicy::normalizeRoute([['stage_code'=>'x','name'=>'X','decision_policy'=>'all','required_approvals'=>0,'selectors'=>[]]]), InvalidArgumentException::class, 'empty stage selector set rejected');
esc_p7_route_throws(static fn()=>ApprovalRoutePolicy::normalizeRoute([['stage_code'=>'x','name'=>'X','decision_policy'=>'all','required_approvals'=>0,'selectors'=>[['selector_type'=>'tenant_role','role_code'=>'member']]]]), InvalidArgumentException::class, 'legacy member role is not route assignable');
esc_p7_route_throws(static fn()=>ApprovalRoutePolicy::normalizeRoute([['stage_code'=>'x','name'=>'X','decision_policy'=>'all','required_approvals'=>0,'selectors'=>[['selector_type'=>'org_unit','user_id'=>55]]]]), InvalidArgumentException::class, 'unsupported selector type rejected');
esc_p7_route_throws(static fn()=>ApprovalRoutePolicy::normalizeRoute([['stage_code'=>'x','name'=>'X','decision_policy'=>'all','required_approvals'=>0,'selectors'=>[['selector_type'=>'tenant_user','user_id'=>55],['selector_type'=>'tenant_user','user_id'=>55]]]]), InvalidArgumentException::class, 'duplicate selector rejected');
esc_p7_route_throws(static fn()=>ApprovalRoutePolicy::normalizeRoute([
    ['stage_code'=>'dup','name'=>'A','decision_policy'=>'all','required_approvals'=>0,'selectors'=>[['selector_type'=>'tenant_role','role_code'=>'manager']]],
    ['stage_code'=>'dup','name'=>'B','decision_policy'=>'all','required_approvals'=>0,'selectors'=>[['selector_type'=>'tenant_role','role_code'=>'tenant_admin']]],
]), InvalidArgumentException::class, 'duplicate stage code rejected');
esc_p7_route_throws(static fn()=>ApprovalRoutePolicy::normalizeRoute(array_fill(0, 33, ['stage_code'=>'x','name'=>'X','decision_policy'=>'all','required_approvals'=>0,'selectors'=>[['selector_type'=>'tenant_role','role_code'=>'manager']]])), InvalidArgumentException::class, 'stage count bounded');
esc_p7_route_throws(static fn()=>ApprovalRoutePolicy::normalizeRoute([['stage_code'=>'x','name'=>'X','decision_policy'=>'all','required_approvals'=>0,'selectors'=>array_fill(0,65,['selector_type'=>'tenant_role','role_code'=>'manager'])]]), InvalidArgumentException::class, 'selectors per stage bounded');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::MANAGE_REFERENCE_DATA => true,
    Capabilities::EDIT_CONTRACTS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::VIEW_ASSIGNED => true,
];
update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(17);
$service = new ApprovalRouteService();
$GLOBALS['wpdb']->insert_id = 0;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p7_route_actor(), esc_p7_route_workflow(), esc_p7_route_version(), esc_p7_route_transition(),
    esc_p7_route_locked_transition(), [], esc_p7_route_membership(55),
];
$service->replaceDraftRoute(81, 91, 701, [[
    'stage_code'=>'finance_review','name'=>'Finance review','decision_policy'=>'all','required_approvals'=>0,
    'selectors'=>[['selector_type'=>'tenant_user','user_id'=>55],['selector_type'=>'tenant_role','role_code'=>'manager']],
]]);
esc_p7_route_assert(($GLOBALS['sc_test_queries'][0] ?? '') === 'START TRANSACTION', 'route replacement starts transaction');
esc_p7_route_assert(str_contains(implode("\n", $GLOBALS['sc_test_read_queries']), "v.version_status = 'draft'") && str_contains(implode("\n", $GLOBALS['sc_test_read_queries']), 'LIMIT 1 FOR UPDATE'), 'route authoring locks exact draft transition');
esc_p7_route_assert(str_contains(implode("\n", $GLOBALS['sc_test_read_queries']), 'safecontracts_tenant_memberships') && str_contains(implode("\n", $GLOBALS['sc_test_read_queries']), "m.status = 'active'"), 'tenant user selector requires active tenant membership');
$writes = implode("\n", $GLOBALS['sc_test_queries']);
esc_p7_route_assert(str_contains($writes, 'INSERT INTO wp_safecontracts_workflow_transition_approval_routes'), 'route row persisted');
esc_p7_route_assert(str_contains($writes, 'INSERT INTO wp_safecontracts_workflow_transition_approval_stages'), 'stage row persisted');
esc_p7_route_assert(substr_count($writes, 'INSERT INTO wp_safecontracts_workflow_transition_approval_selectors') === 2, 'parallel selectors persisted');
esc_p7_route_assert(str_contains($writes, "'user:55'") && str_contains($writes, "'role:manager'"), 'canonical selector identities persisted');
esc_p7_route_assert(end($GLOBALS['sc_test_queries']) === 'COMMIT', 'route replacement commits');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p7_route_actor(), esc_p7_route_workflow(), esc_p7_route_version('published')];
esc_p7_route_throws(static fn()=>$service->replaceDraftRoute(81,91,701,[]), InvalidArgumentException::class, 'published approval route immutable');
esc_p7_route_assert($GLOBALS['sc_test_queries'] === [], 'published route rejection performs no write');

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = false;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p7_route_throws(static fn()=>$service->replaceDraftRoute(81,91,701,[]), DomainException::class, 'route authoring requires manage reference data');
esc_p7_route_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'global capability denial happens before data access');
$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = true;
$GLOBALS['sc_test_result_queue'] = [esc_p7_route_actor('viewer')];
esc_p7_route_throws(static fn()=>$service->replaceDraftRoute(81,91,701,[]), DomainException::class, 'tenant viewer cannot author approval routes');

$repository = new ApprovalRouteRepository();
$GLOBALS['wpdb']->insert_id = 0;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p7_route_locked_transition(), [], []];
esc_p7_route_throws(static fn()=>$repository->replaceDraftRoute(81,91,701,$allRoute,42), RuntimeException::class, 'inactive or foreign tenant user aborts route authoring');
esc_p7_route_assert(in_array('ROLLBACK', $GLOBALS['sc_test_queries'], true), 'inactive tenant user causes rollback');
esc_p7_route_assert(! in_array('COMMIT', $GLOBALS['sc_test_queries'], true), 'inactive tenant user never commits partial route');
esc_p7_route_assert(! (bool) array_filter($GLOBALS['sc_test_queries'], static fn(string $sql): bool => str_contains($sql, 'INSERT INTO wp_safecontracts_workflow_transition_approval_')), 'invalid membership fails before any Approval Route write');

$reverseUserRoute = ApprovalRoutePolicy::normalizeRoute([[
    'stage_code'=>'ordered_users','name'=>'Ordered users','decision_policy'=>'all','required_approvals'=>0,
    'selectors'=>[['selector_type'=>'tenant_user','user_id'=>90],['selector_type'=>'tenant_user','user_id'=>55]],
]]);
$GLOBALS['wpdb']->insert_id = 0;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p7_route_locked_transition(), [], esc_p7_route_membership(55), esc_p7_route_membership(90)];
$repository->replaceDraftRoute(81,91,701,$reverseUserRoute,42);
$membershipQueries = esc_p7_route_membership_queries();
esc_p7_route_assert(count($membershipQueries) === 2, 'authoring locks each unique tenant user exactly once');
esc_p7_route_assert(str_contains($membershipQueries[0], 'm.user_id = 55') && str_contains($membershipQueries[1], 'm.user_id = 90'), 'authoring locks tenant users in ascending deterministic order independent of selector order');

$GLOBALS['sc_test_result_queue'] = [esc_p7_route_publish_row(), esc_p7_route_stage_rows(ApprovalRoutePolicy::MAX_STAGES + 1)];
esc_p7_route_throws(static fn()=>$repository->getRoute(81,91,701), RuntimeException::class, 'ordinary route read fails closed on stage sentinel overflow');
$GLOBALS['sc_test_result_queue'] = [esc_p7_route_publish_row(), esc_p7_route_stage(), esc_p7_route_user_selectors(range(1, ApprovalRoutePolicy::MAX_SELECTORS_PER_STAGE + 1))];
esc_p7_route_throws(static fn()=>$repository->getRoute(81,91,701), RuntimeException::class, 'ordinary route read fails closed on selector sentinel overflow');

$GLOBALS['sc_test_result_queue'] = [esc_p7_route_publish_row(true)];
esc_p7_route_throws(static fn()=>$repository->assertVersionPublishable(81,91), RuntimeException::class, 'stale route transition snapshot blocks publication');

$GLOBALS['sc_test_result_queue'] = [esc_p7_route_publish_row(), esc_p7_route_stage(), esc_p7_route_user_selector(55), esc_p7_route_membership(55)];
$repository->assertVersionPublishable(81,91);
esc_p7_route_assert(true, 'valid active tenant user route is publishable');

$GLOBALS['sc_test_result_queue'] = [esc_p7_route_publish_row(), esc_p7_route_stage(), esc_p7_route_user_selector(55), []];
esc_p7_route_throws(static fn()=>$repository->assertVersionPublishable(81,91), RuntimeException::class, 'inactive tenant user blocks publication');

$GLOBALS['sc_test_result_queue'] = [esc_p7_route_publish_row(), esc_p7_route_stage(), esc_p7_route_role_selector('member')];
esc_p7_route_throws(static fn()=>$repository->assertVersionPublishable(81,91), RuntimeException::class, 'unassignable tenant role blocks publication');

$GLOBALS['sc_test_result_queue'] = [esc_p7_route_publish_row(), esc_p7_route_stage('quorum', 2), esc_p7_route_role_selector('manager')];
esc_p7_route_throws(static fn()=>$repository->assertVersionPublishable(81,91), RuntimeException::class, 'stored invalid quorum blocks publication');

$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p7_route_publish_row(),
    esc_p7_route_stage(),
    esc_p7_route_user_selectors([90,55]),
    esc_p7_route_membership(55),
    esc_p7_route_membership(90),
];
$repository->assertVersionPublishable(81,91);
$membershipQueries = esc_p7_route_membership_queries();
esc_p7_route_assert(count($membershipQueries) === 2, 'publication locks each unique tenant user exactly once');
esc_p7_route_assert(str_contains($membershipQueries[0], 'm.user_id = 55') && str_contains($membershipQueries[1], 'm.user_id = 90'), 'publication locks tenant users in ascending deterministic order independent of stored selector order');

esc_p7_route_assert(str_contains($workflowRepositorySource, 'ApprovalRouteRepository') && str_contains($workflowRepositorySource, 'assertVersionPublishable($workflowId, $versionId)'), 'Workflow publication invokes Approval Route validation');
esc_p7_route_assert(strpos($workflowRepositorySource, 'assertVersionPublishable($workflowId, $versionId)') < strpos($workflowRepositorySource, "SET version_status = 'published'"), 'Approval Route validation occurs before publication update');
esc_p7_route_assert(str_contains($repositorySource, 'START TRANSACTION') && str_contains($repositorySource, 'ROLLBACK') && str_contains($repositorySource, 'FOR UPDATE'), 'Approval Route repository is transactionally locked');
esc_p7_route_assert(str_contains($repositorySource, 'MAX_STAGES + 1') && str_contains($repositorySource, 'MAX_SELECTORS_PER_STAGE + 1'), 'authoritative and ordinary route scans use bounded sentinels');
esc_p7_route_assert(str_contains($repositorySource, 'sort($ordered, SORT_NUMERIC)') && str_contains($repositorySource, 'lockActiveTenantUsers'), 'tenant-user membership locks are canonicalized before acquisition');
esc_p7_route_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability') && str_contains($serviceSource, 'MANAGE_REFERENCE_DATA'), 'Approval Route service enforces tenant-role mutation ceiling');
esc_p7_route_assert(! str_contains($policySource, 'eval(') && ! str_contains($policySource, 'exec(') && ! str_contains($policySource, 'callback'), 'Approval Route policy contains no executable language');
esc_p7_route_assert(
    str_contains($transitionRepositorySource, 'approval_route_id')
        && str_contains($transitionRepositorySource, 'allowApprovalRouted')
        && ! str_contains($transitionRepositorySource, 'ApprovalRouteRepository')
        && ! str_contains($transitionServiceSource, 'ApprovalRoute'),
    'P7-004 adds only a route-presence execution gate to P6; P7-001 route orchestration remains outside P6 runtime'
);
esc_p7_route_assert(str_contains($statusSource, 'final class ContractStatus') && ! str_contains($statusSource, 'Approval'), 'legacy ContractStatus remains independent');
esc_p7_route_assert(str_contains($gateSource, 'enterprise_workflow_approval_routes_p7_001.php'), 'P7-001 regression explicitly wired into backend Gate');

CoreTenantEnforcement::disable();
TenantContextStore::reset();
echo "P7-001 Enterprise Workflow approval route checks passed ({$assertions} assertions).\n";
