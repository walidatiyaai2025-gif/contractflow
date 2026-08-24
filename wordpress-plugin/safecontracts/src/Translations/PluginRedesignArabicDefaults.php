<?php

declare(strict_types=1);

namespace SafeContracts\Translations;

/**
 * Arabic defaults introduced by the locked Plugin Premium Redesign.
 *
 * This is a LEAD-owned shared translation dependency. It keeps the zero-debt
 * translation gate authoritative: new UI copy must resolve to real Arabic
 * wording rather than being allow-listed or skipped by tests.
 */
final class PluginRedesignArabicDefaults
{
    /** @var array<string,string> */
    private const MAP = [
        // LEAD SC-001..SC-012.
        'A real-time overview of contracts, receivables, payables, settlements and notifications.' => 'نظرة فورية على العقود والمبالغ المستحقة لنا وعلينا والتسويات والإشعارات.',
        'Add contract' => 'إضافة عقد',
        'Allowed range: 10–200 records per administrative page.' => 'النطاق المسموح: من 10 إلى 200 سجل في كل صفحة إدارية.',
        'Create a customer or supplier contract' => 'إنشاء عقد لعميل أو مورد',
        'Create a scheduled contract payment' => 'إنشاء دفعة مجدولة للعقد',
        'Create or manage a customer record' => 'إنشاء سجل عميل أو إدارته',
        'Create or manage supplier records' => 'إنشاء سجلات الموردين أو إدارتها',
        'Current configuration' => 'الإعدادات الحالية',
        'Dashboard quick actions' => 'إجراءات لوحة التحكم السريعة',
        'Database Upgrade Recovery' => 'استعادة ترقية قاعدة البيانات',
        'Do not repeatedly retry this migration.' => 'لا تكرر محاولة تنفيذ هذا الترحيل.',
        'Follow the repository-controlled production rollback procedure. Do not bypass schema validation or manually mark the migration complete.' => 'اتبع إجراء الرجوع الإنتاجي المعتمد في المستودع. لا تتجاوز التحقق من مخطط قاعدة البيانات ولا تعلّم الترحيل كمكتمل يدويًا.',
        'General Settings' => 'الإعدادات العامة',
        'Manage organization identity and non-secret operational display preferences. Permissions, assignment scope and accounting rules remain server-side.' => 'أدر هوية المؤسسة وتفضيلات العرض التشغيلية غير السرية. تظل الصلاحيات ونطاق الإسناد والقواعد المحاسبية خاضعة للخادم.',
        'Open %s with the permissions assigned to your WordPress account.' => 'افتح %s وفق الصلاحيات المسندة إلى حساب WordPress الخاص بك.',
        'Operational summary' => 'الملخص التشغيلي',
        'Operator evidence' => 'أدلة المشغّل',
        'Organization & display' => 'المؤسسة والعرض',
        'Organization settings, email delivery, Firebase, mobile configuration, translations and diagnostics.' => 'إعدادات المؤسسة وتسليم البريد الإلكتروني وFirebase وإعدادات الموبايل والترجمات والتشخيصات.',
        'Presentation symbol only; it does not replace the stored currency code.' => 'رمز للعرض فقط ولا يستبدل رمز العملة المخزن.',
        'Protected recovery mode' => 'وضع الاستعادة المحمي',
        'Recorded migration state' => 'حالة الترحيل المسجلة',
        'Recovery runbook' => 'دليل إجراءات الاستعادة',
        'Rows per page' => 'عدد الصفوف في الصفحة',
        'Safe Contracts workspace' => 'مساحة عمل Safe Contracts',
        'SafeContracts stopped the migration before declaring it complete. Keep business operations paused until the recorded failure is reconciled.' => 'أوقف Safe Contracts الترحيل قبل اعتباره مكتملًا. أبقِ العمليات متوقفة حتى تتم معالجة حالة الفشل المسجلة.',
        'Save General Settings' => 'حفظ الإعدادات العامة',
        'Secrets, authorization, tenant/data scope and financial rules cannot be disabled from this page.' => 'لا يمكن تعطيل الأسرار أو التفويض أو نطاق المستأجر والبيانات أو القواعد المالية من هذه الصفحة.',
        'Settings & integrations' => 'الإعدادات والتكاملات',
        'Symbol' => 'الرمز',
        'System and organization preferences' => 'تفضيلات النظام والمؤسسة',
        'The business name displayed in SafeContracts-managed experiences.' => 'اسم المنشأة المعروض في الواجهات التي يديرها Safe Contracts.',
        'These values are used by SafeContracts administrative and mobile presentation without changing financial authority.' => 'تستخدم هذه القيم في عرض Safe Contracts الإداري والموبايل دون تغيير المرجعية المالية.',
        'Use an approved ISO currency code. Cross-currency values remain separated.' => 'استخدم رمز عملة ISO معتمدًا. تظل القيم بالعملات المختلفة منفصلة.',
        'Verify the pre-deployment backup, migration journal and exact plugin package before one controlled recovery attempt.' => 'تحقق من النسخة الاحتياطية قبل النشر وسجل الترحيل وحزمة الإضافة المطابقة قبل محاولة استعادة واحدة مضبوطة.',

        // WORKER-2 finance direction labels.
        'Payable' => 'مستحق الدفع',
        'Receivable' => 'مستحق التحصيل',

        // WORKER-3 notifications, access, Firebase and translations surfaces.
        '%d users · %d permissions' => '%d مستخدم · %d صلاحية',
        'Active devices' => 'الأجهزة النشطة',
        'Assign SafeContracts role' => 'إسناد دور Safe Contracts',
        'Assigned' => 'مسند',
        'Available templates' => 'القوالب المتاحة',
        'Choose only supported SafeContracts business permissions. Internal capability codes are intentionally hidden.' => 'اختر فقط صلاحيات الأعمال المدعومة في Safe Contracts. رموز الصلاحيات الداخلية مخفية عمدًا.',
        'Clear' => 'مسح',
        'Code or name' => 'الرمز أو الاسم',
        'Compare the WordPress user ID above with User ID in the SafeContracts mobile Profile. No FCM token or bearer credential is displayed here.' => 'قارن معرّف مستخدم WordPress أعلاه بمعرّف المستخدم في ملف Safe Contracts على الموبايل. لا يتم عرض رمز FCM أو بيانات اعتماد Bearer هنا.',
        'Configure public Firebase identifiers, encrypted server credentials, connectivity checks and real device diagnostics without exposing private keys or device tokens.' => 'اضبط معرّفات Firebase العامة وبيانات اعتماد الخادم المشفرة وفحوص الاتصال وتشخيصات الأجهزة الفعلية دون كشف المفاتيح الخاصة أو رموز الأجهزة.',
        'Configure real rule triggers, recipients, channels and templates. Existing recipient and suppression enforcement remains authoritative.' => 'اضبط مشغلات القواعد والمستلمين والقنوات والقوالب الفعلية. تظل قواعد فرض المستلمين والإيقاف الحالية هي المرجع.',
        'Delete this notification rule and all of its scheduled occurrences? Delivery history already sent by the transport is not erased.' => 'حذف قاعدة الإشعار هذه وكل مرات التنفيذ المجدولة لها؟ لن يتم مسح سجل التسليم الذي تم إرساله بالفعل.',
        'Disabled rules' => 'القواعد المعطلة',
        'Editable SafeContracts capabilities' => 'صلاحيات Safe Contracts القابلة للتعديل',
        'Editing a rule rebuilds its future schedule. Deactivating or deleting a rule clears scheduled occurrences for that rule. In-flight sends must finish before the change is accepted.' => 'يؤدي تعديل القاعدة إلى إعادة بناء جدولها المستقبلي. تعطيل القاعدة أو حذفها يزيل مرات التنفيذ المجدولة لها. يجب أن تكتمل عمليات الإرسال الجارية قبل قبول التغيير.',
        'Editing, activation and deletion use the existing schedule reconciliation path.' => 'تستخدم عمليات التعديل والتفعيل والحذف مسار تسوية الجدولة الحالي.',
        'Encrypted credential state and server-side connectivity controls.' => 'حالة بيانات الاعتماد المشفرة وضوابط الاتصال على الخادم.',
        'Manage SafeContracts role membership and business capabilities without weakening existing WordPress authorization boundaries.' => 'أدر عضوية أدوار Safe Contracts وصلاحيات الأعمال دون إضعاف حدود التفويض الحالية في WordPress.',
        'Maximum 64 KB. Uploaded plaintext exists only during this request and is converted immediately into encrypted credential storage.' => 'الحد الأقصى 64 كيلوبايت. النص المرفوع يوجد فقط أثناء هذا الطلب ويتم تحويله فورًا إلى تخزين مشفر لبيانات الاعتماد.',
        'Name or email' => 'الاسم أو البريد الإلكتروني',
        'No SafeContracts role' => 'لا يوجد دور Safe Contracts',
        'No encrypted service-account metadata is currently available.' => 'لا تتوفر حاليًا بيانات وصفية مشفرة لحساب الخدمة.',
        'No notification rules match the selected filters.' => 'لا توجد قواعد إشعار تطابق الفلاتر المحددة.',
        'No users match the selected filters.' => 'لا يوجد مستخدمون يطابقون الفلاتر المحددة.',
        'Not assigned' => 'غير مسند',
        'Notification rule activated and future schedule reconciled.' => 'تم تفعيل قاعدة الإشعار وتسوية جدولها المستقبلي.',
        'Notification rule change could not be applied. Review the entered values and try again.' => 'تعذر تطبيق تغيير قاعدة الإشعار. راجع القيم المدخلة وحاول مرة أخرى.',
        'Notification rule deactivated and future scheduled occurrences cleared.' => 'تم تعطيل قاعدة الإشعار ومسح مرات التنفيذ المجدولة مستقبلًا.',
        'Notification rule deleted and future scheduled occurrences cleared.' => 'تم حذف قاعدة الإشعار ومسح مرات التنفيذ المجدولة مستقبلًا.',
        'Notification rule saved and future schedule reconciled.' => 'تم حفظ قاعدة الإشعار وتسوية جدولها المستقبلي.',
        'Only SafeContracts role membership changes here. Other WordPress roles are preserved.' => 'يتغير هنا فقط إسناد دور Safe Contracts، بينما تظل أدوار WordPress الأخرى محفوظة.',
        'Public config complete' => 'الإعدادات العامة مكتملة',
        'Public config incomplete' => 'الإعدادات العامة غير مكتملة',
        'Public fields configured' => 'الحقول العامة المضبوطة',
        'SafeContracts never renders the Firebase private key, OAuth access token, bearer token or FCM device token on this page.' => 'لا يعرض Safe Contracts في هذه الصفحة مفتاح Firebase الخاص أو رمز وصول OAuth أو رمز Bearer أو رمز جهاز FCM.',
        'SafeContracts role' => 'دور Safe Contracts',
        'Search rules' => 'بحث في القواعد',
        'Search users' => 'بحث في المستخدمين',
        'Security boundary' => 'حدود الأمان',
        'Security, recipient scope and notification-engine validation remain unchanged.' => 'تظل قواعد الأمان ونطاق المستلمين والتحقق في محرك الإشعارات دون تغيير.',
        'Test Notification targets every active device registered for this exact WordPress user. Existing Firebase delivery semantics and token deactivation behavior remain unchanged.' => 'يستهدف اختبار الإشعار كل جهاز نشط مسجل لهذا المستخدم المحدد في WordPress. تظل دلالات تسليم Firebase الحالية وسلوك تعطيل الرموز دون تغيير.',
        'The service-account upload is validated and converted immediately to encrypted vault storage. Only non-secret metadata and a key fingerprint are shown afterward.' => 'يتم التحقق من ملف حساب الخدمة وتحويله فورًا إلى مخزن مشفر. بعد ذلك لا يظهر سوى البيانات الوصفية غير السرية وبصمة المفتاح.',
        'These are public project identifiers only.' => 'هذه معرّفات المشروع العامة فقط.',
        'This table reflects current WordPress users and their current SafeContracts role membership.' => 'يعكس هذا الجدول مستخدمي WordPress الحاليين وعضويتهم الحالية في أدوار Safe Contracts.',
        'Use only a server-side credential reference here. Never paste service-account JSON, private keys or OAuth tokens into this field.' => 'استخدم هنا مرجع بيانات اعتماد على الخادم فقط. لا تلصق JSON لحساب الخدمة أو مفاتيح خاصة أو رموز OAuth في هذا الحقل.',
        'User directory' => 'دليل المستخدمين',
        'Users with SafeContracts role' => 'المستخدمون الذين لديهم دور Safe Contracts',
        'of 3 required fields' => 'من أصل 3 حقول مطلوبة',
    ];

    public static function register(): void
    {
        add_filter('gettext_safecontracts', [self::class, 'filterGettext'], 23, 3);
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
