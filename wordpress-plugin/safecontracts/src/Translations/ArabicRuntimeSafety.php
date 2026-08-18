<?php

declare(strict_types=1);

namespace SafeContracts\Translations;

/**
 * Final Arabic runtime guard.
 *
 * The plugin has a legacy generic gettext brand-normalization filter that can
 * turn `SafeContracts` into `Safe Contracts` before domain-specific Arabic
 * filters run. This guard executes last and resolves the same full Arabic
 * fallback chain used by the audit/editor while preserving real user overrides.
 */
final class ArabicRuntimeSafety
{
    public static function register(): void
    {
        add_filter('gettext_safecontracts', [self::class, 'filterGettext'], 100, 3);
    }

    public static function resolveArabic(string $text): string
    {
        // TranslationCatalog::text() is first because stored user overrides are
        // authoritative and its built-in core dictionary covers shared labels.
        $arabic = TranslationCatalog::text($text, 'ar');
        if ($arabic === $text) {
            $arabic = AdminArabicDefaults::default($text);
        }
        if ($arabic === $text) {
            $arabic = RuntimeLabels::default($text);
        }
        if ($arabic === $text) {
            $arabic = ProductionUxArabicDefaults::default($text);
        }
        if ($arabic === $text) {
            $arabic = NavigationArabicDefaults::default($text);
        }
        if ($arabic === $text) {
            $arabic = MigrationRecoveryArabicDefaults::default($text);
        }
        if ($arabic === $text) {
            $arabic = ControlledInputArabicDefaults::default($text);
        }
        if ($arabic === $text) {
            $arabic = CompleteArabicDefaults::default($text);
        }
        return $arabic;
    }

    public static function filterGettext(string $translation, string $text, string $domain = 'safecontracts'): string
    {
        if ($domain !== 'safecontracts' || TranslationCatalog::currentLanguage() !== 'ar') {
            return $translation;
        }

        $arabic = self::resolveArabic($text);
        if ($arabic === $text || trim($arabic) === '') {
            return $translation;
        }

        $brandNormalizedSource = str_replace('SafeContracts', 'Safe Contracts', $text);

        // Preserve an actual translation already produced by an earlier filter.
        // Source text and the historical brand-only English mutation are the two
        // cases that are still considered untranslated here.
        if ($translation !== $text && $translation !== $brandNormalizedSource) {
            return $translation;
        }

        return $arabic;
    }
}
