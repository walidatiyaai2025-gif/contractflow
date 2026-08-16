<?php

declare(strict_types=1);

namespace SafeContracts\Translations;

final class AdminArabicDefaults
{
    /** @var array<string,string> */
    private const MAP = [
        // Feedback and shared form behavior.
        'Saved' => 'تم الحفظ',
        'Your changes were saved successfully.' => 'تم حفظ البيانات بنجاح.',
        'Check the form' => 'راجع البيانات',
        'The record could not be saved. Check required fields and entered values, then try again.' => 'تعذر الحفظ. راجع الحقول المطلوبة وصحة القيم ثم حاول مرة أخرى.',
        'Safely deleted' => 'تم الحذف الآمن',
        'The item was removed from active operations while required historical, financial and audit evidence was preserved.' => 'تمت إزالة العنصر من العمليات النشطة مع الحفاظ على السجل التاريخي والمالي وسجل التدقيق عند الحاجة.',
        'Delete failed' => 'تعذر الحذف',
        'The item could not be deleted. It may be protected by linked records or the current permissions may not allow this operation.' => 'لم يتم الحذف. قد يكون العنصر محمياً بسجلات مرتبطة أو لا تسمح الصلاحيات الحالية بهذه العملية.',
        'Uploaded' => 'تم الرفع',
        'The file was uploaded successfully.' => 'تم رفع الملف بنجاح.',
        'Upload failed' => 'فشل الرفع',
        'The file could not be uploaded. Check the file and input, then try again.' => 'تعذر رفع الملف. راجع الملف والبيانات ثم حاول مرة أخرى.',
        'The operation completed successfully.' => 'تم تنفيذ العملية بنجاح.',
        'Complete required fields and correct invalid values before continuing.' => 'يرجى استكمال الحقول المطلوبة والتأكد من صحة القيم قبل المتابعة.',
        'First field to review:' => 'أول حقل يحتاج مراجعة:',
        'Delete this record from active SafeContracts operations? Required historical and financial evidence will be preserved.' => 'هل أنت متأكد من الحذف؟ سيتم إخراج السجل من العمليات النشطة مع الحفاظ على السجل التاريخي والمالي عند الحاجة.',

        // Dynamic mobile templates made editable from the WordPress dashboard.
        'Page {page} • {count} shown' => 'الصفحة {page} • معروض {count}',
        'Page {page}' => 'الصفحة {page}',
        'Payment #{id}' => 'دفعة #{id}',
        'Customer #{id}' => 'عميل #{id}',
        'Contract #{id}' => 'عقد #{id}',
        'Collection #{id} recorded.' => 'تم تسجيل التحصيل #{id}.',
        'Follow-up #{id} recorded.' => 'تم تسجيل المتابعة #{id}.',
        'Loading customer #{id}…' => 'جارٍ تحميل العميل #{id}…',

        // Follow-up administration.
        'You do not have permission to manage follow-up.' => 'ليست لديك صلاحية لإدارة المتابعة.',
        'You do not have permission to access follow-up.' => 'ليست لديك صلاحية للوصول إلى المتابعة.',
        'Operational receivables' => 'متابعة المستحقات',
        'Assigned follow-up queue' => 'قائمة المتابعة المسندة',
        'Follow-up state' => 'حالة المتابعة',
        'Contractual due date remains the receivable due authority. Promise/deferred dates are operational follow-up state only.' => 'يظل تاريخ الاستحقاق التعاقدي هو المرجع للاستحقاق. تواريخ الوعد أو التأجيل للمتابعة التشغيلية فقط.',
        'Follow-up action & history' => 'إجراء المتابعة والسجل',
        'Select a payment from the queue to review history or add an operational follow-up action.' => 'اختر دفعة من قائمة المتابعة لمراجعة السجل أو إضافة إجراء متابعة تشغيلية.',
        'Contact note' => 'ملاحظة تواصل',
        'Promise to pay' => 'وعد بالسداد',
        'Deferred' => 'مؤجل',
        'Needs escalation' => 'يحتاج تصعيد',
        'Promised date' => 'تاريخ السداد الموعود',
        'Deferred until' => 'مؤجل حتى',
        'Save follow-up' => 'حفظ المتابعة',
        'Append-only history' => 'سجل إضافي غير قابل للتعديل',
        'When' => 'الوقت',
        'State' => 'الحالة',
        'Promise / defer' => 'الوعد / التأجيل',

        // Notification operations.
        'You do not have permission to manage notifications.' => 'ليست لديك صلاحية لإدارة الإشعارات.',
        'Notification operations' => 'عمليات الإشعارات',
        'Rules' => 'القواعد',
        'Rule' => 'القاعدة',
        'Trigger' => 'المشغّل',
        'Recipients' => 'المستلمون',
        'Template' => 'القالب',
        ' + assigned accountant' => ' + المحاسب المسند',
        'Disabled' => 'معطل',
        'This operational screen intentionally exposes no Firebase credentials, service-account material or device-token values. Notification configuration is handled by dedicated settings tasks.' => 'هذه الشاشة التشغيلية لا تعرض بيانات اعتماد Firebase أو بيانات حساب الخدمة أو رموز الأجهزة. تتم إدارة إعدادات الإشعارات من صفحات الإعدادات المخصصة.',
        'Recent delivery log' => 'سجل التسليم الأخير',
        'User' => 'المستخدم',
        'Attempt' => 'المحاولة',
        'Result' => 'النتيجة',
        'Delivery state is read from the server-side log. Settled-payment suppression and retry rules remain enforced by the notification engine.' => 'تُقرأ حالة التسليم من سجل الخادم، وتظل قواعد منع إشعارات الدفعات المسددة وإعادة المحاولة مطبقة بواسطة محرك الإشعارات.',

        // Notification settings.
        'You do not have permission to manage notification settings.' => 'ليست لديك صلاحية لإدارة إعدادات الإشعارات.',
        'Notification configuration' => 'إعداد الإشعارات',
        'Edit rule' => 'تعديل قاعدة',
        'Add rule' => 'إضافة قاعدة',
        'Days before' => 'أيام قبل الاستحقاق',
        'Days after' => 'أيام بعد الاستحقاق',
        'Repeat interval days' => 'فاصل التكرار بالأيام',
        'Max repeats' => 'الحد الأقصى للتكرار',
        'Assigned Accountant' => 'المحاسب المسند',
        'Escalation roles' => 'أدوار التصعيد',
        'Save Notification Rule' => 'حفظ قاعدة الإشعار',
        'Add Notification Rule' => 'إضافة قاعدة إشعار',
        'Settled-payment suppression, contractual due-date matching and recipient scope remain enforced by the notification engine.' => 'يظل محرك الإشعارات مسؤولاً عن منع إشعارات الدفعات المسددة، ومطابقة تاريخ الاستحقاق التعاقدي، وتطبيق نطاق المستلمين.',
        'System Administrator' => 'مسؤول النظام',
        'Manager' => 'مدير',
        'Accountant' => 'محاسب',
        'Viewer' => 'مشاهد',

        // Reports.
        'You do not have permission to export reports.' => 'ليست لديك صلاحية لتصدير التقارير.',
        'You do not have permission to view reports.' => 'ليست لديك صلاحية لعرض التقارير.',
        'Server-side reporting' => 'تقارير الخادم',
        'Run report' => 'تشغيل التقرير',
        'Export current filters to Excel' => 'تصدير الفلاتر الحالية إلى Excel',
        'XLSX is generated server-side from your authorized report scope.' => 'يتم إنشاء ملف XLSX على الخادم من نطاق التقرير المصرح لك به.',
        'Scheduled receivables' => 'المستحقات المجدولة',
        'Remaining receivables' => 'المستحقات المتبقية',
        'Collection transactions' => 'معاملات التحصيل',
        'Follow-up events' => 'أحداث المتابعة',
        'Payments followed up' => 'الدفعات التي تمت متابعتها',
        'Scoped report boundary' => 'نطاق التقرير المصرح',
        'All totals and XLSX sheets are computed server-side using the same authorized customer, contract, accountant, payment-status and contractual due-date filters. Export completion is written through the SafeContracts audit hook.' => 'يتم حساب جميع الإجماليات وأوراق XLSX على الخادم باستخدام نفس فلاتر العميل والعقد والمحاسب وحالة الدفعة وتاريخ الاستحقاق التعاقدي المصرح بها. ويتم تسجيل اكتمال التصدير في سجل تدقيق SafeContracts.',

        // Users and roles.
        'You do not have permission to view SafeContracts users and roles.' => 'ليست لديك صلاحية لعرض مستخدمي وأدوار SafeContracts.',
        'Authorization directory' => 'دليل الصلاحيات',
        'This screen is read-only in SC-P6-013. It shows effective WordPress role grants and user membership without exposing passwords, credentials or authentication secrets.' => 'هذه الشاشة للقراءة فقط. تعرض صلاحيات أدوار WordPress الفعلية وعضوية المستخدمين من دون عرض كلمات المرور أو بيانات الاعتماد أو أسرار المصادقة.',
        '%d users' => '%d مستخدمين',
        'Effective SafeContracts capabilities' => 'صلاحيات SafeContracts الفعلية',
        'Members' => 'الأعضاء',

        // Imports.
        'Import' => 'استيراد',
        'Controlled data onboarding' => 'إدخال بيانات منضبط',
        'Excel Import' => 'استيراد Excel',
        'Upload workbook' => 'رفع ملف Excel',
        'Only .xlsx files up to 20 MiB are accepted. Macros, external links, workbook connections and formula cells are rejected; uploads are staged in private server storage.' => 'يتم قبول ملفات .xlsx فقط حتى 20 MiB. يتم رفض وحدات الماكرو والروابط الخارجية واتصالات المصنف وخلايا الصيغ، وتُحفظ الملفات المرفوعة مؤقتاً في مساحة خاصة على الخادم.',
        'Upload & inspect workbook' => 'رفع وفحص الملف',
        'Recent import runs' => 'عمليات الاستيراد الأخيرة',
        'Run' => 'العملية',
        'Workbook' => 'ملف Excel',
        'Imported / skipped / errors' => 'مستورد / متجاوز / أخطاء',
        'Created' => 'أُنشئ',
        'Run #%d — summary & audit evidence' => 'العملية #%d — الملخص وأدلة التدقيق',
        'Actor' => 'المنفذ',
        'Worksheet' => 'ورقة العمل',
        'Duplicate strategy' => 'استراتيجية التكرار',
        'Sheets / mapped fields' => 'الأوراق / الحقول المربوطة',
        'Rows total / valid' => 'إجمالي الصفوف / الصالحة',
        'Imported / skipped / error rows' => 'مستورد / متجاوز / صفوف أخطاء',
        'Error entries' => 'إدخالات الأخطاء',
        'Created / updated' => 'الإنشاء / التحديث',
        'Summary intentionally excludes private storage keys, workbook hashes and raw workbook content. Lifecycle stages are recorded in the SafeContracts audit trail.' => 'يستبعد الملخص عمداً مفاتيح التخزين الخاصة وبصمات الملفات ومحتوى ملف Excel الخام. ويتم تسجيل مراحل دورة الاستيراد في سجل تدقيق SafeContracts.',
        'Run #%d — column mapping' => 'العملية #%d — ربط الأعمدة',
        'Ignore / not mapped' => 'تجاهل / غير مربوط',
        'Save mapping' => 'حفظ الربط',
        'This import run is terminal and its mapping is read-only. Create a new run to import the workbook again.' => 'عملية الاستيراد هذه نهائية وربط الأعمدة فيها للقراءة فقط. أنشئ عملية جديدة لاستيراد الملف مرة أخرى.',
        'Import preview' => 'معاينة الاستيراد',
        'No data rows found after the header.' => 'لا توجد صفوف بيانات بعد صف العناوين.',
        'Row' => 'الصف',
        'Preview is read-only. All rows are validated before any business mutation.' => 'المعاينة للقراءة فقط. يتم التحقق من كل الصفوف قبل أي تعديل على بيانات الأعمال.',
        'Validate & execute' => 'تحقق ونفّذ',
        'Fail duplicate row' => 'إيقاف عند صف مكرر',
        'Skip duplicate row' => 'تجاوز الصف المكرر',
        'Update safe fields only' => 'تحديث الحقول الآمنة فقط',
        'Execution re-reads the private workbook and mapping server-side. Validation errors prevent all business writes. Successful rows run inside database transactions.' => 'يعيد التنفيذ قراءة الملف الخاص وربط الأعمدة على الخادم. أخطاء التحقق تمنع جميع عمليات الكتابة، وتُنفذ الصفوف الناجحة داخل معاملات قاعدة البيانات.',
        'Validate & execute import' => 'تحقق ونفّذ الاستيراد',
        'Row errors' => 'أخطاء الصفوف',
        'Field' => 'الحقل',
        'Message' => 'الرسالة',

        // Mobile configuration.
        'You do not have permission to manage mobile configuration.' => 'ليست لديك صلاحية لإدارة إعدادات الموبايل.',
        'Mobile bootstrap' => 'تهيئة الموبايل',
        'Support / footer text' => 'نص الدعم / التذييل',
        'Default mobile page size' => 'حجم صفحة الموبايل الافتراضي',
        'Feature availability' => 'إتاحة الخصائص',
        'Push notifications' => 'إشعارات Push',
        'Collection entry' => 'إدخال التحصيل',
        'Save Mobile Configuration' => 'حفظ إعدادات الموبايل',
        'This stores only non-secret bootstrap values in WordPress. P8 will expose the authorized mobile configuration endpoint; this task does not add REST endpoints early.' => 'يتم هنا حفظ قيم تهيئة غير سرية فقط في WordPress. يظل الوصول إلى إعدادات الموبايل محمياً بالصلاحيات من خلال واجهة SafeContracts البرمجية.',

        // Firebase settings and diagnostics.
        'You do not have permission to manage Firebase settings.' => 'ليست لديك صلاحية لإدارة إعدادات Firebase.',
        'Push infrastructure' => 'بنية الإشعارات',
        'Ready' => 'جاهز',
        'Incomplete' => 'غير مكتمل',
        'Firebase project' => 'مشروع Firebase',
        'Firebase project ID' => 'معرف مشروع Firebase',
        'Messaging sender ID' => 'معرف مرسل الرسائل',
        'Firebase app ID' => 'معرف تطبيق Firebase',
        'Advanced external credential provider' => 'مزود بيانات اعتماد خارجي متقدم',
        'Credential reference' => 'مرجع بيانات الاعتماد',
        'Use this only when credentials are supplied by custom server code. Never paste service-account JSON, private keys or OAuth tokens here.' => 'استخدم هذا فقط عندما يتم توفير بيانات الاعتماد بواسطة كود خادم مخصص. لا تلصق JSON حساب الخدمة أو المفاتيح الخاصة أو رموز OAuth هنا.',
        'Save Firebase Settings' => 'حفظ إعدادات Firebase',
        'Server Service Account' => 'حساب خدمة الخادم',
        'Upload the Firebase service-account JSON. SafeContracts validates it, encrypts it with authenticated encryption derived from this WordPress installation security salts, and never stores the private key as plaintext.' => 'ارفع ملف JSON لحساب خدمة Firebase. يتحقق SafeContracts منه ويشفّره بتشفير موثّق مشتق من مفاتيح أمان تثبيت WordPress، ولا يخزن المفتاح الخاص كنص صريح.',
        'Connected credential' => 'بيانات اعتماد متصلة',
        'Project' => 'المشروع',
        'Service account' => 'حساب الخدمة',
        'Key fingerprint' => 'بصمة المفتاح',
        'Stored at' => 'وقت التخزين',
        'Service Account JSON' => 'JSON حساب الخدمة',
        'Replace Service Account JSON' => 'استبدال JSON حساب الخدمة',
        'Maximum 64 KB. The uploaded plaintext exists only during this request and is converted immediately into encrypted credential storage.' => 'الحد الأقصى 64 KB. يوجد النص الصريح المرفوع أثناء هذا الطلب فقط ويتم تحويله فوراً إلى تخزين مشفر لبيانات الاعتماد.',
        'Upload Service Account' => 'رفع حساب الخدمة',
        'Replace Service Account' => 'استبدال حساب الخدمة',
        'Mobile device registration' => 'تسجيل أجهزة الموبايل',
        'Current WordPress user ID' => 'رقم مستخدم WordPress الحالي',
        'Active devices for this user' => 'الأجهزة النشطة لهذا المستخدم',
        'Active devices in SafeContracts' => 'الأجهزة النشطة في SafeContracts',
        ' (500-user diagnostic limit reached)' => ' (تم بلوغ حد التشخيص لـ500 مستخدم)',
        'Users with active devices' => 'المستخدمون الذين لديهم أجهزة نشطة',
        'Compare the current WordPress user ID above with User ID in the SafeContracts mobile Profile. No FCM token or bearer credential is displayed here.' => 'قارن رقم مستخدم WordPress الحالي أعلاه مع رقم المستخدم في ملف SafeContracts على الموبايل. لا يتم عرض رمز FCM أو بيانات اعتماد Bearer هنا.',
        'Test Firebase Connection' => 'اختبار اتصال Firebase',
        'Send Test Notification' => 'إرسال إشعار تجريبي',
        'Delete the stored Firebase service account?' => 'حذف حساب خدمة Firebase المخزن؟',
        'Delete Credential' => 'حذف بيانات الاعتماد',
        'Test Notification targets every active device registered for this exact WordPress user. If two devices are active, both receive the test. Mobile Profile shows registration and diagnostic state when registration is incomplete.' => 'يستهدف الإشعار التجريبي كل جهاز نشط مسجل لمستخدم WordPress الحالي. إذا كان هناك جهازان نشطان فسيصل الاختبار إليهما. يعرض ملف الموبايل حالة التسجيل والتشخيص عندما يكون التسجيل غير مكتمل.',
        'Firebase settings saved.' => 'تم حفظ إعدادات Firebase.',
        'Firebase service account encrypted and saved successfully.' => 'تم تشفير حساب خدمة Firebase وحفظه بنجاح.',
        'Firebase service account deleted.' => 'تم حذف حساب خدمة Firebase.',
        'Firebase OAuth and FCM HTTP v1 authorization test succeeded.' => 'نجح اختبار تفويض Firebase OAuth وFCM HTTP v1.',
        'Test notification sent to all active SafeContracts devices registered to this WordPress user.' => 'تم إرسال الإشعار التجريبي إلى جميع أجهزة SafeContracts النشطة المسجلة لمستخدم WordPress الحالي.',
        'Test notification reached some, but not all, active devices for this WordPress user. SafeContracts continued sending to the remaining devices and deactivated any device token Firebase reported as unregistered.' => 'وصل الإشعار التجريبي إلى بعض الأجهزة النشطة وليس كلها. واصل SafeContracts الإرسال إلى بقية الأجهزة وعطّل أي رمز جهاز أبلغ Firebase أنه غير مسجل.',
        'No active SafeContracts mobile device is registered yet. Open Mobile Profile and use Retry device registration.' => 'لا يوجد جهاز موبايل نشط مسجل في SafeContracts حتى الآن. افتح ملف الموبايل واستخدم إعادة محاولة تسجيل الجهاز.',
        'Active SafeContracts devices exist, but none belong to this WordPress user. Compare the WordPress user ID on this page with User ID in the mobile Profile.' => 'توجد أجهزة SafeContracts نشطة، لكن لا ينتمي أي منها لمستخدم WordPress الحالي. قارن رقم المستخدم في هذه الصفحة برقم المستخدم في ملف الموبايل.',
        'Active device records belong to this WordPress user, but none currently contains a usable FCM token. Open Mobile Profile and use Retry device registration.' => 'توجد سجلات أجهزة لهذا المستخدم، لكن لا يحتوي أي منها حالياً على رمز FCM صالح للاستخدام. افتح ملف الموبايل وأعد محاولة تسجيل الجهاز.',
        'Firebase reported the registered device token as unregistered or not found. SafeContracts deactivated the rejected device registration; retry device registration from Mobile Profile.' => 'أبلغ Firebase أن رمز الجهاز المسجل غير مسجل أو غير موجود. عطّل SafeContracts تسجيل الجهاز المرفوض؛ أعد تسجيل الجهاز من ملف الموبايل.',
        'Firebase rejected the device because its FCM token belongs to a different Firebase sender or project. Verify the mobile Firebase configuration matches this project.' => 'رفض Firebase الجهاز لأن رمز FCM الخاص به ينتمي إلى مرسل أو مشروع Firebase مختلف. تحقق من تطابق إعداد Firebase في الموبايل مع هذا المشروع.',
        'Firebase rejected the test notification as invalid. Verify the Firebase project, app registration and device token configuration.' => 'رفض Firebase الإشعار التجريبي باعتباره غير صالح. تحقق من مشروع Firebase وتسجيل التطبيق وإعداد رمز الجهاز.',
        'Firebase denied permission to send the notification. Verify the service account belongs to this project and has Firebase Cloud Messaging send permission.' => 'رفض Firebase صلاحية إرسال الإشعار. تحقق من أن حساب الخدمة ينتمي لهذا المشروع ولديه صلاحية إرسال Firebase Cloud Messaging.',
        'Firebase authentication failed. Verify the stored service account and project configuration.' => 'فشلت مصادقة Firebase. تحقق من حساب الخدمة المخزن وإعداد المشروع.',
        'Firebase rejected the test because the messaging quota is exhausted. Review the Firebase project quota before retrying.' => 'رفض Firebase الاختبار بسبب نفاد حصة الرسائل. راجع حصة مشروع Firebase قبل إعادة المحاولة.',
        'Firebase Cloud Messaging is temporarily unavailable. Retry the test after the service recovers.' => 'خدمة Firebase Cloud Messaging غير متاحة مؤقتاً. أعد الاختبار بعد عودة الخدمة.',
        'Firebase settings are invalid.' => 'إعدادات Firebase غير صالحة.',
        'The Firebase service-account JSON is invalid or does not match this Firebase project.' => 'ملف JSON لحساب خدمة Firebase غير صالح أو لا يطابق مشروع Firebase الحالي.',
        'Firebase service-account deletion failed.' => 'فشل حذف حساب خدمة Firebase.',
        'Firebase connection test failed. Verify the service account and its FCM permissions.' => 'فشل اختبار اتصال Firebase. تحقق من حساب الخدمة وصلاحيات FCM.',
        'Firebase test notification failed for every usable device registered to this WordPress user. Review the Firebase project, credential and device registration diagnostics.' => 'فشل الإشعار التجريبي على كل جهاز صالح مسجل لمستخدم WordPress الحالي. راجع تشخيص مشروع Firebase وبيانات الاعتماد وتسجيل الأجهزة.',
    ];

    public static function register(): void
    {
        add_filter('gettext_safecontracts', [self::class, 'filterGettext'], 20, 3);
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
