#!/usr/bin/env php
<?php

declare(strict_types=1);

$options = getopt('', ['wp-root:', 'status', 'enable', 'disable']);
$wpRoot = isset($options['wp-root']) ? rtrim((string) $options['wp-root'], '/\\') : '';
if ($wpRoot === '' || ! is_file($wpRoot . '/wp-load.php')) {
    fwrite(STDERR, "Usage: php scripts/enterprise_noncore_tenant_enforcement.php --wp-root=/path/to/wordpress [--status|--enable|--disable]\n");
    exit(64);
}

require_once $wpRoot . '/wp-load.php';

if (! class_exists(\SafeContracts\Tenancy\NonCoreTenantEnforcement::class)) {
    fwrite(STDERR, "FAIL: deployed Enterprise Safe Contracts does not include non-core tenant enforcement.\n");
    exit(1);
}

try {
    if (array_key_exists('enable', $options)) {
        \SafeContracts\Tenancy\NonCoreTenantEnforcement::enable();
    } elseif (array_key_exists('disable', $options)) {
        \SafeContracts\Tenancy\NonCoreTenantEnforcement::disable();
    }

    $ownership = (new \SafeContracts\Tenancy\NonCoreTenantOwnershipBackfill())->report();
    $hardener = new \SafeContracts\Tenancy\NonCoreTenantSchemaHardener();
    $report = [
        'enabled' => \SafeContracts\Tenancy\NonCoreTenantEnforcement::isEnabled(),
        'ownership_ready' => (bool) ($ownership['ready'] ?? false),
        'schema_hardened' => $hardener->isHardened(),
        'schema' => $hardener->verify(),
    ];
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
