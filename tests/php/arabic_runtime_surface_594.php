<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (! function_exists('get_locale')) {
    function get_locale(): string
    {
        return 'ar_KW';
    }
}

require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Translations\AdminArabicDefaults;
use SafeContracts\Translations\ArabicRuntimeSafety;
use SafeContracts\Translations\CompleteArabicDefaults;
use SafeContracts\Translations\ControlledInputArabicDefaults;
use SafeContracts\Translations\MigrationRecoveryArabicDefaults;
use SafeContracts\Translations\NavigationArabicDefaults;
use SafeContracts\Translations\ProductionUxArabicDefaults;
use SafeContracts\Translations\RuntimeLabels;

$assertions = 0;
$failures = [];

$assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;
    if (! $condition) {
        $failures[] = $message;
    }
};

$resolveArabic = static function (string $source): string {
    $value = AdminArabicDefaults::default($source);
    if ($value === $source) {
        $value = RuntimeLabels::default($source);
    }
    if ($value === $source) {
        $value = ProductionUxArabicDefaults::default($source);
    }
    if ($value === $source) {
        $value = NavigationArabicDefaults::default($source);
    }
    if ($value === $source) {
        $value = MigrationRecoveryArabicDefaults::default($source);
    }
    if ($value === $source) {
        $value = ControlledInputArabicDefaults::default($source);
    }
    if ($value === $source) {
        $value = CompleteArabicDefaults::default($source);
    }
    return $value;
};

$criticalExpected = [
    'Suppliers' => 'الموردون',
    'Supplier' => 'المورد',
    'Supplier directory' => 'دليل الموردين',
    'Supplier master' => 'بيانات الموردين الرئيسية',
    'Open Accounts Payable' => 'فتح الحسابات الدائنة',
    'Accounts Payable' => 'الحسابات الدائنة',
    'Accounts Receivable' => 'الحسابات المدينة',
    'AP / AR by currency' => 'الدائنون / المدينون حسب العملة',
    'Financial operations' => 'العمليات المالية',
    'Financial position' => 'المركز المالي',
    'Finance filters' => 'فلاتر المالية',
    'Finance work queue' => 'قائمة عمل المالية',
    'Aging' => 'أعمار الديون',
    'Aging report' => 'تقرير أعمار الديون',
    'Responsible accountant' => 'المحاسب المسؤول',
    'Outstanding' => 'القائم',
    'Payment terms' => 'شروط السداد',
];

foreach ($criticalExpected as $source => $expected) {
    $assert($resolveArabic($source) === $expected, sprintf('Critical Arabic wording mismatch for "%s".', $source));
}

$brandSource = 'SafeContracts could not load the finance workspace.';
$brandOnlyTranslation = 'Safe Contracts could not load the finance workspace.';
$runtimeArabic = ArabicRuntimeSafety::filterGettext($brandOnlyTranslation, $brandSource, 'safecontracts');
$assert(
    $runtimeArabic === 'تعذر على Alkenzy ADV تحميل مساحة العمل المالية.',
    'Final Arabic runtime guard did not override the legacy brand-only English mutation.'
);
$assert(
    ArabicRuntimeSafety::filterGettext('ترجمة عربية مخصصة', $brandSource, 'safecontracts') === 'ترجمة عربية مخصصة',
    'Final Arabic runtime guard overwrote a real Arabic/user translation override.'
);

$targetFiles = [
    'SuppliersPage.php',
    'FinancePage.php',
    'ReportsPage.php',
];
$adminRoot = dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Admin/';

foreach ($targetFiles as $fileName) {
    $path = $adminRoot . $fileName;
    $content = file_get_contents($path);
    $assert(is_string($content) && $content !== '', $fileName . ' must be readable for Arabic surface audit.');
    if (! is_string($content) || $content === '') {
        continue;
    }

    preg_match_all(
        "/(?:__|esc_html__|esc_attr__)\\(\\s*'((?:\\\\'|[^'])*)'\\s*,\\s*'safecontracts'/",
        $content,
        $matches
    );
    $sources = array_values(array_unique(array_map(
        static fn (string $value): string => str_replace("\\'", "'", $value),
        $matches[1] ?? []
    )));
    $assert($sources !== [], $fileName . ' must expose translatable user-facing strings.');

    foreach ($sources as $source) {
        $arabic = $resolveArabic($source);
        $assert(
            trim($arabic) !== '' && $arabic !== $source,
            sprintf('%s has an untranslated gettext source: %s', $fileName, $source)
        );
    }

    // Strip PHP blocks and fail if literal English text was written directly
    // into visible HTML instead of using the translation layer.
    $htmlOnly = preg_replace('/<\\?php.*?\\?>/s', '', $content);
    if (is_string($htmlOnly) && preg_match_all('/>([^<>]*[A-Za-z][^<>]*)</', $htmlOnly, $rawMatches)) {
        foreach ($rawMatches[1] as $rawText) {
            $rawText = trim(html_entity_decode(strip_tags((string) $rawText)));
            if ($rawText !== '') {
                $failures[] = sprintf('%s contains raw visible English HTML outside gettext: %s', $fileName, $rawText);
            }
        }
    }

    if (preg_match_all('/\\becho\\s+[\'\"]([^\'\"]*[A-Za-z][^\'\"]*)[\'\"]\\s*;/', $content, $echoMatches)) {
        foreach ($echoMatches[1] as $rawText) {
            $failures[] = sprintf('%s directly echoes an English literal outside gettext: %s', $fileName, $rawText);
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL: Alkenzy Arabic supplier/finance surface audit failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, sprintf(
    "Alkenzy Arabic supplier/finance runtime audit #594 passed (%d assertions; Suppliers/Finance/Reports fully translated).\n",
    $assertions
));
