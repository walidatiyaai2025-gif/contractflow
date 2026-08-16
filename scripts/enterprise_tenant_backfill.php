#!/usr/bin/env php
<?php

declare(strict_types=1);

$options = getopt('', ['wp-root:', 'tenant-id::', 'apply', 'verify']);
$wpRoot = isset($options['wp-root']) ? rtrim((string) $options['wp-root'], '/\\') : '';
if ($wpRoot === '' || ! is_file($wpRoot . '/wp-load.php')) {
    fwrite(STDERR, "Usage: php scripts/enterprise_tenant_backfill.php --wp-root=/path/to/wordpress [--tenant-id=ID --apply] [--verify]\n");
    exit(64);
}

require_once $wpRoot . '/wp-load.php';

if (! class_exists(\SafeContracts\Tenancy\CoreTenantOwnershipBackfill::class)) {
    fwrite(STDERR, "FAIL: the deployed Enterprise Safe Contracts plugin does not expose CoreTenantOwnershipBackfill. Deploy the exact ESC candidate first.\n");
    exit(1);
}

$service = new \SafeContracts\Tenancy\CoreTenantOwnershipBackfill();
$apply = array_key_exists('apply', $options);
$verify = array_key_exists('verify', $options) || ! $apply;

try {
    if ($apply) {
        $tenantId = isset($options['tenant-id']) ? (int) $options['tenant-id'] : 0;
        if ($tenantId <= 0) {
            throw new RuntimeException('--tenant-id is required with --apply.');
        }
        fwrite(STDOUT, "Applying reviewed legacy core-data ownership to Enterprise tenant {$tenantId}...\n");
        $report = $service->applyDefaultTenant($tenantId);
    } else {
        $report = $service->report();
    }

    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    if ($verify && ! $report['ready']) {
        fwrite(STDERR, "NOT READY: unowned or cross-tenant core rows remain. Enforcement must not be enabled.\n");
        exit(2);
    }
    if ($report['ready']) {
        fwrite(STDOUT, "READY: core business graph has complete, internally consistent tenant ownership.\n");
    }
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
