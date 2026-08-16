#!/usr/bin/env php
<?php

declare(strict_types=1);

$options = getopt('', ['wp-root:', 'tenant-id::', 'apply', 'verify', 'enable-enforcement', 'disable-enforcement']);
$wpRoot = isset($options['wp-root']) ? rtrim((string) $options['wp-root'], '/\\') : '';
if ($wpRoot === '' || ! is_file($wpRoot . '/wp-load.php')) {
    fwrite(STDERR, "Usage: php scripts/enterprise_tenant_backfill.php --wp-root=/path/to/wordpress [--tenant-id=ID --apply] [--verify] [--enable-enforcement|--disable-enforcement]\n");
    exit(64);
}

require_once $wpRoot . '/wp-load.php';

if (! class_exists(\SafeContracts\Tenancy\CoreTenantOwnershipBackfill::class)
    || ! class_exists(\SafeContracts\Tenancy\CoreTenantEnforcement::class)) {
    fwrite(STDERR, "FAIL: the deployed Enterprise Safe Contracts plugin does not expose the tenant ownership/enforcement services. Deploy the exact ESC candidate first.\n");
    exit(1);
}

$service = new \SafeContracts\Tenancy\CoreTenantOwnershipBackfill();
$apply = array_key_exists('apply', $options);
$enable = array_key_exists('enable-enforcement', $options);
$disable = array_key_exists('disable-enforcement', $options);
$verify = array_key_exists('verify', $options) || (! $apply && ! $disable);

if ($enable && $disable) {
    fwrite(STDERR, "FAIL: --enable-enforcement and --disable-enforcement are mutually exclusive.\n");
    exit(64);
}

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

    if ($verify && ! $report['ready']) {
        fwrite(STDOUT, json_encode([
            ...$report,
            'enforcement_enabled' => \SafeContracts\Tenancy\CoreTenantEnforcement::isEnabled(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        fwrite(STDERR, "NOT READY: unowned or cross-tenant core rows remain. Enforcement must not be enabled.\n");
        exit(2);
    }

    if ($enable) {
        \SafeContracts\Tenancy\CoreTenantEnforcement::enable();
        fwrite(STDOUT, "ENFORCEMENT ENABLED: verified core business access now requires a locked TenantContext.\n");
    } elseif ($disable) {
        \SafeContracts\Tenancy\CoreTenantEnforcement::disable();
        fwrite(STDOUT, "ENFORCEMENT DISABLED: use only for controlled migration/remediation.\n");
    }

    fwrite(STDOUT, json_encode([
        ...$report,
        'enforcement_enabled' => \SafeContracts\Tenancy\CoreTenantEnforcement::isEnabled(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    if ($report['ready']) {
        fwrite(STDOUT, "READY: core business graph has complete, internally consistent tenant ownership.\n");
    }
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
