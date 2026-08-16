<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Notifications\DeviceTokenService;
use SafeContracts\Notifications\DirectNotificationService;
use SafeContracts\Notifications\RecipientResolver;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\NonCoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use SafeContracts\Tenancy\TenantMembershipRepository;

$assertions = 0;

function esc_recipient_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_recipient_source(string $relative): string
{
    $source = file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
    esc_recipient_assert($source !== false, 'recipient isolation source exists: ' . $relative);
    return $source === false ? '' : $source;
}

$GLOBALS['sc_test_options'][NonCoreTenantEnforcement::OPTION] = '1';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(17);

// Batch membership filtering is the final boundary for role, explicit-user and
// assigned-accountant candidates. Foreign and stale memberships are discarded.
$GLOBALS['sc_test_result_queue'] = [[
    ['user_id' => '42'],
    ['user_id' => '101'],
]];
$GLOBALS['sc_test_read_queries'] = [];
$memberships = new TenantMembershipRepository();
$active = $memberships->filterActiveUserIds(17, [42, 99, 100, 101, 101]);
esc_recipient_assert($active === [42, 101], 'membership batch filter keeps only active users returned for the current tenant');
$membershipSql = implode("\n", $GLOBALS['sc_test_read_queries']);
esc_recipient_assert(str_contains($membershipSql, 'm.tenant_id = 17'), 'membership filter is locked to the active tenant');
esc_recipient_assert(str_contains($membershipSql, "m.status = 'active'"), 'stale/inactive membership cannot pass recipient filtering');
esc_recipient_assert(str_contains($membershipSql, "t.status = 'active'"), 'disabled tenant cannot supply notification recipients');

// RecipientResolver may discover WordPress role members globally, but the final
// result is intersected with current-tenant active membership before fan-out.
$GLOBALS['sc_test_users_by_role']['safecontracts_manager'] = [42, 99];
$GLOBALS['sc_test_result_queue'] = [[
    ['user_id' => '42'],
    ['user_id' => '101'],
]];
$GLOBALS['sc_test_read_queries'] = [];
$resolved = (new RecipientResolver())->resolve([
    'recipient_roles' => ['safecontracts_manager'],
    'recipient_user_ids' => [100],
    'target_assigned_accountant' => true,
], 101);
esc_recipient_assert($resolved === [42, 101], 'foreign role member and explicit user are removed while active assigned accountant remains');
esc_recipient_assert(! in_array(99, $resolved, true) && ! in_array(100, $resolved, true), 'known foreign/stale user IDs cannot survive recipient resolution');
esc_recipient_assert(str_contains(implode("\n", $GLOBALS['sc_test_read_queries']), 'm.tenant_id = 17'), 'resolver performs tenant-membership intersection server-side');

// Direct messaging must fail before push/email transport when the requested user
// is not an active member of the current tenant.
$GLOBALS['sc_test_result_queue'] = [[]];
$directBlocked = false;
try {
    (new DirectNotificationService())->send(999, 'Tenant notice', 'Should not leave tenant 17.', false, true);
} catch (\DomainException $error) {
    $directBlocked = str_contains($error->getMessage(), 'not an active member');
}
esc_recipient_assert($directBlocked, 'direct notification rejects foreign/stale recipient before delivery transport');

// Device registration/revocation uses the same active-membership gate so a stale
// user cannot keep registering tenant-owned FCM tokens after membership removal.
$GLOBALS['sc_test_current_caps'][Capabilities::ACCESS] = true;
$GLOBALS['sc_test_result_queue'] = [[]];
$deviceBlocked = false;
try {
    (new DeviceTokenService())->register(str_repeat('t', 32), 'android');
} catch (\DomainException $error) {
    $deviceBlocked = str_contains($error->getMessage(), 'active membership');
}
esc_recipient_assert($deviceBlocked, 'stale member cannot register a device token in the tenant');

