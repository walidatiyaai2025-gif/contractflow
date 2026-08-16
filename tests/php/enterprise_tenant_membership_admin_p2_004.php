<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (! function_exists('get_userdata')) {
    function get_userdata(int $userId): object|false
    {
        return in_array($userId, [42, 77, 88, 99], true) ? (object) ['ID' => $userId] : false;
    }
}

require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\NonCoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use SafeContracts\Tenancy\TenantMembershipAdminService;
use SafeContracts\Tenancy\TenantMembershipRepository;
use SafeContracts\Tenancy\TenantRolePolicy;

final class ESC_P2_Membership_Wpdb extends SC_Test_Wpdb
{
    /** @var list<int|false> */
    public array $forcedQueryResults = [];

    public function query(string $sql): int|false
    {
        $this->queries[] = $sql;
        if ($this->forcedQueryResults !== []) {
            return array_shift($this->forcedQueryResults);
        }
        return 1;
    }
}

$assertions = 0;

function esc_p2_membership_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p2_membership_throws(callable $callback, string $message, ?string $contains = null): void
{
    try {
        $callback();
    } catch (Throwable $throwable) {
        esc_p2_membership_assert(
            $contains === null || str_contains($throwable->getMessage(), $contains),
            $message
        );
        return;
    }
    esc_p2_membership_assert(false, $message);
}

/** @return array<string,string> */
function esc_p2_membership_row(int $userId, string $role, bool $owner = false, string $status = 'active'): array
{
    return [
        'id' => (string) (1000 + $userId),
        'tenant_id' => '55',
        'user_id' => (string) $userId,
        'role_code' => $role,
        'status' => $status,
        'is_owner' => $owner ? '1' : '0',
    ];
}

function esc_p2_membership_queue(array ...$resultSets): void
{
    $GLOBALS['sc_test_result_queue'] = $resultSets;
    $GLOBALS['sc_test_results'] = [];
}

$wpdb = new ESC_P2_Membership_Wpdb();
$GLOBALS['wpdb'] = $wpdb;
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::MANAGE_USERS => true,
    Capabilities::VIEW_ALL => true,
];
$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
$GLOBALS['sc_test_options'][NonCoreTenantEnforcement::OPTION] = '0';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(55);

$repository = new TenantMembershipRepository();
$service = new TenantMembershipAdminService($repository);

esc_p2_membership_assert(TenantRolePolicy::isRecognized(TenantRolePolicy::MEMBER), 'legacy member remains recognized for compatibility');
esc_p2_membership_assert(! TenantRolePolicy::isAssignable(TenantRolePolicy::MEMBER), 'legacy member is not used for new deliberate assignments');
foreach ([TenantRolePolicy::TENANT_ADMIN, TenantRolePolicy::MANAGER, TenantRolePolicy::ACCOUNTANT, TenantRolePolicy::VIEWER] as $role) {
    esc_p2_membership_assert(TenantRolePolicy::isAssignable($role), "{$role} is explicitly assignable");
}

// New membership: actor authorization succeeds, target is absent, UPDATE misses,
// then INSERT creates a non-owner row in the locked tenant.
esc_p2_membership_queue(
    [esc_p2_membership_row(42, TenantRolePolicy::TENANT_ADMIN)],
    [esc_p2_membership_row(42, TenantRolePolicy::TENANT_ADMIN)],
    []
);
$wpdb->queries = [];
$wpdb->forcedQueryResults = [0, 1];
$service->assignRole(77, TenantRolePolicy::VIEWER, 42);
$writes = $wpdb->queries;
esc_p2_membership_assert(count($writes) === 2, 'new membership falls through scoped update to explicit insert');
esc_p2_membership_assert(str_contains($writes[0], 'WHERE tenant_id = 55 AND user_id = 77 AND is_owner = 0'), 'role update is scoped by locked tenant and user and cannot touch owners');
esc_p2_membership_assert(str_contains($writes[1], 'VALUES (55, 77'), 'new membership insert uses the locked tenant id');
esc_p2_membership_assert(str_contains($writes[1], "'viewer', 'active', 0"), 'new deliberate membership is active, explicitly assigned and never grants owner');

