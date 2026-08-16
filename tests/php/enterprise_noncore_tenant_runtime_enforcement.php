<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Audit\AuditRepository;
use SafeContracts\Import\ImportRunRepository;
use SafeContracts\Import\PrivateImportStorage;
use SafeContracts\Notifications\DeviceTokenRepository;
use SafeContracts\Notifications\NotificationRuleRepository;
use SafeContracts\Notifications\NotificationTemplateRepository;
use SafeContracts\Tenancy\NonCoreTenantEnforcement;
use SafeContracts\Tenancy\NonCoreTenantSchemaHardener;
use SafeContracts\Tenancy\NonCoreTenantScope;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_noncore_runtime_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_noncore_runtime_source(string $relative): string
{
    $source = file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
    esc_noncore_runtime_assert($source !== false, 'runtime isolation source exists: ' . $relative);
    return $source === false ? '' : $source;
}

$GLOBALS['sc_test_options'][NonCoreTenantEnforcement::OPTION] = '0';
$GLOBALS['sc_test_options'][NonCoreTenantSchemaHardener::OPTION] = '0';
TenantContextStore::reset();

// Rollout order: ownership verification may enable runtime enforcement while
// schema hardening is still intentionally false.
NonCoreTenantEnforcement::enable();
esc_noncore_runtime_assert(NonCoreTenantEnforcement::isEnabled(), 'verified ownership enables non-core runtime isolation');
esc_noncore_runtime_assert(get_option(NonCoreTenantSchemaHardener::OPTION, '0') !== '1', 'runtime enforcement does not require or silently perform schema hardening');

$missingContextBlocked = false;
try {
    NonCoreTenantScope::tenantId();
} catch (Throwable $error) {
    $missingContextBlocked = str_contains($error->getMessage(), 'tenant context is required');
}
esc_noncore_runtime_assert($missingContextBlocked, 'non-core runtime enforcement fails closed without a locked tenant context');

TenantContextStore::context()->setTenantId(17);
esc_noncore_runtime_assert(NonCoreTenantScope::tenantId() === 17, 'locked tenant context is used for non-core runtime scope');
esc_noncore_runtime_assert(NonCoreTenantScope::condition() === ' AND tenant_id = 17', 'tenant SQL predicate is deterministic');
esc_noncore_runtime_assert(NonCoreTenantScope::condition('s.tenant_id') === ' AND s.tenant_id = 17', 'aliased tenant SQL predicate is deterministic');

// Known-ID/device operations must emit tenant-qualified mutation/read SQL.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
(new DeviceTokenRepository())->revokeOwned(42, 'tenant-device-token');
$deviceMutation = implode("\n", $GLOBALS['sc_test_queries']);
esc_noncore_runtime_assert(str_contains($deviceMutation, 'tenant_id = 17'), 'device revocation cannot mutate another tenant by known token');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
(new DeviceTokenRepository())->activeForUsers([42, 43]);
$deviceRead = implode("\n", $GLOBALS['sc_test_read_queries']);
esc_noncore_runtime_assert(str_contains($deviceRead, 'tenant_id = 17'), 'device fanout lookup is tenant-scoped');

// Rule/template lookups and creates are scoped. Empty result queues force the
// tenant insert path, proving legacy ON DUPLICATE cannot cross-update another tenant.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$ruleRepository = new NotificationRuleRepository();
$ruleRepository->findById(991);
esc_noncore_runtime_assert(str_contains(implode("\n", $GLOBALS['sc_test_read_queries']), 'tenant_id = 17'), 'known notification rule ID lookup is tenant-scoped');

