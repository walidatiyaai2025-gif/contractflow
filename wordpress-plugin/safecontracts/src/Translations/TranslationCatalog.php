<?php

declare(strict_types=1);

namespace SafeContracts\Translations;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class TranslationCatalog
{
    public const OPTION = 'safecontracts_translation_overrides';
    public const LANGUAGES = ['en', 'ar'];
    public const MAX_SOURCE_LENGTH = 5000;
    public const MAX_TRANSLATION_LENGTH = 10000;

    /**
     * Built-in Arabic defaults shared by the WordPress UI and the bundled
     * Flutter wording. English defaults are always the source phrase itself.
     * Dashboard overrides are stored separately and always win.
     *
     * @var array<string,string>
     */
    private const ARABIC_DEFAULTS = [
        'SafeContracts' => 'SafeContracts',
        'Dashboard' => 'لوحة التحكم',
        'Customers' => 'العملاء',
        'Contracts' => 'العقود',
        'Payments' => 'الدفعات',
        'Collections' => 'التحصيلات',
        'Follow-up' => 'المتابعة',
        'Follow Ups' => 'المتابعات',
        'Notifications' => 'الإشعارات',
        'Reports' => 'التقارير',
        'Users & Roles' => 'المستخدمون والصلاحيات',
        'Imports' => 'الاستيراد',
        'Settings' => 'الإعدادات',
        'Translations' => 'الترجمات',
        'Payment Methods' => 'طرق السداد',
        'Notification Settings' => 'إعدادات الإشعارات',
        'Firebase Settings' => 'إعدادات Firebase',
        'Mobile Configuration' => 'إعدادات الموبايل',
        'Excel export' => 'تصدير Excel',
        'Profile' => 'الملف الشخصي',
        'Environment' => 'البيئة',
        'Retry session' => 'إعادة محاولة الجلسة',
        'SafeContracts mobile is unavailable.' => 'تطبيق SafeContracts غير متاح حالياً.',
        'Remote mobile configuration is unavailable. Safe defaults are active.' => 'تعذر تحميل إعدادات الموبايل من الخادم. يتم استخدام الإعدادات الآمنة الافتراضية.',
        'Sign in with your WordPress username and password' => 'سجّل الدخول باسم مستخدم وكلمة مرور WordPress',
        'Username' => 'اسم المستخدم',
        'Password' => 'كلمة المرور',
        'Enter your username.' => 'أدخل اسم المستخدم.',
        'Enter your password.' => 'أدخل كلمة المرور.',
        'Signing in…' => 'جارٍ تسجيل الدخول…',
        'Sign in' => 'تسجيل الدخول',
        'Remember me' => 'تذكرني',
        'Keep me signed in on this device. Your password is never stored.' => 'احتفظ بتسجيل الدخول على هذا الجهاز. لا يتم حفظ كلمة المرور.',
        'Loading' => 'جارٍ التحميل',
        'Retry' => 'إعادة المحاولة',
        'Refresh' => 'تحديث',
        'Close' => 'إغلاق',
        'Close message' => 'إغلاق الرسالة',
        'Cancel' => 'إلغاء',
        'Save' => 'حفظ',
        'Delete' => 'حذف',
        'Open' => 'فتح',
        'Actions' => 'الإجراءات',
        'Yes' => 'نعم',
        'No' => 'لا',
        'New' => 'جديد',
        'Read' => 'مقروء',
        'Previous' => 'السابق',
        'Next' => 'التالي',
        'Previous page' => 'الصفحة السابقة',
        'Next page' => 'الصفحة التالية',
        'Dashboard filters' => 'فلاتر لوحة التحكم',
        'Dashboard is not loaded yet.' => 'لم يتم تحميل لوحة التحكم بعد.',
        'Unable to load dashboard.' => 'تعذر تحميل لوحة التحكم.',
        'Dashboard refresh failed.' => 'فشل تحديث لوحة التحكم.',
        'Scheduled' => 'المجدول',
        'Remaining' => 'المتبقي',
        'Overdue' => 'متأخر',
        'Overdue exposure' => 'قيمة المتأخرات',
        'Collected' => 'المحصّل',
        'Customer' => 'العميل',
        'Contract' => 'العقد',
        'Status' => 'الحالة',
        'All customers' => 'كل العملاء',
        'All contracts' => 'كل العقود',
        'All statuses' => 'كل الحالات',
        'Any status' => 'أي حالة',
        'Due from' => 'استحقاق من',
        'Due to' => 'استحقاق إلى',
        'Apply filters' => 'تطبيق الفلاتر',
        'Currency' => 'العملة',
        'Currency code' => 'رمز العملة',
        'Currency symbol' => 'علامة العملة',
        'No records match the current filters.' => 'لا توجد سجلات تطابق الفلاتر الحالية.',
        'Draft' => 'مسودة',
        'Active' => 'نشط',
        'Active contracts' => 'العقود النشطة',
        'Completed' => 'مكتمل',
        'Cancelled' => 'ملغي',
        'Upcoming' => 'قادم',
        'Due soon' => 'استحقاق قريب',
        'Due' => 'مستحق',
        'Partially paid' => 'مدفوع جزئياً',
        'Paid' => 'مدفوع',
        'Inactive' => 'غير نشط',
        'Archived' => 'مؤرشف',
        'None' => 'لا يوجد',
        'Note' => 'ملاحظة',
        'Promise' => 'وعد بالسداد',
        'Issue' => 'مشكلة',
        'Defer' => 'تأجيل',
        'Escalate' => 'تصعيد',
        'Refresh customers' => 'تحديث العملاء',
        'Customers are not loaded yet.' => 'لم يتم تحميل العملاء بعد.',
        'Unable to load customers.' => 'تعذر تحميل العملاء.',
        'Customer refresh failed.' => 'فشل تحديث العملاء.',
        'No customers are available in your scope.' => 'لا يوجد عملاء ضمن صلاحياتك.',
        'Select a customer to view authorized details.' => 'اختر عميلاً لعرض التفاصيل المسموح بها.',
        'Unable to load customer.' => 'تعذر تحميل العميل.',
        'Customer ID' => 'رقم العميل',
        'Internal code' => 'الكود الداخلي',
        'Internal code (optional)' => 'الكود الداخلي (اختياري)',
        'Contact name' => 'اسم جهة الاتصال',
        'Contact' => 'جهة الاتصال',
        'Email' => 'البريد الإلكتروني',
        'Phone' => 'الهاتف',
        'Name' => 'الاسم',
        'Code' => 'الكود',
        'Notes' => 'ملاحظات',
        'Only server-authorized customer fields are shown.' => 'يتم عرض بيانات العميل المصرح بها من الخادم فقط.',
        'Sort' => 'الترتيب',
        'Newest' => 'الأحدث',
        'Contract number' => 'رقم العقد',
        'Start date' => 'تاريخ البداية',
        'End date' => 'تاريخ النهاية',
        'Refresh contracts' => 'تحديث العقود',
        'Unable to load contracts.' => 'تعذر تحميل العقود.',
        'Contracts are not loaded yet.' => 'لم يتم تحميل العقود بعد.',
        'No contracts match the current filters.' => 'لا توجد عقود تطابق الفلاتر الحالية.',
        'Contract refresh failed.' => 'فشل تحديث العقود.',
        'Start' => 'البداية',
        'End' => 'النهاية',
        'Value' => 'القيمة',
        'Contract details' => 'تفاصيل العقد',
        'Edit contract' => 'تعديل العقد',
        'Create contract' => 'إنشاء عقد',
        'Save contract' => 'حفظ العقد',
        'Contract not found' => 'العقد غير موجود',
        'This contract was not found in your authorized scope.' => 'العقد غير موجود ضمن نطاق صلاحياتك.',
        'Contract access denied' => 'غير مسموح بالوصول للعقد',
        'You do not have permission to view this contract.' => 'ليست لديك صلاحية لعرض هذا العقد.',
        'Unable to load contract' => 'تعذر تحميل العقد',
        'SafeContracts could not load this contract.' => 'تعذر على SafeContracts تحميل هذا العقد.',
        'Contract ID' => 'رقم العقد',
        'Customer & assignment' => 'العميل والتكليف',
        'Assigned accountant user ID' => 'رقم مستخدم المحاسب المكلّف',
        'Accountant user ID' => 'رقم مستخدم المحاسب',
        'Accountant ID' => 'رقم المحاسب',
        'Financial values' => 'القيم المالية',
        'Base value' => 'القيمة الأساسية',
        'Net value:' => 'القيمة الصافية:',
        'Status and financial values are displayed exactly as returned by the SafeContracts server. The mobile app does not recalculate them.' => 'يتم عرض الحالة والقيم المالية كما يعيدها خادم SafeContracts دون إعادة حساب داخل تطبيق الموبايل.',
        'This contract is read-only for the current session.' => 'هذا العقد للقراءة فقط في الجلسة الحالية.',
        'Contract details are unavailable.' => 'تفاصيل العقد غير متاحة.',
        'Archived contracts are read-only.' => 'العقود المؤرشفة للقراءة فقط.',
        'Update start/end dates' => 'تحديث تاريخ البداية والنهاية',
        'Start date YYYY-MM-DD' => 'تاريخ البداية YYYY-MM-DD',
        'End date YYYY-MM-DD' => 'تاريخ النهاية YYYY-MM-DD',
        'Save supported fields' => 'حفظ الحقول المدعومة',
        'Status, assignment and financial values are not editable here. Server scope, validation and audit remain authoritative.' => 'الحالة والتكليف والقيم المالية غير قابلة للتعديل هنا. تظل الصلاحيات والتحقق وسجل التدقيق على الخادم هي المرجع.',
        'Contract editing is not authorized for this session.' => 'تعديل العقود غير مسموح به في هذه الجلسة.',
        'Contract number must contain 1 to 100 characters.' => 'يجب أن يحتوي رقم العقد على 1 إلى 100 حرف.',
        'Contract dates must use YYYY-MM-DD or be blank.' => 'يجب أن تكون تواريخ العقد بصيغة YYYY-MM-DD أو فارغة.',
        'Contract end date cannot precede start date.' => 'لا يمكن أن يسبق تاريخ نهاية العقد تاريخ البداية.',
        'Expected payment date' => 'تاريخ الدفع المتوقع',
        'YYYY-MM-DD (blank clears)' => 'YYYY-MM-DD (اتركه فارغاً للمسح)',
        'Expected payment date updated.' => 'تم تحديث تاريخ الدفع المتوقع.',
        'Validation' => 'تحقق البيانات',
        'Forbidden' => 'غير مسموح',
        'Conflict' => 'تعارض',
        'Error' => 'خطأ',
        'No payments match the authorized filters.' => 'لا توجد دفعات تطابق الفلاتر المسموح بها.',
        'Payment access denied' => 'غير مسموح بالوصول للدفعة',
        'Payment not found' => 'الدفعة غير موجودة',
        'Unable to load payment' => 'تعذر تحميل الدفعة',
        'Payment details' => 'تفاصيل الدفعة',
        'SafeContracts request failed.' => 'فشل طلب SafeContracts.',
        'Payment not found.' => 'الدفعة غير موجودة.',
        'Payment' => 'الدفعة',
        'Due date' => 'تاريخ الاستحقاق',
        'Contractual due date' => 'تاريخ الاستحقاق التعاقدي',
        'Original' => 'الأصلي',
        'Original amount' => 'المبلغ الأصلي',
        'Paid amount' => 'المبلغ المدفوع',
        'Remaining amount' => 'المبلغ المتبقي',
        'Contract archived' => 'العقد مؤرشف',
        'Dates, balances and status are server-authoritative. Mobile does not recalculate receivables.' => 'التواريخ والأرصدة والحالة معتمدة من الخادم. تطبيق الموبايل لا يعيد حساب المستحقات.',
        'Edit expected payment date' => 'تعديل تاريخ الدفع المتوقع',
        'Record collection' => 'تسجيل تحصيل',
        'Unable to load payments' => 'تعذر تحميل الدفعات',
        'Schedule payment' => 'جدولة دفعة',
        'Save payment dates' => 'حفظ تواريخ الدفعة',
        'Sequence' => 'التسلسل',
        'Reference' => 'المرجع',
        'Reference (optional)' => 'المرجع (اختياري)',
        'Amount' => 'المبلغ',
        'Collection date' => 'تاريخ التحصيل',
        'Collection date YYYY-MM-DD' => 'تاريخ التحصيل YYYY-MM-DD',
        'Payment method' => 'طريقة السداد',
        'Method' => 'الطريقة',
        'Proof media ID (optional)' => 'رقم مرفق الإثبات (اختياري)',
        'Retry methods' => 'إعادة تحميل طرق السداد',
        'The server validates scope, payment balance, settlement status and audit history. Mobile performs input-shape checks only.' => 'يتحقق الخادم من الصلاحيات ورصيد الدفعة وحالة التسوية وسجل التدقيق. تطبيق الموبايل يتحقق من شكل الإدخال فقط.',
        'No active payment methods are available.' => 'لا توجد طرق سداد نشطة متاحة.',
        'Enter a positive amount with up to 4 decimal places.' => 'أدخل مبلغاً موجباً بحد أقصى 4 منازل عشرية.',
        'Collection date must be valid YYYY-MM-DD.' => 'يجب أن يكون تاريخ التحصيل صحيحاً بصيغة YYYY-MM-DD.',
        'Choose an active payment method.' => 'اختر طريقة سداد نشطة.',
        'Reference cannot exceed 191 characters.' => 'لا يمكن أن يتجاوز المرجع 191 حرفاً.',
        'Proof media ID must be a positive integer.' => 'يجب أن يكون رقم مرفق الإثبات عدداً صحيحاً موجباً.',
        'No follow-up items match the authorized filters.' => 'لا توجد عناصر متابعة تطابق الفلاتر المسموح بها.',
        'Add follow-up' => 'إضافة متابعة',
        'No follow-up history yet.' => 'لا يوجد سجل متابعة حتى الآن.',
        'Promised:' => 'موعد السداد الموعود:',
        'Deferred until:' => 'مؤجل حتى:',
        'Operational follow-up' => 'متابعة تشغيلية',
        'Action' => 'الإجراء',
        'Note (optional)' => 'ملاحظة (اختياري)',
        'Promised date YYYY-MM-DD' => 'تاريخ السداد الموعود YYYY-MM-DD',
        'Deferred until YYYY-MM-DD' => 'مؤجل حتى YYYY-MM-DD',
        'Record' => 'تسجيل',
        'A note is required for this follow-up action.' => 'الملاحظة مطلوبة لهذا الإجراء.',
        'A valid YYYY-MM-DD date is required.' => 'مطلوب تاريخ صحيح بصيغة YYYY-MM-DD.',
        'Follow-up note cannot exceed 5000 characters.' => 'لا يمكن أن تتجاوز ملاحظة المتابعة 5000 حرف.',
        'Loading notifications…' => 'جارٍ تحميل الإشعارات…',
        'Notifications are unavailable.' => 'الإشعارات غير متاحة.',
        'No notifications are available for this account.' => 'لا توجد إشعارات متاحة لهذا الحساب.',
        'The workbook is generated by SafeContracts on the server using your current authorized dashboard filters.' => 'يتم إنشاء ملف Excel على خادم SafeContracts باستخدام فلاتر لوحة التحكم الحالية المسموح بها.',
        'Current filters' => 'الفلاتر الحالية',
        'Any date' => 'أي تاريخ',
        'Generating Excel…' => 'جارٍ إنشاء ملف Excel…',
        'Download Excel' => 'تنزيل Excel',
        'Excel export is not authorized for this session.' => 'تصدير Excel غير مسموح به في هذه الجلسة.',
        'Excel export ready' => 'ملف Excel جاهز',
        'Saved in app cache:' => 'تم الحفظ في ذاكرة التطبيق المؤقتة:',
        'Rows exported' => 'الصفوف المصدرة',
        'Clear result' => 'مسح النتيجة',
        'Language' => 'اللغة',
        'Arabic' => 'العربية',
        'English' => 'English',
        'Not configured' => 'غير مهيأ',
        'Session' => 'الجلسة',
        'User ID' => 'رقم المستخدم',
        'Data scope' => 'نطاق البيانات',
        'Default page size' => 'حجم الصفحة الافتراضي',
        'Support' => 'الدعم',
        'Push registration' => 'تسجيل الإشعارات',
        'Granted capabilities' => 'الصلاحيات الممنوحة',
        'Registered devices' => 'الأجهزة المسجلة',
        'Clear local session state' => 'مسح حالة الجلسة المحلية',
        'Push notifications are disabled by mobile configuration.' => 'إشعارات Push معطلة من إعدادات الموبايل.',
        'Enable Push notifications in SafeContracts → Mobile Configuration.' => 'فعّل إشعارات Push من SafeContracts ← إعدادات الموبايل.',
        'Device registered with SafeContracts' => 'الجهاز مسجل في SafeContracts',
        'Device registration is not complete' => 'تسجيل الجهاز غير مكتمل',
        'Notification permission' => 'إذن الإشعارات',
        'FCM token acquired' => 'تم الحصول على رمز FCM',
        'Backend registration' => 'التسجيل على الخادم',
        'Diagnostic code' => 'كود التشخيص',
        'Android notification permission is denied. The device can still register, but notification display remains blocked until permission is enabled.' => 'إذن إشعارات Android مرفوض. يمكن تسجيل الجهاز، لكن عرض الإشعارات سيظل متوقفاً حتى يتم السماح بالإذن.',
        'Retry device registration' => 'إعادة محاولة تسجيل الجهاز',
        'Loading device state…' => 'جارٍ تحميل حالة الجهاز…',
        'Device state is unavailable.' => 'حالة الجهاز غير متاحة.',
        'No registered devices are currently visible.' => 'لا توجد أجهزة مسجلة ظاهرة حالياً.',
        'No last-seen timestamp' => 'لا يوجد وقت لآخر ظهور',
        'Last seen' => 'آخر ظهور',
        'Allowed' => 'مسموح',
        'Provisional' => 'مؤقت',
        'Denied' => 'مرفوض',
        'Unknown' => 'غير معروف',
        'Not started' => 'لم يبدأ',
        'Registering…' => 'جارٍ التسجيل…',
        'Registered' => 'مسجل',
        'Failed' => 'فشل',
        'Contract Operations' => 'عمليات العقود',
        'Secure contract, receivable, collection, follow-up and notification operations from one workspace.' => 'إدارة آمنة للعقود والمستحقات والتحصيلات والمتابعات والإشعارات من مساحة عمل واحدة.',
        'Operational overview' => 'نظرة تشغيلية',
        'Server-side authorization' => 'صلاحيات الخادم',
        'No data scope assigned. Your account can access SafeContracts, but it does not currently have permission to view all data or assigned contract data. Contact a SafeContracts administrator if operational access is required.' => 'لم يتم تعيين نطاق بيانات لحسابك. يمكنك الدخول إلى SafeContracts لكن ليست لديك حالياً صلاحية عرض كل البيانات أو بيانات العقود المسندة. تواصل مع مسؤول SafeContracts إذا كنت تحتاج وصولاً تشغيلياً.',
        'Dashboard values use the configured SafeContracts currency and are calculated from server-side scoped contract/payment data. Contractual due dates remain authoritative for overdue exposure.' => 'تستخدم قيم لوحة التحكم عملة SafeContracts المهيأة ويتم حسابها من بيانات العقود والدفعات المصرح بها على الخادم. يظل تاريخ الاستحقاق التعاقدي المرجع لحساب المتأخرات.',
        'Quick management' => 'إدارة سريعة',
        'No active contracts match the current dashboard filters.' => 'لا توجد عقود نشطة تطابق فلاتر لوحة التحكم الحالية.',
        'Delete is a safe archive action: the contract disappears from this dashboard list, while financial, collection, history and audit records are preserved.' => 'الحذف هنا أرشفة آمنة: يختفي العقد من قائمة لوحة التحكم مع الاحتفاظ بالسجلات المالية والتحصيلية والتاريخية وسجل التدقيق.',
        'Master data' => 'البيانات الأساسية',
        'Edit customer' => 'تعديل عميل',
        'Add customer' => 'إضافة عميل',
        'Update customer' => 'تحديث العميل',
        'Contract operations' => 'عمليات العقود',
        'Receivables' => 'المستحقات',
        'Contractual due date controls Due/Due Soon/Overdue classification. Expected payment date is operational follow-up only.' => 'يتحكم تاريخ الاستحقاق التعاقدي في تصنيف مستحق/قريب الاستحقاق/متأخر. تاريخ الدفع المتوقع للمتابعة التشغيلية فقط.',
        'Settled payments are terminal and shown read-only. Payments on archived contracts are also terminal and shown read-only.' => 'الدفعات المسددة نهائية وتظهر للقراءة فقط، وكذلك الدفعات المرتبطة بعقود مؤرشفة.',
        'Cash application' => 'تطبيق التحصيلات',
        'Collection ledger' => 'سجل التحصيلات',
        'Date' => 'التاريخ',
        'Customer / Contract' => 'العميل / العقد',
        'Select payment' => 'اختر دفعة',
        'Select active method' => 'اختر طريقة نشطة',
        'Details' => 'التفاصيل',
        'The backend collection service enforces active payment methods, assignment scope, exact remaining balance and atomic settlement reconciliation. Proof is optional.' => 'تفرض خدمة التحصيل على الخادم استخدام طرق سداد نشطة ونطاق التكليف والرصيد المتبقي الدقيق وتسوية ذرية. إرفاق الإثبات اختياري.',
        'Reference data' => 'البيانات المرجعية',
        'Order' => 'الترتيب',
        'Stable code' => 'الكود الثابت',
        'Edit payment method' => 'تعديل طريقة سداد',
        'Add payment method' => 'إضافة طريقة سداد',
        'Save Payment Method' => 'حفظ طريقة السداد',
        'Add Payment Method' => 'إضافة طريقة سداد',
        'Collection entry accepts only active SafeContracts payment methods. Delete safely deactivates a method without changing historical collections.' => 'إدخال التحصيل يقبل طرق السداد النشطة في SafeContracts فقط. الحذف يعطل الطريقة بأمان من دون تغيير التحصيلات التاريخية.',
        'System configuration' => 'إعدادات النظام',
        'SafeContracts Settings' => 'إعدادات SafeContracts',
        'Organization name' => 'اسم الجهة',
        'Single currency code' => 'كود العملة الموحدة',
        'Admin page size' => 'حجم صفحة الإدارة',
        'Save SafeContracts Settings' => 'حفظ إعدادات SafeContracts',
        'V1 remains single-currency. Configure both the three-letter code and the display symbol used by mobile financial values. Leaving either blank keeps it explicitly unconfigured.' => 'يعمل الإصدار الحالي بعملة واحدة. اضبط كود العملة المكون من ثلاثة أحرف ورمز العرض المستخدم في القيم المالية على الموبايل. ترك أي منهما فارغاً يعني أنه غير مهيأ.',
        'These are non-secret operational preferences only. Authorization, assignment scope and financial rules remain server-side and cannot be disabled here.' => 'هذه تفضيلات تشغيلية غير سرية فقط. تظل الصلاحيات ونطاق التكليف والقواعد المالية على الخادم ولا يمكن تعطيلها من هنا.',
        'Translation management' => 'إدارة الترجمات',
        'Edit SafeContracts Arabic and English wording without changing the WordPress language.' => 'عدّل نصوص SafeContracts العربية والإنجليزية من دون تغيير لغة WordPress.',
        'Search translations' => 'البحث في الترجمات',
        'Source / key' => 'المصدر / المفتاح',
        'Default English' => 'الإنجليزية الافتراضية',
        'English override' => 'تعديل الإنجليزية',
        'Default Arabic' => 'العربية الافتراضية',
        'Arabic override' => 'تعديل العربية',
        'Save translations' => 'حفظ الترجمات',
        'Reset all translations' => 'إعادة كل الترجمات للوضع الافتراضي',
        'Reset Arabic' => 'إعادة العربية للوضع الافتراضي',
        'Reset English' => 'إعادة الإنجليزية للوضع الافتراضي',
        'Leave an override empty to use the built-in default.' => 'اترك التعديل فارغاً لاستخدام النص الافتراضي المدمج.',
        'No translation entries match this search.' => 'لا توجد ترجمات تطابق البحث.',
        'Translations saved.' => 'تم حفظ الترجمات.',
        'Translations reset.' => 'تمت إعادة الترجمات للوضع الافتراضي.',
        'You do not have permission to manage SafeContracts translations.' => 'ليست لديك صلاحية لإدارة ترجمات SafeContracts.',
        'Delete this customer from active SafeContracts records? Linked contracts and history will be preserved.' => 'حذف هذا العميل من سجلات SafeContracts النشطة؟ سيتم الاحتفاظ بالعقود المرتبطة والسجل التاريخي.',
        'Delete this contract from active operations? Payments, collections, history and audit evidence will be preserved.' => 'حذف هذا العقد من العمليات النشطة؟ سيتم الاحتفاظ بالدفعات والتحصيلات والسجل التاريخي وأدلة التدقيق.',
        'Delete this contract from active operations? Its financial, collection, history and audit records will be preserved.' => 'حذف هذا العقد من العمليات النشطة؟ سيتم الاحتفاظ بالسجلات المالية والتحصيلية والتاريخية وسجل التدقيق.',
        'Delete this scheduled payment? Payments with collection history are protected and must have their collections reversed first.' => 'حذف هذه الدفعة المجدولة؟ الدفعات التي لها سجل تحصيل محمية ويجب عكس تحصيلاتها أولاً.',
        'Delete/reverse this collection? The payment paid amount, remaining amount and status will be recalculated from the remaining active collection ledger.' => 'حذف/عكس هذا التحصيل؟ سيتم إعادة حساب المبلغ المدفوع والمتبقي وحالة الدفعة من سجل التحصيلات النشطة المتبقي.',
        'Delete this payment method from active choices? Existing collection history will keep its method reference.' => 'حذف طريقة السداد من الخيارات النشطة؟ سيحتفظ سجل التحصيلات السابق بمرجع طريقة السداد.',
        'You do not have permission to access SafeContracts.' => 'ليست لديك صلاحية للوصول إلى SafeContracts.',
        'You do not have permission to manage SafeContracts settings.' => 'ليست لديك صلاحية لإدارة إعدادات SafeContracts.',
        'You do not have permission to manage customers.' => 'ليست لديك صلاحية لإدارة العملاء.',
        'You do not have permission to delete customers.' => 'ليست لديك صلاحية لحذف العملاء.',
        'You do not have permission to access customers.' => 'ليست لديك صلاحية للوصول إلى العملاء.',
        'You do not have permission to create contracts.' => 'ليست لديك صلاحية لإنشاء العقود.',
        'You do not have permission to edit or assign contracts.' => 'ليست لديك صلاحية لتعديل العقود أو إسنادها.',
        'You do not have permission to delete contracts.' => 'ليست لديك صلاحية لحذف العقود.',
        'You do not have permission to access contracts.' => 'ليست لديك صلاحية للوصول إلى العقود.',
        'You do not have permission to manage payments.' => 'ليست لديك صلاحية لإدارة الدفعات.',
        'You do not have permission to delete payments.' => 'ليست لديك صلاحية لحذف الدفعات.',
        'You do not have permission to access payments.' => 'ليست لديك صلاحية للوصول إلى الدفعات.',
        'You do not have permission to record collections.' => 'ليست لديك صلاحية لتسجيل التحصيلات.',
        'You do not have permission to delete collections.' => 'ليست لديك صلاحية لحذف التحصيلات.',
        'You do not have permission to access collections.' => 'ليست لديك صلاحية للوصول إلى التحصيلات.',
        'You do not have permission to manage SafeContracts reference data.' => 'ليست لديك صلاحية لإدارة البيانات المرجعية في SafeContracts.',
        'You do not have permission to delete payment methods.' => 'ليست لديك صلاحية لحذف طرق السداد.',
    ];

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        add_filter('gettext_safecontracts', [self::class, 'filterGettext'], 10, 3);
    }

    public static function currentLanguage(): string
    {
        $locale = function_exists('get_user_locale')
            ? (string) get_user_locale()
            : (function_exists('get_locale') ? (string) get_locale() : 'en_US');
        return str_starts_with(strtolower($locale), 'ar') ? 'ar' : 'en';
    }

    public static function text(string $source, ?string $language = null): string
    {
        $language = self::normalizeLanguage($language ?? self::currentLanguage());
        $overrides = self::overrides();
        $override = trim((string) ($overrides[$language][$source] ?? ''));
        if ($override !== '') {
            return $override;
        }
        if ($language === 'ar') {
            return self::ARABIC_DEFAULTS[$source] ?? $source;
        }
        return $source;
    }

    public static function filterGettext(string $translation, string $text, string $domain = 'safecontracts'): string
    {
        if ($domain !== 'safecontracts') {
            return $translation;
        }
        $resolved = self::text($text);
        if ($resolved !== $text || self::currentLanguage() === 'ar') {
            return $resolved;
        }
        return $translation;
    }

    /** @return array{en:array<string,string>,ar:array<string,string>} */
    public static function overrides(): array
    {
        $raw = get_option(self::OPTION, []);
        $clean = ['en' => [], 'ar' => []];
        if (! is_array($raw)) {
            return $clean;
        }
        foreach (self::LANGUAGES as $language) {
            $rows = $raw[$language] ?? [];
            if (! is_array($rows)) {
                continue;
            }
            foreach ($rows as $source => $value) {
                if (! is_string($source) || ! is_string($value)) {
                    continue;
                }
                $source = trim($source);
                $value = trim($value);
                if ($source === '' || $value === '' || strlen($source) > self::MAX_SOURCE_LENGTH || strlen($value) > self::MAX_TRANSLATION_LENGTH) {
                    continue;
                }
                $clean[$language][$source] = $value;
            }
        }
        return $clean;
    }

    /**
     * Save only submitted rows so a search-filtered editor never erases other
     * overrides. Empty values remove the override and restore its default.
     *
     * @param array<int,array{source?:mixed,en?:mixed,ar?:mixed}> $rows
     */
    public static function saveRows(array $rows): void
    {
        $known = self::catalog();
        $overrides = self::overrides();
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $source = self::sanitizeSource($row['source'] ?? '');
            if ($source === '' || ! isset($known[$source])) {
                continue;
            }
            foreach (self::LANGUAGES as $language) {
                $value = self::sanitizeTranslation($row[$language] ?? '');
                if ($value === '') {
                    unset($overrides[$language][$source]);
                } else {
                    $overrides[$language][$source] = $value;
                }
            }
        }
        update_option(self::OPTION, $overrides, false);
    }

    public static function reset(?string $language = null): void
    {
        if ($language === null) {
            update_option(self::OPTION, ['en' => [], 'ar' => []], false);
            return;
        }
        $language = self::normalizeLanguage($language);
        $overrides = self::overrides();
        $overrides[$language] = [];
        update_option(self::OPTION, $overrides, false);
    }

    /**
     * @return array<string,array{en:string,ar:string,surfaces:array<int,string>}>
     */
    public static function catalog(): array
    {
        $catalog = [];
        foreach (self::ARABIC_DEFAULTS as $source => $arabic) {
            $catalog[$source] = ['en' => $source, 'ar' => $arabic, 'surfaces' => ['mobile/core']];
        }
        foreach (self::discoverPluginSources() as $source) {
            if (! isset($catalog[$source])) {
                $catalog[$source] = ['en' => $source, 'ar' => self::ARABIC_DEFAULTS[$source] ?? $source, 'surfaces' => ['wp-admin']];
            } elseif (! in_array('wp-admin', $catalog[$source]['surfaces'], true)) {
                $catalog[$source]['surfaces'][] = 'wp-admin';
            }
        }
        foreach (self::discoverThemeDefaults() as $source => $arabic) {
            if (! isset($catalog[$source])) {
                $catalog[$source] = ['en' => $source, 'ar' => $arabic, 'surfaces' => ['theme']];
            } else {
                if ($catalog[$source]['ar'] === $source && $arabic !== '') {
                    $catalog[$source]['ar'] = $arabic;
                }
                if (! in_array('theme', $catalog[$source]['surfaces'], true)) {
                    $catalog[$source]['surfaces'][] = 'theme';
                }
            }
        }
        ksort($catalog, SORT_NATURAL | SORT_FLAG_CASE);
        return $catalog;
    }

    /** @return array<string,string> */
    public static function resolved(string $language): array
    {
        $language = self::normalizeLanguage($language);
        $resolved = [];
        foreach (self::catalog() as $source => $defaults) {
            $resolved[$source] = self::text($source, $language);
            if ($resolved[$source] === $source && $language === 'ar' && $defaults['ar'] !== $source) {
                $resolved[$source] = $defaults['ar'];
            }
        }
        return $resolved;
    }

    /** @return array{en:array<string,string>,ar:array<string,string>} */
    public static function mobileOverrides(): array
    {
        return self::overrides();
    }

    /** @return array<int,string> */
    private static function discoverPluginSources(): array
    {
        if (! defined('SAFECONTRACTS_PATH')) {
            return array_keys(self::ARABIC_DEFAULTS);
        }
        $root = SAFECONTRACTS_PATH . 'src';
        if (! is_dir($root)) {
            return array_keys(self::ARABIC_DEFAULTS);
        }
        $sources = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            if (! is_string($content) || $content === '') {
                continue;
            }
            if (preg_match_all("/(?:__|esc_html__|esc_attr__)\\(\\s*'((?:\\\\'|[^'])*)'\\s*,\\s*'safecontracts'/", $content, $matches)) {
                foreach ($matches[1] as $match) {
                    $source = str_replace("\\'", "'", (string) $match);
                    if ($source !== '') {
                        $sources[$source] = true;
                    }
                }
            }
        }
        return array_keys($sources);
    }

    /** @return array<string,string> */
    private static function discoverThemeDefaults(): array
    {
        if (! function_exists('safecontracts_copy_catalog')) {
            return [];
        }
        $catalog = safecontracts_copy_catalog();
        if (! is_array($catalog) || ! isset($catalog['en'], $catalog['ar']) || ! is_array($catalog['en']) || ! is_array($catalog['ar'])) {
            return [];
        }
        $found = [];
        self::walkThemePair($catalog['en'], $catalog['ar'], $found);
        return $found;
    }

    /** @param array<string,string> $found */
    private static function walkThemePair(mixed $english, mixed $arabic, array &$found): void
    {
        if (is_string($english)) {
            $source = trim($english);
            if ($source !== '') {
                $found[$source] = is_string($arabic) && trim($arabic) !== '' ? trim($arabic) : $source;
            }
            return;
        }
        if (! is_array($english)) {
            return;
        }
        foreach ($english as $key => $value) {
            self::walkThemePair($value, is_array($arabic) && array_key_exists($key, $arabic) ? $arabic[$key] : null, $found);
        }
    }

    private static function normalizeLanguage(string $language): string
    {
        return strtolower($language) === 'ar' ? 'ar' : 'en';
    }

    private static function sanitizeSource(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }
        $source = trim((string) $value);
        if ($source === '' || strlen($source) > self::MAX_SOURCE_LENGTH || preg_match('/[\\x00]/', $source)) {
            return '';
        }
        return $source;
    }

    private static function sanitizeTranslation(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }
        $translation = trim((string) $value);
        if ($translation === '' || strlen($translation) > self::MAX_TRANSLATION_LENGTH || preg_match('/[\\x00]/', $translation)) {
            return '';
        }
        if (function_exists('sanitize_textarea_field')) {
            return sanitize_textarea_field($translation);
        }
        return trim(strip_tags($translation));
    }
}
