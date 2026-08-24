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

        // Plugin redesign Worker #3 — SC-027/030/031 authoritative Arabic defaults.
        '%d users · %d permissions' => '%d مستخدمون · %d صلاحيات',
        'Active devices' => 'الأجهزة النشطة',
        'Assign SafeContracts role' => 'تعيين دور SafeContracts',
        'Assigned' => 'مُعيّن',
        'Available templates' => 'القوالب المتاحة',
        'Choose only supported SafeContracts business permissions. Internal capability codes are intentionally hidden.' => 'اختر فقط صلاحيات الأعمال المدعومة في SafeContracts. يتم إخفاء رموز الصلاحيات الداخلية عمداً.',
        'Clear' => 'مسح',
        'Code or name' => 'الرمز أو الاسم',
        'Compare the WordPress user ID above with User ID in the SafeContracts mobile Profile. No FCM token or bearer credential is displayed here.' => 'قارن معرّف مستخدم WordPress أعلاه مع معرّف المستخدم في ملف SafeContracts على الموبايل. لا يتم عرض رمز FCM أو بيانات اعتماد Bearer هنا.',
        'Configure public Firebase identifiers, encrypted server credentials, connectivity checks and real device diagnostics without exposing private keys or device tokens.' => 'اضبط معرّفات Firebase العامة وبيانات اعتماد الخادم المشفرة واختبارات الاتصال وتشخيصات الأجهزة الفعلية دون كشف المفاتيح الخاصة أو رموز الأجهزة.',
        'Configure real rule triggers, recipients, channels and templates. Existing recipient and suppression enforcement remains authoritative.' => 'اضبط مشغلات القواعد والمستلمين والقنوات والقوالب الفعلية. تظل قواعد المستلمين والمنع الحالية هي المرجع المعتمد.',
        'Delete this notification rule and all of its scheduled occurrences? Delivery history already sent by the transport is not erased.' => 'هل تريد حذف قاعدة الإشعار هذه وجميع مرات تشغيلها المجدولة؟ لن يتم حذف سجل التسليم الذي تم إرساله بالفعل.',
        'Disabled rules' => 'القواعد المعطلة',
        'Editable SafeContracts capabilities' => 'صلاحيات SafeContracts القابلة للتعديل',
        'Editing a rule rebuilds its future schedule. Deactivating or deleting a rule clears scheduled occurrences for that rule. In-flight sends must finish before the change is accepted.' => 'يؤدي تعديل القاعدة إلى إعادة بناء جدولها المستقبلي. ويؤدي تعطيل القاعدة أو حذفها إلى إزالة مرات التشغيل المجدولة لها. يجب أن تكتمل عمليات الإرسال الجارية قبل قبول التغيير.',
        'Editing, activation and deletion use the existing schedule reconciliation path.' => 'تستخدم عمليات التعديل والتفعيل والحذف مسار تسوية الجدول الحالي.',
        'Encrypted credential state and server-side connectivity controls.' => 'حالة بيانات الاعتماد المشفرة وضوابط الاتصال من جهة الخادم.',
        'Manage SafeContracts role membership and business capabilities without weakening existing WordPress authorization boundaries.' => 'أدر عضوية أدوار SafeContracts وصلاحيات الأعمال دون إضعاف حدود تفويض WordPress الحالية.',
        'Maximum 64 KB. Uploaded plaintext exists only during this request and is converted immediately into encrypted credential storage.' => 'الحد الأقصى 64 كيلوبايت. توجد البيانات غير المشفرة فقط أثناء هذا الطلب ثم تُحوّل فوراً إلى تخزين مشفر لبيانات الاعتماد.',
        'Name or email' => 'الاسم أو البريد الإلكتروني',
        'No SafeContracts role' => 'لا يوجد دور SafeContracts',
        'No encrypted service-account metadata is currently available.' => 'لا تتوفر حالياً بيانات وصفية مشفرة لحساب الخدمة.',
        'No notification rules match the selected filters.' => 'لا توجد قواعد إشعارات تطابق الفلاتر المحددة.',
        'No users match the selected filters.' => 'لا يوجد مستخدمون يطابقون الفلاتر المحددة.',
        'Not assigned' => 'غير مُعيّن',
        'Notification rule activated and future schedule reconciled.' => 'تم تفعيل قاعدة الإشعار وتسوية جدولها المستقبلي.',
        'Notification rule change could not be applied. Review the entered values and try again.' => 'تعذر تطبيق تغيير قاعدة الإشعار. راجع القيم المدخلة وحاول مرة أخرى.',
        'Notification rule deactivated and future scheduled occurrences cleared.' => 'تم تعطيل قاعدة الإشعار وإزالة مرات التشغيل المجدولة مستقبلاً.',
        'Notification rule deleted and future scheduled occurrences cleared.' => 'تم حذف قاعدة الإشعار وإزالة مرات التشغيل المجدولة مستقبلاً.',
        'Notification rule saved and future schedule reconciled.' => 'تم حفظ قاعدة الإشعار وتسوية جدولها المستقبلي.',
        'Only SafeContracts role membership changes here. Other WordPress roles are preserved.' => 'يتم هنا تغيير عضوية أدوار SafeContracts فقط. تظل أدوار WordPress الأخرى محفوظة.',
        'Public config complete' => 'الإعدادات العامة مكتملة',
        'Public config incomplete' => 'الإعدادات العامة غير مكتملة',
        'Public fields configured' => 'الحقول العامة المضبوطة',
        'SafeContracts never renders the Firebase private key, OAuth access token, bearer token or FCM device token on this page.' => 'لا يعرض SafeContracts مطلقاً مفتاح Firebase الخاص أو رمز وصول OAuth أو رمز Bearer أو رمز جهاز FCM في هذه الصفحة.',
        'SafeContracts role' => 'دور SafeContracts',
        'Search rules' => 'بحث في القواعد',
        'Search users' => 'بحث في المستخدمين',
        'Security boundary' => 'حدود الأمان',
        'Security, recipient scope and notification-engine validation remain unchanged.' => 'تظل ضوابط الأمان ونطاق المستلمين والتحقق في محرك الإشعارات دون تغيير.',
        'Test Notification targets every active device registered for this exact WordPress user. Existing Firebase delivery semantics and token deactivation behavior remain unchanged.' => 'يستهدف اختبار الإشعار كل جهاز نشط مسجل لمستخدم WordPress هذا تحديداً. تظل دلالات تسليم Firebase الحالية وسلوك تعطيل الرموز دون تغيير.',
        'The service-account upload is validated and converted immediately to encrypted vault storage. Only non-secret metadata and a key fingerprint are shown afterward.' => 'يتم التحقق من ملف حساب الخدمة وتحويله فوراً إلى مخزن مشفر. بعد ذلك لا تُعرض سوى البيانات الوصفية غير السرية وبصمة المفتاح.',
        'These are public project identifiers only.' => 'هذه معرّفات عامة للمشروع فقط.',
        'This table reflects current WordPress users and their current SafeContracts role membership.' => 'يعكس هذا الجدول مستخدمي WordPress الحاليين وعضويتهم الحالية في أدوار SafeContracts.',
        'Use only a server-side credential reference here. Never paste service-account JSON, private keys or OAuth tokens into this field.' => 'استخدم هنا مرجع بيانات اعتماد من جهة الخادم فقط. لا تلصق JSON لحساب الخدمة أو مفاتيح خاصة أو رموز OAuth في هذا الحقل.',
        'User directory' => 'دليل المستخدمين',
        'Users with SafeContracts role' => 'المستخدمون الذين لديهم دور SafeContracts',
        'of 3 required fields' => 'من أصل 3 حقول مطلوبة',
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
