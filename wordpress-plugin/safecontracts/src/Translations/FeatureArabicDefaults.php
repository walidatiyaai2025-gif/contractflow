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
        'Payment or attachment was not saved. Check the payment values, file type and permissions.' => 'لم يتم حفظ الدفعة. راجع البيانات والمرفقات؛ النظام يمنع أي دفعة تجعل إجمالي الدفعات المجدولة يتجاوز قيمة العقد.',
        'Delete this payment? Collection history prevents unsafe deletion.' => 'هل تريد حذف هذه الدفعة؟ يمنع النظام الحذف غير الآمن عند وجود سجل تحصيل أو سداد مرتبط بها.',
        'Collection / receipt files' => 'ملفات التحصيل / الإيصال',
        'Add files' => 'إضافة ملفات',
        'Collection attachments were updated.' => 'تم تحديث مرفقات التحصيل.',
        'Collection or attachment was not saved. Check the amount, payment method, file type and permissions.' => 'لم يتم حفظ التحصيل أو السداد. راجع المبلغ وطريقة الدفع والمرفقات؛ المبلغ لا يمكن أن يتجاوز المتبقي في الدفعة أو قيمة العقد.',
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
        'Payment description' => 'وصف الدفعة',
        'Description' => 'الوصف',
        'Obligation amount' => 'قيمة الالتزام',
        'Payable amount' => 'قيمة المديونية المستحقة علينا',
        'Receivable amount' => 'القيمة المستحقة لنا',
        'Payment amount is locked after settlement activity. Dates and reference may still be changed.' => 'لا يمكن تغيير قيمة الدفعة بعد وجود حركة سداد أو تحصيل عليها. يمكن الاستمرار في تعديل التواريخ والمرجع.',
        'Payment amount is locked after settlement activity. Dates and description may still be changed.' => 'لا يمكن تغيير قيمة الدفعة بعد وجود حركة سداد أو تحصيل عليها. يمكن الاستمرار في تعديل التواريخ ووصف الدفعة.',
        'Save payment' => 'حفظ تعديل الدفعة',
        'Contract:' => 'العقد:',
        'Obligation type:' => 'نوع الالتزام:',
        'Receivable contracts' => 'العقود المستحقة لنا',
        'Payable contracts' => 'العقود المستحقة علينا',
        'Money customers will pay us' => 'مبالغ سيقوم العملاء بسدادها لنا',
        'Money we will pay suppliers' => 'مبالغ سنقوم بسدادها للموردين',
        'View all' => 'عرض الكل',
        'No contracts in this direction match the current filters.' => 'لا توجد عقود من هذا النوع تطابق الفلاتر الحالية.',
        'Accounting totals' => 'الإجماليات المحاسبية',
        'Accounting totals by currency' => 'الإجماليات المحاسبية حسب العملة',
        'Currencies are never added together. Each currency is calculated independently from active contracts and non-archived scheduled payments.' => 'لا يتم جمع العملات المختلفة معاً. يتم حساب كل عملة بشكل مستقل من العقود النشطة والدفعات المجدولة غير المؤرشفة.',
        'Receivable totals' => 'إجماليات المستحق لنا',
        'Payable totals' => 'إجماليات المستحق علينا',
        'No accounting totals are available for this direction.' => 'لا توجد إجماليات محاسبية متاحة لهذا الاتجاه.',
        'Contracts count' => 'عدد العقود',
        'Base contract total' => 'إجمالي قيمة العقود',
        'Scheduled payments count' => 'عدد الدفعات المجدولة',
        'Collections / settlements count' => 'عدد الدفعات التي بها تحصيل',
        'Payments / settlements count' => 'عدد الدفعات التي بها سداد',
        'Collected from customers' => 'المحصل من العملاء',
        'Paid to suppliers' => 'المدفوع للموردين',
        'Outstanding' => 'المتبقي',
        'Receivables and payables are kept in separate accounting lanes. Green means money we expect to receive; red means money we must pay.' => 'يتم فصل المستحقات لنا عن المديونيات علينا. اللون الأخضر يعني مبالغ سنستلمها، واللون الأحمر يعني مبالغ سندفعها.',
        'Receivable payments · we will receive' => 'دفعات مستحقة لنا · سنستلمها',
        'Payable payments · we will pay' => 'دفعات مستحقة علينا · سندفعها',
        'Money coming in' => 'سنستلمها',
        'Money going out' => 'سندفعها',
        'No receivable payments match the current filters.' => 'لا توجد دفعات مستحقة لنا تطابق الفلاتر الحالية.',
        'No payable payments match the current filters.' => 'لا توجد دفعات مستحقة علينا تطابق الفلاتر الحالية.',
        'Green payments are receivables we expect to collect. Red payments are payables we must pay. Direction and currency always come from the contract.' => 'الدفعات الخضراء مبالغ مستحقة لنا سنقوم بتحصيلها، والدفعات الحمراء مبالغ مستحقة علينا سنقوم بسدادها. الاتجاه والعملة دائماً من بيانات العقد.',
        'Email notification' => 'إشعار بالبريد الإلكتروني',
        'In-app / push notification' => 'إشعار داخل التطبيق / دفع',
        'No notification rules are configured yet.' => 'لا توجد قواعد إشعارات مهيأة حتى الآن.',
        'Add contract' => 'إضافة عقد',
        'Add supplier' => 'إضافة مورد',
        'All months' => 'كل الشهور',
        'All visible contract types in the selected period.' => 'كل أنواع العقود الظاهرة ضمن الفترة المحددة.',
        'Cash flow' => 'التدفق المالي',
        'Contract portfolio' => 'محفظة العقود',
        'Expected inflows and outflows from the current finance scope.' => 'التدفقات الداخلة والخارجة المتوقعة ضمن نطاق المالية الحالي.',
        'Month' => 'الشهر',
        'Quick add' => 'إضافة سريعة',
        'Unable to load contract media.' => 'تعذر تحميل وسائط العقد.',
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