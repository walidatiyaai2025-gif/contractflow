<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Rest\EnterpriseRateLimitGuard;
use SafeContracts\Rest\EnterpriseRateLimitStore;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\NonCoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p2_rate_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

final class ESC_P2_Rate_Request extends WP_REST_Request
{
    public function __construct(
        array $jsonParams,
        private string $route,
        private string $method = 'GET'
    ) {
        parent::__construct($jsonParams);
    }

    public function get_route(): string
    {
        return $this->route;
    }

    public function get_method(): string
    {
        return $this->method;
    }
}

function esc_p2_rate_last_write(): string
{
    $query = end($GLOBALS['sc_test_queries']);
    return is_string($query) ? $query : '';
}

$root = dirname(__DIR__, 2);
$guardPath = $root . '/wordpress-plugin/safecontracts/src/Rest/EnterpriseRateLimitGuard.php';
$storePath = $root . '/wordpress-plugin/safecontracts/src/Rest/EnterpriseRateLimitStore.php';
$migrationPath = $root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0019EnterpriseRateLimits.php';
$migratorPath = $root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php';
$pluginPath = $root . '/wordpress-plugin/safecontracts/src/Plugin.php';
$abusePath = $root . '/wordpress-plugin/safecontracts/src/Rest/ApiAbuseGuard.php';
$guardSource = (string) file_get_contents($guardPath);
$storeSource = (string) file_get_contents($storePath);
$migrationSource = (string) file_get_contents($migrationPath);
$migratorSource = (string) file_get_contents($migratorPath);
$pluginSource = (string) file_get_contents($pluginPath);
$abuseSource = (string) file_get_contents($abusePath);

esc_p2_rate_assert(EnterpriseRateLimitGuard::LOGIN_IP_LIMIT === 10 && EnterpriseRateLimitGuard::LOGIN_IP_WINDOW === 300, 'login IP default is 10 attempts per 5 minutes');
esc_p2_rate_assert(EnterpriseRateLimitGuard::LOGIN_USERNAME_LIMIT === 20 && EnterpriseRateLimitGuard::LOGIN_USERNAME_WINDOW === 900, 'login username default is 20 attempts per 15 minutes');
esc_p2_rate_assert(EnterpriseRateLimitGuard::AUTH_READ_LIMIT === 300 && EnterpriseRateLimitGuard::AUTH_READ_WINDOW === 60, 'authenticated read default is 300 requests per minute');
esc_p2_rate_assert(EnterpriseRateLimitGuard::AUTH_WRITE_LIMIT === 120 && EnterpriseRateLimitGuard::AUTH_WRITE_WINDOW === 60, 'authenticated write default is 120 requests per minute');
esc_p2_rate_assert(EnterpriseRateLimitGuard::isSafeContractsRoute('/safecontracts/v1/contracts'), 'SafeContracts v1 route classification is explicit');
esc_p2_rate_assert(! EnterpriseRateLimitGuard::isSafeContractsRoute('/wp/v2/users'), 'unrelated WordPress REST namespaces are excluded');

// ESC-only boundary: with both Enterprise enforcement flags disabled, no limiter
// storage is touched and the legacy Safe Contract request path remains unchanged.
CoreTenantEnforcement::disable();
NonCoreTenantEnforcement::disable();
TenantContextStore::reset();
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$disabledRequest = new ESC_P2_Rate_Request([], '/safecontracts/v1/contracts', 'GET');
$disabledResult = EnterpriseRateLimitGuard::enforce(null, null, $disabledRequest);
esc_p2_rate_assert($disabledResult === null, 'rate limiting is a no-op outside Enterprise enforcement');
esc_p2_rate_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'non-ESC request performs no limiter database access');

update_option(CoreTenantEnforcement::OPTION, '1', false);
update_option(NonCoreTenantEnforcement::OPTION, '0', false);
esc_p2_rate_assert(EnterpriseRateLimitGuard::isEnabled(), 'core Enterprise enforcement activates rate limiter');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$healthResult = EnterpriseRateLimitGuard::enforce(null, null, new ESC_P2_Rate_Request([], '/safecontracts/v1/health', 'GET'));
esc_p2_rate_assert($healthResult === null && $GLOBALS['sc_test_queries'] === [], 'health route remains outside throttling');

