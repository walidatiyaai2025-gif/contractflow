<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Translations\AdminArabicDefaults;
use SafeContracts\Translations\ControlledInputArabicDefaults;
use SafeContracts\Translations\MigrationRecoveryArabicDefaults;
use SafeContracts\Translations\NavigationArabicDefaults;
use SafeContracts\Translations\ProductionUxArabicDefaults;
use SafeContracts\Translations\RuntimeLabels;
use SafeContracts\Translations\TranslationCatalog;

/**
 * ALK-PROD-001 translation rule:
 *
 * - Existing translation debt on the exact production base is tolerated so a
 *   hardening PR does not unexpectedly become a rewrite of historical UI.
 * - The debt set may never grow. Any user-facing source string that did not
 *   exist in the exact base revision must ship with an Arabic default now.
 * - English remains the source/default text and is already catalogued by the
 *   existing TranslationCatalog.
 *
 * This is deliberately base-revision driven rather than a manually maintained
 * exception list. A developer cannot make CI green by silently adding a new
 * string to a baseline file.
 */

$baseSha = trim((string) getenv('ALKENZY_TRANSLATION_BASE_SHA'));
if (! preg_match('/^[0-9a-f]{40}$/i', $baseSha) || preg_match('/^0{40}$/', $baseSha)) {
    $baseSha = trim((string) shell_exec('git rev-parse HEAD^1 2>/dev/null'));
}
if (! preg_match('/^[0-9a-f]{40}$/i', $baseSha)) {
    fwrite(STDERR, "FAIL: exact base revision is required for the translation completeness gate.\n");
    exit(1);
}

exec('git cat-file -e ' . escapeshellarg($baseSha . '^{commit}') . ' 2>/dev/null', $unused, $baseStatus);
if ($baseStatus !== 0) {
    fwrite(STDERR, "FAIL: translation base revision is not available in the checkout. Quality Gates must use fetch-depth: 0.\n");
    exit(1);
}

$catalog = TranslationCatalog::catalog();
$missing = [];
foreach ($catalog as $source => $row) {
    $arabic = (string) ($row['ar'] ?? $source);
    if ($arabic === $source) {
        $arabic = AdminArabicDefaults::default($source);
    }
    if ($arabic === $source) {
        $arabic = RuntimeLabels::default($source);
    }
    if ($arabic === $source) {
        $arabic = ProductionUxArabicDefaults::default($source);
    }
    if ($arabic === $source) {
        $arabic = NavigationArabicDefaults::default($source);
    }
    if ($arabic === $source) {
        $arabic = MigrationRecoveryArabicDefaults::default($source);
    }
    if ($arabic === $source) {
        $arabic = ControlledInputArabicDefaults::default($source);
    }
    if (trim($arabic) === '' || $arabic === $source) {
        $missing[] = $source;
    }
}

sort($missing, SORT_STRING);
$newMissing = [];
$legacyMissing = 0;
foreach ($missing as $source) {
    $command = 'git grep -F -q -e '
        . escapeshellarg($source)
        . ' '
        . escapeshellarg($baseSha)
        . ' -- wordpress-plugin/safecontracts 2>/dev/null';
    exec($command, $grepOutput, $grepStatus);
    if ($grepStatus === 0) {
        $legacyMissing++;
        continue;
    }
    if ($grepStatus > 1) {
        fwrite(STDERR, "FAIL: could not compare translation source against the exact base revision.\n");
        exit(1);
    }
    $newMissing[] = $source;
}

if ($newMissing !== []) {
    fwrite(STDERR, "FAIL: new Alkenzy ADV user-facing strings do not have Arabic defaults:\n");
    foreach ($newMissing as $source) {
        fwrite(STDERR, " - " . $source . "\n");
    }
    fwrite(STDERR, "Add Arabic defaults. New translation debt is not permitted.\n");
    exit(1);
}

fwrite(STDOUT, sprintf(
    "Alkenzy translation completeness #586 passed (%d discovered strings; %d legacy untranslated source strings on base %s; 0 new untranslated strings).\n",
    count($catalog),
    $legacyMissing,
    substr($baseSha, 0, 12)
));
