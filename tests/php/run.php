<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
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
sc_assert(Migrator::LATEST_VERSION === '1.1.0', 'P1 master-data migration version registered');
sc_assert(count($GLOBALS['sc_test_dbdelta']) === 3, 'foundation and P1 master-data tables migrated once');
sc_assert(str_contains($GLOBALS['sc_test_dbdelta'][0], 'wp_safecontracts_meta'), 'foundation migration uses WordPress prefix');

$customerSchema = $GLOBALS['sc_test_dbdelta'][1];
sc_assert(str_contains($customerSchema, 'wp_safecontracts_customers'), 'customer master table created');
sc_assert(str_contains($customerSchema, 'internal_code varchar(100) NULL'), 'customer internal code is optional');
sc_assert(str_contains($customerSchema, 'UNIQUE KEY internal_code (internal_code)'), 'customer internal code is unique when supplied');
sc_assert(str_contains($customerSchema, 'is_active tinyint(1) NOT NULL DEFAULT 1'), 'customer active state stored explicitly');

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

$accountant = $GLOBALS['sc_test_roles'][RoleRegistrar::ACCOUNTANT]->capabilities;
sc_assert(isset($accountant[Capabilities::CREATE_CONTRACTS]), 'accountant can create contracts by default');
sc_assert(! isset($accountant[Capabilities::EDIT_CONTRACTS]), 'contract edit capability remains independently grantable');
sc_assert(isset($accountant[Capabilities::VIEW_ASSIGNED]), 'accountant defaults to assigned scope');

$manager = $GLOBALS['sc_test_roles'][RoleRegistrar::MANAGER]->capabilities;
sc_assert(isset($manager[Capabilities::VIEW_ALL]), 'manager defaults to all-data scope');
sc_assert(isset($manager[Capabilities::EDIT_CONTRACTS]), 'manager can edit contracts by default');

$admin = $GLOBALS['sc_test_roles']['administrator']->capabilities;
sc_assert(isset($admin[Capabilities::MANAGE_SYSTEM]), 'native WordPress administrator receives SafeContracts system capabilities');

// Boot after activation; repeated migrations and seeds must be idempotent.
$seedCountBeforeBoot = count($GLOBALS['sc_test_queries']);
do_action('plugins_loaded');
sc_assert(count($GLOBALS['sc_test_dbdelta']) === 3, 'migrations are not replayed after stored version is current');
sc_assert(count($GLOBALS['sc_test_queries']) === $seedCountBeforeBoot, 'default payment methods are not reseeded after current migration');
sc_assert(isset($GLOBALS['sc_test_actions']['rest_api_init']), 'REST registration hook attached');

do_action('rest_api_init');
sc_assert(isset($GLOBALS['sc_test_routes'][Router::NAMESPACE . '/health']), 'health endpoint registered');
sc_assert(isset($GLOBALS['sc_test_routes'][Router::NAMESPACE . '/me']), 'protected me endpoint registered');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
sc_assert(AccessScope::current() === AccessScope::ASSIGNED, 'assigned scope resolved from capabilities');
sc_assert(Router::canAccess() === true, 'authorized scoped user can access protected REST route');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true];
sc_assert(AccessScope::current() === AccessScope::ALL, 'all scope takes precedence when granted');

$GLOBALS['sc_test_current_caps'] = [];
sc_assert(Router::canAccess() instanceof WP_Error, 'unauthorized REST access returns WP_Error');

$health = Router::health(new WP_REST_Request());
sc_assert($health->status === 200, 'health response status is 200');
sc_assert($health->data['data']['service'] === 'SafeContracts', 'health response identifies service');

// Deactivation must be non-destructive so reactivation is safe.
$optionsBeforeDeactivate = $GLOBALS['sc_test_options'];
$rolesBeforeDeactivate = array_keys($GLOBALS['sc_test_roles']);
$migrationCountBeforeDeactivate = count($GLOBALS['sc_test_dbdelta']);
$seedCountBeforeDeactivate = count($GLOBALS['sc_test_queries']);
$deactivate = $GLOBALS['sc_test_deactivation_hooks'][SAFECONTRACTS_FILE];
$deactivate();

sc_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_deactivated']), 'deactivation lifecycle action fired');
sc_assert($GLOBALS['sc_test_options'] === $optionsBeforeDeactivate, 'deactivation preserves options and installation state');
sc_assert(array_keys($GLOBALS['sc_test_roles']) === $rolesBeforeDeactivate, 'deactivation preserves roles/capabilities');
sc_assert(count($GLOBALS['sc_test_dbdelta']) === $migrationCountBeforeDeactivate, 'deactivation does not mutate schema/data');
sc_assert(count($GLOBALS['sc_test_queries']) === $seedCountBeforeDeactivate, 'deactivation does not mutate reference data');

echo "SafeContracts PHP foundation tests passed ({$tests} assertions).\n";
