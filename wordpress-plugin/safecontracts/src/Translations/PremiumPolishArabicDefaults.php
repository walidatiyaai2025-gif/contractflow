<?php

declare(strict_types=1);

namespace SafeContracts\Translations;

/** Arabic defaults for the final Alkenzy ADV admin/mobile landing polish. */
final class PremiumPolishArabicDefaults
{
    /** @var array<string,string> */
    private const MAP = [
        'Agency name — Arabic' => 'اسم الوكالة — بالعربية',
        'Agency name — English' => 'اسم الوكالة — بالإنجليزية',
        'App landing' => 'واجهة التطبيق',
        'Arabic and English values are stored together. The app shows the matching language the next time its landing content is refreshed.' => 'يتم حفظ القيم العربية والإنجليزية معاً، ويعرض التطبيق اللغة المناسبة عند تحديث محتوى صفحة البداية في المرة التالية.',
        'Brand name' => 'اسم العلامة التجارية',
        'Cash flow trend' => 'اتجاه التدفق المالي',
        'Create a new customer' => 'إنشاء عميل جديد',
        'Create a scheduled obligation' => 'إنشاء دفعة مجدولة',
        'Create customer or supplier contract' => 'إنشاء عقد عميل أو مورد',
        'Dashboard actions' => 'إجراءات لوحة التحكم',
        'Edit the mobile landing page' => 'تعديل صفحة بداية تطبيق الموبايل',
        'Edit the public pre-login page shown by Alkenzy ADV. Changes use the existing public landing endpoint and never expose contracts, users, payments or private configuration.' => 'عدّل صفحة البداية العامة التي تظهر قبل تسجيل الدخول في تطبيق Alkenzy ADV. تستخدم التغييرات واجهة صفحة البداية العامة الحالية ولا تكشف العقود أو المستخدمين أو الدفعات أو الإعدادات الخاصة.',
        'Experience years' => 'سنوات الخبرة',
        'Headline — Arabic' => 'العنوان الرئيسي — بالعربية',
        'Headline — English' => 'العنوان الرئيسي — بالإنجليزية',
        'Highlight — Arabic' => 'النص المميز — بالعربية',
        'Highlight — English' => 'النص المميز — بالإنجليزية',
        'Incoming' => 'وارد',
        'Incoming and outgoing obligations over time, independently scaled for each currency.' => 'الالتزامات الواردة والصادرة عبر الوقت، مع مقياس مستقل لكل عملة.',
        'Learn-more button — Arabic' => 'زر اعرف المزيد — بالعربية',
        'Learn-more button — English' => 'زر اعرف المزيد — بالإنجليزية',
        'Mobile landing page content' => 'محتوى صفحة بداية التطبيق',
        'Mobile workspace' => 'مساحة عمل الموبايل',
        'Office address — Arabic' => 'عنوان المكتب — بالعربية',
        'Office address — English' => 'عنوان المكتب — بالإنجليزية',
        'Open system settings' => 'فتح إعدادات النظام',
        'Outgoing' => 'صادر',
        'Phone %d' => 'الهاتف %d',
        'Runtime configuration' => 'إعدادات تشغيل التطبيق',
        'Save Mobile & Landing Configuration' => 'حفظ إعدادات الموبايل وصفحة البداية',
        'Service: %s' => 'الخدمة: %s',
        'Sign-in button — Arabic' => 'زر تسجيل الدخول — بالعربية',
        'Sign-in button — English' => 'زر تسجيل الدخول — بالإنجليزية',
        'Subtitle — Arabic' => 'الوصف الفرعي — بالعربية',
        'Subtitle — English' => 'الوصف الفرعي — بالإنجليزية',
        'Summary — Arabic' => 'الملخص — بالعربية',
        'Summary — English' => 'الملخص — بالإنجليزية',
        'Title — Arabic' => 'العنوان — بالعربية',
        'Title — English' => 'العنوان — بالإنجليزية',
        'cash flow trend' => 'اتجاه التدفق المالي',
    ];

    public static function register(): void
    {
        add_filter('gettext_safecontracts', [self::class, 'filterGettext'], 29, 3);
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
