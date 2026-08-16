#!/usr/bin/env php
<?php

declare(strict_types=1);

$options = getopt('', ['wp-root:', 'status', 'apply']);
$wpRoot = isset($options['wp-root']) ? rtrim((string) $options['wp-root'], '/\\') : '';
if ($wpRoot === '' || ! is_file($wpRoot . '/wp-load.php')) {
    fwrite(STDERR, "Usage: php scripts/enterprise_noncore_tenant_schema_harden.php --wp-root=/path/to/wordpress [--status|--apply]\n");
    exit(64);
}

require_once $wpRoot . '/wp-load.php';

if (! class_exists(\SafeContracts\Tenancy\NonCoreTenantSchemaHardener::class)) {
    fwrite(STDERR, "FAIL: deployed Enterprise Safe Contracts does not include the non-core tenant schema hardener.\n");
    exit(1);
}

$hardener = new \SafeContracts\Tenancy\NonCoreTenantSchemaHardener();
$apply = array_key_exists('apply', $options);

try {
    if ($apply) {
        fwrite(STDOUT, "Hardening verified Enterprise non-core tenant schema...\n");
        $report = $hardener->harden();
    } else {
        $report = [
            'preflight' => $hardener->preflight(),
            'schema' => $hardener->verify(),
            'hardened' => $hardener->isHardened(),
        ];
    }

    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    if ($apply) {
        fwrite(STDOUT, "HARDENED: tenant-owned non-core tables are structurally scoped; platform-global audit ownership remains nullable by design.\n");
    }
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
