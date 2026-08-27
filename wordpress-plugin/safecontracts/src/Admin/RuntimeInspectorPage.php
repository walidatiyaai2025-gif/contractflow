<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Database\Migrator;
use SafeContracts\Diagnostics\RuntimeInspector;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;

final class RuntimeInspectorPage
{
    public const SLUG = 'safecontracts-runtime-inspector';
    public const CLEAR_ACTION = 'safecontracts_clear_runtime_inspector';

    public static function register(): void
    {
        add_submenu_page(
            AdminShell::SLUG,
            __('Runtime Inspector', 'safecontracts'),
            __('Runtime Inspector', 'safecontracts'),
            Capabilities::MANAGE_SYSTEM,
            self::SLUG,
            [self::class, 'render']
        );
    }

    public static function renderCapturedNotice(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            return;
        }
        $runtimeId = isset($_GET['safecontracts_runtime_id']) && is_scalar($_GET['safecontracts_runtime_id'])
            ? sanitize_text_field((string) $_GET['safecontracts_runtime_id'])
            : '';
        if ($runtimeId === '') {
            return;
        }
        $page = sanitize_key((string) ($_GET['page'] ?? ''));
        if ($page === self::SLUG) {
            return;
        }
        $url = add_query_arg(['page' => self::SLUG, 'runtime_id' => $runtimeId], admin_url('admin.php'));
        ?>
        <div class="notice notice-warning is-dismissible"><p><strong><?php echo esc_html__('Runtime diagnostic captured.', 'safecontracts'); ?></strong> <?php echo esc_html__('Open Runtime Inspector to review the exact failure stage and sanitized technical context.', 'safecontracts'); ?> <a href="<?php echo esc_url($url); ?>"><?php echo esc_html__('Open Runtime Inspector', 'safecontracts'); ?></a> <code><?php echo esc_html($runtimeId); ?></code></p></div>
        <?php
    }

    public static function handleClear(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to manage runtime diagnostics.', 'safecontracts'));
        }
        check_admin_referer(self::CLEAR_ACTION);
        RuntimeInspector::clear();
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'safecontracts_status' => 'runtime_cleared'], admin_url('admin.php')));
        exit;
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to view runtime diagnostics.', 'safecontracts'));
        }
        $events = RuntimeInspector::recent();
        $requestedId = isset($_GET['runtime_id']) && is_scalar($_GET['runtime_id'])
            ? sanitize_text_field((string) $_GET['runtime_id'])
            : '';
        if ($requestedId !== '') {
            usort($events, static fn (array $left, array $right): int => (($left['id'] ?? '') === $requestedId ? -1 : 0) <=> (($right['id'] ?? '') === $requestedId ? -1 : 0));
        }
        $environment = RuntimeInspector::environmentSnapshot();
        $checks = self::checks();
        $exportPayload = self::exportPayload($environment, $checks, $events);
        $exportJson = wp_json_encode(
            $exportPayload,
            JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
        );
        if (! is_string($exportJson) || $exportJson === '') {
            $exportJson = '{}';
        }
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Production diagnostics', 'safecontracts'); ?></p>
                    <h1><?php echo esc_html__('Runtime Inspector', 'safecontracts'); ?></h1>
                    <p class="description"><?php echo esc_html__('This page captures sanitized SafeContracts runtime failures so administrators can diagnose operations without asking the end user to reproduce technical details.', 'safecontracts'); ?></p>
                </div>
            </div>

            <section class="safecontracts-admin-card safecontracts-settings-card">
                <h2><?php echo esc_html__('Runtime health', 'safecontracts'); ?></h2>
                <dl class="safecontracts-detail-list">
                    <div><dt><?php echo esc_html__('Plugin version', 'safecontracts'); ?></dt><dd><?php echo esc_html((string) ($environment['plugin_version'] ?? '')); ?></dd></div>
                    <div><dt><?php echo esc_html__('Database version', 'safecontracts'); ?></dt><dd><?php echo esc_html((string) ($environment['db_version'] ?? '')); ?> / <?php echo esc_html(Migrator::LATEST_VERSION); ?></dd></div>
                    <div><dt><?php echo esc_html__('PHP version', 'safecontracts'); ?></dt><dd><?php echo esc_html((string) ($environment['php_version'] ?? '')); ?></dd></div>
                    <div><dt><?php echo esc_html__('WordPress version', 'safecontracts'); ?></dt><dd><?php echo esc_html((string) ($environment['wordpress_version'] ?? '')); ?></dd></div>
                </dl>
                <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Check', 'safecontracts'); ?></th><th><?php echo esc_html__('Result', 'safecontracts'); ?></th><th><?php echo esc_html__('Details', 'safecontracts'); ?></th></tr></thead><tbody>
                    <?php foreach ($checks as $check) : ?>
                        <tr><td><?php echo esc_html($check['label']); ?></td><td><strong><?php echo esc_html(strtoupper($check['state'])); ?></strong></td><td><?php echo esc_html($check['detail']); ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table>
            </section>

            <section class="safecontracts-admin-card safecontracts-table-card">
                <div class="safecontracts-section-heading">
                    <div><h2><?php echo esc_html__('Recent runtime failures', 'safecontracts'); ?></h2><p class="description"><?php echo esc_html__('Retention is bounded to the most recent 50 events. Secrets, tokens, passwords, cookies, authorization headers, nonces and raw request bodies are never stored.', 'safecontracts'); ?></p></div>
                    <div>
                        <button type="button" class="button button-secondary" id="safecontracts-runtime-export"><?php echo esc_html__('Export runtime JSON', 'safecontracts'); ?></button>
                        <?php if ($events !== []) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-inline-start:6px">
                                <input type="hidden" name="action" value="<?php echo esc_attr(self::CLEAR_ACTION); ?>">
                                <?php wp_nonce_field(self::CLEAR_ACTION); ?>
                                <?php submit_button(__('Clear runtime history', 'safecontracts'), 'secondary', 'submit', false); ?>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($events === []) : ?>
                    <p><?php echo esc_html__('No runtime failures have been recorded.', 'safecontracts'); ?></p>
                <?php else : ?>
                    <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Correlation ID', 'safecontracts'); ?></th><th><?php echo esc_html__('Time (UTC)', 'safecontracts'); ?></th><th><?php echo esc_html__('Operation / stage', 'safecontracts'); ?></th><th><?php echo esc_html__('Failure', 'safecontracts'); ?></th><th><?php echo esc_html__('Diagnostics', 'safecontracts'); ?></th></tr></thead><tbody>
                        <?php foreach ($events as $event) : ?>
                            <?php $highlight = $requestedId !== '' && (string) ($event['id'] ?? '') === $requestedId; ?>
                            <tr<?php echo $highlight ? ' style="outline:2px solid currentColor"' : ''; ?>>
                                <td><code><?php echo esc_html((string) ($event['id'] ?? '')); ?></code></td>
                                <td><?php echo esc_html((string) ($event['occurred_at_utc'] ?? '')); ?></td>
                                <td><strong><?php echo esc_html((string) ($event['operation'] ?? '')); ?></strong><br><code><?php echo esc_html((string) ($event['stage'] ?? '')); ?></code></td>
                                <td><strong><?php echo esc_html((string) ($event['exception_class'] ?? '')); ?></strong><br><?php echo esc_html((string) ($event['message'] ?? '')); ?><?php if (! empty($event['db_error'])) : ?><br><strong><?php echo esc_html__('Database error:', 'safecontracts'); ?></strong> <?php echo esc_html((string) $event['db_error']); ?><?php endif; ?></td>
                                <td><details><summary><?php echo esc_html__('Open diagnostic context', 'safecontracts'); ?></summary><pre><?php echo esc_html((string) json_encode([
                                    'user_id' => $event['user_id'] ?? 0,
                                    'request' => $event['request'] ?? [],
                                    'capabilities' => array_keys(array_filter((array) ($event['capabilities'] ?? []))),
                                    'environment' => $event['environment'] ?? [],
                                    'context' => $event['context'] ?? [],
                                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></pre></details></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody></table>
                <?php endif; ?>
            </section>
        </div>
        <script type="application/json" id="safecontracts-runtime-export-data"><?php echo $exportJson; // JSON_HEX_* protects the script context. ?></script>
        <script>
        (() => {
            const button = document.getElementById('safecontracts-runtime-export');
            const source = document.getElementById('safecontracts-runtime-export-data');
            if (!button || !source || typeof Blob === 'undefined' || !window.URL) {
                return;
            }

            button.addEventListener('click', () => {
                let payload;
                try {
                    payload = JSON.parse(source.textContent || '{}');
                } catch (error) {
                    return;
                }

                const json = JSON.stringify(payload, null, 2);
                const blob = new Blob([json], {type: 'application/json;charset=utf-8'});
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = String(payload.download_filename || 'alkenzy-runtime-inspector.json');
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.setTimeout(() => URL.revokeObjectURL(url), 0);
            });
        })();
        </script>
        <?php
    }

    /**
     * @param array<string,mixed> $environment
     * @param list<array{label:string,state:string,detail:string}> $checks
     * @param list<array<string,mixed>> $events
     * @return array<string,mixed>
     */
    private static function exportPayload(array $environment, array $checks, array $events): array
    {
        $currentCapabilities = [];
        foreach (Capabilities::all() as $capability) {
            if (current_user_can($capability)) {
                $currentCapabilities[] = $capability;
            }
        }

        return [
            'product' => 'ALKENZY ADV / SafeContracts',
            'generated_at_utc' => gmdate('c'),
            'download_filename' => 'alkenzy-runtime-inspector-' . gmdate('Ymd-His') . '.json',
            'current_user' => [
                'user_id' => get_current_user_id(),
                'capabilities' => $currentCapabilities,
            ],
            'environment' => $environment,
            'checks' => $checks,
            'events' => array_map([self::class, 'exportEvent'], $events),
            'privacy' => [
                'sanitized' => true,
                'excluded' => [
                    'passwords',
                    'tokens',
                    'cookies',
                    'authorization_headers',
                    'nonces',
                    'raw_request_bodies',
                ],
                'retention_limit' => RuntimeInspector::MAX_EVENTS,
            ],
        ];
    }

    /** @param array<string,mixed> $event @return array<string,mixed> */
    private static function exportEvent(array $event): array
    {
        return [
            'id' => (string) ($event['id'] ?? ''),
            'occurred_at_utc' => (string) ($event['occurred_at_utc'] ?? ''),
            'operation' => (string) ($event['operation'] ?? ''),
            'stage' => (string) ($event['stage'] ?? ''),
            'exception_class' => (string) ($event['exception_class'] ?? ''),
            'message' => (string) ($event['message'] ?? ''),
            'db_error' => (string) ($event['db_error'] ?? ''),
            'user_id' => (int) ($event['user_id'] ?? 0),
            'request' => (array) ($event['request'] ?? []),
            'capabilities' => array_keys(array_filter((array) ($event['capabilities'] ?? []))),
            'environment' => (array) ($event['environment'] ?? []),
            'context' => (array) ($event['context'] ?? []),
        ];
    }

    /** @return list<array{label:string,state:string,detail:string}> */
    private static function checks(): array
    {
        global $wpdb;
        $checks = [];
        $dbVersion = (string) get_option(Migrator::VERSION_OPTION, '0.0.0');
        $checks[] = [
            'label' => __('Database migration level', 'safecontracts'),
            'state' => version_compare($dbVersion, Migrator::LATEST_VERSION, '>=') ? 'pass' : 'fail',
            'detail' => sprintf(__('Installed %1$s; required %2$s.', 'safecontracts'), $dbVersion, Migrator::LATEST_VERSION),
        ];

        if (! is_object($wpdb)) {
            $checks[] = ['label' => __('Database schema', 'safecontracts'), 'state' => 'fail', 'detail' => __('WordPress database access is unavailable.', 'safecontracts')];
            return $checks;
        }

        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $suppliers = $wpdb->prefix . 'safecontracts_suppliers';
        $contractColumns = self::columns($wpdb, $contracts);
        $requiredContractColumns = ['counterparty_type', 'counterparty_id', 'financial_direction', 'currency_code'];
        $missingContractColumns = array_values(array_diff($requiredContractColumns, $contractColumns));
        $checks[] = [
            'label' => __('Contract counterparty schema', 'safecontracts'),
            'state' => $missingContractColumns === [] ? 'pass' : 'fail',
            'detail' => $missingContractColumns === []
                ? __('Required supplier/AP-AR contract columns are present.', 'safecontracts')
                : sprintf(__('Missing contract columns: %s', 'safecontracts'), implode(', ', $missingContractColumns)),
        ];

        $supplierColumns = self::columns($wpdb, $suppliers);
        $requiredSupplierColumns = ['status', 'is_active', 'is_archived'];
        $missingSupplierColumns = array_values(array_diff($requiredSupplierColumns, $supplierColumns));
        $checks[] = [
            'label' => __('Supplier lifecycle schema', 'safecontracts'),
            'state' => $missingSupplierColumns === [] ? 'pass' : 'fail',
            'detail' => $missingSupplierColumns === []
                ? __('Supplier lifecycle columns are present.', 'safecontracts')
                : sprintf(__('Missing supplier columns: %s', 'safecontracts'), implode(', ', $missingSupplierColumns)),
        ];

        if ($missingSupplierColumns === []) {
            $rows = $wpdb->get_results("SELECT COUNT(*) AS total FROM {$suppliers} WHERE status = 'active' AND (is_active <> 1 OR is_archived <> 0)", ARRAY_A);
            $inconsistent = is_array($rows) && isset($rows[0]['total']) ? (int) $rows[0]['total'] : 0;
            $checks[] = [
                'label' => __('Active supplier consistency', 'safecontracts'),
                'state' => $inconsistent === 0 ? 'pass' : 'warn',
                'detail' => $inconsistent === 0
                    ? __('No active supplier lifecycle mismatches were found.', 'safecontracts')
                    : sprintf(__('%d active supplier record(s) have conflicting legacy lifecycle flags.', 'safecontracts'), $inconsistent),
            ];
        }

        $accountants = get_users(['role' => RoleRegistrar::ACCOUNTANT]);
        $totalAccountants = is_array($accountants) ? count($accountants) : 0;
        $eligible = 0;
        if (is_array($accountants)) {
            foreach ($accountants as $accountant) {
                $id = (int) ($accountant->ID ?? 0);
                if ($id > 0
                    && user_can($id, Capabilities::ACCESS)
                    && user_can($id, Capabilities::CREATE_CONTRACTS)
                    && user_can($id, Capabilities::VIEW_ASSIGNED)) {
                    $eligible++;
                }
            }
        }
        $checks[] = [
            'label' => __('Responsible accountant eligibility', 'safecontracts'),
            'state' => $eligible > 0 ? ($eligible === $totalAccountants ? 'pass' : 'warn') : 'fail',
            'detail' => sprintf(__('%1$d of %2$d Accountant role user(s) satisfy ACCESS + CREATE_CONTRACTS + VIEW_ASSIGNED.', 'safecontracts'), $eligible, $totalAccountants),
        ];

        return $checks;
    }

    /** @return list<string> */
    private static function columns(object $wpdb, string $table): array
    {
        $rows = $wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }
        $columns = [];
        foreach ($rows as $row) {
            $field = trim((string) ($row['Field'] ?? $row['field'] ?? ''));
            if ($field !== '') {
                $columns[] = $field;
            }
        }
        return $columns;
    }
}
