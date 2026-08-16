<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;

final class ArchivePage
{
    public const SLUG = 'safecontracts-archive';

    public static function register(): void
    {
        add_submenu_page(
            AdminShell::SLUG,
            __('Archive', 'safecontracts'),
            __('Archive', 'safecontracts'),
            Capabilities::VIEW_ALL,
            self::SLUG,
            [self::class, 'render']
        );
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::VIEW_ALL)) {
            wp_die(__('You do not have permission to view the archive.', 'safecontracts'));
        }
        $rows = self::rows();
        $counts = [];
        foreach ($rows as $row) {
            $type = (string) $row['type'];
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Safe deletion', 'safecontracts'); ?></p><h1><?php echo esc_html__('Archive', 'safecontracts'); ?></h1></div></div>
            <?php AdminSummaryCards::render([
                ['label' => __('Archived contracts', 'safecontracts'), 'value' => $counts['Contract'] ?? 0],
                ['label' => __('Archived payments', 'safecontracts'), 'value' => $counts['Payment'] ?? 0],
                ['label' => __('Archived collections', 'safecontracts'), 'value' => $counts['Collection'] ?? 0],
                ['label' => __('Archived reference data', 'safecontracts'), 'value' => ($counts['Customer'] ?? 0) + ($counts['Payment method'] ?? 0)],
            ]); ?>
            <section class="safecontracts-admin-card safecontracts-table-card">
                <h2><?php echo esc_html__('Deleted / archived records', 'safecontracts'); ?></h2>
                <p class="description"><?php echo esc_html__('These records are soft-deleted: financial history and audit evidence remain preserved, while the records are excluded from normal operational pages.', 'safecontracts'); ?></p>
                <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Type', 'safecontracts'); ?></th><th><?php echo esc_html__('ID', 'safecontracts'); ?></th><th><?php echo esc_html__('Record', 'safecontracts'); ?></th><th><?php echo esc_html__('Archived / updated', 'safecontracts'); ?></th><th><?php echo esc_html__('By user', 'safecontracts'); ?></th></tr></thead><tbody>
                <?php if ($rows === []) : ?><tr><td colspan="5"><?php echo esc_html__('Archive is empty.', 'safecontracts'); ?></td></tr><?php endif; ?>
                <?php foreach ($rows as $row) : ?><tr><td><?php echo esc_html((string) $row['type']); ?></td><td>#<?php echo esc_html((string) $row['id']); ?></td><td><?php echo esc_html((string) $row['label']); ?></td><td><?php echo esc_html((string) $row['archived_at']); ?></td><td><?php echo (int) $row['archived_by'] > 0 ? '#' . esc_html((string) $row['archived_by']) : '—'; ?></td></tr><?php endforeach; ?>
                </tbody></table>
            </section>
        </div>
        <?php
    }

    /** @return list<array{type:string,id:int,label:string,archived_at:string,archived_by:int}> */
    private static function rows(): array
    {
        global $wpdb;
        $result = [];
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $collections = $wpdb->prefix . 'safecontracts_payment_collections';
        $methods = $wpdb->prefix . 'safecontracts_payment_methods';

        self::append($result, $wpdb->get_results("SELECT id, name AS label, updated_at AS archived_at, 0 AS archived_by FROM {$customers} WHERE is_active = 0 ORDER BY updated_at DESC LIMIT 500", ARRAY_A), 'Customer');
        self::append($result, $wpdb->get_results("SELECT id, contract_number AS label, updated_at AS archived_at, COALESCE(updated_by, 0) AS archived_by FROM {$contracts} WHERE is_archived = 1 ORDER BY updated_at DESC LIMIT 500", ARRAY_A), 'Contract');
        self::append($result, $wpdb->get_results("SELECT id, COALESCE(reference, CONCAT('Payment #', id)) AS label, COALESCE(archived_at, updated_at) AS archived_at, COALESCE(archived_by, 0) AS archived_by FROM {$payments} WHERE is_archived = 1 ORDER BY archived_at DESC, id DESC LIMIT 500", ARRAY_A), 'Payment');
        self::append($result, $wpdb->get_results("SELECT id, COALESCE(reference, CONCAT('Collection #', id)) AS label, COALESCE(archived_at, updated_at) AS archived_at, COALESCE(archived_by, 0) AS archived_by FROM {$collections} WHERE is_archived = 1 ORDER BY archived_at DESC, id DESC LIMIT 500", ARRAY_A), 'Collection');
        self::append($result, $wpdb->get_results("SELECT id, name AS label, updated_at AS archived_at, 0 AS archived_by FROM {$methods} WHERE is_active = 0 ORDER BY updated_at DESC LIMIT 500", ARRAY_A), 'Payment method');

        usort($result, static fn (array $a, array $b): int => strcmp((string) $b['archived_at'], (string) $a['archived_at']));
        return array_slice($result, 0, 1000);
    }

    /** @param list<array{type:string,id:int,label:string,archived_at:string,archived_by:int}> $result */
    private static function append(array &$result, mixed $rows, string $type): void
    {
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $result[] = [
                'type' => $type,
                'id' => $id,
                'label' => trim((string) ($row['label'] ?? '')),
                'archived_at' => (string) ($row['archived_at'] ?? ''),
                'archived_by' => (int) ($row['archived_by'] ?? 0),
            ];
        }
    }
}
