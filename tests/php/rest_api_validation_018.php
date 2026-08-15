<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Rest\ApiResponse;
use SafeContracts\Rest\AuthController;
use SafeContracts\Rest\Router;

$tests = 0;
function sc_p8v18_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

sc_p8v18_assert(Router::NAMESPACE === 'safecontracts/v1', 'SC-P8-018 namespace remains the stable safecontracts/v1 contract');
sc_p8v18_assert(Router::API_VERSION === 'v1', 'SC-P8-018 API version constant remains v1');

$ok = ApiResponse::ok(['value' => 1]);
sc_p8v18_assert($ok instanceof WP_REST_Response && $ok->status === 200, 'SC-P8-018 success envelope uses WP_REST_Response with requested status');
sc_p8v18_assert(($ok->data['data']['value'] ?? null) === 1 && ($ok->data['meta']['api_version'] ?? '') === Router::API_VERSION, 'SC-P8-018 success envelope carries data plus canonical version metadata');
$override = ApiResponse::ok([], ['api_version' => 'v999', 'page' => 2]);
sc_p8v18_assert(($override->data['meta']['api_version'] ?? '') === Router::API_VERSION && ($override->data['meta']['page'] ?? 0) === 2, 'SC-P8-018 endpoint metadata cannot override canonical API version');

$error = ApiResponse::error('safecontracts_example', 'Example', 422, ['field' => 'x']);
sc_p8v18_assert($error instanceof WP_Error && $error->code === 'safecontracts_example', 'SC-P8-018 error envelope preserves canonical WP_Error code');
sc_p8v18_assert(($error->data['status'] ?? 0) === 422 && ($error->data['api_version'] ?? '') === Router::API_VERSION && ($error->data['details']['field'] ?? '') === 'x', 'SC-P8-018 error envelope carries HTTP status, API version and bounded details');
$notFound = ApiResponse::notFound('Contract');
sc_p8v18_assert($notFound->code === 'safecontracts_not_found' && ($notFound->data['status'] ?? 0) === 404 && ($notFound->data['api_version'] ?? '') === Router::API_VERSION, 'SC-P8-018 not-found response follows the same versioned error convention');

Router::register();
sc_p8v18_assert(isset($GLOBALS['sc_test_routes'][Router::NAMESPACE . '/health']), 'SC-P8-018 health route is registered under v1 namespace');
sc_p8v18_assert(isset($GLOBALS['sc_test_routes'][Router::NAMESPACE . '/auth/login']), 'SC-P8-018 mobile login route is registered under v1 namespace');
sc_p8v18_assert(isset($GLOBALS['sc_test_routes'][Router::NAMESPACE . '/auth/logout']), 'SC-P8-018 mobile logout route is registered under v1 namespace');
sc_p8v18_assert(isset($GLOBALS['sc_test_routes'][Router::NAMESPACE . '/me']) && isset($GLOBALS['sc_test_routes'][Router::NAMESPACE . '/session']), 'SC-P8-018 session routes are registered under v1 namespace');

$routeCount = 0;
$endpointCount = 0;
foreach ($GLOBALS['sc_test_routes'] as $route => $definition) {
    $routeCount++;
    sc_p8v18_assert(str_starts_with($route, Router::NAMESPACE . '/'), "SC-P8-018 route {$route} cannot drift outside v1 namespace");

    $endpoints = isset($definition['methods']) ? [$definition] : array_values(array_filter($definition, 'is_array'));
    sc_p8v18_assert($endpoints !== [], "SC-P8-018 route {$route} exposes at least one endpoint definition");
    foreach ($endpoints as $endpoint) {
        $endpointCount++;
        sc_p8v18_assert(isset($endpoint['methods'], $endpoint['callback'], $endpoint['permission_callback']), "SC-P8-018 route {$route} has method/callback/permission contract");
        $methods = $endpoint['methods'];
        sc_p8v18_assert(in_array($methods, [WP_REST_Server::READABLE, WP_REST_Server::CREATABLE, 'PATCH'], true), "SC-P8-018 route {$route} uses supported v1 HTTP method contract");
        if ($route === Router::NAMESPACE . '/health') {
            sc_p8v18_assert($endpoint['permission_callback'] === '__return_true', 'SC-P8-018 health stays explicitly public');
        } elseif ($route === Router::NAMESPACE . '/auth/login') {
            sc_p8v18_assert($endpoint['permission_callback'] === [AuthController::class, 'allowLogin'], 'SC-P8-018 mobile login uses its explicit public permission callback');
        } else {
            sc_p8v18_assert($endpoint['permission_callback'] !== '__return_true', "SC-P8-018 protected route {$route} is not accidentally public");
        }
    }
}
sc_p8v18_assert($routeCount >= 10 && $endpointCount >= $routeCount, 'SC-P8-018 validates the complete registered REST surface including multi-method routes');

$health = Router::health(new WP_REST_Request());
sc_p8v18_assert(($health->data['data']['service'] ?? '') === 'SafeContracts' && ($health->data['data']['api_version'] ?? '') === Router::API_VERSION, 'SC-P8-018 health payload reports canonical API version');
sc_p8v18_assert(($health->data['meta']['api_version'] ?? '') === Router::API_VERSION, 'SC-P8-018 health envelope metadata matches payload version');

$apiResponseSource = file_get_contents((string) (new ReflectionClass(ApiResponse::class))->getFileName()) ?: '';
sc_p8v18_assert(str_contains($apiResponseSource, 'Router::API_VERSION') && ! str_contains($apiResponseSource, "'api_version' => 'v1'"), 'SC-P8-018 response envelopes use Router version as the single source of truth');

$restDir = dirname((string) (new ReflectionClass(Router::class))->getFileName());
foreach (glob($restDir . '/*.php') ?: [] as $file) {
    $source = file_get_contents($file) ?: '';
    sc_p8v18_assert(! str_contains($source, 'safecontracts/v2'), 'SC-P8-018 REST source contains no accidental v2 namespace drift: ' . basename($file));
    if (str_ends_with($file, 'Controller.php')) {
        sc_p8v18_assert(! str_contains($source, 'new WP_REST_Response') && ! str_contains($source, 'new WP_Error'), 'SC-P8-018 controller uses canonical ApiResponse/RequestGuard envelopes: ' . basename($file));
    }
}

printf("SafeContracts P8 API conventions/versioning SC-P8-018 passed (%d assertions).\n", $tests);
