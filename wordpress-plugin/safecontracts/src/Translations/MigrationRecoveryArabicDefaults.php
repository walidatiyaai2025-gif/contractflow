<?php

declare(strict_types=1);

namespace SafeContracts\Translations;

final class MigrationRecoveryArabicDefaults
{
    /** @var array<string,string> */
    private const MAP = [
        'Alkenzy ADV Recovery' => 'استعادة Alkenzy ADV',
        'Current database version' => 'إصدار قاعدة البيانات الحالي',
        'Expected plugin database version' => 'إصدار قاعدة البيانات المتوقع للبلجن',
        'Recorded at' => 'وقت التسجيل',
        'Production recovery sequence' => 'تسلسل استعادة الإنتاج',
        'Keep Alkenzy ADV business operations stopped and do not repeatedly retry the migration.' => 'أبقِ عمليات Alkenzy ADV متوقفة ولا تكرر محاولة الترحيل بشكل متتابع.',
        'Verify the pre-deployment database backup and the exact plugin package used for this deployment.' => 'تحقق من نسخة قاعدة البيانات الاحتياطية قبل النشر ومن حزمة البلجن المطابقة المستخدمة في هذا النشر.',
        'If application rollback succeeded, investigate and correct the failed migration before one controlled retry.' => 'إذا نجح الرجوع على مستوى التطبيق، فحقق في سبب فشل الترحيل وأصلحه قبل محاولة واحدة منضبطة.',
        'If rollback failed or schema integrity is uncertain, restore the verified pre-deployment database backup and matching plugin release.' => 'إذا فشل الرجوع أو كانت سلامة المخطط غير مؤكدة، فاستعد نسخة قاعدة البيانات الاحتياطية الموثقة قبل النشر وإصدار البلجن المطابق.',
        'Run database, API and business smoke checks before reopening production operations.' => 'شغّل اختبارات قاعدة البيانات وواجهات API واختبارات الأعمال السريعة قبل إعادة فتح عمليات الإنتاج.',
        'Operator runbook' => 'دليل مشغل النظام',
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
