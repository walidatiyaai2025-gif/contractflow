<?php

declare(strict_types=1);

namespace SafeContracts\Translations;

/**
 * Human labels for enum/state values that arrive from SafeContracts data rows
 * rather than literal UI gettext calls.
 */
final class RuntimeLabels
{
    /** @var array<string,string> */
    private const ARABIC = [
        'Pending' => 'قيد المتابعة',
        'Contacted' => 'تم التواصل',
        'Promised to pay' => 'وعد بالسداد',
        'Issue' => 'مشكلة',
        'Deferred' => 'مؤجل',
        'Needs escalation' => 'يحتاج تصعيد',
        'Before due' => 'قبل الاستحقاق',
        'Due today' => 'مستحق اليوم',
        'Overdue' => 'متأخر',
        'Queued' => 'في قائمة الانتظار',
        'Processing' => 'قيد التنفيذ',
        'Sent' => 'تم الإرسال',
        'Delivered' => 'تم التسليم',
        'Failed' => 'فشل',
        'Suppressed' => 'تم المنع',
        'Skipped' => 'تم التجاوز',
        'Enabled' => 'مفعّل',
        'Disabled' => 'معطل',
        'System Administrator' => 'مسؤول النظام',
        'Manager' => 'مدير',
        'Accountant' => 'محاسب',
        'Viewer' => 'مشاهد',
        'Assigned Accountant' => 'المحاسب المسند',

        // Runtime Inspector and production diagnostics.
        'Runtime Inspector' => 'فاحص وقت التشغيل',
        'You do not have permission to manage runtime diagnostics.' => 'ليست لديك صلاحية لإدارة تشخيص وقت التشغيل.',
        'You do not have permission to view runtime diagnostics.' => 'ليست لديك صلاحية لعرض تشخيص وقت التشغيل.',
        'Production diagnostics' => 'تشخيص بيئة الإنتاج',
        'This page captures sanitized SafeContracts runtime failures so administrators can diagnose operations without asking the end user to reproduce technical details.' => 'تسجل هذه الصفحة أخطاء SafeContracts وقت التشغيل بعد تنقيح البيانات الحساسة، حتى يتمكن المسؤولون من تشخيص العمليات دون مطالبة المستخدم بإعادة وصف التفاصيل الفنية.',
        'Runtime health' => 'سلامة وقت التشغيل',
        'Plugin version' => 'إصدار الإضافة',
        'Database version' => 'إصدار قاعدة البيانات',
        'PHP version' => 'إصدار PHP',
        'WordPress version' => 'إصدار WordPress',
        'Check' => 'الفحص',
        'Details' => 'التفاصيل',
        'Recent runtime failures' => 'أحدث أخطاء وقت التشغيل',
        'Retention is bounded to the most recent 50 events. Secrets, tokens, passwords, cookies, authorization headers, nonces and raw request bodies are never stored.' => 'يُحتفظ بأحدث 50 حدثاً فقط. لا يتم أبداً تخزين الأسرار أو الرموز أو كلمات المرور أو ملفات تعريف الارتباط أو ترويسات التفويض أو قيم nonce أو أجسام الطلبات الخام.',
        'Clear runtime history' => 'مسح سجل وقت التشغيل',
        'No runtime failures have been recorded.' => 'لم يتم تسجيل أخطاء وقت تشغيل.',
        'Correlation ID' => 'معرّف التتبع',
        'Time (UTC)' => 'الوقت (UTC)',
        'Operation / stage' => 'العملية / المرحلة',
        'Failure' => 'الخطأ',
        'Diagnostics' => 'التشخيص',
        'Database error:' => 'خطأ قاعدة البيانات:',
        'Open diagnostic context' => 'فتح سياق التشخيص',
        'Database migration level' => 'مستوى ترحيل قاعدة البيانات',
        'Installed %1$s; required %2$s.' => 'المثبت %1$s؛ المطلوب %2$s.',
        'Database schema' => 'بنية قاعدة البيانات',
        'WordPress database access is unavailable.' => 'الوصول إلى قاعدة بيانات WordPress غير متاح.',
        'Contract counterparty schema' => 'بنية جهة تعاقد العقد',
        'Required supplier/AP-AR contract columns are present.' => 'أعمدة عقود الموردين والحسابات الدائنة/المدينة المطلوبة موجودة.',
        'Missing contract columns: %s' => 'أعمدة العقد المفقودة: %s',
        'Supplier lifecycle schema' => 'بنية دورة حياة المورد',
        'Supplier lifecycle columns are present.' => 'أعمدة دورة حياة المورد موجودة.',
        'Missing supplier columns: %s' => 'أعمدة المورد المفقودة: %s',
        'Active supplier consistency' => 'اتساق الموردين النشطين',
        'No active supplier lifecycle mismatches were found.' => 'لم يتم العثور على تعارضات في دورة حياة الموردين النشطين.',
        '%d active supplier record(s) have conflicting legacy lifecycle flags.' => 'يوجد %d سجل مورد نشط به تعارض في علامات دورة الحياة القديمة.',
        'Responsible accountant eligibility' => 'أهلية المحاسب المسؤول',
        '%1$d of %2$d Accountant role user(s) satisfy ACCESS + CREATE_CONTRACTS + VIEW_ASSIGNED.' => '%1$d من أصل %2$d من مستخدمي دور المحاسب يستوفون صلاحيات ACCESS + CREATE_CONTRACTS + VIEW_ASSIGNED.',
        'Open Runtime Inspector' => 'فتح فاحص وقت التشغيل',
        'Runtime diagnostic captured.' => 'تم تسجيل تشخيص لخطأ وقت التشغيل.',
        'Open Runtime Inspector to review the exact failure stage and sanitized technical context.' => 'افتح فاحص وقت التشغيل لمراجعة مرحلة الفشل الدقيقة والسياق الفني بعد تنقيح البيانات الحساسة.',
    ];

