<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0020NotificationRecipientRepair;
use SafeContracts\Notifications\NotificationRecipientRolePolicy;
use SafeContracts\Notifications\RecipientResolver;
use SafeContracts\Roles\RoleRegistrar;

$tests = 0;
function sc_recipient_repair_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

final class SC_Recipient_Repair_Wpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';

    /** @var array<int,array<string,mixed>> */
    public array $rows = [
        10 => [
            'id' => 10,
            'recipient_roles_json' => '["administrator","manager","subscriber"]',
            'recipient_user_ids_json' => '[42]',
            'escalation_roles_json' => '["accountant","editor"]',
            'target_assigned_accountant' => 0,
            'is_active' => 1,
        ],
        11 => [
            'id' => 11,
            'recipient_roles_json' => '["subscriber"]',
            'recipient_user_ids_json' => '[]',
            'escalation_roles_json' => '[]',
            'target_assigned_accountant' => 0,
            'is_active' => 1,
        ],
    ];

    public function get_results(string $sql, mixed $output = null): array
    {
        unset($output);
        if (str_contains($sql, 'LIMIT 1')) {
            $first = reset($this->rows);
            return is_array($first) ? [['id' => $first['id']]] : [];
        }
        return array_values($this->rows);
    }

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
        if (! preg_match(
            "/SET recipient_roles_json = '((?:\\\\.|[^'])*)', escalation_roles_json = '((?:\\\\.|[^'])*)', is_active = (\\d+)\\s+WHERE id = (\\d+)/s",
            $sql,
            $match
        )) {
            return false;
        }
        $id = (int) $match[4];
        if (! isset($this->rows[$id])) {
            return false;
        }
        $this->rows[$id]['recipient_roles_json'] = stripslashes($match[1]);
        $this->rows[$id]['escalation_roles_json'] = stripslashes($match[2]);
        $this->rows[$id]['is_active'] = (int) $match[3];
        return 1;
    }
}

sc_recipient_repair_assert(
    version_compare(Migrator::LATEST_VERSION, '1.19.0', '>=') && class_exists(Migration0020NotificationRecipientRepair::class),
    'Notification recipient production repair remains available from database version 1.19.0 onward'
);

$normalized = NotificationRecipientRolePolicy::normalizeStoredRoles([
    'administrator',
    'admin',
    'safecontracts_admin',
    'manager',
    'accountant',
    'viewer',
    'subscriber',
]);
sc_recipient_repair_assert(
    $normalized === [
        RoleRegistrar::SYSTEM_ADMIN,
        RoleRegistrar::MANAGER,
        RoleRegistrar::ACCOUNTANT,
        RoleRegistrar::VIEWER,
    ],
    'Known legacy aliases map deterministically and unknown WordPress roles are dropped fail-closed'
);

$GLOBALS['sc_test_users_by_role'] = [
    RoleRegistrar::SYSTEM_ADMIN => [1],
    RoleRegistrar::MANAGER => [2],
];
$resolved = (new RecipientResolver())->resolve([
    'recipient_roles' => ['administrator', 'manager', 'subscriber'],
    'recipient_user_ids' => [],
    'target_assigned_accountant' => false,
], null);
sc_recipient_repair_assert(
    $resolved === [1, 2],
    'Runtime recipient resolution tolerates stored legacy aliases without broadening to unknown roles'
);

$db = new SC_Recipient_Repair_Wpdb();
$migration = new Migration0020NotificationRecipientRepair();
$migration->preflight($db);
$migration->up($db);
$migration->verify($db);

sc_recipient_repair_assert(
    $db->rows[10]['recipient_roles_json'] === '["safecontracts_system_admin","safecontracts_manager"]'
        && $db->rows[10]['escalation_roles_json'] === '["safecontracts_accountant"]'
        && $db->rows[10]['is_active'] === 1,
    'Production repair maps known primary/escalation roles while preserving a valid active rule'
);
sc_recipient_repair_assert(
    $db->rows[11]['recipient_roles_json'] === '[]' && $db->rows[11]['is_active'] === 0,
    'Production repair disables an orphaned rule instead of widening notification recipients'
);

$migration->rollback($db);
sc_recipient_repair_assert(
    $db->rows[10]['recipient_roles_json'] === '["administrator","manager","subscriber"]'
        && $db->rows[10]['escalation_roles_json'] === '["accountant","editor"]'
        && $db->rows[11]['recipient_roles_json'] === '["subscriber"]'
        && $db->rows[11]['is_active'] === 1,
    'Recipient repair rollback restores the exact pre-migration stored policy'
);

printf("SafeContracts notification recipient repair regression passed (%d assertions).\n", $tests);