// Existing non-owner membership changes role in place without a cross-tenant key.
esc_p2_membership_queue(
    [esc_p2_membership_row(42, TenantRolePolicy::TENANT_ADMIN)],
    [esc_p2_membership_row(42, TenantRolePolicy::TENANT_ADMIN)],
    [esc_p2_membership_row(77, TenantRolePolicy::VIEWER)]
);
$wpdb->queries = [];
$wpdb->forcedQueryResults = [1];
$service->assignRole(77, TenantRolePolicy::ACCOUNTANT, 42);
esc_p2_membership_assert(count($wpdb->queries) === 1, 'existing non-owner role update does not insert a duplicate membership');
esc_p2_membership_assert(str_contains($wpdb->queries[0], "role_code = 'accountant'"), 'role update stores the validated explicit role');
esc_p2_membership_assert(str_contains($wpdb->queries[0], 'tenant_id = 55 AND user_id = 77'), 'existing role mutation remains tenant+user scoped');

// Generic role flow cannot mutate any owner membership, preventing demotion and
// keeping ownership changes out of this task entirely.
esc_p2_membership_queue(
    [esc_p2_membership_row(42, TenantRolePolicy::TENANT_ADMIN, true)],
    [esc_p2_membership_row(42, TenantRolePolicy::TENANT_ADMIN, true)],
    [esc_p2_membership_row(88, TenantRolePolicy::TENANT_ADMIN, true)]
);
$wpdb->queries = [];
esc_p2_membership_throws(
    static fn () => $service->assignRole(88, TenantRolePolicy::VIEWER, 42),
    'owner role mutation is rejected by generic assignment flow',
    'Owner memberships cannot be changed'
);
esc_p2_membership_assert($wpdb->queries === [], 'rejected owner role mutation performs no write');

// Invalid/legacy role assignment is rejected before mutation.
esc_p2_membership_queue(
    [esc_p2_membership_row(42, TenantRolePolicy::TENANT_ADMIN)],
    [esc_p2_membership_row(42, TenantRolePolicy::TENANT_ADMIN)]
);
$wpdb->queries = [];
esc_p2_membership_throws(
    static fn () => $service->assignRole(77, TenantRolePolicy::MEMBER, 42),
    'legacy member cannot be deliberately assigned by the new admin flow',
    'assignable Enterprise tenant role'
);
esc_p2_membership_assert($wpdb->queries === [], 'invalid role assignment performs no write');

// Missing WordPress user cannot produce an orphaned tenant membership.
esc_p2_membership_queue(
    [esc_p2_membership_row(42, TenantRolePolicy::TENANT_ADMIN)],
    [esc_p2_membership_row(42, TenantRolePolicy::TENANT_ADMIN)]
);
$wpdb->queries = [];
esc_p2_membership_throws(
    static fn () => $service->assignRole(123456, TenantRolePolicy::VIEWER, 42),
    'unknown WordPress user cannot be assigned to tenant',
    'does not exist'
);
esc_p2_membership_assert($wpdb->queries === [], 'unknown WordPress user assignment performs no write');

// Non-owner deactivation uses an atomic owner-count guard even though the target
// is not an owner; the same SQL protects owner races without a read/write gap.
esc_p2_membership_queue(
    [esc_p2_membership_row(42, TenantRolePolicy::TENANT_ADMIN)],
    [esc_p2_membership_row(42, TenantRolePolicy::TENANT_ADMIN)],
    [esc_p2_membership_row(77, TenantRolePolicy::VIEWER)]
);
$wpdb->queries = [];
$wpdb->forcedQueryResults = [1];
$service->deactivate(77, 42);
$deactivateSql = end($wpdb->queries);
esc_p2_membership_assert(str_contains((string) $deactivateSql, 'target.tenant_id = 55'), 'deactivation is scoped to locked tenant');
esc_p2_membership_assert(str_contains((string) $deactivateSql, 'target.user_id = 77'), 'deactivation is scoped to target user within tenant');
esc_p2_membership_assert(str_contains((string) $deactivateSql, 'COUNT(*) AS owner_count'), 'deactivation contains atomic active-owner guard');
esc_p2_membership_assert(str_contains((string) $deactivateSql, "tenant_id = 55 AND status = 'active' AND is_owner = 1"), 'owner guard counts owners only inside same tenant');

