<?php

declare(strict_types=1);

namespace SafeContracts\Translations;

/**
 * Arabic defaults introduced by the production-safety UX layer.
 *
 * Keeping these defaults in one registry makes capability wording and the
 * contextual user guide auditable, testable and editable through the existing
 * translation dashboard without leaking internal identifiers to end users.
 */
final class ProductionUxArabicDefaults
{
    /** @var array<string,string> */
    private const MAP = [
        // Permission presentation.
        'Access Alkenzy ADV' => 'الدخول إلى Alkenzy ADV',
        'Open the Alkenzy ADV workspace and use the features allowed by the assigned role.' => 'فتح مساحة عمل Alkenzy ADV واستخدام الوظائف المسموح بها وفق الدور المسند.',
        'Manage system settings' => 'إدارة إعدادات النظام',
        'Change organization, mobile, translation and other administrator-only system settings.' => 'تعديل إعدادات المؤسسة والموبايل والترجمات وباقي إعدادات النظام المخصصة لمسؤول النظام.',
        'Manage reference data' => 'إدارة البيانات المرجعية',
        'Maintain controlled reference choices such as payment methods without changing historical transactions.' => 'إدارة الخيارات المرجعية المنضبطة مثل طرق السداد من دون تغيير المعاملات التاريخية.',
        'Manage users and roles' => 'إدارة المستخدمين والأدوار',
        'Assign Alkenzy ADV roles and choose the business permissions available to each role.' => 'إسناد أدوار Alkenzy ADV واختيار صلاحيات الأعمال المتاحة لكل دور.',
        'View all business records' => 'عرض جميع سجلات الأعمال',
        'View records across the full authorized organization scope instead of assigned records only.' => 'عرض السجلات على مستوى نطاق المؤسسة المصرح به بدلاً من السجلات المسندة فقط.',
        'View assigned records' => 'عرض السجلات المسندة',
        'View records assigned to the current user within the authorized business scope.' => 'عرض السجلات المسندة للمستخدم الحالي ضمن نطاق الأعمال المصرح به.',
        'Create customers' => 'إنشاء العملاء',
        'Add new customer records.' => 'إضافة سجلات عملاء جديدة.',
        'Edit customers' => 'تعديل العملاء',
        'Update existing customer records within the authorized scope.' => 'تحديث سجلات العملاء الحالية ضمن النطاق المصرح به.',
        'View suppliers' => 'عرض الموردين',
        'Open supplier records and supplier-linked contracts within the authorized scope.' => 'عرض سجلات الموردين والعقود المرتبطة بهم ضمن النطاق المصرح به.',
        'Create suppliers' => 'إنشاء الموردين',
        'Add new supplier records.' => 'إضافة سجلات موردين جديدة.',
        'Edit suppliers' => 'تعديل الموردين',
        'Update existing supplier records within the authorized scope.' => 'تحديث سجلات الموردين الحالية ضمن النطاق المصرح به.',
        'Archive suppliers' => 'أرشفة الموردين',
        'Remove suppliers from active operations while preserving required history and financial evidence.' => 'إخراج الموردين من العمليات النشطة مع الحفاظ على السجل والأدلة المالية المطلوبة.',
        'Manage supplier operations' => 'إدارة عمليات الموردين',
        'Perform supplier administration and supplier-side payable operations allowed by the system.' => 'تنفيذ إدارة الموردين وعمليات المستحقات الدائنة المسموح بها في النظام.',
        'Create contracts' => 'إنشاء العقود',
        'Create customer or supplier contracts using controlled business selections.' => 'إنشاء عقود العملاء أو الموردين باستخدام اختيارات أعمال منضبطة.',
        'Edit contracts' => 'تعديل العقود',
        'Update editable contract details while server validation remains authoritative.' => 'تحديث بيانات العقد القابلة للتعديل مع بقاء تحقق الخادم هو المرجع.',
        'Assign contracts' => 'إسناد العقود',
        'Assign contracts to authorized accountants or responsible users.' => 'إسناد العقود إلى المحاسبين أو المستخدمين المسؤولين المصرح بهم.',
        'Create payment schedules' => 'إنشاء جداول الدفعات',
        'Add contractual payment schedule entries for authorized contracts.' => 'إضافة بنود جدول الدفعات التعاقدية للعقود المصرح بها.',
        'Edit payment schedules' => 'تعديل جداول الدفعات',
        'Update permitted payment schedule fields before settlement.' => 'تحديث حقول جدول الدفعات المسموح بها قبل التسوية.',
        'Manage payment operations' => 'إدارة عمليات الدفعات',
        'Perform payment administration, reconciliation and allowed payment actions.' => 'تنفيذ إدارة الدفعات والتسوية والإجراءات المسموح بها على الدفعات.',
        'View finance workspace' => 'عرض مساحة العمل المالية',
        'View authorized receivable, payable, aging and cash-flow information.' => 'عرض معلومات المستحقات المدينة والدائنة والأعمار والتدفقات النقدية المصرح بها.',
        'Manage finance operations' => 'إدارة العمليات المالية',
        'Perform authorized finance actions and settlement workflows.' => 'تنفيذ الإجراءات المالية ومسارات التسوية المصرح بها.',
        'View payables' => 'عرض المستحقات الدائنة',
        'View supplier-side amounts that are due for payment.' => 'عرض المبالغ المستحقة للدفع للموردين.',
        'View receivables' => 'عرض المستحقات المدينة',
        'View customer-side amounts that are due for collection.' => 'عرض المبالغ المستحقة للتحصيل من العملاء.',
        'Record and manage collections' => 'تسجيل وإدارة التحصيلات',
        'Record authorized receipts and manage their supported lifecycle actions.' => 'تسجيل التحصيلات المصرح بها وإدارة إجراءات دورة حياتها المدعومة.',
        'Manage follow-up' => 'إدارة المتابعة',
        'Record and review operational follow-up actions for outstanding receivables.' => 'تسجيل ومراجعة إجراءات المتابعة التشغيلية للمستحقات القائمة.',
        'View reports' => 'عرض التقارير',
        'Run authorized operational and financial reports.' => 'تشغيل التقارير التشغيلية والمالية المصرح بها.',
        'Export reports' => 'تصدير التقارير',
        'Export authorized report results to supported files such as Excel.' => 'تصدير نتائج التقارير المصرح بها إلى الملفات المدعومة مثل Excel.',
        'Manage notifications' => 'إدارة الإشعارات',
        'Manage notification rules, schedules, templates and permitted delivery actions.' => 'إدارة قواعد الإشعارات والجداول والقوالب وإجراءات الإرسال المسموح بها.',
        'Run imports' => 'تشغيل الاستيراد',
        'Upload, validate, map and execute controlled data imports.' => 'رفع ملفات الاستيراد والتحقق منها وربط الحقول وتنفيذ الاستيراد المنضبط.',
        'View audit history' => 'عرض سجل التدقيق',
        'Review protected operational history and audit evidence.' => 'مراجعة السجل التشغيلي المحمي وأدلة التدقيق.',
        'Permission' => 'الصلاحية',
        'What this permission allows' => 'ما الذي تسمح به هذه الصلاحية',
        'Role permissions' => 'صلاحيات الدور',
        'Choose business permissions by their clear names. Internal capability codes are intentionally hidden.' => 'اختر صلاحيات الأعمال من مسمياتها الواضحة. يتم إخفاء أكواد الصلاحيات الداخلية عمداً.',
        'Unnamed WordPress user' => 'مستخدم WordPress بدون اسم',

        // Contextual guide framework.
        'User Guide' => 'دليل المستخدم',
        'How to use this page' => 'كيفية استخدام هذه الصفحة',
        'What this page does' => 'وظيفة هذه الصفحة',
        'Recommended steps' => 'الخطوات المقترحة',
        'Related places' => 'أماكن مرتبطة',
        'Open full user guide' => 'فتح دليل المستخدم الكامل',
        'Use this guide to understand each area before changing production data.' => 'استخدم هذا الدليل لفهم كل جزء قبل تعديل بيانات الإنتاج.',
        'Only sections available to your current role are shown.' => 'يتم عرض الأجزاء المتاحة لدورك الحالي فقط.',
        'Select records from the available lists instead of typing internal IDs or codes.' => 'اختر السجلات من القوائم المتاحة بدلاً من كتابة أرقام أو أكواد داخلية.',
        'Review the selected business entity and the entered dates before saving.' => 'راجع جهة الأعمال المختارة والتواريخ المدخلة قبل الحفظ.',
        'Use filters and search to find an existing record before creating a duplicate.' => 'استخدم الفلاتر والبحث للعثور على السجل الحالي قبل إنشاء سجل مكرر.',
        'Dashboard gives you a current operational summary and shortcuts to the main business areas.' => 'تعرض لوحة التحكم ملخصاً تشغيلياً حالياً واختصارات إلى مناطق الأعمال الرئيسية.',
        'Review the key indicators first, then open the related list to investigate the underlying records.' => 'راجع المؤشرات الرئيسية أولاً ثم افتح القائمة المرتبطة لفحص السجلات التي تكوّن هذه الأرقام.',
        'Customers stores customer master data used by receivable contracts and collection workflows.' => 'تحتفظ صفحة العملاء بالبيانات الأساسية للعملاء المستخدمة في عقود المستحقات المدينة ومسارات التحصيل.',
        'Create or edit a customer here, then go to Contracts to create a customer receivable contract.' => 'أنشئ أو عدّل العميل هنا، ثم انتقل إلى العقود لإنشاء عقد مستحقات مدينة للعميل.',
        'Suppliers stores supplier master data used by payable contracts and payment obligations.' => 'تحتفظ صفحة الموردين بالبيانات الأساسية للموردين المستخدمة في العقود والمستحقات الدائنة.',
        'Create or edit a supplier here, then go to Contracts to create a supplier payable contract.' => 'أنشئ أو عدّل المورد هنا، ثم انتقل إلى العقود لإنشاء عقد مستحقات دائنة للمورد.',
        'Contracts is the authoritative workspace for customer receivable and supplier payable agreements.' => 'صفحة العقود هي مساحة العمل الرئيسية لعقود العملاء المدينة وعقود الموردين الدائنة.',
        'Choose the counterparty from the provided list, confirm direction and dates, then save the contract.' => 'اختر جهة التعاقد من القائمة المتاحة، وتأكد من اتجاه العقد والتواريخ، ثم احفظ العقد.',
        'Payments manages contractual due schedule entries and their outstanding balances.' => 'تدير صفحة الدفعات بنود جدول الاستحقاق التعاقدي والأرصدة المتبقية عليها.',
        'Choose the contract from the list, review due date and amount, then save the schedule entry.' => 'اختر العقد من القائمة، وراجع تاريخ الاستحقاق والمبلغ، ثم احفظ بند الجدول.',
        'Collections records money received against authorized receivable payments.' => 'تسجل صفحة التحصيلات الأموال المستلمة مقابل الدفعات المدينة المصرح بها.',
        'Choose the payment from the available list and record the receipt details; do not type payment IDs manually.' => 'اختر الدفعة من القائمة المتاحة وسجل بيانات التحصيل؛ لا تكتب رقم الدفعة يدوياً.',
        'Follow-up tracks operational contact, promises and escalation for outstanding receivables.' => 'تتابع صفحة المتابعة التواصل التشغيلي ووعود السداد والتصعيد للمستحقات القائمة.',
        'Select an outstanding payment from the queue, review its history, then add the next follow-up action.' => 'اختر دفعة قائمة من قائمة المتابعة، وراجع سجلها، ثم أضف إجراء المتابعة التالي.',
        'Notification Center manages templates, rules and controlled direct notification operations.' => 'يدير مركز الإشعارات القوالب والقواعد وعمليات الإرسال المباشر المنضبطة.',
        'Review the rule and recipient scope before sending or changing notification content.' => 'راجع القاعدة ونطاق المستلمين قبل الإرسال أو تعديل محتوى الإشعار.',
        'Notifications shows operational notification activity and delivery outcomes.' => 'تعرض صفحة الإشعارات نشاط الإشعارات التشغيلي ونتائج التسليم.',
        'Use delivery status and filters here; change configuration from Notification Settings when needed.' => 'استخدم حالة التسليم والفلاتر هنا؛ وعدّل الإعدادات من صفحة إعدادات الإشعارات عند الحاجة.',
        'Notification Schedule controls when scheduled reminder work is executed and reviewed.' => 'تتحكم صفحة جدول الإشعارات في توقيت تنفيذ ومراجعة أعمال التذكير المجدولة.',
        'Review the configured schedule and pending rows before running any permitted manual action.' => 'راجع الجدول المضبوط والصفوف المعلقة قبل تنفيذ أي إجراء يدوي مسموح.',
        'Finance combines authorized receivable, payable, aging and cash-flow views.' => 'تجمع صفحة المالية عروض المستحقات المدينة والدائنة والأعمار والتدفقات النقدية المصرح بها.',
        'Start from the finance summary, then open the relevant customer, supplier or contract for source details.' => 'ابدأ من الملخص المالي، ثم افتح العميل أو المورد أو العقد المرتبط لمراجعة التفاصيل المصدرية.',
        'Reports provides server-calculated operational and financial reporting for your authorized scope.' => 'توفر صفحة التقارير تقارير تشغيلية ومالية محسوبة على الخادم ضمن نطاق صلاحياتك.',
        'Set the required filters, run the report, then export only after reviewing the on-screen result.' => 'حدد الفلاتر المطلوبة وشغّل التقرير، ثم صدّر بعد مراجعة النتيجة الظاهرة على الشاشة.',
        'Active Users shows current system activity without exposing authentication secrets.' => 'تعرض صفحة المستخدمين النشطين نشاط النظام الحالي من دون كشف أسرار المصادقة.',
        'Use this page for operational visibility; manage role membership from Users & Roles.' => 'استخدم هذه الصفحة للرؤية التشغيلية؛ وأدر عضوية الأدوار من صفحة المستخدمين والصلاحيات.',
        'Users & Roles controls Alkenzy ADV role membership and business permissions.' => 'تتحكم صفحة المستخدمين والصلاحيات في عضوية أدوار Alkenzy ADV وصلاحيات الأعمال.',
        'Choose a user and role from the lists, then configure permissions using the clear business labels.' => 'اختر المستخدم والدور من القوائم، ثم اضبط الصلاحيات باستخدام مسميات الأعمال الواضحة.',
        'Archive contains records removed from active operations while preserving required evidence.' => 'تحتوي صفحة الأرشيف على السجلات التي خرجت من العمليات النشطة مع الحفاظ على الأدلة المطلوبة.',
        'Review archived history here; return to the source business page to work with active records.' => 'راجع السجل المؤرشف هنا؛ وارجع إلى صفحة الأعمال الأصلية للعمل على السجلات النشطة.',
        'Imports provides a controlled path for bringing validated workbook data into Alkenzy ADV.' => 'توفر صفحة الاستيراد مساراً منضبطاً لإدخال بيانات ملفات Excel التي تم التحقق منها إلى Alkenzy ADV.',
        'Upload the workbook, inspect it, map columns, review validation results, then execute the import.' => 'ارفع ملف Excel وافحصه واربط الأعمدة وراجع نتائج التحقق ثم نفّذ الاستيراد.',
        'Settings contains organization-wide operational preferences used by Alkenzy ADV.' => 'تحتوي صفحة الإعدادات على تفضيلات التشغيل العامة للمؤسسة المستخدمة في Alkenzy ADV.',
        'Change settings only after confirming the production impact; use controlled choices where provided.' => 'غيّر الإعدادات بعد التأكد من تأثيرها على الإنتاج، واستخدم الاختيارات المنضبطة المتاحة.',
        'Payment Methods maintains the controlled list of methods users can choose when recording collections.' => 'تدير صفحة طرق السداد القائمة المنضبطة التي يختار منها المستخدم عند تسجيل التحصيلات.',
        'Create or deactivate a method here, then choose it from the list when recording a collection.' => 'أنشئ أو عطّل طريقة سداد هنا، ثم اخترها من القائمة عند تسجيل التحصيل.',
        'Notification Settings controls notification behavior and recipient rules.' => 'تتحكم إعدادات الإشعارات في سلوك الإشعارات وقواعد المستلمين.',
        'Review trigger timing, recipient scope and repeat limits before saving a rule.' => 'راجع توقيت المشغّل ونطاق المستلمين وحدود التكرار قبل حفظ القاعدة.',
        'Firebase Settings connects Alkenzy ADV mobile notifications to the approved Firebase project.' => 'تربط إعدادات Firebase إشعارات موبايل Alkenzy ADV بمشروع Firebase المعتمد.',
        'Verify the project and service account, run the connection test, then test notification delivery.' => 'تحقق من المشروع وحساب الخدمة وشغّل اختبار الاتصال ثم اختبر تسليم الإشعار.',
        'Mobile Configuration controls server-provided mobile feature flags and safe defaults.' => 'تتحكم إعدادات الموبايل في خصائص الموبايل التي يوفرها الخادم والقيم الآمنة الافتراضية.',
        'Review each feature flag and page-size setting before publishing configuration changes.' => 'راجع كل خاصية وإعداد حجم الصفحة قبل نشر تغييرات الإعداد.',
        'Translations manages the English and Arabic wording used across Alkenzy ADV surfaces.' => 'تدير صفحة الترجمات النصوص الإنجليزية والعربية المستخدمة عبر واجهات Alkenzy ADV.',
        'Search for the source phrase, edit the required language override, then save and verify the affected screen.' => 'ابحث عن النص المصدر وعدّل اللغة المطلوبة ثم احفظ وتحقق من الشاشة المتأثرة.',
        'The User Guide explains every Alkenzy ADV area and links you to the next related task.' => 'يشرح دليل المستخدم كل جزء في Alkenzy ADV ويوجهك إلى المهمة المرتبطة التالية.',
        'Choose a section below to read its purpose and recommended steps, then open that page when you are ready.' => 'اختر قسماً أدناه لقراءة وظيفته والخطوات المقترحة ثم افتح الصفحة عندما تكون جاهزاً.',

        // Production migration recovery wording.
        'Database upgrade requires attention' => 'ترقية قاعدة البيانات تحتاج إلى تدخل',
        'Alkenzy ADV stopped the database upgrade before marking it complete. Review the migration journal and rollback runbook before retrying.' => 'أوقف Alkenzy ADV ترقية قاعدة البيانات قبل اعتبارها مكتملة. راجع سجل الترحيل ودليل الرجوع قبل إعادة المحاولة.',
        'Migration target' => 'إصدار الترحيل المستهدف',
        'Rollback status' => 'حالة الرجوع',
        'Review production rollback guide' => 'مراجعة دليل الرجوع للإنتاج',
    ];

    public static function register(): void
    {
        add_filter('gettext_safecontracts', [self::class, 'filterGettext'], 21, 3);
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