// Login abuse uses privacy-safe independent IP and username digests. Neither raw
// identity appears in persisted SQL and the response does not disclose bucket type.
$_SERVER['REMOTE_ADDR'] = '203.0.113.5';
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_results'] = [['request_count' => '1', 'retry_after' => '299']];
$login = new ESC_P2_Rate_Request(['username' => 'Alice@example.test', 'password' => 'secret'], '/safecontracts/v1/auth/login', 'POST');
$loginAllowed = EnterpriseRateLimitGuard::enforce(null, null, $login);
esc_p2_rate_assert($loginAllowed === null, 'ordinary login attempt remains allowed');
esc_p2_rate_assert(count($GLOBALS['sc_test_queries']) === 2, 'allowed login consumes independent IP and username buckets');
$loginSql = implode("\n", $GLOBALS['sc_test_queries']);
esc_p2_rate_assert(! str_contains($loginSql, '203.0.113.5'), 'raw client IP is never persisted in limiter SQL');
esc_p2_rate_assert(! str_contains(strtolower($loginSql), 'alice@example.test'), 'raw login username is never persisted in limiter SQL');
esc_p2_rate_assert(! str_contains($loginSql, 'secret'), 'password is never part of limiter storage');
esc_p2_rate_assert(preg_match_all("/'[a-f0-9]{64}'/", $loginSql) >= 2, 'login persistence uses SHA-256 HMAC bucket digests');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_results'] = [['request_count' => '11', 'retry_after' => '123']];
$blocked = EnterpriseRateLimitGuard::enforce(null, null, $login);
esc_p2_rate_assert($blocked instanceof WP_Error, 'login over the IP ceiling returns an error');
esc_p2_rate_assert($blocked->code === 'safecontracts_esc_rate_limited', '429 uses a stable Enterprise rate-limit error code');
esc_p2_rate_assert(($blocked->data['status'] ?? null) === 429, 'rate-limit response uses HTTP 429');
esc_p2_rate_assert(($blocked->data['details']['retry_after'] ?? null) === 123, 'rate-limit response exposes retry delay metadata');
esc_p2_rate_assert(! isset($blocked->data['details']['scope']), 'rate-limit response does not reveal which identity bucket was exceeded');
esc_p2_rate_assert(str_contains(esc_p2_rate_last_write(), 'DELETE FROM wp_safecontracts_esc_rate_limits'), 'active throttling triggers bounded expired-bucket cleanup');

// Authenticated identity is user + server-authoritative locked tenant. The request
// header never enters the bucket material, so spoofing it cannot redirect accounting.
$GLOBALS['sc_test_results'] = [['request_count' => '1', 'retry_after' => '59']];
$GLOBALS['sc_test_queries'] = [];
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(17);
unset($_SERVER['HTTP_X_ESC_TENANT_ID']);
$readRequest = new ESC_P2_Rate_Request([], '/safecontracts/v1/contracts', 'GET');
EnterpriseRateLimitGuard::enforce(null, null, $readRequest);
$tenant17ReadSql = esc_p2_rate_last_write();

$GLOBALS['sc_test_queries'] = [];
$_SERVER['HTTP_X_ESC_TENANT_ID'] = '999999';
EnterpriseRateLimitGuard::enforce(null, null, $readRequest);
$tenant17ForgedHeaderSql = esc_p2_rate_last_write();
esc_p2_rate_assert($tenant17ReadSql === $tenant17ForgedHeaderSql, 'forged tenant header cannot redirect a locked-tenant rate-limit bucket');

$GLOBALS['sc_test_queries'] = [];
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(18);
EnterpriseRateLimitGuard::enforce(null, null, $readRequest);
$tenant18ReadSql = esc_p2_rate_last_write();
esc_p2_rate_assert($tenant17ReadSql !== $tenant18ReadSql, 'different locked tenants receive distinct authenticated buckets for the same user');

