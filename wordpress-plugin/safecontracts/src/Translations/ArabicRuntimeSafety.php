<?php

declare(strict_types=1);

namespace SafeContracts\Translations;

/**
 * Final Arabic runtime guard.
 *
 * The plugin has a legacy generic gettext brand-normalization filter that can
 * turn `SafeContracts` into `Safe Contracts` before domain-specific Arabic
 * filters run. This guard executes last and treats that brand-only mutation as
 * still untranslated, while preserving real TranslationCatalog overrides and
 * any Arabic translation already produced by an earlier filter.
 */
final class ArabicRuntimeSafety
{
    public static function register(): void
    {
        add_filter('gettext_safecontracts', [self::class, 'filterGettext'], 100, 3);
    }

    public static function filterGettext(string $translation, string $text, string $domain = 'safecontracts'): string
    {
        if ($domain !== 'safecontracts' || TranslationCatalog::currentLanguage() !== 'ar') {
            return $translation;
        }

        $arabic = CompleteArabicDefaults::default($text);
        if ($arabic === $text || trim($arabic) === '') {
            return $translation;
        }

        $brandNormalizedSource = str_replace('SafeContracts', 'Safe Contracts', $text);

        // Preserve a real user override or an Arabic default produced by an
        // earlier filter. Only source text and the legacy brand-only mutation
        // are considered untranslated at this final stage.
        if ($translation !== $text && $translation !== $brandNormalizedSource) {
            return $translation;
        }

        return $arabic;
    }
}
