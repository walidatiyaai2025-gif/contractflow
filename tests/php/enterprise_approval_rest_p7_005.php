<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

$assertions = 0;
function esc_p7_rest_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
$controller = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/ApprovalController.php');
$router = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
$requestService = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Approvals/ApprovalRequestService.php');
$decisionService = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Approvals/ApprovalDecisionService.php');
$releaseService = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Approvals/ApprovalReleaseService.php');
$gate = (string) file_get_contents($root . '/scripts/test-php.sh');

esc_p7_rest_assert(str_contains($router, "public const NAMESPACE = 'safecontracts/v1'"), 'P7-005 preserves established REST v1 namespace');
esc_p7_rest_assert(str_contains($router, 'ApprovalController::register(self::NAMESPACE);'), 'Router registers Approval REST controller through canonical namespace');
esc_p7_rest_assert(str_contains($controller, 'CoreTenantEnforcement::isEnabled()'), 'Approval routes register only under Enterprise core tenant enforcement');
esc_p7_rest_assert(substr_count($controller, 'register_rest_route(') === 3, 'P7-005 registers three route patterns');
esc_p7_rest_assert(substr_count($controller, "'methods' => WP_REST_Server::READABLE") === 3, 'P7-005 exposes three bounded read operations');
esc_p7_rest_assert(substr_count($controller, "'methods' => WP_REST_Server::CREATABLE") === 3, 'P7-005 exposes three bounded mutation operations');
esc_p7_rest_assert(str_contains($controller, "'/contracts/(?P<contract_id>\\d+)/approval-requests'"), 'request list/create route is contract scoped');
esc_p7_rest_assert(str_contains($controller, "'/approval-requests/(?P<request_id>\\d+)/decisions'"), 'decision list/create route is request scoped');
esc_p7_rest_assert(str_contains($controller, "'/approval-requests/(?P<request_id>\\d+)/release'"), 'release read/create route is request scoped');

esc_p7_rest_assert(substr_count($controller, 'CoreTenantRestGuard::permission($request, Capabilities::ACCESS)') === 3, 'all Approval REST reads require ACCESS through locked tenant guard');
esc_p7_rest_assert(substr_count($controller, 'CoreTenantRestGuard::permission($request, Capabilities::EDIT_CONTRACTS)') === 3, 'all Approval REST mutations require EDIT_CONTRACTS through locked tenant guard');
esc_p7_rest_assert(str_contains($controller, "ApiRequest::routeId($request, 'contract_id')") && str_contains($controller, "ApiRequest::routeId($request, 'request_id')"), 'route object identities use existing positive-ID parser');
esc_p7_rest_assert(substr_count($controller, "ApiRequest::positiveInt($request->get_param('page'), 1, 100000, 1)") === 2, 'request and decision list pagination validates page consistently');
esc_p7_rest_assert(substr_count($controller, "ApiRequest::positiveInt($request->get_param('per_page'), 1, 100, 50)") === 2, 'request and decision list pagination is bounded to 100');

esc_p7_rest_assert(substr_count($controller, 'RequestGuard::requireJsonObject($request)') === 3, 'all Approval REST mutations require JSON object bodies');
esc_p7_rest_assert(str_contains($controller, "RequestGuard::assertAllowedKeys($payload, ['transition_code', 'idempotency_key'])"), 'Approval Request creation accepts only transition_code and idempotency_key');
esc_p7_rest_assert(str_contains($controller, "RequestGuard::assertAllowedKeys($payload, ['action', 'idempotency_key', 'comment'])"), 'Approval Decision accepts only action, idempotency_key and optional comment');
esc_p7_rest_assert(str_contains($controller, "RequestGuard::assertAllowedKeys($payload, ['idempotency_key'])"), 'Approval Release accepts only idempotency_key');
esc_p7_rest_assert(! str_contains($controller, 'Idempotency-Key') && ! str_contains($controller, 'get_header('), 'P7-005 introduces no divergent REST idempotency-header convention');
esc_p7_rest_assert(substr_count($controller, "'idempotency_key'") >= 3, 'Approval idempotency identity is explicit JSON input');

