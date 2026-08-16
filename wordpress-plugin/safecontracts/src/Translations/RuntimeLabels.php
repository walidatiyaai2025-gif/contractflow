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
        ];
    }
}
