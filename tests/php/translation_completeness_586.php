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
 * ALK-I18N-002 zero-debt translation rule:
 *
 * Every discovered user-facing WordPress plugin source string must resolve to
 * a non-empty Arabic default. Historical translation debt is no longer
 * tolerated. Any missing Arabic wording fails Quality Gates and is printed in
 * full so the gap cannot be hidden behind a moving baseline.
 */

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
if ($missing !== []) {
    fwrite(STDERR, sprintf(
        "FAIL: Alkenzy ADV Arabic translation audit found %d untranslated user-facing strings:\n",
        count($missing)
    ));
    foreach ($missing as $source) {
        fwrite(STDERR, ' - ' . $source . "\n");
    }
    exit(1);
}

fwrite(STDOUT, sprintf(
    "Alkenzy Arabic zero-debt translation gate passed (%d discovered strings; 0 untranslated).\n",
    count($catalog)
));
