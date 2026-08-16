#!/usr/bin/env php
<?php

declare(strict_types=1);

$options = getopt('', ['wp-root:', 'derive', 'tenant-id::', 'roots::', 'verify']);
$wpRoot = isset($options['wp-root']) ? rtrim((string) $options['wp-root'], '/\\') : '';
if ($wpRoot === '' || ! is_file($wpRoot . '/wp-load.php')) {
    fwrite(STDERR, "Usage: php scripts/enterprise_noncore_tenant_backfill.php --wp-root=/path/to/wordpress [--derive] [--tenant-id=ID --roots=rules,templates,devices,imports,suppressions,audit] [--verify]\n");
    exit(64);
}

require_once $wpRoot . '/wp-load.php';

if (! class_exists(\SafeContracts\Tenancy\NonCoreTenantOwnershipBackfill::class)) {
    fwrite(STDERR, "FAIL: deployed Enterprise Safe Contracts does not include the non-core tenant ownership service.\n");
    exit(1);
}

$service = new \SafeContracts\Tenancy\NonCoreTenantOwnershipBackfill();
$derive = array_key_exists('derive', $options);
$verify = array_key_exists('verify', $options) || (! $derive && ! isset($options['roots']));
$roots = isset($options['roots'])
    ? array_values(array_filter(array_map('trim', explode(',', (string) $options['roots'])), static fn (string $value): bool => $value !== ''))
    : [];

try {
    if ($roots !== []) {
        $tenantId = isset($options['tenant-id']) ? (int) $options['tenant-id'] : 0;
        $report = $service->assignRootsToTenant($tenantId, $roots);
    } elseif ($derive) {
        $report = $service->deriveDeterministic();
    } else {
        $report = $service->report();
    }

    fwrite(STDOUT, json_encode([
        ...$report,
        'available_root_groups' => \SafeContracts\Tenancy\NonCoreTenantOwnershipBackfill::rootGroups(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

    if ($verify && ! $report['ready']) {
        fwrite(STDERR, "NOT READY: non-core tenant-owned rows remain unowned or cross-tenant mismatches exist.\n");
        exit(2);
    }
    if ($report['ready']) {
        fwrite(STDOUT, "READY: non-core tenant ownership is complete and internally consistent.\n");
    } else {
        fwrite(STDOUT, "PARTIAL: reviewed mapping/derivation committed, but more explicit root ownership is required.\n");
    }
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