$GLOBALS['sc_test_queries'] = [];
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(17);
$writeRequest = new ESC_P2_Rate_Request([], '/safecontracts/v1/contracts/5', 'POST');
EnterpriseRateLimitGuard::enforce(null, null, $writeRequest);
$tenant17WriteSql = esc_p2_rate_last_write();
esc_p2_rate_assert($tenant17ReadSql !== $tenant17WriteSql, 'read and write traffic use separate bucket classes');

// Persistence must update the current fixed window atomically and reset its count
// inside the same upsert when the stored window has expired.
esc_p2_rate_assert(str_contains($storeSource, 'ON DUPLICATE KEY UPDATE'), 'rate-limit counter uses one atomic upsert mutation');
esc_p2_rate_assert(str_contains($storeSource, 'request_count = IF(window_expires_at <= UTC_TIMESTAMP(), 1, request_count + 1)'), 'atomic upsert resets expired window or increments active window');
esc_p2_rate_assert(str_contains($storeSource, 'window_expires_at = IF(window_expires_at <= UTC_TIMESTAMP()'), 'expiry reset occurs atomically with the counter update');
esc_p2_rate_assert(str_contains($storeSource, 'LIMIT %d'), 'expired-row cleanup is explicitly bounded');
esc_p2_rate_assert(! str_contains($storeSource, 'username') && ! str_contains($storeSource, 'REMOTE_ADDR'), 'persistence layer has no raw identity-specific columns or inputs');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_results'] = [['request_count' => '3', 'retry_after' => '45']];
$storeState = (new EnterpriseRateLimitStore())->hit(str_repeat('a', 64), 5, 60);
esc_p2_rate_assert($storeState['allowed'] === true && $storeState['count'] === 3 && $storeState['retry_after'] === 45, 'store returns normalized counter state after atomic hit');
esc_p2_rate_assert(str_contains($GLOBALS['sc_test_queries'][0] ?? '', 'wp_safecontracts_esc_rate_limits'), 'rate-limit store uses isolated Enterprise limiter table');

esc_p2_rate_assert(str_contains($migrationSource, 'bucket_key char(64) NOT NULL'), 'rate-limit schema stores only fixed-size hashed bucket identity');
esc_p2_rate_assert(str_contains($migrationSource, 'PRIMARY KEY (bucket_key)'), 'bucket identity is unique for atomic upsert semantics');
esc_p2_rate_assert(str_contains($migrationSource, 'KEY expires_at (window_expires_at)'), 'expiry cleanup has an indexed predicate');
esc_p2_rate_assert(str_contains($migratorSource, "LATEST_VERSION = '1.18.0'"), 'P2-007 schema is a versioned migration');
esc_p2_rate_assert(str_contains($migratorSource, 'Migration0019EnterpriseRateLimits::class'), 'P2-007 migration is registered in the migrator');

esc_p2_rate_assert(str_contains($pluginSource, 'EnterpriseRateLimitGuard::register();'), 'plugin boot wires the Enterprise rate-limit guard');
esc_p2_rate_assert(str_contains($guardSource, "add_filter('rest_request_before_callbacks', [self::class, 'enforce'], 20, 3)"), 'rate limiter runs after tenant context reset/core guard priorities');
esc_p2_rate_assert(! str_contains($guardSource, 'HTTP_X_FORWARDED_FOR'), 'limiter never trusts X-Forwarded-For directly');
esc_p2_rate_assert(str_contains($guardSource, 'safecontracts_esc_rate_limit_client_ip'), 'trusted proxy deployments have an explicit server-side client-IP override hook');
esc_p2_rate_assert(str_contains($guardSource, 'safecontracts_esc_rate_limit_policy'), 'rate-limit policy can be tuned only through a server-side filter');
esc_p2_rate_assert(str_contains($abuseSource, 'MAX_QUERY_PARAMS') && str_contains($abuseSource, 'MAX_STRING_BYTES'), 'existing ApiAbuseGuard request-shape protections remain intact');

unset($_SERVER['HTTP_X_ESC_TENANT_ID'], $_SERVER['REMOTE_ADDR']);
CoreTenantEnforcement::disable();
NonCoreTenantEnforcement::disable();
TenantContextStore::reset();

fwrite(STDOUT, "Enterprise REST rate limiting P2-007 passed ({$assertions} assertions).\n");
