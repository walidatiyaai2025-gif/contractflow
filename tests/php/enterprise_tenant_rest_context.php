<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Rest\Router;
use SafeContracts\Rest\TenantRequestContext;
use SafeContracts\Rest\TenantsController;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_rest_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$GLOBALS['sc_test_current_caps']['safecontracts_access'] = true;
$GLOBALS['sc_test_current_caps']['safecontracts_view_assigned'] = true;

do_action('rest_api_init');
esc_rest_assert(isset($GLOBALS['sc_test_routes']['safecontracts/v1/tenants']), 'tenant directory route is registered');
esc_rest_assert(TenantRequestContext::HEADER === 'X-ESC-Tenant-ID', 'tenant selection header is stable');

unset($_SERVER['HTTP_X_ESC_TENANT_ID']);
TenantContextStore::reset();
$GLOBALS['sc_test_results'] = [['tenant_id' => '17']];
$resolved = TenantRequestContext::resolve(new WP_REST_Request(), true);
esc_rest_assert($resolved === 17, 'single active membership resolves when tenant is required');
esc_rest_assert(TenantContextStore::context()->requireTenantId() === 17, 'resolved tenant is locked into request context');

TenantContextStore::reset();
$GLOBALS['sc_test_results'] = [['tenant_id' => '17'], ['tenant_id' => '18']];
$ambiguous = TenantRequestContext::resolve(new WP_REST_Request(), true);
esc_rest_assert($ambiguous instanceof WP_Error && $ambiguous->code === 'esc_tenant_selection_required', 'multiple memberships require explicit tenant selection');

TenantContextStore::reset();
$_SERVER['HTTP_X_ESC_TENANT_ID'] = 'abc';
$invalid = TenantRequestContext::resolve(new WP_REST_Request(), true);
esc_rest_assert($invalid instanceof WP_Error && $invalid->code === 'esc_tenant_header_invalid', 'malformed tenant header is rejected');

TenantContextStore::reset();
$_SERVER['HTTP_X_ESC_TENANT_ID'] = '99';
$GLOBALS['sc_test_results'] = [];
$forbidden = TenantRequestContext::resolve(new WP_REST_Request(), true);
esc_rest_assert($forbidden instanceof WP_Error && $forbidden->code === 'esc_tenant_forbidden', 'client-supplied tenant id is not authorization');

TenantContextStore::reset();
$_SERVER['HTTP_X_ESC_TENANT_ID'] = '17';
$GLOBALS['sc_test_results'] = [['id' => '1']];
$authorized = TenantRequestContext::resolve(new WP_REST_Request(), true);
esc_rest_assert($authorized === 17, 'active membership authorizes explicit tenant selection');

$GLOBALS['sc_test_results'] = [['id' => '1']];
$_SERVER['HTTP_X_ESC_TENANT_ID'] = '18';
$conflict = TenantRequestContext::resolve(new WP_REST_Request(), true);
esc_rest_assert($conflict instanceof WP_Error && $conflict->code === 'esc_tenant_context_conflict', 'request context cannot switch tenants after lock');

TenantContextStore::reset();
$_SERVER['HTTP_X_ESC_TENANT_ID'] = '17';
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '1']],
    [[
        'id' => '17',
        'uuid' => 'd4498287-9e17-4a45-93ea-882455c52309',
        'slug' => 'acme-export',
        'name' => 'Acme Export',
        'legal_name' => 'Acme Export LLC',
        'country_code' => 'KW',
        'timezone' => 'Asia/Kuwait',
        'default_currency' => 'KWD',
        'locale' => 'ar',
        'role_code' => 'owner',
        'is_owner' => '1',
    ]],
];
$directory = TenantsController::index(new WP_REST_Request());
esc_rest_assert($directory instanceof WP_REST_Response, 'tenant directory returns an API response');
$directoryData = $directory->data['data'] ?? [];
esc_rest_assert(($directoryData['selected_tenant_id'] ?? null) === 17, 'tenant directory reports server-authorized selected tenant');
esc_rest_assert(($directoryData['items'][0]['name'] ?? '') === 'Acme Export', 'tenant directory returns only active user memberships');
esc_rest_assert(($directoryData['items'][0]['default_currency'] ?? '') === 'KWD', 'tenant directory exposes tenant locale/currency context');

TenantContextStore::reset();
$_SERVER['HTTP_X_ESC_TENANT_ID'] = '17';
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '1']],
    [[
        'id' => '17',
        'uuid' => 'd4498287-9e17-4a45-93ea-882455c52309',
        'slug' => 'acme-export',
        'name' => 'Acme Export',
        'legal_name' => 'Acme Export LLC',
        'country_code' => 'KW',
        'timezone' => 'Asia/Kuwait',
        'default_currency' => 'KWD',
        'locale' => 'ar',
        'role_code' => 'owner',
        'is_owner' => '1',
    ]],
];
$me = Router::me(new WP_REST_Request());
esc_rest_assert($me instanceof WP_REST_Response, '/me remains a successful authenticated response');
$meData = $me->data['data'] ?? [];
esc_rest_assert(($meData['tenant']['id'] ?? null) === 17, '/me includes only the selected authorized tenant identity');
esc_rest_assert(($meData['tenant_selection_header'] ?? '') === 'X-ESC-Tenant-ID', '/me publishes the stable tenant selection contract');

TenantContextStore::reset();
TenantContextStore::context()->setTenantId(55);
$resetResult = TenantContextStore::resetBeforeCallbacks('sentinel', null, new WP_REST_Request());
esc_rest_assert($resetResult === 'sentinel', 'request-boundary reset preserves REST response pipeline');
esc_rest_assert(! TenantContextStore::context()->hasTenant(), 'request-boundary reset prevents tenant leakage across requests');

unset($_SERVER['HTTP_X_ESC_TENANT_ID']);

fwrite(STDOUT, "Enterprise tenant REST context passed ({$assertions} assertions).\n");
