<?php

declare(strict_types=1);

namespace SafeContracts\Translations;

final class ControlledInputArabicDefaults
{
    /** @var array<string,string> */
    private const MAP = [
        'All currencies' => 'كل العملات',
        'Select currency' => 'اختر العملة',
        'System currency' => 'عملة النظام',
        'Choose the system currency from the approved list and set the display symbol used by mobile financial values. Leaving either blank keeps it explicitly unconfigured.' => 'اختر عملة النظام من القائمة المعتمدة وحدد رمز العرض المستخدم في القيم المالية على الموبايل. ترك أي منهما فارغاً يبقيه غير مهيأ بشكل صريح.',
        'All counterparties' => 'كل جهات التعاقد',
        'All responsible accountants' => 'كل المحاسبين المسؤولين',
        'Assigned user unavailable' => 'المستخدم المسند غير متاح',
        'Select country' => 'اختر الدولة',
        'United Arab Emirates' => 'الإمارات العربية المتحدة',
        'Australia' => 'أستراليا',
        'Bahrain' => 'البحرين',
        'Canada' => 'كندا',
        'Switzerland' => 'سويسرا',
        'China' => 'الصين',
        'Germany' => 'ألمانيا',
        'Algeria' => 'الجزائر',
        'Egypt' => 'مصر',
        'Spain' => 'إسبانيا',
        'France' => 'فرنسا',
        'United Kingdom' => 'المملكة المتحدة',
        'India' => 'الهند',
        'Iraq' => 'العراق',
        'Italy' => 'إيطاليا',
        'Jordan' => 'الأردن',
        'Japan' => 'اليابان',
        'Kuwait' => 'الكويت',
        'Lebanon' => 'لبنان',
        'Morocco' => 'المغرب',
        'Malaysia' => 'ماليزيا',
        'Netherlands' => 'هولندا',
        'New Zealand' => 'نيوزيلندا',
        'Oman' => 'عُمان',
        'Pakistan' => 'باكستان',
        'Qatar' => 'قطر',
        'Saudi Arabia' => 'المملكة العربية السعودية',
        'Singapore' => 'سنغافورة',
        'Tunisia' => 'تونس',
        'Türkiye' => 'تركيا',
        'United States' => 'الولايات المتحدة',
        'South Africa' => 'جنوب أفريقيا',
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
