<?php

declare(strict_types=1);

namespace SafeContracts\Translations;

final class NotificationScheduleArabicDefaults
{
    /** @var array<string,string> */
    private const MAP = [
        'Notification Schedule' => 'جدولة الإشعارات',
        'You do not have permission to send notifications.' => 'ليست لديك صلاحية لإرسال الإشعارات.',
        'You do not have permission to manage notification scheduling.' => 'ليست لديك صلاحية لإدارة جدولة الإشعارات.',
        'From' => 'من',
        'To' => 'إلى',
        'All' => 'الكل',
        'Daily dispatch time' => 'وقت الإرسال اليومي',
        'Save time' => 'حفظ الوقت',
        'Invalid period: the From date must not be after the To date.' => 'الفترة غير صحيحة: لا يمكن أن يكون تاريخ البداية بعد تاريخ النهاية.',
        'Schedule dates follow the configured WordPress/site timezone. Dispatch runs every five minutes through WP-Cron; actual execution depends on site traffic or the server cron that invokes WordPress cron.' => 'تتبع مواعيد الجدولة المنطقة الزمنية المضبوطة للموقع. يتم فحص الإرسال كل خمس دقائق عبر WP-Cron، ويعتمد التنفيذ الفعلي على زيارات الموقع أو مهمة الخادم التي تشغّل WordPress Cron.',
        'Last scheduler run (UTC):' => 'آخر تشغيل للمجدول (UTC):',
        'Actual scheduled notifications' => 'الإشعارات المجدولة فعلياً',
        'Notification' => 'الإشعار',
        'Recipients / result' => 'المستلمون / النتيجة',
        'Sent via' => 'طريقة الإرسال',
        'Last attempt' => 'آخر محاولة',
        'Action' => 'الإجراء',
        'No scheduled notifications match this period and status.' => 'لا توجد إشعارات مجدولة تطابق الفترة والحالة المحددتين.',
        'Local/site time' => 'توقيت الموقع',
        'Rule attempt' => 'محاولة القاعدة',
        'Push' => 'إشعار فوري',
        'Sent %d / Failed %d / Recipients %d' => 'تم %d / فشل %d / المستلمون %d',
        'Manual attempts: %d' => 'محاولات الإرسال اليدوي: %d',
        'Sending…' => 'جارٍ الإرسال…',
        'Send this notification now using the current rule, recipients and Firebase configuration?' => 'هل تريد إرسال هذا الإشعار الآن باستخدام القاعدة والمستلمين وإعدادات Firebase الحالية؟',
        'Resend manually' => 'إعادة إرسال يدوي',
        'Send manually' => 'إرسال يدوي',
        'Manual Send never bypasses settled-payment suppression, active-rule checks, recipient resolution, Firebase transport, delivery logging or audit recording.' => 'الإرسال اليدوي لا يتجاوز منع إشعارات الدفعات المسددة أو فحص القاعدة النشطة أو تحديد المستلمين أو إرسال Firebase أو سجل التسليم أو سجل التدقيق.',
        'Sent' => 'تم الإرسال',
        'Not sent' => 'لم يتم الإرسال',
        'Pending' => 'قيد الانتظار',
        'Processing' => 'جارٍ التنفيذ',
        'Partial' => 'تم جزئياً',
        'Failed' => 'فشل',
        'Skipped' => 'تم التجاوز',
        'Manual notification dispatch completed.' => 'تم تنفيذ إرسال الإشعار اليدوي.',
        'This notification is already being processed.' => 'هذا الإشعار قيد التنفيذ بالفعل.',
        'Manual notification dispatch could not be completed.' => 'تعذر إكمال إرسال الإشعار اليدوي.',
        'Notification dispatch time was saved and pending schedules were refreshed.' => 'تم حفظ وقت إرسال الإشعارات وتحديث الجداول المعلقة.',
        'Notification dispatch time is invalid.' => 'وقت إرسال الإشعارات غير صحيح.',
        'Every five minutes (SafeContracts notifications)' => 'كل خمس دقائق (إشعارات SafeContracts)',
    ];

    public static function register(): void
    {
        add_filter('gettext_safecontracts', [self::class, 'filterGettext'], 21, 3);
    }

    public static function filterGettext(string $translation, string $text, string $domain = 'safecontracts'): string
    {
        if ($domain !== 'safecontracts' || TranslationCatalog::currentLanguage() !== 'ar') {
            return $translation;
        }
        if ($translation !== $text) {
            return $translation;
        }
        return self::MAP[$text] ?? $translation;
    }
}
