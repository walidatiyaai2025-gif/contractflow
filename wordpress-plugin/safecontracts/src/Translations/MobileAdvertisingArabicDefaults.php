<?php

declare(strict_types=1);

namespace SafeContracts\Translations;

final class MobileAdvertisingArabicDefaults
{
    /** @var array<string,string> */
    private const MAP = [
        'Advertising (Google AdMob)' => 'الإعلانات (Google AdMob)',
        'Ads are disabled by default. Test mode uses Google test inventory so QA cannot generate invalid production traffic.' => 'الإعلانات معطلة افتراضيًا. يستخدم وضع الاختبار إعلانات Google التجريبية حتى لا تتسبب اختبارات الجودة في زيارات إنتاج غير صالحة.',
        'Enable mobile advertising' => 'تفعيل الإعلانات في تطبيق الموبايل',
        'Test mode (recommended until Play/AdMob production verification)' => 'وضع الاختبار (موصى به حتى اكتمال التحقق من Google Play وAdMob)',
        'Show banner ads' => 'عرض إعلانات البانر',
        'Android banner Ad Unit ID' => 'معرّف وحدة إعلان البانر لأندرويد',
        'The production AdMob App ID belongs to the signed Android build and is supplied through release secrets, not saved in WordPress. The banner Ad Unit ID is safe to manage here at runtime.' => 'معرّف تطبيق AdMob للإنتاج جزء من نسخة أندرويد الموقعة ويتم تمريره عبر أسرار الإصدار، ولا يُحفظ في WordPress. يمكن إدارة معرّف وحدة إعلان البانر بأمان من هنا أثناء التشغيل.',
        'This page stores non-secret mobile bootstrap and advertising controls in WordPress. Production signing material and the AdMob App ID must remain outside the repository.' => 'تخزن هذه الصفحة إعدادات تشغيل تطبيق الموبايل وعناصر التحكم في الإعلانات غير السرية داخل WordPress. يجب أن تظل بيانات توقيع الإنتاج ومعرّف تطبيق AdMob خارج المستودع.',
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
