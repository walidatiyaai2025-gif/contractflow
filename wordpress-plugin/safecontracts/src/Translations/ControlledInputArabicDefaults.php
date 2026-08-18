<?php

declare(strict_types=1);

namespace SafeContracts\Translations;

final class ControlledInputArabicDefaults
{
    /** @var array<string,string> */
    private const MAP = [
        'All currencies' => 'كل العملات',
        'All counterparties' => 'كل جهات التعاقد',
        'All responsible accountants' => 'كل المحاسبين المسؤولين',
        'Assigned user unavailable' => 'المستخدم المسند غير متاح',
    ];

    public static function register(): void
    {
        add_filter('gettext_safecontracts', [self::class, 'filterGettext'], 23, 3);
    }

    public static function default(string $source): string
    {
        return self::MAP[$source] ?? $source;
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        return self::MAP;
    }

    public static function filterGettext(string $translation, string $text, string $domain = 'safecontracts'): string
    {
        if ($domain !== 'safecontracts' || TranslationCatalog::currentLanguage() !== 'ar') {
            return $translation;
        }
        if ($translation !== $text) {
            return $translation;
        }
        return self::default($text);
    }
}
