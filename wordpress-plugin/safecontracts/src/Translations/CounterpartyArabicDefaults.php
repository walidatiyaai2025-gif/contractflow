<?php

declare(strict_types=1);

namespace SafeContracts\Translations;

final class CounterpartyArabicDefaults
{
    /** @var array<string,string> */
    private const MAP = [
        'Suppliers' => 'الموردون',
        'Supplier' => 'المورد',
        'Supplier master' => 'دليل الموردين',
        'Supplier directory' => 'دليل الموردين',
        'Supplier profile' => 'ملف المورد',
        'Supplier master data' => 'البيانات الرئيسية للموردين',
        'Counterparty master data' => 'البيانات الرئيسية للأطراف المتعاقدة',
        'New counterparty' => 'طرف متعاقد جديد',
        'Create supplier' => 'إنشاء مورد',
        'Save supplier' => 'حفظ المورد',
        'Supplier saved.' => 'تم حفظ المورد.',
        'Supplier archived. Existing contract and finance history is preserved.' => 'تمت أرشفة المورد مع الحفاظ على سجل العقود والبيانات المالية السابقة.',
        'Supplier could not be archived.' => 'تعذر أرشفة المورد.',
        'Supplier was not saved. Check required fields, duplicate identifiers and validation rules.' => 'تعذر حفظ المورد. راجع الحقول المطلوبة والمعرفات المكررة وقواعد التحقق.',
        'You do not have permission to save suppliers.' => 'ليست لديك صلاحية لحفظ الموردين.',
        'You do not have permission to archive suppliers.' => 'ليست لديك صلاحية لأرشفة الموردين.',
        'You do not have permission to view suppliers.' => 'ليست لديك صلاحية لعرض الموردين.',
        'Search suppliers' => 'البحث في الموردين',
        'Name, code, registration or tax number' => 'الاسم أو الكود أو رقم التسجيل أو الرقم الضريبي',
        'Include archived' => 'تضمين المؤرشف',
        'No suppliers match the current search.' => 'لا توجد جهات توريد تطابق البحث الحالي.',
        'Legal name' => 'الاسم القانوني',
        'Trading name' => 'الاسم التجاري',
        'Internal code' => 'الكود الداخلي',
        'Contact name' => 'اسم جهة الاتصال',
        'Country code' => 'رمز الدولة',
        'Registration number' => 'رقم التسجيل',
        'Tax / VAT number' => 'الرقم الضريبي / ضريبة القيمة المضافة',
        'Default currency' => 'العملة الافتراضية',
        'Payment terms' => 'شروط السداد',
        'Address' => 'العنوان',
        'Notes' => 'ملاحظات',
        'Terms' => 'الشروط',
        'Status' => 'الحالة',
        'Active' => 'نشط',
        'Inactive' => 'غير نشط',
        'Suspended' => 'موقوف',
        'Archived' => 'مؤرشف',
        'Archive' => 'أرشفة',
        'Open Accounts Payable' => 'فتح الحسابات الدائنة',
        'Archived suppliers are read-only. Their historical contracts and financial records remain available.' => 'الموردون المؤرشفون للقراءة فقط، وتظل عقودهم وسجلاتهم المالية السابقة متاحة.',
        'Supplier master data is authoritative for Accounts Payable contracts. Archiving removes a supplier from new operations while preserving contract and financial history.' => 'بيانات الموردين هي المرجع لعقود الحسابات الدائنة. الأرشفة تمنع استخدام المورد في العمليات الجديدة مع الحفاظ على سجل العقود والبيانات المالية.',
        'Archive this supplier? Existing contracts and financial history will remain available, but the supplier cannot be used for new contracts.' => 'هل تريد أرشفة هذا المورد؟ ستظل العقود والسجلات المالية السابقة متاحة، ولن يمكن استخدام المورد في عقود جديدة.',

        'Counterparty' => 'الطرف المتعاقد',
        'Counterparty type' => 'نوع الطرف المتعاقد',
        'Select customer or supplier' => 'اختر عميلاً أو مورداً',
        'Customers · Accounts Receivable' => 'العملاء · الحسابات المدينة',
        'Suppliers · Accounts Payable' => 'الموردون · الحسابات الدائنة',
        'Accounts Payable' => 'الحسابات الدائنة',
        'Accounts Receivable' => 'الحسابات المدينة',
        'Direction' => 'الاتجاه المالي',
        'Currency' => 'العملة',
        'Contract currency' => 'عملة العقد',
        'Supplier master' => 'دليل الموردين',
        'Finance' => 'المالية',
        'Contract was not saved. Check the counterparty, currency, lifecycle transition and assignment permissions.' => 'تعذر حفظ العقد. راجع الطرف المتعاقد والعملة وانتقال الحالة وصلاحيات الإسناد.',
        'Every contract has an explicit counterparty. Customer contracts are Accounts Receivable; Supplier contracts are Accounts Payable. Direction is derived server-side from the counterparty type.' => 'لكل عقد طرف متعاقد محدد. عقود العملاء حسابات مدينة، وعقود الموردين حسابات دائنة. يحدد الخادم الاتجاه المالي تلقائياً حسب نوع الطرف.',
        'Customer automatically means Accounts Receivable. Supplier automatically means Accounts Payable. This direction is determined by the backend and cannot be overridden by the form.' => 'العميل يعني تلقائياً حسابات مدينة، والمورد يعني تلقائياً حسابات دائنة. يحدد الخادم هذا الاتجاه ولا يمكن تغييره من النموذج.',
        'Currency belongs to this contract and its financial obligations. Different currencies remain separate in finance totals and reports.' => 'العملة مرتبطة بهذا العقد والتزاماته المالية. تظل العملات المختلفة منفصلة في الإجماليات والتقارير المالية.',
        'Counterparty:' => 'الطرف المتعاقد:',
        'Responsible accountant' => 'المحاسب المسؤول',
        'Responsible accountant:' => 'المحاسب المسؤول:',
        'Contract number' => 'رقم العقد',
        'Contract details' => 'تفاصيل العقد',
        'Create contract' => 'إنشاء عقد',
        'Save contract' => 'حفظ العقد',
        'Base value' => 'القيمة الأساسية',
        'Net value:' => 'صافي القيمة:',
        'Start date' => 'تاريخ البداية',
        'End date' => 'تاريخ النهاية',
        'Contract status' => 'حالة العقد',
    ];

    public static function register(): void
    {
        add_filter('gettext_safecontracts', [self::class, 'filterGettext'], 21, 3);
    }

    public static function default(string $source): string
    {
        return self::MAP[$source] ?? $source;
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
