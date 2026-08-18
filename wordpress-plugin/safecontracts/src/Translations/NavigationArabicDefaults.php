<?php

declare(strict_types=1);

namespace SafeContracts\Translations;

/** Arabic defaults for the compact grouped Alkenzy ADV admin navigation. */
final class NavigationArabicDefaults
{
    /** @var array<string,string> */
    private const MAP = [
        'Grouped navigation' => 'تنقل مجمّع',
        'No pages in this section are available to your current role.' => 'لا توجد صفحات متاحة لدورك الحالي داخل هذا القسم.',
        'Parties & Contracts' => 'الأطراف والعقود',
        'Customers, suppliers and their customer or supplier contracts.' => 'العملاء والموردون والعقود المرتبطة بكل منهم.',
        'Finance' => 'المالية',
        'Payment schedules, collections, receivables, payables and financial reports.' => 'جداول الدفعات والتحصيلات والمستحقات المدينة والدائنة والتقارير المالية.',
        'Operations' => 'العمليات',
        'Follow-up, archive and controlled data import operations.' => 'المتابعات والأرشيف وعمليات استيراد البيانات المنضبطة.',
        'Notification center, delivery activity, schedules and notification settings.' => 'مركز الإشعارات ونشاط التسليم والجداول وإعدادات الإشعارات.',
        'Users & Access' => 'المستخدمون والصلاحيات',
        'Active-user visibility, user roles and business permission management.' => 'متابعة المستخدمين النشطين وإدارة الأدوار وصلاحيات الأعمال.',
        'Settings & Integrations' => 'الإعدادات والتكاملات',
        'Organization settings, Firebase, mobile configuration and translations.' => 'إعدادات المؤسسة وFirebase وإعدادات الموبايل والترجمات.',
        'Clear guidance for every area and the next related task.' => 'إرشادات واضحة لكل جزء والخطوة المرتبطة التالية.',
        'More' => 'المزيد',
        'Additional authorized areas that are not yet assigned to a primary group.' => 'أجزاء إضافية مصرح بها لم يتم إسنادها بعد إلى مجموعة رئيسية.',
    ];

    public static function register(): void
    {
        add_filter('gettext_safecontracts', [self::class, 'filterGettext'], 22, 3);
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
