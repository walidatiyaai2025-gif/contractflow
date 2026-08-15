<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/wp-stub/');
}
if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

$GLOBALS['sc_test_actions'] = [];
$GLOBALS['sc_test_action_accepted_args'] = [];
$GLOBALS['sc_test_activation_hooks'] = [];
$GLOBALS['sc_test_deactivation_hooks'] = [];
$GLOBALS['sc_test_options'] = [];
$GLOBALS['sc_test_roles'] = [];
$GLOBALS['sc_test_routes'] = [];
$GLOBALS['sc_test_dbdelta'] = [];
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_results'] = [];
$GLOBALS['sc_test_result_queue'] = [];
$GLOBALS['sc_test_admin_pages'] = [];
$GLOBALS['sc_test_current_caps'] = [];
$GLOBALS['sc_test_user_caps'] = [];
$GLOBALS['sc_test_users_by_role'] = [];
$GLOBALS['sc_test_fired_actions'] = [];

final class SC_Test_Role
{
    /** @var array<string, bool> */
    public array $capabilities;

    public function __construct(array $capabilities = [])
    {
        $this->capabilities = $capabilities;
    }

    public function add_cap(string $capability): void
    {
        $this->capabilities[$capability] = true;
    }
}

final class SC_Test_Wpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 0;

    public function get_charset_collate(): string
    {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
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
        $GLOBALS['sc_test_queries'][] = $sql;
        if (str_starts_with(ltrim($sql), 'INSERT INTO') && $this->insert_id === 0) {
            $this->insert_id = 1001;
        }
        return 1;
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

$GLOBALS['wpdb'] = new SC_Test_Wpdb();
$GLOBALS['sc_test_roles']['administrator'] = new SC_Test_Role(['read' => true]);

class WP_REST_Request
{
    public function __construct(private array $jsonParams = []) {}

    public function get_json_params(): array
    {
        return $this->jsonParams;
    }
}
class WP_REST_Response
{
    public function __construct(public mixed $data = null, public int $status = 200) {}
}
class WP_REST_Server
{
    public const READABLE = 'GET';
    public const CREATABLE = 'POST';
}
class WP_Error
{
    public function __construct(public string $code, public string $message, public array $data = []) {}
}

function plugin_dir_path(string $file): string { return dirname($file) . '/'; }
function plugin_dir_url(string $file): string { return 'https://example.test/wp-content/plugins/' . basename(dirname($file)) . '/'; }
function plugin_basename(string $file): string { return basename(dirname($file)) . '/' . basename($file); }
function register_activation_hook(string $file, callable $callback): void { $GLOBALS['sc_test_activation_hooks'][$file] = $callback; }
function register_deactivation_hook(string $file, callable $callback): void { $GLOBALS['sc_test_deactivation_hooks'][$file] = $callback; }
function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
{
    unset($priority);
    $GLOBALS['sc_test_actions'][$hook][] = $callback;
    $GLOBALS['sc_test_action_accepted_args'][$hook][] = max(0, $acceptedArgs);
}
function do_action(string $hook, mixed ...$args): void
{
    $GLOBALS['sc_test_fired_actions'][$hook][] = $args;
    foreach ($GLOBALS['sc_test_actions'][$hook] ?? [] as $index => $cb) {
        $acceptedArgs = $GLOBALS['sc_test_action_accepted_args'][$hook][$index] ?? 1;
        $cb(...array_slice($args, 0, $acceptedArgs));
    }
}
function get_option(string $key, mixed $default = false): mixed { return $GLOBALS['sc_test_options'][$key] ?? $default; }
function update_option(string $key, mixed $value, bool $autoload = true): bool { unset($autoload); $GLOBALS['sc_test_options'][$key] = $value; return true; }
function get_role(string $slug): ?SC_Test_Role { return $GLOBALS['sc_test_roles'][$slug] ?? null; }
function add_role(string $slug, string $name, array $caps): SC_Test_Role { unset($name); $role = new SC_Test_Role($caps); $GLOBALS['sc_test_roles'][$slug] = $role; return $role; }
function current_user_can(string $capability): bool { return (bool) ($GLOBALS['sc_test_current_caps'][$capability] ?? false); }
function user_can(int $userId, string $capability): bool { return (bool) ($GLOBALS['sc_test_user_caps'][$userId][$capability] ?? false); }
function get_current_user_id(): int { return 42; }
function get_users(array $args = []): array
{
    $role = isset($args['role']) ? (string) $args['role'] : '';
    return array_values($GLOBALS['sc_test_users_by_role'][$role] ?? []);
}
function register_rest_route(string $namespace, string $route, array $args): void { $GLOBALS['sc_test_routes'][$namespace . $route] = $args; }
function add_options_page(string $pageTitle, string $menuTitle, string $capability, string $menuSlug, callable $callback): string
{
    $GLOBALS['sc_test_admin_pages'][$menuSlug] = [
        'page_title' => $pageTitle,
        'menu_title' => $menuTitle,
        'capability' => $capability,
        'callback' => $callback,
    ];
    return 'settings_page_' . $menuSlug;
}
function __return_true(): bool { return true; }
function __(string $text, string $domain = 'default'): string { unset($domain); return $text; }
function esc_html__(string $text, string $domain = 'default'): string { unset($domain); return $text; }
function sanitize_key(string $key): string { return preg_replace('/[^a-z0-9_-]/', '', strtolower($key)) ?? ''; }
function sanitize_text_field(string $text): string { return trim(strip_tags($text)); }
function deactivate_plugins(string $plugin): void { unset($plugin); }
function wp_die(string $message, string $title = '', array $args = []): never { unset($title, $args); throw new RuntimeException($message); }
function dbDelta(string $sql): array { $GLOBALS['sc_test_dbdelta'][] = $sql; return []; }
