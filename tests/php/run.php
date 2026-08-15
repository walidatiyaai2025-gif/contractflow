<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\PaymentMethodsPage;
use SafeContracts\Database\Migrator;
use SafeContracts\ReferenceData\PaymentMethodRepository;
use SafeContracts\Rest\PaymentMethodsController;
use SafeContracts\Rest\Router;
use SafeContracts\Roles\AccessScope;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;

$tests = 0;

function sc_assert(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

sc_assert(isset($GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE]), 'activation hook registered');
sc_assert(isset($GLOBALS['sc_test_deactivation_hooks'][SAFECONTRACTS_FILE]), 'deactivation hook registered');
sc_assert(isset($GLOBALS['sc_test_actions']['plugins_loaded']), 'plugins_loaded bootstrap registered');

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE];
$activate();

sc_assert(get_option(Migrator::VERSION_OPTION) === Migrator::LATEST_VERSION, 'migration version stored after successful migration');
sc_assert(Migrator::LATEST_VERSION === '1.3.0', 'contract data-model migration version registered');
sc_assert(count($GLOBALS['sc_test_dbdelta']) === 4, 'foundation, master-data and contract tables migrated once');
sc_assert(str_contains($GLOBALS['sc_test_dbdelta'][0], 'wp_safecontracts_meta'), 'foundation migration uses WordPress prefix');

$customerSchema = $GLOBALS['sc_test_dbdelta'][1];
sc_assert(str_contains($customerSchema, 'wp_safecontracts_customers'), 'customer master table created');
sc_assert(str_contains($customerSchema, 'name varchar(191) NOT NULL'), 'customer name is required');
sc_assert(str_contains($customerSchema, 'internal_code varchar(100) NULL'), 'customer internal code is optional');
sc_assert(str_contains($customerSchema, 'UNIQUE KEY internal_code (internal_code)'), 'customer internal code is unique when supplied');
sc_assert(str_contains($customerSchema, 'contact_name varchar(191) NULL'), 'customer contact name is supported');
sc_assert(str_contains($customerSchema, 'email varchar(191) NULL'), 'customer email is supported');
sc_assert(str_contains($customerSchema, 'phone varchar(64) NULL'), 'customer phone is supported');
sc_assert(str_contains($customerSchema, 'notes text NULL'), 'customer notes are supported');
sc_assert(str_contains($customerSchema, 'created_by bigint(20) unsigned NULL'), 'customer creator audit link is supported');
sc_assert(str_contains($customerSchema, 'is_active tinyint(1) NOT NULL DEFAULT 1'), 'customer active state stored explicitly');
sc_assert(str_contains($customerSchema, 'KEY active_name (is_active, name)'), 'customer active/name filter is indexed');

$paymentMethodSchema = $GLOBALS['sc_test_dbdelta'][2];
sc_assert(str_contains($paymentMethodSchema, 'wp_safecontracts_payment_methods'), 'payment-method master table created');
sc_assert(str_contains($paymentMethodSchema, 'UNIQUE KEY code (code)'), 'payment-method stable code is unique');
sc_assert(str_contains($paymentMethodSchema, 'display_order int(11) unsigned NOT NULL DEFAULT 0'), 'payment methods support explicit ordering');
sc_assert(str_contains($paymentMethodSchema, 'KEY active_order (is_active, display_order)'), 'payment methods support active ordered lookup');

sc_assert(count($GLOBALS['sc_test_queries']) === 3, 'three default payment methods are seeded');
$seedSql = implode("\n", $GLOBALS['sc_test_queries']);
sc_assert(str_contains($seedSql, "'cash', 'Cash'"), 'Cash payment method seeded');
sc_assert(str_contains($seedSql, "'bank_transfer', 'Bank Transfer'"), 'Bank Transfer payment method seeded');
sc_assert(str_contains($seedSql, "'wallet', 'Wallet'"), 'Wallet payment method seeded');
sc_assert(substr_count($seedSql, 'ON DUPLICATE KEY UPDATE') === 3, 'default payment-method seed is idempotent');

sc_assert(get_option('safecontracts_installed_at', false) !== false, 'activation stores installation timestamp');
sc_assert(get_option('safecontracts_plugin_version') === SAFECONTRACTS_VERSION, 'activation stores plugin version');
sc_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_activated']), 'activation lifecycle action fired');

foreach ([RoleRegistrar::SYSTEM_ADMIN, RoleRegistrar::MANAGER, RoleRegistrar::ACCOUNTANT, RoleRegistrar::VIEWER] as $role) {
    sc_assert(isset($GLOBALS['sc_test_roles'][$role]), "role {$role} registered");
}

$systemAdmin = $GLOBALS['sc_test_roles'][RoleRegistrar::SYSTEM_ADMIN]->capabilities;
sc_assert(isset($systemAdmin[Capabilities::MANAGE_REFERENCE_DATA]), 'SafeContracts system administrator can manage reference data');

