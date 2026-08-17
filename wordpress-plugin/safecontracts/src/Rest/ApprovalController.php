<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Approvals\ApprovalDecisionService;
use SafeContracts\Approvals\ApprovalReleaseService;
use SafeContracts\Approvals\ApprovalRequestService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class ApprovalController
{
    public function __construct(
        private ?ApprovalRequestService $requests = null,
        private ?ApprovalDecisionService $decisions = null,
        private ?ApprovalReleaseService $releases = null
    ) {
        $this->requests ??= new ApprovalRequestService();
        $this->decisions ??= new ApprovalDecisionService();
        $this->releases ??= new ApprovalReleaseService();
    }

    public static function register(string $namespace): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            return;
        }
        $controller = new self();

        register_rest_route($namespace, '/contracts/(?P<contract_id>\d+)/approval-requests', [
            [
                'methods' => WP_REST_Server::READABLE,
                'permission_callback' => static fn (WP_REST_Request $request): bool|WP_Error => CoreTenantRestGuard::permission($request, Capabilities::ACCESS),
                'callback' => [$controller, 'listRequests'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'permission_callback' => static fn (WP_REST_Request $request): bool|WP_Error => CoreTenantRestGuard::permission($request, Capabilities::EDIT_CONTRACTS),
                'callback' => [$controller, 'createRequest'],
            ],
        ]);

        register_rest_route($namespace, '/approval-requests/(?P<request_id>\d+)/decisions', [
            [
                'methods' => WP_REST_Server::READABLE,
                'permission_callback' => static fn (WP_REST_Request $request): bool|WP_Error => CoreTenantRestGuard::permission($request, Capabilities::ACCESS),
                'callback' => [$controller, 'listDecisions'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'permission_callback' => static fn (WP_REST_Request $request): bool|WP_Error => CoreTenantRestGuard::permission($request, Capabilities::EDIT_CONTRACTS),
                'callback' => [$controller, 'createDecision'],
            ],
        ]);

        register_rest_route($namespace, '/approval-requests/(?P<request_id>\d+)/release', [
            [
                'methods' => WP_REST_Server::READABLE,
                'permission_callback' => static fn (WP_REST_Request $request): bool|WP_Error => CoreTenantRestGuard::permission($request, Capabilities::ACCESS),
                'callback' => [$controller, 'getRelease'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'permission_callback' => static fn (WP_REST_Request $request): bool|WP_Error => CoreTenantRestGuard::permission($request, Capabilities::EDIT_CONTRACTS),
                'callback' => [$controller, 'release'],
            ],
        ]);
    }

    public function listRequests(WP_REST_Request $request): WP_REST_Response
    {
        return $this->safe(function () use ($request): WP_REST_Response {
            $contractId = ApiRequest::routeId($request, 'contract_id');
            $page = ApiRequest::positiveInt($request->get_param('page'), 1, 100000, 1);
            $perPage = ApiRequest::positiveInt($request->get_param('per_page'), 1, 100, 50);
            $items = $this->requests->listRequests($contractId, $perPage, ($page - 1) * $perPage);
            return ApiResponse::collection($items, $page, $perPage);
        });
    }

    public function createRequest(WP_REST_Request $request): WP_REST_Response
    {
        return $this->safe(function () use ($request): WP_REST_Response {
            $contractId = ApiRequest::routeId($request, 'contract_id');
            $payload = RequestGuard::requireJsonObject($request);
            RequestGuard::assertAllowedKeys($payload, ['transition_code', 'idempotency_key']);
            $transitionCode = $this->requireString($payload, 'transition_code');
            $idempotencyKey = $this->requireString($payload, 'idempotency_key');
            $result = $this->requests->request($contractId, $transitionCode, $idempotencyKey);
            $status = ($result['approval_required'] ?? false) && ! ($result['idempotent'] ?? false) ? 201 : 200;
            return ApiResponse::success($result, $status);
        });
    }

    public function listDecisions(WP_REST_Request $request): WP_REST_Response
    {
        return $this->safe(function () use ($request): WP_REST_Response {
            $requestId = ApiRequest::routeId($request, 'request_id');
            $page = ApiRequest::positiveInt($request->get_param('page'), 1, 100000, 1);
            $perPage = ApiRequest::positiveInt($request->get_param('per_page'), 1, 100, 50);
            $items = $this->decisions->listDecisions($requestId, $perPage, ($page - 1) * $perPage);
            return ApiResponse::collection($items, $page, $perPage);
        });
    }

    public function createDecision(WP_REST_Request $request): WP_REST_Response
    {
        return $this->safe(function () use ($request): WP_REST_Response {
            $requestId = ApiRequest::routeId($request, 'request_id');
            $payload = RequestGuard::requireJsonObject($request);
            RequestGuard::assertAllowedKeys($payload, ['action', 'idempotency_key', 'comment']);
            $action = $this->requireString($payload, 'action');
            $idempotencyKey = $this->requireString($payload, 'idempotency_key');
            $comment = $this->optionalString($payload, 'comment');
            $result = $this->decisions->decide($requestId, $action, $idempotencyKey, $comment);
            return ApiResponse::success($result, ($result['idempotent'] ?? false) ? 200 : 201);
        });
    }

    public function getRelease(WP_REST_Request $request): WP_REST_Response
    {
        return $this->safe(function () use ($request): WP_REST_Response {
            $requestId = ApiRequest::routeId($request, 'request_id');
            $result = $this->releases->getRelease($requestId);
            if ($result === null) {
                return ApiResponse::error('approval_release_not_found', 'Approval Release was not found.', 404);
            }
            return ApiResponse::success($result);
        });
    }

    public function release(WP_REST_Request $request): WP_REST_Response
    {
        return $this->safe(function () use ($request): WP_REST_Response {
            $requestId = ApiRequest::routeId($request, 'request_id');
            $payload = RequestGuard::requireJsonObject($request);
            RequestGuard::assertAllowedKeys($payload, ['idempotency_key']);
            $idempotencyKey = $this->requireString($payload, 'idempotency_key');
            $result = $this->releases->release($requestId, $idempotencyKey);
            return ApiResponse::success($result, ($result['idempotent'] ?? false) ? 200 : 201);
        });
    }

    /** @param callable():WP_REST_Response $callback */
    private function safe(callable $callback): WP_REST_Response
    {
        try {
            return $callback();
        } catch (Throwable $error) {
            $status = $this->statusForThrowable($error);
            $message = $status >= 500 ? 'Approval operation failed.' : $error->getMessage();
            return ApiResponse::error('approval_operation_failed', $message, $status);
        }
    }

    private function statusForThrowable(Throwable $error): int
    {
        $message = strtolower($error->getMessage());
        if ($error instanceof InvalidArgumentException) {
            return str_contains($message, 'not found') ? 404 : 400;
        }
        if ($error instanceof DomainException) {
            if (str_contains($message, 'does not allow')
                || str_contains($message, 'outside the current user data scope')
                || str_contains($message, 'authenticated tenant user')) {
                return 403;
            }
            return 409;
        }
        if ($error instanceof RuntimeException) {
            foreach ([
                'already', 'concurrent', 'stale', 'requires an approved', 'only an approved',
                'not an immutable candidate', 'terminal', 'current state', 'no executable transition',
                'does not match', 'no longer matches', 'unavailable', 'idempotency key',
            ] as $needle) {
                if (str_contains($message, $needle)) {
                    return 409;
                }
            }
        }
        return 500;
    }

    /** @param array<string,mixed> $payload */
    private function requireString(array $payload, string $key): string
    {
        if (! array_key_exists($key, $payload) || ! is_string($payload[$key])) {
            throw new InvalidArgumentException($key . ' is required and must be a string.');
        }
        return $payload[$key];
    }

    /** @param array<string,mixed> $payload */
    private function optionalString(array $payload, string $key): ?string
    {
        if (! array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }
        if (! is_string($payload[$key])) {
            throw new InvalidArgumentException($key . ' must be a string when supplied.');
        }
        return $payload[$key];
    }
}
