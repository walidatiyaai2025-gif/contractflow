<?php

declare(strict_types=1);

namespace SafeContracts\Translations;

final class MobileAdvertisingArabicDefaults
{
    /** @var array<string,string> */
    private const MAP = [
        'Advertising (Google AdMob)' => 'الإعلانات (Google AdMob)',
        'Advertising providers' => 'مزودو الإعلانات',
        'Advertising provider' => 'مزود الإعلانات',
        'Google AdMob' => 'Google AdMob',
        'AppLovin MAX' => 'AppLovin MAX',
        'Ads are disabled by default. Test mode uses Google test inventory so QA cannot generate invalid production traffic.' => 'الإعلانات معطلة افتراضيًا. يستخدم وضع الاختبار إعلانات Google التجريبية حتى لا تتسبب اختبارات الجودة في زيارات إنتاج غير صالحة.',
        'Ads are disabled by default. Choose the active provider here; switching providers takes effect from remote configuration without publishing a new app build.' => 'الإعلانات معطلة افتراضيًا. اختر مزود الإعلانات النشط من هنا؛ ويمكن تبديل المزود من الإعدادات البعيدة دون نشر نسخة جديدة من التطبيق.',
        'If AdMob is suspended or intentionally disabled, select AppLovin MAX and save. The app will stop requesting AdMob ads and use AppLovin on the next configuration refresh/app start.' => 'إذا تم تعليق AdMob أو أردت إيقافه، اختر AppLovin MAX ثم احفظ. سيتوقف التطبيق عن طلب إعلانات AdMob ويستخدم AppLovin عند تحديث الإعدادات أو تشغيل التطبيق التالي.',
        'Enable mobile advertising' => 'تفعيل الإعلانات في تطبيق الموبايل',
        'Test mode (recommended until Play/AdMob production verification)' => 'وضع الاختبار (موصى به حتى اكتمال التحقق من Google Play وAdMob)',
        'Test / QA mode' => 'وضع الاختبار / الجودة',
        'Show banner ads' => 'عرض إعلانات البانر',
        'Android banner Ad Unit ID' => 'معرّف وحدة إعلان البانر لأندرويد',
        'AdMob banner Ad Unit ID' => 'معرّف وحدة بانر AdMob',
        'AppLovin SDK key' => 'مفتاح SDK لـ AppLovin',
        'AppLovin banner Ad Unit ID' => 'معرّف وحدة بانر AppLovin',
        'For AppLovin QA, add the test device GAID in MAX > Mediation > Manage > Test Mode. AppLovin does not provide a universal public banner test unit like AdMob.' => 'لاختبار AppLovin، أضف GAID لجهاز الاختبار من MAX > Mediation > Manage > Test Mode. لا يوفر AppLovin وحدة بانر تجريبية عامة موحدة مثل AdMob.',
        'The production AdMob App ID belongs to the signed Android build and is supplied through release secrets, not saved in WordPress. The banner Ad Unit ID is safe to manage here at runtime.' => 'معرّف تطبيق AdMob للإنتاج جزء من نسخة أندرويد الموقعة ويتم تمريره عبر أسرار الإصدار، ولا يُحفظ في WordPress. يمكن إدارة معرّف وحدة إعلان البانر بأمان من هنا أثناء التشغيل.',
        'The AdMob App ID remains a signed-build release secret. AppLovin uses only the SDK key here; never paste an AppLovin Management Key, API Key, or Ad Review Key into this page.' => 'يظل معرّف تطبيق AdMob سرًا خاصًا ببناء النسخة الموقعة. يستخدم AppLovin هنا مفتاح SDK فقط؛ لا تضع مفتاح الإدارة أو API أو مفتاح Ad Review الخاص بـ AppLovin في هذه الصفحة.',
        'Store compliance pages' => 'صفحات متطلبات المتجر',
        'Privacy policy' => 'سياسة الخصوصية',
        'Account deletion' => 'حذف الحساب',
        'Support' => 'الدعم',
        'Terms of use' => 'شروط الاستخدام',
        'Use these public URLs in Google Play Console, AdMob/AppLovin privacy configuration, and the app listing.' => 'استخدم هذه الروابط العامة في Google Play Console وإعدادات الخصوصية في AdMob/AppLovin وصفحة التطبيق على المتجر.',
        'AdMob setup checklist' => 'خطوات إعداد AdMob',
        'AppLovin setup checklist' => 'خطوات إعداد AppLovin',
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
