<?php

use SafeContracts\Admin\AdminReadRepository;
use SafeContracts\Admin\CollectorAttachmentView;
use SafeContracts\FollowUps\FollowUpService;
use SafeContracts\Support\DemoDataService;

if (! defined('ABSPATH')) {
    fwrite(STDERR, "Demo-data runtime QA must run through wp eval-file.\n");
    exit(1);
}

$admin = get_user_by('login', 'visual-admin');
if (! $admin instanceof WP_User) {
    throw new RuntimeException('visual-admin fixture user does not exist.');
}
wp_set_current_user((int) $admin->ID);

/** @param array<string,mixed> $batch */
function sc_demo_assert_batch(array $batch): void
{
    if ((int) ($batch['total_rows'] ?? 0) !== 11000) {
        throw new RuntimeException('Demo batch did not create exactly 11,000 rows.');
    }
    $tables = is_array($batch['tables'] ?? null) ? $batch['tables'] : [];
    if (count($tables) !== 22) {
        throw new RuntimeException('Demo batch did not cover exactly 22 plugin tables.');
    }
    foreach ($tables as $suffix => $ids) {
        if (! is_string($suffix) || ! is_array($ids) || count($ids) !== DemoDataService::ROWS_PER_TABLE) {
            throw new RuntimeException('Demo table registry is incomplete for ' . (string) $suffix . '.');
        }
    }
}

/** @param list<string> $suffixes @return array<string,int> */
function sc_demo_table_counts(array $suffixes): array
{
    global $wpdb;
    $counts = [];
    foreach ($suffixes as $suffix) {
        if (! preg_match('/^safecontracts_[a-z0-9_]+$/', $suffix)) {
            throw new RuntimeException('Unsafe demo runtime table suffix.');
        }
        $counts[$suffix] = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $wpdb->prefix . $suffix);
    }
    return $counts;
}

/** @param array<string,int> $before @param array<string,int> $after */
function sc_demo_assert_count_delta(array $before, array $after, int $delta): void
{
    foreach ($before as $suffix => $count) {
        if (($after[$suffix] ?? -1) !== $count + $delta) {
            throw new RuntimeException(sprintf('Unexpected demo row count for %s: before=%d after=%d expected-delta=%d.', $suffix, $count, $after[$suffix] ?? -1, $delta));
        }
    }
}

$service = new DemoDataService();
if ($service->registry() !== null) {
    $service->delete();
}

$first = $service->create();
sc_demo_assert_batch($first);
$attachmentIds = is_array($first['wordpress_attachment_ids'] ?? null) ? $first['wordpress_attachment_ids'] : [];
if (count($attachmentIds) !== 1 || CollectorAttachmentView::resolve($attachmentIds[0]) === null) {
    throw new RuntimeException('Demo attachment fields do not resolve to a real, visible WordPress media record.');
}
$suffixes = array_keys($first['tables']);
$afterFirst = sc_demo_table_counts($suffixes);
$before = array_map(static fn (int $count): int => $count - DemoDataService::ROWS_PER_TABLE, $afterFirst);
sc_demo_assert_count_delta($before, $afterFirst, DemoDataService::ROWS_PER_TABLE);

$scope = trim((string) getenv('SC_QA_SCOPE'));
if ($scope === 'lead') {
    $second = $service->create();
    sc_demo_assert_batch($second);
    $attachmentIds = array_merge($attachmentIds, is_array($second['wordpress_attachment_ids'] ?? null) ? $second['wordpress_attachment_ids'] : []);
    $registry = $service->registry();
    if ((int) ($registry['batch_count'] ?? 0) !== 2 || (int) ($registry['total_rows'] ?? 0) !== 22000) {
        throw new RuntimeException('Repeated demo creation did not accumulate two independently registered batches.');
    }
    sc_demo_assert_count_delta($before, sc_demo_table_counts($suffixes), 2 * DemoDataService::ROWS_PER_TABLE);
}

$read = new AdminReadRepository();
$customers = $read->customers();
$contracts = $read->contracts();
$payments = $read->payments();
$followUps = (new FollowUpService())->recent(500);
if (! array_filter($customers, static fn (array $row): bool => str_starts_with((string) ($row['internal_code'] ?? ''), 'DEMO-C-'))) {
    throw new RuntimeException('Demo customers are not visible through the real admin read model.');
}
if (! array_filter($contracts, static fn (array $row): bool => str_starts_with((string) ($row['contract_number'] ?? ''), 'DEMO-CTR-'))) {
    throw new RuntimeException('Demo contracts are not visible through the real admin read model.');
}
if (! array_filter($payments, static fn (array $row): bool => str_starts_with((string) ($row['reference'] ?? ''), 'DEMO-PAY-'))) {
    throw new RuntimeException('Demo payments are not visible through the real admin read model.');
}
if (! array_filter($followUps, static fn (array $row): bool => str_contains((string) ($row['note'] ?? ''), '[SC-DEMO:'))) {
    throw new RuntimeException('Demo employee follow-up notes are not visible through the real follow-up read model.');
}

if ($scope === 'lead') {
    $deleted = $service->delete();
    if ((int) ($deleted['deleted_rows'] ?? 0) !== 22000 || $service->registry() !== null) {
        throw new RuntimeException('Delete-all did not remove both exact registered demo batches.');
    }
    if (sc_demo_table_counts($suffixes) !== $before) {
        throw new RuntimeException('Delete-all did not restore every plugin table to its exact pre-demo row count.');
    }
    foreach ($attachmentIds as $attachmentId) {
        if (get_post((int) $attachmentId) !== null) {
            throw new RuntimeException('Delete-all retained a registered demo WordPress media record.');
        }
    }
    $final = $service->create();
    sc_demo_assert_batch($final);
}

fwrite(STDOUT, sprintf(
    "Demo runtime QA passed for scope=%s: visible customers/contracts/payments/follow-ups, repeatable batches, exact delete; one 500-per-table batch retained for screenshots.\n",
    $scope !== '' ? $scope : 'unknown'
));
