<?php

declare(strict_types=1);

$GLOBALS['esc_admin_user_meta'] = [];

function get_user_meta(int $userId, string $key, bool $single = false): mixed
{
    $value = $GLOBALS['esc_admin_user_meta'][$userId][$key] ?? ($single ? '' : []);
    return $single ? $value : ($value === '' ? [] : [$value]);
}

function update_user_meta(int $userId, string $key, mixed $value): int|bool
{
    $GLOBALS['esc_admin_user_meta'][$userId][$key] = $value;
    return 1;
}

function delete_user_meta(int $userId, string $key): bool
{
    unset($GLOBALS['esc_admin_user_meta'][$userId][$key]);
    return true;
}

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\AdminShell;
use SafeContracts\Tenancy\AdminTenantContext;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_admin_context_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_admin_context_throws(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable) {
        esc_admin_context_assert(true, $message);
        return;
    }
    esc_admin_context_assert(false, $message);
}

$userId = 42;

$GLOBALS['esc_admin_user_meta'] = [];
$GLOBALS['sc_test_result_queue'] = [];
$GLOBALS['sc_test_results'] = [['tenant_id' => '17']];
$single = AdminTenantContext::resolveUser($userId);
esc_admin_context_assert($single === 17, 'single active membership auto-selects');
esc_admin_context_assert(TenantContextStore::context()->requireTenantId() === 17, 'single membership locks request TenantContext');
esc_admin_context_assert((int) get_user_meta($userId, AdminTenantContext::USER_META_KEY, true) === 17, 'single auto-selection is persisted as preference');

$GLOBALS['esc_admin_user_meta'] = [];
$GLOBALS['sc_test_result_queue'] = [];
$GLOBALS['sc_test_results'] = [['tenant_id' => '17'], ['tenant_id' => '18']];
$multiple = AdminTenantContext::resolveUser($userId);
esc_admin_context_assert($multiple === null, 'multiple memberships require explicit admin selection');
esc_admin_context_assert(! TenantContextStore::context()->hasTenant(), 'ambiguous multi-tenant admin remains fail closed');
esc_admin_context_assert(AdminTenantContext::storedTenantId($userId) === null, 'ambiguous membership does not persist arbitrary tenant');

$GLOBALS['esc_admin_user_meta'] = [$userId => [AdminTenantContext::USER_META_KEY => 18]];
$GLOBALS['sc_test_result_queue'] = [];
$GLOBALS['sc_test_results'] = [['id' => '1']];
$stored = AdminTenantContext::resolveUser($userId);
esc_admin_context_assert($stored === 18, 'stored tenant is accepted only after active-membership revalidation');
esc_admin_context_assert(TenantContextStore::context()->requireTenantId() === 18, 'validated stored tenant locks context');

$GLOBALS['esc_admin_user_meta'] = [$userId => [AdminTenantContext::USER_META_KEY => 99]];
$GLOBALS['sc_test_result_queue'] = [[], [['tenant_id' => '17']]];
$GLOBALS['sc_test_results'] = [];
$staleFallback = AdminTenantContext::resolveUser($userId);
esc_admin_context_assert($staleFallback === 17, 'stale stored tenant is discarded before single-membership fallback');
esc_admin_context_assert((int) get_user_meta($userId, AdminTenantContext::USER_META_KEY, true) === 17, 'stale preference is replaced with verified single tenant');
esc_admin_context_assert(TenantContextStore::context()->requireTenantId() === 17, 'fallback tenant is locked only after membership query');

$GLOBALS['esc_admin_user_meta'] = [$userId => [AdminTenantContext::USER_META_KEY => 99]];
$GLOBALS['sc_test_result_queue'] = [[], [['tenant_id' => '17'], ['tenant_id' => '18']]];
$GLOBALS['sc_test_results'] = [];
$staleAmbiguous = AdminTenantContext::resolveUser($userId);
esc_admin_context_assert($staleAmbiguous === null, 'stale preference plus multiple memberships remains fail closed');
esc_admin_context_assert(AdminTenantContext::storedTenantId($userId) === null, 'stale unauthorized preference is deleted');
esc_admin_context_assert(! TenantContextStore::context()->hasTenant(), 'stale ambiguous resolution cannot leak prior request context');

$GLOBALS['esc_admin_user_meta'] = [];
$GLOBALS['sc_test_result_queue'] = [];
$GLOBALS['sc_test_results'] = [];
esc_admin_context_throws(
    static fn () => AdminTenantContext::selectForUser($userId, 77),
    'explicit unauthorized tenant selection is rejected'
);
esc_admin_context_assert(AdminTenantContext::storedTenantId($userId) === null, 'unauthorized selection is never persisted');

$GLOBALS['sc_test_results'] = [['id' => '1']];
$selected = AdminTenantContext::selectForUser($userId, 18);
esc_admin_context_assert($selected === 18, 'explicit active membership selection succeeds');
esc_admin_context_assert((int) get_user_meta($userId, AdminTenantContext::USER_META_KEY, true) === 18, 'authorized selection persists preference');
esc_admin_context_assert(TenantContextStore::context()->requireTenantId() === 18, 'authorized selection locks tenant context');

esc_admin_context_assert(AdminTenantContext::safePageSlug('safecontracts-contracts') === 'safecontracts-contracts', 'known SafeContracts page slug is retained');
esc_admin_context_assert(AdminTenantContext::safePageSlug('https://evil.example/path') === AdminShell::SLUG, 'external redirect-shaped input collapses to local SafeContracts dashboard');
esc_admin_context_assert(AdminTenantContext::safePageSlug('../users.php') === AdminShell::SLUG, 'non-SafeContracts admin slug cannot be used as tenant-switch redirect');

$source = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Tenancy/AdminTenantContext.php');
esc_admin_context_assert(is_string($source) && str_contains($source, 'check_admin_referer(self::SELECT_ACTION)'), 'admin switch action requires CSRF nonce verification');
esc_admin_context_assert(is_string($source) && str_contains($source, 'current_user_can(Capabilities::ACCESS)'), 'admin switch action requires SafeContracts access capability');
esc_admin_context_assert(is_string($source) && str_contains($source, "admin_url('admin.php')"), 'tenant switch redirect is constructed from local WordPress admin URL');
esc_admin_context_assert(is_string($source) && str_contains($source, 'wp_safe_redirect'), 'admin switch uses safe WordPress redirect');
esc_admin_context_assert(is_string($source) && ! str_contains($source, 'redirect_to'), 'tenant switch does not accept a caller-supplied redirect URL');
esc_admin_context_assert(is_string($source) && str_contains($source, 'TenantDirectoryRepository'), 'switcher tenant options come from authorized tenant directory');
esc_admin_context_assert(is_string($source) && str_contains($source, "add_action('admin_init', [self::class, 'resolveRequest'], 1)"), 'admin tenant resolution is wired to admin request lifecycle');
esc_admin_context_assert(is_string($source) && str_contains($source, "add_action('admin_post_' . self::SELECT_ACTION, [self::class, 'handleSelect'])"), 'tenant switch admin-post handler is wired');

$plugin = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Plugin.php');
esc_admin_context_assert(is_string($plugin) && str_contains($plugin, 'AdminTenantContext::register();'), 'plugin boot registers admin tenant context');

TenantContextStore::reset();
$GLOBALS['esc_admin_user_meta'] = [];
$GLOBALS['sc_test_result_queue'] = [];
$GLOBALS['sc_test_results'] = [];

fwrite(STDOUT, "Enterprise Admin tenant context passed ({$assertions} assertions).\n");