// Non-owner actor may not deactivate an owner even when globally granted MANAGE_USERS.
esc_p2_membership_queue(
    [esc_p2_membership_row(42, TenantRolePolicy::TENANT_ADMIN)],
    [esc_p2_membership_row(42, TenantRolePolicy::TENANT_ADMIN)],
    [esc_p2_membership_row(88, TenantRolePolicy::TENANT_ADMIN, true)],
    [esc_p2_membership_row(42, TenantRolePolicy::TENANT_ADMIN)]
);
$wpdb->queries = [];
esc_p2_membership_throws(
    static fn () => $service->deactivate(88, 42),
    'non-owner actor cannot deactivate owner membership',
    'Only an active tenant owner'
);
esc_p2_membership_assert($wpdb->queries === [], 'non-owner owner-deactivation attempt performs no write');

// Last owner: database guard reports zero affected rows; service turns that into a
// fail-closed owner invariant rather than treating it as success.
esc_p2_membership_queue(
    [esc_p2_membership_row(42, TenantRolePolicy::TENANT_ADMIN, true)],
    [esc_p2_membership_row(42, TenantRolePolicy::TENANT_ADMIN, true)],
    [esc_p2_membership_row(88, TenantRolePolicy::TENANT_ADMIN, true)],
    [esc_p2_membership_row(42, TenantRolePolicy::TENANT_ADMIN, true)]
);
$wpdb->queries = [];
$wpdb->forcedQueryResults = [0];
esc_p2_membership_throws(
    static fn () => $service->deactivate(88, 42),
    'last active owner cannot be deactivated',
    'last active tenant owner'
);
esc_p2_membership_assert(count($wpdb->queries) === 1, 'last-owner protection is enforced by one atomic guarded write');

// With another active owner, the same guarded write may succeed.
esc_p2_membership_queue(
    [esc_p2_membership_row(42, TenantRolePolicy::TENANT_ADMIN, true)],
    [esc_p2_membership_row(42, TenantRolePolicy::TENANT_ADMIN, true)],
    [esc_p2_membership_row(88, TenantRolePolicy::TENANT_ADMIN, true)],
    [esc_p2_membership_row(42, TenantRolePolicy::TENANT_ADMIN, true)]
);
$wpdb->queries = [];
$wpdb->forcedQueryResults = [1];
$service->deactivate(88, 42);
esc_p2_membership_assert(count($wpdb->queries) === 1, 'owner deactivation may succeed only when the atomic owner guard allows it');

// Actor role is still a tenant ceiling: a manager with a global MANAGE_USERS grant
// cannot use the membership service because manager role does not allow it.
esc_p2_membership_queue(
    [esc_p2_membership_row(42, TenantRolePolicy::MANAGER)],
    [esc_p2_membership_row(42, TenantRolePolicy::MANAGER)]
);
esc_p2_membership_throws(
    static fn () => $service->assignRole(77, TenantRolePolicy::VIEWER, 42),
    'manager tenant role cannot administer memberships despite global MANAGE_USERS',
    'tenant role does not allow'
);

$repoSource = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Tenancy/TenantMembershipRepository.php');
esc_p2_membership_assert(! str_contains($repoSource, 'SET is_owner = 1'), 'membership admin repository exposes no ownership-grant update');
esc_p2_membership_assert(str_contains($repoSource, "'active', 0, %d"), 'new membership insert hard-codes is_owner=0');
esc_p2_membership_assert(str_contains($repoSource, 'WHERE tenant_id = %d AND user_id = %d AND is_owner = 0'), 'role mutation SQL carries tenant+user+non-owner predicates');

TenantContextStore::reset();
$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '0';
$GLOBALS['sc_test_options'][NonCoreTenantEnforcement::OPTION] = '0';
$GLOBALS['sc_test_current_caps'] = [];
$GLOBALS['sc_test_result_queue'] = [];
$GLOBALS['sc_test_results'] = [];

fwrite(STDOUT, "Enterprise tenant membership admin P2-004 passed ({$assertions} assertions).\n");