$GLOBALS['sc_test_results'] = [];
$GLOBALS['sc_test_result_queue'] = [];
$GLOBALS['sc_test_queries'] = [];
$ruleRepository->save([
    'code' => 'tenant_due',
    'name' => 'Tenant Due',
    'trigger_type' => 'before_due',
    'days_before' => 3,
    'days_after' => 0,
    'repeat_interval_days' => 0,
    'max_repeats' => 0,
    'recipient_roles' => [],
    'recipient_user_ids' => [],
    'escalation_roles' => [],
    'target_assigned_accountant' => false,
    'push_enabled' => true,
    'email_enabled' => false,
    'template_code' => 'tenant_due',
    'is_active' => true,
], 42);
esc_noncore_runtime_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), '(tenant_id, code, name'), 'new notification rule is written with direct tenant ownership');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$templateRepository = new NotificationTemplateRepository();
$templateRepository->findByCode('tenant_due');
esc_noncore_runtime_assert(str_contains(implode("\n", $GLOBALS['sc_test_read_queries']), 'tenant_id = 17'), 'notification template code lookup is tenant-scoped');

// Import root/error repository queries carry direct ownership.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['wpdb']->insert_id = 0;
$runId = (new ImportRunRepository())->create('tenant.xlsx', 'tenant-17/' . str_repeat('a', 64), str_repeat('a', 64), 100, 42);
esc_noncore_runtime_assert($runId > 0, 'tenant import run can be created');
esc_noncore_runtime_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), '(tenant_id, original_filename, storage_key'), 'import run persists tenant_id directly');

$GLOBALS['sc_test_read_queries'] = [];
(new ImportRunRepository())->find(999);
esc_noncore_runtime_assert(str_contains(implode("\n", $GLOBALS['sc_test_read_queries']), 'tenant_id = 17'), 'known import run ID lookup is tenant-scoped');

// New stored files are tenant-keyed and a key owned by another tenant is rejected.
$tempDir = sys_get_temp_dir() . '/esc-noncore-runtime-' . bin2hex(random_bytes(4));
@mkdir($tempDir, 0700, true);
$sourcePath = $tempDir . '/source.xlsx';
file_put_contents($sourcePath, 'tenant import fixture');
$sha = hash_file('sha256', $sourcePath);
$storage = new PrivateImportStorage($tempDir . '/private', static function (string $source, string $destination): bool {
    return copy($source, $destination);
});
$key = $storage->store($sourcePath, $sha);
esc_noncore_runtime_assert($key === 'tenant-17/' . $sha, 'new import storage key includes tenant identity');
esc_noncore_runtime_assert(str_contains($storage->pathForKey($key), '/tenant-17/'), 'tenant storage path is separated on disk');
$foreignStorageBlocked = false;
try {
    $storage->pathForKey('tenant-18/' . $sha);
} catch (Throwable $error) {
    $foreignStorageBlocked = str_contains($error->getMessage(), 'another tenant');
}
esc_noncore_runtime_assert($foreignStorageBlocked, 'known foreign tenant storage key is rejected');
@unlink($sourcePath);
@unlink($tempDir . '/private/tenant-17/' . $sha . '.xlsx');
@unlink($tempDir . '/private/tenant-17/.htaccess');
@unlink($tempDir . '/private/tenant-17/index.php');
@unlink($tempDir . '/private/.htaccess');
@unlink($tempDir . '/private/index.php');
@rmdir($tempDir . '/private/tenant-17');
@rmdir($tempDir . '/private');
@rmdir($tempDir);

// Tenant-owned audit writes/reads use the direct tenant column. Platform-global
// audit remains explicitly representable with nullable tenant ownership.
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['wpdb']->insert_id = 0;
$audit = new AuditRepository();
$audit->append('contract', 77, 'contract_status_changed', 42, null, ['status' => 'active'], null);
$tenantAuditInsert = implode("\n", $GLOBALS['sc_test_queries']);
esc_noncore_runtime_assert(str_contains($tenantAuditInsert, '(tenant_id, entity_type'), 'tenant audit write persists tenant_id directly');
esc_noncore_runtime_assert(str_contains($tenantAuditInsert, 'VALUES (17,'), 'tenant audit write uses locked tenant id');