$accountant = $GLOBALS['sc_test_roles'][RoleRegistrar::ACCOUNTANT]->capabilities;
sc_assert(isset($accountant[Capabilities::CREATE_CONTRACTS]), 'accountant can create contracts by default');
sc_assert(! isset($accountant[Capabilities::EDIT_CONTRACTS]), 'contract edit capability remains independently grantable');
sc_assert(isset($accountant[Capabilities::VIEW_ASSIGNED]), 'accountant defaults to assigned scope');
sc_assert(! isset($accountant[Capabilities::MANAGE_REFERENCE_DATA]), 'accountant cannot manage reference data by default');

$manager = $GLOBALS['sc_test_roles'][RoleRegistrar::MANAGER]->capabilities;
sc_assert(isset($manager[Capabilities::VIEW_ALL]), 'manager defaults to all-data scope');
sc_assert(isset($manager[Capabilities::EDIT_CONTRACTS]), 'manager can edit contracts by default');
sc_assert(! isset($manager[Capabilities::MANAGE_REFERENCE_DATA]), 'manager cannot manage administrator reference data by default');

$admin = $GLOBALS['sc_test_roles']['administrator']->capabilities;
sc_assert(isset($admin[Capabilities::MANAGE_SYSTEM]), 'native WordPress administrator receives SafeContracts system capabilities');
sc_assert(isset($admin[Capabilities::MANAGE_REFERENCE_DATA]), 'native WordPress administrator receives reference-data capability');

// Boot after activation; repeated migrations and seeds must be idempotent.
$seedCountBeforeBoot = count($GLOBALS['sc_test_queries']);
do_action('plugins_loaded');
sc_assert(count($GLOBALS['sc_test_dbdelta']) === 4, 'migrations are not replayed after stored version is current');
sc_assert(count($GLOBALS['sc_test_queries']) === $seedCountBeforeBoot, 'default payment methods are not reseeded after current migration');
sc_assert(isset($GLOBALS['sc_test_actions']['rest_api_init']), 'REST registration hook attached');
sc_assert(isset($GLOBALS['sc_test_actions']['admin_menu']), 'reference-data admin menu hook attached');
sc_assert(isset($GLOBALS['sc_test_actions']['admin_post_' . PaymentMethodsPage::SAVE_ACTION]), 'reference-data admin save hook attached');

do_action('admin_menu');
sc_assert(isset($GLOBALS['sc_test_admin_pages'][PaymentMethodsPage::SLUG]), 'payment-method settings page registered');
sc_assert(
    $GLOBALS['sc_test_admin_pages'][PaymentMethodsPage::SLUG]['capability'] === Capabilities::MANAGE_REFERENCE_DATA,
    'payment-method settings page requires reference-data capability'
);

do_action('rest_api_init');
sc_assert(isset($GLOBALS['sc_test_routes'][Router::NAMESPACE . '/health']), 'health endpoint registered');
sc_assert(isset($GLOBALS['sc_test_routes'][Router::NAMESPACE . '/me']), 'protected me endpoint registered');
sc_assert(isset($GLOBALS['sc_test_routes'][Router::NAMESPACE . '/payment-methods']), 'active payment-method endpoint registered');
sc_assert(isset($GLOBALS['sc_test_routes'][Router::NAMESPACE . '/admin/payment-methods']), 'admin payment-method endpoint registered');
sc_assert(
    $GLOBALS['sc_test_routes'][Router::NAMESPACE . '/payment-methods']['permission_callback'] === [Router::class, 'canAccess'],
    'active payment methods use normal SafeContracts access authorization'
);
$adminRoutes = $GLOBALS['sc_test_routes'][Router::NAMESPACE . '/admin/payment-methods'];
sc_assert(count($adminRoutes) === 2, 'admin payment methods expose read and write operations');
sc_assert($adminRoutes[0]['permission_callback'] === [PaymentMethodsController::class, 'canManage'], 'admin reference-data read is capability protected');
sc_assert($adminRoutes[1]['permission_callback'] === [PaymentMethodsController::class, 'canManage'], 'admin reference-data write is capability protected');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
sc_assert(AccessScope::current() === AccessScope::ASSIGNED, 'assigned scope resolved from capabilities');
sc_assert(Router::canAccess() === true, 'authorized scoped user can access protected REST route');
sc_assert(PaymentMethodsController::canManage() instanceof WP_Error, 'ordinary scoped user cannot manage reference data');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true];
sc_assert(AccessScope::current() === AccessScope::ALL, 'all scope takes precedence when granted');
sc_assert(PaymentMethodsController::canManage() instanceof WP_Error, 'manager-style all-data scope does not grant reference-data administration');

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = true;
sc_assert(PaymentMethodsController::canManage() === true, 'reference-data capability authorizes administration');

