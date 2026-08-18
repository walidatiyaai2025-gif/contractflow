<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Translations\AdminArabicDefaults;
use SafeContracts\Translations\ControlledInputArabicDefaults;
use SafeContracts\Translations\MigrationRecoveryArabicDefaults;
use SafeContracts\Translations\ProductionUxArabicDefaults;
use SafeContracts\Translations\RuntimeLabels;
use SafeContracts\Translations\TranslationCatalog;

$baselinePath = __DIR__ . '/translation_missing_ar_baseline_586.php';
$baseline = is_file($baselinePath) ? require $baselinePath : [];
if (! is_array($baseline)) {
    fwrite(STDERR, "FAIL: translation missing-Arabic baseline must return an array.\n");
    exit(1);
}
$baseline = array_fill_keys(array_map('strval', $baseline), true);

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
$current = array_fill_keys($missing, true);
$newMissing = array_values(array_filter(
    $missing,
    static fn (string $source): bool => ! isset($baseline[$source])
));
$staleBaseline = array_values(array_filter(
    array_keys($baseline),
    static fn (string $source): bool => ! isset($current[$source])
));

if ($newMissing !== []) {
    fwrite(STDERR, "FAIL: newly discovered user-facing strings do not have Arabic defaults:\n");
    foreach ($newMissing as $source) {
        fwrite(STDERR, " - " . $source . "\n");
    }
    fwrite(STDERR, "Add Arabic defaults; do not expand the baseline for new work.\n");
    exit(1);
}

if ($staleBaseline !== []) {
    fwrite(STDERR, "FAIL: translation baseline contains entries that are now translated or removed. Shrink the baseline:\n");
    foreach ($staleBaseline as $source) {
        fwrite(STDERR, " - " . $source . "\n");
    }
    exit(1);
}

fwrite(STDOUT, sprintf(
    "Alkenzy translation completeness #586 passed (%d discovered strings, %d legacy baseline exceptions).\n",
    count($catalog),
    count($baseline)
));
