<?php

declare(strict_types=1);

namespace SafeContracts\Translations;

/** Arabic defaults for recently added operational controls. */
final class FeatureArabicDefaults
{
    /** @var array<string,string> */
    private const MAP = [
        'Activate' => 'تفعيل',
        'Deactivate' => 'تعطيل',
        'Editing a rule rebuilds its future schedule. Deactivating or deleting a rule clears all scheduled occurrences for that rule. In-flight sends must finish before the change is accepted.' => 'يؤدي تعديل القاعدة إلى إعادة بناء جدولها المستقبلي. ويؤدي تعطيل القاعدة أو حذفها إلى إزالة جميع الإشعارات المجدولة التابعة لها. يجب أن تكتمل عمليات الإرسال الجارية قبل قبول التغيير.',
        'Attachments' => 'المرفقات',
        'No attachments' => 'لا توجد مرفقات',
        'Files' => 'الملفات',
        'Remove' => 'إزالة',
        'Remove this file from the record? The WordPress Media file itself will not be deleted.' => 'هل تريد إزالة هذا الملف من السجل؟ لن يتم حذف ملف الوسائط نفسه من WordPress.',
        'Upload up to %d files at once. Supported: PDF, images, Word, Excel and text files.' => 'يمكن رفع حتى %d ملفات في المرة الواحدة. الأنواع المدعومة: PDF والصور وWord وExcel والملفات النصية.',
        'Base contract value' => 'القيمة الأساسية للعقد',
        'The base value is the original contractual amount before additions, discounts or other financial adjustments.' => 'القيمة الأساسية هي القيمة الأصلية للعقد قبل الإضافات أو الخصومات أو أي تعديلات مالية أخرى.',
        'Contract files' => 'ملفات العقد',
        'Contract attachments' => 'مرفقات العقد',
        'Add contract files' => 'إضافة ملفات للعقد',
        'Contract attachments were updated.' => 'تم تحديث مرفقات العقد.',
        'Contract or attachment was not saved. Check the values, file types, counterparty, currency, lifecycle transition and permissions.' => 'لم يتم حفظ العقد أو المرفق. راجع القيم وأنواع الملفات والطرف المقابل والعملة وانتقال الحالة والصلاحيات.',
        'Payment files' => 'ملفات الدفعة',
        'Payment attachments' => 'مرفقات الدفعة',
        'Add payment files' => 'إضافة ملفات للدفعة',
        'Add payment' => 'إضافة دفعة',
        'Payment attachments were updated.' => 'تم تحديث مرفقات الدفعة.',
        'Payment or attachment was not saved. Check the payment values, file type and permissions.' => 'لم يتم حفظ الدفعة أو المرفق. راجع بيانات الدفعة ونوع الملف والصلاحيات.',
        'Collection / receipt files' => 'ملفات التحصيل / الإيصال',
        'Add files' => 'إضافة ملفات',
        'Collection attachments were updated.' => 'تم تحديث مرفقات التحصيل.',
        'Collection or attachment was not saved. Check the amount, payment method, file type and permissions.' => 'لم يتم حفظ التحصيل أو المرفق. راجع المبلغ وطريقة الدفع ونوع الملف والصلاحيات.',
        'The backend collection service enforces active payment methods, assignment scope, exact remaining balance and atomic settlement reconciliation. You can attach several supporting files to each collection.' => 'تفرض خدمة التحصيل في الخادم استخدام طرق دفع نشطة ونطاق الإسناد والرصيد المتبقي الدقيق والتسوية الذرية. ويمكن إرفاق عدة ملفات داعمة بكل عملية تحصيل.',
        'Financial obligations' => 'الالتزامات المالية',
        'Contract filter' => 'فلتر العقد',
        'Clear filters' => 'مسح الفلاتر',
        'Contract summary' => 'ملخص العقد',
        'Counterparty' => 'الطرف المقابل',
        'Obligation type' => 'نوع الالتزام',
        'Net value' => 'القيمة الصافية',
        'Scheduled total' => 'إجمالي الدفعات المجدولة',
        'Settled total' => 'إجمالي المسدد',
        'Outstanding total' => 'إجمالي المتبقي',
        'Accounts Payable · we will pay it' => 'مديونية علينا · سندفعها',
        'Accounts Receivable · will be paid to us' => 'مستحق لنا · سيتم دفعه لنا',
        'Edit payment' => 'تعديل الدفعة',
        'Select a contract' => 'اختر عقداً',
        'Payment reference' => 'مرجع الدفعة',
        'Obligation amount' => 'قيمة الالتزام',
        'Payable amount' => 'قيمة المديونية المستحقة علينا',
        'Receivable amount' => 'القيمة المستحقة لنا',
        'Payment amount is locked after settlement activity. Dates and reference may still be changed.' => 'لا يمكن تغيير قيمة الدفعة بعد وجود حركة سداد أو تحصيل عليها. يمكن الاستمرار في تعديل التواريخ والمرجع.',
        'Save payment' => 'حفظ تعديل الدفعة',
        'Contract:' => 'العقد:',
        'Obligation type:' => 'نوع الالتزام:',
    ];

    public static function register(): void
    {
        add_filter('gettext_safecontracts', [self::class, 'filterGettext'], 28, 3);
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