$GLOBALS['sc_test_results'] = [
    ['id' => '1', 'code' => 'cash', 'name' => 'Cash', 'display_order' => '10', 'is_active' => '1'],
    ['id' => '2', 'code' => 'bank_transfer', 'name' => 'Bank Transfer', 'display_order' => '20', 'is_active' => '1'],
];
$activeMethods = PaymentMethodsController::active(new WP_REST_Request());
sc_assert($activeMethods->status === 200, 'active payment-method API returns 200');
sc_assert(count($activeMethods->data['data']) === 2, 'active payment-method API returns repository rows');
sc_assert($activeMethods->data['data'][0]['display_order'] === 10, 'payment-method API normalizes display order to integer');
sc_assert($activeMethods->data['data'][0]['is_active'] === true, 'payment-method API normalizes active state to boolean');
$activeReadSql = end($GLOBALS['sc_test_read_queries']);
sc_assert(str_contains((string) $activeReadSql, 'WHERE is_active = 1'), 'mobile/reference API queries active payment methods only');
sc_assert(str_contains((string) $activeReadSql, 'ORDER BY display_order ASC, name ASC'), 'payment-method API preserves configured ordering');

$allMethods = PaymentMethodsController::all(new WP_REST_Request());
sc_assert($allMethods->status === 200, 'admin payment-method API returns 200');
$allReadSql = end($GLOBALS['sc_test_read_queries']);
sc_assert(! str_contains((string) $allReadSql, 'WHERE is_active = 1'), 'admin reference-data API includes inactive methods');

$queryCountBeforeSave = count($GLOBALS['sc_test_queries']);
$savedMethod = PaymentMethodsController::save(new WP_REST_Request([
    'code' => 'CARD',
    'name' => '<b>Card</b>',
    'display_order' => 40,
    'is_active' => true,
]));
sc_assert($savedMethod instanceof WP_REST_Response, 'valid payment-method write returns REST response');
sc_assert($savedMethod->status === 200, 'valid payment-method write returns 200');
sc_assert($savedMethod->data['data']['code'] === 'card', 'payment-method code is sanitized and normalized');
sc_assert($savedMethod->data['data']['name'] === 'Card', 'payment-method name is sanitized');
sc_assert($savedMethod->data['data']['display_order'] === 40, 'payment-method display order is persisted');
sc_assert($savedMethod->data['data']['is_active'] === true, 'payment-method active state is persisted');
sc_assert(count($GLOBALS['sc_test_queries']) === $queryCountBeforeSave + 1, 'payment-method write executes one mutation query');
$saveSql = end($GLOBALS['sc_test_queries']);
sc_assert(str_contains((string) $saveSql, 'wp_safecontracts_payment_methods'), 'payment-method write uses prefixed master table');
sc_assert(str_contains((string) $saveSql, 'display_order'), 'payment-method write matches master-data schema');
sc_assert(str_contains((string) $saveSql, "'card'"), 'payment-method write uses prepared normalized code');
sc_assert(str_contains((string) $saveSql, 'ON DUPLICATE KEY UPDATE'), 'payment-method administration is idempotent by stable code');
sc_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_payment_method_saved']), 'payment-method mutation emits domain action');

$queryCountBeforeInvalid = count($GLOBALS['sc_test_queries']);
$invalidMethod = PaymentMethodsController::save(new WP_REST_Request([
    'code' => '!',
    'name' => 'Invalid',
    'display_order' => 1,
    'is_active' => true,
]));
sc_assert($invalidMethod instanceof WP_Error, 'invalid payment-method code returns WP_Error');
sc_assert($invalidMethod->data['status'] === 422, 'invalid payment-method code returns validation status');
sc_assert(count($GLOBALS['sc_test_queries']) === $queryCountBeforeInvalid, 'invalid payment method does not mutate data');

$GLOBALS['sc_test_current_caps'] = [];
sc_assert(Router::canAccess() instanceof WP_Error, 'unauthorized REST access returns WP_Error');

$health = Router::health(new WP_REST_Request());
sc_assert($health->status === 200, 'health response status is 200');
sc_assert($health->data['data']['service'] === 'SafeContracts', 'health response identifies service');

// Deactivation must be non-destructive so reactivation is safe.
$optionsBeforeDeactivate = $GLOBALS['sc_test_options'];
$rolesBeforeDeactivate = array_keys($GLOBALS['sc_test_roles']);
$migrationCountBeforeDeactivate = count($GLOBALS['sc_test_dbdelta']);
$queryCountBeforeDeactivate = count($GLOBALS['sc_test_queries']);
$deactivate = $GLOBALS['sc_test_deactivation_hooks'][SAFECONTRACTS_FILE];
$deactivate();

sc_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_deactivated']), 'deactivation lifecycle action fired');
sc_assert($GLOBALS['sc_test_options'] === $optionsBeforeDeactivate, 'deactivation preserves options and installation state');
sc_assert(array_keys($GLOBALS['sc_test_roles']) === $rolesBeforeDeactivate, 'deactivation preserves roles/capabilities');
sc_assert(count($GLOBALS['sc_test_dbdelta']) === $migrationCountBeforeDeactivate, 'deactivation does not mutate schema/data');
sc_assert(count($GLOBALS['sc_test_queries']) === $queryCountBeforeDeactivate, 'deactivation does not mutate reference data');

echo "SafeContracts PHP foundation tests passed ({$tests} assertions).\n";
