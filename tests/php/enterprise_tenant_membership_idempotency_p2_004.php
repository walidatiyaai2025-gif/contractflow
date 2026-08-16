<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (! function_exists('get_userdata')) {
    function get_userdata(int $userId): object|false
    {
        return in_array($userId, [42, 77], true) ? (object) ['ID' => $userId] : false;
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

final class ESC_P2_Idempotent_Membership_Wpdb
{
    public string $prefix = 'wp_';
    /** @var list<string> */
    public array $queries = [];
    /** @var list<int|false> */
    public array $forcedQueryResults = [];

    public function prepare(string $query, mixed ...$args): string
    {
        $prepared = array_map(
            static fn (mixed $value): mixed => is_int($value) ? $value : "'" . addslashes((string) $value) . "'",
            $args
        );
        return vsprintf($query, $prepared);
    }

    public function query(string $sql): int|false
    {
        $this->queries[] = $sql;
        return $this->forcedQueryResults !== [] ? array_shift($this->forcedQueryResults) : 1;
    }

    public function get_results(string $sql, mixed $output = null): array
    {
        unset($output);
        $GLOBALS['sc_test_read_queries'][] = $sql;
        if ($GLOBALS['sc_test_result_queue'] !== []) {
            $rows = array_shift($GLOBALS['sc_test_result_queue']);
            return is_array($rows) ? $rows : [];
        }
        return $GLOBALS['sc_test_results'];
    }
}

function esc_p2_idempotent_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$actor = [
    'id' => '1042', 'tenant_id' => '55', 'user_id' => '42',
    'role_code' => TenantRolePolicy::TENANT_ADMIN, 'status' => 'active', 'is_owner' => '0',
];
$target = [
    'id' => '1077', 'tenant_id' => '55', 'user_id' => '77',
    'role_code' => TenantRolePolicy::VIEWER, 'status' => 'active', 'is_owner' => '0',
];

$wpdb = new ESC_P2_Idempotent_Membership_Wpdb();
$GLOBALS['wpdb'] = $wpdb;
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::MANAGE_USERS => true,
    Capabilities::VIEW_ALL => true,
];
$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
$GLOBALS['sc_test_options'][NonCoreTenantEnforcement::OPTION] = '0';
$GLOBALS['sc_test_result_queue'] = [[$actor], [$actor], [$target], [$target]];
$GLOBALS['sc_test_results'] = [];
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(55);

$wpdb->forcedQueryResults = [0]; // MySQL: requested role/status already stored.
$service = new TenantMembershipAdminService(new TenantMembershipRepository());
$service->assignRole(77, TenantRolePolicy::VIEWER, 42);

esc_p2_idempotent_assert(count($wpdb->queries) === 1, 'idempotent role save performs only the scoped UPDATE attempt');
esc_p2_idempotent_assert(str_starts_with(ltrim($wpdb->queries[0]), 'UPDATE '), 'idempotent role save never falls through to INSERT');
esc_p2_idempotent_assert(! str_contains($wpdb->queries[0], 'INSERT INTO'), 'idempotent save cannot create a duplicate membership');
$lastRead = end($GLOBALS['sc_test_read_queries']);
esc_p2_idempotent_assert(str_contains((string) $lastRead, 'WHERE tenant_id = 55 AND user_id = 77'), 'zero-row update is reconciled only against the same tenant+user key');

fwrite(STDOUT, "Enterprise tenant membership idempotency P2-004 passed (4 assertions).\n");
