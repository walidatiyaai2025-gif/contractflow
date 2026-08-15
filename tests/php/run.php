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
sc_assert(count($GLOBALS['sc_test_dbdelta']) === 1, 'foundation custom table migration executed once');
sc_assert(str_contains($GLOBALS['sc_test_dbdelta'][0], 'wp_safecontracts_meta'), 'migration uses WordPress prefix');
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

// Boot after activation; repeated migration must be idempotent.
do_action('plugins_loaded');
sc_assert(count($GLOBALS['sc_test_dbdelta']) === 1, 'migration is not replayed after stored version is current');
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
$deactivate = $GLOBALS['sc_test_deactivation_hooks'][SAFECONTRACTS_FILE];
$deactivate();

sc_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_deactivated']), 'deactivation lifecycle action fired');
sc_assert($GLOBALS['sc_test_options'] === $optionsBeforeDeactivate, 'deactivation preserves options and installation state');
sc_assert(array_keys($GLOBALS['sc_test_roles']) === $rolesBeforeDeactivate, 'deactivation preserves roles/capabilities');
sc_assert(count($GLOBALS['sc_test_dbdelta']) === $migrationCountBeforeDeactivate, 'deactivation does not mutate schema/data');

echo "SafeContracts PHP foundation tests passed ({$tests} assertions).\n";