$GLOBALS['sc_test_queries'] = [];
$audit->append('system', null, 'system_health_checked', 42, null, null, null);
$globalAuditInsert = implode("\n", $GLOBALS['sc_test_queries']);
esc_noncore_runtime_assert(! str_contains($globalAuditInsert, '(tenant_id, entity_type'), 'explicit platform-global audit remains nullable/global');

$GLOBALS['sc_test_read_queries'] = [];
$audit->forEntity('contract', 77, 20);
$auditRead = implode("\n", $GLOBALS['sc_test_read_queries']);
esc_noncore_runtime_assert(str_contains($auditRead, 'tenant_id = 17'), 'audit known-ID browsing is tenant-scoped');
esc_noncore_runtime_assert(str_contains($auditRead, 'tenant_id IS NULL'), 'audit browsing permits only explicitly documented platform-global rows alongside tenant rows');

// Static full-impact assertions cover repositories whose integration calls need
// richer fixtures than the shared fake database provides.
$deliverySource = esc_noncore_runtime_source('wordpress-plugin/safecontracts/src/Notifications/DeliveryLogRepository.php');
foreach (['NonCoreTenantScope::condition()', 'assertTenantParents', '(tenant_id, rule_id, payment_id'] as $marker) {
    esc_noncore_runtime_assert(str_contains($deliverySource, $marker), 'delivery isolation marker is present: ' . $marker);
}

$scheduleSource = esc_noncore_runtime_source('wordpress-plugin/safecontracts/src/Notifications/NotificationScheduleRepository.php');
foreach (['NonCoreTenantScope::tenantId()', 'tenant_id = %d AND rule_id = %d AND payment_id = %d', 'NonCoreTenantScope::condition()', 'payment does not belong to the active Enterprise tenant'] as $marker) {
    esc_noncore_runtime_assert(str_contains($scheduleSource, $marker), 'schedule isolation marker is present: ' . $marker);
}

$suppressionSource = esc_noncore_runtime_source('wordpress-plugin/safecontracts/src/Notifications/NotificationSuppressionRepository.php');
foreach (['assertScopeOwnership', 'tenant_id = %d AND scope_type = %s AND scope_id = %d', 'NonCoreTenantScope::condition()'] as $marker) {
    esc_noncore_runtime_assert(str_contains($suppressionSource, $marker), 'suppression isolation marker is present: ' . $marker);
}

$schedulerSource = esc_noncore_runtime_source('wordpress-plugin/safecontracts/src/Notifications/NotificationScheduler.php');
foreach (['TenantDirectoryRepository())->activeIds()', 'TenantContextStore::context()->setTenantId($tenantId)', 'finally {', 'TenantContextStore::reset()', 'safecontracts_notification_schedule_last_run_tenant_'] as $marker) {
    esc_noncore_runtime_assert(str_contains($schedulerSource, $marker), 'background tenant isolation marker is present: ' . $marker);
}

$enforcementSource = esc_noncore_runtime_source('wordpress-plugin/safecontracts/src/Tenancy/NonCoreTenantEnforcement.php');
esc_noncore_runtime_assert(! str_contains($enforcementSource, 'isHardened()'), 'runtime enforcement no longer depends on prior schema hardening');
$hardenerSource = esc_noncore_runtime_source('wordpress-plugin/safecontracts/src/Tenancy/NonCoreTenantSchemaHardener.php');
esc_noncore_runtime_assert(str_contains($hardenerSource, 'NonCoreTenantEnforcement::isEnabled()'), 'schema hardening requires prior runtime enforcement');

TenantContextStore::reset();
$GLOBALS['sc_test_options'][NonCoreTenantEnforcement::OPTION] = '0';
$GLOBALS['sc_test_options'][NonCoreTenantSchemaHardener::OPTION] = '0';
$GLOBALS['sc_test_results'] = [];
$GLOBALS['sc_test_result_queue'] = [];

fwrite(STDOUT, "Enterprise non-core tenant runtime enforcement passed ({$assertions} assertions).\n");