// Defense in depth: even a caller that bypasses resolver/service layers cannot
// persist a delivery for a non-member because DeliveryLogRepository validates the
// recipient membership alongside tenant-owned parent rows.
$deliverySource = esc_recipient_source('wordpress-plugin/safecontracts/src/Notifications/DeliveryLogRepository.php');
foreach ([
    'TenantMembershipRepository())->isActiveMember($tenantId, $userId)',
    'Notification recipient is not an active member of the current Enterprise tenant.',
    'if ($paymentId > 0)',
] as $marker) {
    esc_recipient_assert(str_contains($deliverySource, $marker), 'delivery membership defense marker is present: ' . $marker);
}

// Push and email revalidate membership at the final transport boundary. A stale
// schedule/plan cannot rely on membership state captured earlier in the pipeline.
$pushSource = esc_recipient_source('wordpress-plugin/safecontracts/src/Notifications/PushDeliveryService.php');
foreach ([
    'NonCoreTenantScope::tenantId()',
    'filterActiveUserIds($tenantId, $recipientIds)',
    '$this->tokens->activeForUsers($recipientIds)',
] as $marker) {
    esc_recipient_assert(str_contains($pushSource, $marker), 'push final-hop membership marker is present: ' . $marker);
}
$emailSource = esc_recipient_source('wordpress-plugin/safecontracts/src/Notifications/EmailDeliveryService.php');
foreach ([
    'NonCoreTenantScope::tenantId()',
    'filterActiveUserIds($tenantId, $recipients)',
    'foreach ($recipients as $userId)',
] as $marker) {
    esc_recipient_assert(str_contains($emailSource, $marker), 'email final-hop membership marker is present: ' . $marker);
}

// Tenant-owned operational keys/storage/read-state are namespaced. Firebase OAuth
// token cache is intentionally environment-global and keyed by Firebase project.
$schedulerSource = esc_recipient_source('wordpress-plugin/safecontracts/src/Notifications/NotificationScheduler.php');
foreach ([
    'safecontracts_notification_schedule_seeded_tenant_',
    'safecontracts_notification_schedule_last_run_tenant_',
] as $marker) {
    esc_recipient_assert(str_contains($schedulerSource, $marker), 'tenant-owned scheduler option is tenant-qualified: ' . $marker);
}
$storageSource = esc_recipient_source('wordpress-plugin/safecontracts/src/Import/PrivateImportStorage.php');
esc_recipient_assert(str_contains($storageSource, "'tenant-' . $tenantId . '/' . $sha256"), 'new import storage keys include tenant identity');
esc_recipient_assert(str_contains($storageSource, 'currentTenantId !== $keyTenantId'), 'foreign tenant import storage key is rejected');

$readStateSource = esc_recipient_source('wordpress-plugin/safecontracts/src/Notifications/NotificationReadStateRepository.php');
esc_recipient_assert(str_contains($readStateSource, 'NonCoreTenantScope::tenantId()'), 'notification read state resolves the active tenant');
esc_recipient_assert(str_contains($readStateSource, "self::META_KEY . '_tenant_' . $tenantId"), 'notification read-state user meta key includes tenant identity');

$firebaseSource = esc_recipient_source('wordpress-plugin/safecontracts/src/Notifications/FirebaseAccessTokenProvider.php');
esc_recipient_assert(str_contains($firebaseSource, 'self::$cache[$projectId]'), 'Firebase access-token cache stays keyed by environment-global project identity');
esc_recipient_assert(! str_contains($firebaseSource, 'NonCoreTenantScope'), 'Firebase environment credential cache is not incorrectly converted into business-tenant state');

TenantContextStore::reset();
$GLOBALS['sc_test_options'][NonCoreTenantEnforcement::OPTION] = '0';
$GLOBALS['sc_test_result_queue'] = [];
$GLOBALS['sc_test_results'] = [];
$GLOBALS['sc_test_users_by_role'] = [];
$GLOBALS['sc_test_current_caps'] = [];

fwrite(STDOUT, "Enterprise non-core recipient isolation passed ({$assertions} assertions).\n");