    public static function register(): void
    {
        add_filter('gettext_safecontracts', [self::class, 'filterGettext'], 30, 3);
    }

    public static function text(string $source): string
    {
        $translated = TranslationCatalog::text($source);
        if ($translated !== $source || TranslationCatalog::currentLanguage() !== 'ar') {
            return $translated;
        }
        return self::ARABIC[$source] ?? $source;
    }

    public static function default(string $source): string
    {
        return self::ARABIC[$source] ?? $source;
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        return self::ARABIC;
    }

    public static function filterGettext(string $translation, string $text, string $domain = 'safecontracts'): string
    {
        if ($domain !== 'safecontracts' || TranslationCatalog::currentLanguage() !== 'ar' || $translation !== $text) {
            return $translation;
        }
        return self::default($text);
    }

    /**
     * Literal gettext hints make enum labels discoverable/editable by the
     * TranslationCatalog scanner without executing this method at runtime.
     *
     * @return list<string>
     */
    public static function catalogHints(): array
    {
        return [
            __('Pending', 'safecontracts'),
            __('Contacted', 'safecontracts'),
            __('Promised to pay', 'safecontracts'),
            __('Issue', 'safecontracts'),
            __('Deferred', 'safecontracts'),
            __('Needs escalation', 'safecontracts'),
            __('Before due', 'safecontracts'),
            __('Due today', 'safecontracts'),
            __('Overdue', 'safecontracts'),
            __('Queued', 'safecontracts'),
            __('Processing', 'safecontracts'),
            __('Sent', 'safecontracts'),
            __('Delivered', 'safecontracts'),
            __('Failed', 'safecontracts'),
            __('Suppressed', 'safecontracts'),
            __('Skipped', 'safecontracts'),
            __('Enabled', 'safecontracts'),
            __('Disabled', 'safecontracts'),
            __('System Administrator', 'safecontracts'),
            __('Manager', 'safecontracts'),
            __('Accountant', 'safecontracts'),
            __('Viewer', 'safecontracts'),
            __('Assigned Accountant', 'safecontracts'),
        ];
    }
}