esc_p7_rest_assert(str_contains($controller, 'ApprovalRequestService') && str_contains($controller, 'ApprovalDecisionService') && str_contains($controller, 'ApprovalReleaseService'), 'Approval REST controller delegates to the three established Approval services');
esc_p7_rest_assert(str_contains($controller, '$this->requests->listRequests(') && str_contains($controller, '$this->requests->request('), 'Approval Request REST uses service-only read/create boundary');
esc_p7_rest_assert(str_contains($controller, '$this->decisions->listDecisions(') && str_contains($controller, '$this->decisions->decide('), 'Approval Decision REST uses service-only read/create boundary');
esc_p7_rest_assert(str_contains($controller, '$this->releases->getRelease(') && str_contains($controller, '$this->releases->release('), 'Approval Release REST uses P7-004 service-only boundary');
esc_p7_rest_assert(! str_contains($controller, '$wpdb') && ! str_contains($controller, 'safecontracts_workflow_approval_'), 'Approval REST controller has no direct persistence/table access');
esc_p7_rest_assert(! str_contains($controller, 'ContractWorkflowTransitionRepository') && ! str_contains($controller, 'ContractWorkflowTransitionService'), 'REST release cannot bypass P7-004 service into P6 directly');

foreach (['request_key_hash', 'decision_key_hash', 'release_key_hash'] as $internal) {
    esc_p7_rest_assert(! str_contains($controller, $internal), 'REST controller does not expose internal ' . $internal);
}
esc_p7_rest_assert(str_contains($releaseService, "unset(\$history['request_key_hash'])"), 'P7-004 service strips internal P6 request hash before REST can receive new-release history');
esc_p7_rest_assert(! str_contains($decisionService, "'decision_key_hash' =>") && ! str_contains($requestService, "'request_key_hash' =>"), 'Approval Request/Decision services expose no explicit internal hash field');

esc_p7_rest_assert(str_contains($controller, "ApiResponse::error('approval_release_not_found', 'Approval Release was not found.', 404)"), 'missing Release uses stable v1 404 envelope');
esc_p7_rest_assert(str_contains($controller, "$status >= 500 ? 'Approval operation failed.' : $error->getMessage()"), 'unexpected server errors are masked instead of leaking internal messages');
esc_p7_rest_assert(str_contains($controller, "return str_contains($message, 'not found') ? 404 : 400"), 'invalid/missing identifiers map to stable 400/404 semantics');
esc_p7_rest_assert(str_contains($controller, "return ApiResponse::success($result, ($result['idempotent'] ?? false) ? 200 : 201)"), 'Decision/Release exact retries preserve 200 while new mutations use 201');
esc_p7_rest_assert(str_contains($controller, "($result['approval_required'] ?? false) && ! ($result['idempotent'] ?? false) ? 201 : 200"), 'Approval Request create distinguishes new routed request from retry/no-route response');

esc_p7_rest_assert(str_contains($requestService, 'TenantAuthorization::allowsCapability') && str_contains($requestService, 'assertScope'), 'Approval Request service retains tenant-role and contract data scope');
esc_p7_rest_assert(str_contains($decisionService, 'TenantAuthorization::allowsCapability') && str_contains($decisionService, 'assertScope'), 'Approval Decision service retains tenant-role and contract data scope');
esc_p7_rest_assert(str_contains($releaseService, 'TenantAuthorization::allowsCapability') && str_contains($releaseService, 'assertScope'), 'Approval Release service retains tenant-role and contract data scope');
esc_p7_rest_assert(! str_contains($controller, 'route_id') && ! str_contains($controller, 'stage_id') && ! str_contains($controller, 'candidate_id') && ! str_contains($controller, 'from_state_id') && ! str_contains($controller, 'to_state_id'), 'caller-forbidden Approval/Workflow identities are absent from REST mutation contract');
esc_p7_rest_assert(str_contains($gate, 'enterprise_workflow_approval_release_service_p7_004.php'), 'P7-004 release service regression remains wired before REST exposure');

echo "P7-005 Approval REST checks passed ({$assertions} assertions).\n";
