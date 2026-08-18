<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$path = $root . '/scripts/collect_alkenzy_production_preflight.php';
$source = file_get_contents($path);

if (! is_string($source) || $source === '') {
    fwrite(STDERR, "FAIL: ALK-PROD-002 production preflight collector is missing.\n");
    exit(1);
}

$assertions = 0;

$required = [
    'Migrator::VERSION_OPTION',
    'Migrator::LATEST_VERSION',
    'MigrationGuard::LOCK_OPTION',
    'MigrationGuard::LOCK_TTL_SECONDS',
    'MigrationGuard::JOURNAL_OPTION',
    'MigrationGuard::failureState()',
    "'safe_to_start_deployment'",
    "'backup_evidence_required_separately' => true",
    "'rollback_artifact_required_separately' => true",
    'exit($safeToStart ? 0 : 3);',
];

foreach ($required as $marker) {
    if (! str_contains($source, $marker)) {
        fwrite(STDERR, "FAIL: ALK-PROD-002 preflight collector is missing required marker: {$marker}\n");
        exit(1);
    }
    $assertions++;
}

$forbiddenMutations = [
    'update_option(',
    'add_option(',
    'delete_option(',
    'wp_insert_post(',
    'wp_update_post(',
    'wp_delete_post(',
    '->query(',
    '->insert(',
    '->update(',
    '->delete(',
    '->acquire(',
    '->withLock(',
    '->migrate(',
];

foreach ($forbiddenMutations as $marker) {
    if (str_contains($source, $marker)) {
        fwrite(STDERR, "FAIL: ALK-PROD-002 collector must remain read-only; found mutation marker: {$marker}\n");
        exit(1);
    }
    $assertions++;
}

$forbiddenSecretExports = [
    '$lock[\'token\']',
    "'token' =>",
    "'password' =>",
    "'authorization' =>",
    "'service_account' =>",
    "'private_key' =>",
];

foreach ($forbiddenSecretExports as $marker) {
    if (str_contains(strtolower($source), strtolower($marker))) {
        fwrite(STDERR, "FAIL: ALK-PROD-002 collector exposes a forbidden secret marker: {$marker}\n");
        exit(1);
    }
    $assertions++;
}

if (! str_contains($source, 'array_slice($journal, -5)')) {
    fwrite(STDERR, "FAIL: ALK-PROD-002 collector must bound migration journal output.\n");
    exit(1);
}
$assertions++;

if (str_contains($source, "'message',")) {
    fwrite(STDERR, "FAIL: ALK-PROD-002 collector must not export migration failure message text.\n");
    exit(1);
}
$assertions++;

echo "SafeContracts ALK-PROD-002 production preflight collector passed ({$assertions} assertions).\n";
