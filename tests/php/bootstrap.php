<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/wp-stub/');
}

$GLOBALS['sc_test_actions'] = [];
$GLOBALS['sc_test_activation_hooks'] = [];
$GLOBALS['sc_test_deactivation_hooks'] = [];
$GLOBALS['sc_test_options'] = [];
$GLOBALS['sc_test_roles'] = [];
$GLOBALS['sc_test_routes'] = [];
$GLOBALS['sc_test_dbdelta'] = [];
$GLOBALS['sc_test_current_caps'] = [];

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

    public function get_charset_collate(): string
    {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }
}

$GLOBALS['wpdb'] = new SC_Test_Wpdb();
$GLOBALS['sc_test_roles']['administrator'] = new SC_Test_Role(['read' => true]);

class WP_REST_Request {}
class WP_REST_Response
{
    public function __construct(public mixed $data = null, public int $status = 200) {}
}
class WP_REST_Server
{
    public const READABLE = 'GET';
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
function add_action(string $hook, callable $callback): void { $GLOBALS['sc_test_actions'][$hook][] = $callback; }
function do_action(string $hook, mixed ...$args): void { foreach ($GLOBALS['sc_test_actions'][$hook] ?? [] as $cb) { $cb(...$args); } }
function get_option(string $key, mixed $default = false): mixed { return $GLOBALS['sc_test_options'][$key] ?? $default; }
function update_option(string $key, mixed $value, bool $autoload = true): bool { unset($autoload); $GLOBALS['sc_test_options'][$key] = $value; return true; }
function get_role(string $slug): ?SC_Test_Role { return $GLOBALS['sc_test_roles'][$slug] ?? null; }
function add_role(string $slug, string $name, array $caps): SC_Test_Role { unset($name); $role = new SC_Test_Role($caps); $GLOBALS['sc_test_roles'][$slug] = $role; return $role; }
function current_user_can(string $capability): bool { return (bool) ($GLOBALS['sc_test_current_caps'][$capability] ?? false); }
function get_current_user_id(): int { return 42; }
function register_rest_route(string $namespace, string $route, array $args): void { $GLOBALS['sc_test_routes'][$namespace . $route] = $args; }
function __return_true(): bool { return true; }
function __(string $text, string $domain = 'default'): string { unset($domain); return $text; }
function esc_html__(string $text, string $domain = 'default'): string { unset($domain); return $text; }
function deactivate_plugins(string $plugin): void { unset($plugin); }
function wp_die(string $message, string $title = '', array $args = []): never { unset($title, $args); throw new RuntimeException($message); }
function dbDelta(string $sql): array { $GLOBALS['sc_test_dbdelta'][] = $sql; return []; }
