<?php

declare(strict_types=1);

namespace SafeContracts\Translations;

final class MobileAdvertisingArabicDefaults
{
    /** @var array<string,string> */
    private const MAP = [
        'Mobile Advertising' => 'إعلانات الموبايل',
        'Mobile advertising configuration is invalid. Review the provider identifiers.' => 'إعدادات إعلانات الموبايل غير صالحة. راجع معرّفات مزودي الإعلانات.',
        'Mobile advertising configuration saved.' => 'تم حفظ إعدادات إعلانات الموبايل.',
        'Mobile monetization' => 'تحقيق الدخل من تطبيق الموبايل',
        'Save Mobile Advertising' => 'حفظ إعدادات إعلانات الموبايل',
        'Switch between Google AdMob and AppLovin MAX remotely. Advertising stays disabled by default and production identifiers remain server-managed.' => 'بدّل عن بُعد بين Google AdMob وAppLovin MAX. تظل الإعلانات معطلة افتراضيًا وتبقى معرّفات الإنتاج مُدارة من الخادم.',
        'The AdMob App ID remains part of the signed Android build. Never paste AppLovin management/API keys here; only the mobile SDK key is accepted.' => 'يبقى معرّف تطبيق AdMob جزءًا من حزمة Android الموقعة. لا تلصق مفاتيح إدارة أو API الخاصة بـAppLovin هنا؛ يُقبل فقط مفتاح SDK للموبايل.',
        'Use these public URLs in Google Play Console and the advertising-provider privacy configuration.' => 'استخدم هذه الروابط العامة في Google Play Console وإعدادات الخصوصية لدى مزود الإعلانات.',
        'You do not have permission to manage mobile advertising.' => 'ليست لديك صلاحية إدارة إعلانات الموبايل.',
        'Advertising (Google AdMob)' => 'الإعلانات (Google AdMob)',
        'Advertising providers' => 'مزودو الإعلانات',
        'Advertising provider' => 'مزود الإعلانات',
        'Google AdMob' => 'جوجل AdMob',
        'AppLovin MAX' => 'أب لوفين MAX',
        'Ads are disabled by default. Test mode uses Google test inventory so QA cannot generate invalid production traffic.' => 'الإعلانات معطلة افتراضيًا. يستخدم وضع الاختبار إعلانات Google التجريبية حتى لا تتسبب اختبارات الجودة في زيارات إنتاج غير صالحة.',
        'Ads are disabled by default. Choose the active provider here; switching providers takes effect from remote configuration without publishing a new app build.' => 'الإعلانات معطلة افتراضيًا. اختر مزود الإعلانات النشط من هنا؛ ويمكن تبديل المزود من الإعدادات البعيدة دون نشر نسخة جديدة من التطبيق.',
        'If AdMob is suspended or intentionally disabled, select AppLovin MAX and save. The app will stop requesting AdMob ads and use AppLovin on the next configuration refresh/app start.' => 'إذا تم تعليق AdMob أو أردت إيقافه، اختر AppLovin MAX ثم احفظ. سيتوقف التطبيق عن طلب إعلانات AdMob ويستخدم AppLovin عند تحديث الإعدادات أو تشغيل التطبيق التالي.',
        'Enable mobile advertising' => 'تفعيل الإعلانات في تطبيق الموبايل',
        'Test mode (recommended until Play/AdMob production verification)' => 'وضع الاختبار (موصى به حتى اكتمال التحقق من Google Play وAdMob)',
        'Test / QA mode' => 'وضع الاختبار / الجودة',
        'Show banner ads' => 'عرض إعلانات البانر',
        'Android banner Ad Unit ID' => 'معرّف وحدة إعلان البانر لأندرويد',
        'AdMob banner Ad Unit ID' => 'معرّف وحدة بانر AdMob',
        'AppLovin SDK key' => 'مفتاح SDK لـ AppLovin',
        'AppLovin banner Ad Unit ID' => 'معرّف وحدة بانر AppLovin',
        'For AppLovin QA, add the test device GAID in MAX > Mediation > Manage > Test Mode. AppLovin does not provide a universal public banner test unit like AdMob.' => 'لاختبار AppLovin، أضف GAID لجهاز الاختبار من MAX > Mediation > Manage > Test Mode. لا يوفر AppLovin وحدة بانر تجريبية عامة موحدة مثل AdMob.',
        'The production AdMob App ID belongs to the signed Android build and is supplied through release secrets, not saved in WordPress. The banner Ad Unit ID is safe to manage here at runtime.' => 'معرّف تطبيق AdMob للإنتاج جزء من نسخة أندرويد الموقعة ويتم تمريره عبر أسرار الإصدار، ولا يُحفظ في WordPress. يمكن إدارة معرّف وحدة إعلان البانر بأمان من هنا أثناء التشغيل.',
        'The AdMob App ID remains a signed-build release secret. AppLovin uses only the SDK key here; never paste an AppLovin Management Key, API Key, or Ad Review Key into this page.' => 'يظل معرّف تطبيق AdMob سرًا خاصًا ببناء النسخة الموقعة. يستخدم AppLovin هنا مفتاح SDK فقط؛ لا تضع مفتاح الإدارة أو API أو مفتاح Ad Review الخاص بـ AppLovin في هذه الصفحة.',
        'The production AdMob App ID is already embedded in the signed Android build. The Banner Ad Unit ID below remains editable from WordPress at runtime.' => 'تم تضمين معرّف تطبيق AdMob للإنتاج بالفعل داخل نسخة أندرويد الموقعة. يظل معرّف وحدة بانر AdMob قابلاً للتعديل من WordPress أثناء التشغيل.',
        'The AdMob App ID is fixed in the signed Android build. AppLovin uses only the SDK key here; never paste an AppLovin Management Key, API Key, or Ad Review Key into this page.' => 'معرّف تطبيق AdMob ثابت داخل نسخة أندرويد الموقعة. يستخدم AppLovin هنا مفتاح SDK فقط؛ لا تضع مفتاح الإدارة أو API أو مفتاح Ad Review الخاص بـ AppLovin في هذه الصفحة.',
        'Store compliance pages' => 'صفحات متطلبات المتجر',
        'Privacy policy' => 'سياسة الخصوصية',
        'Account deletion' => 'حذف الحساب',
        'Support' => 'الدعم',
        'Terms of use' => 'شروط الاستخدام',
        'Use these public URLs in Google Play Console, AdMob/AppLovin privacy configuration, and the app listing.' => 'استخدم هذه الروابط العامة في Google Play Console وإعدادات الخصوصية في AdMob/AppLovin وصفحة التطبيق على المتجر.',
        'AdMob setup checklist' => 'خطوات إعداد AdMob',
        'AppLovin setup checklist' => 'خطوات إعداد AppLovin',
        'This page stores non-secret mobile bootstrap and advertising controls in WordPress. Production signing material and the AdMob App ID must remain outside the repository.' => 'تخزن هذه الصفحة إعدادات تشغيل تطبيق الموبايل وعناصر التحكم في الإعلانات غير السرية داخل WordPress. يجب أن تظل بيانات توقيع الإنتاج ومعرّف تطبيق AdMob خارج المستودع.',
        'Google Play Review' => 'مراجعة Google Play',
        'Google Play Review Account' => 'حساب مراجعة Google Play',
        'Google Play review account is ready. Use the email and the password you just entered in Play Console.' => 'حساب مراجعة Google Play جاهز. استخدم البريد وكلمة المرور التي أدخلتها للتو داخل Play Console.',
        'Google Play review account was disabled and its password was randomized.' => 'تم تعطيل حساب مراجعة Google Play وتغيير كلمة مروره إلى قيمة عشوائية.',
        'Review password must contain between 6 and 128 characters.' => 'يجب أن تتكون كلمة مرور المراجعة من 6 إلى 128 حرفًا.',
        'A WordPress account already uses the review email but was not created by this tool. It was not modified.' => 'يوجد حساب WordPress يستخدم بريد المراجعة بالفعل لكنه لم يُنشأ بواسطة هذه الأداة، لذلك لم يتم تعديله.',
        'The Google Play review account could not be created.' => 'تعذر إنشاء حساب مراجعة Google Play.',
        'This creates a temporary, read-only Safe Contracts viewer for Google Play review. The password is submitted once to WordPress and is never stored in plugin settings or committed to Git.' => 'ينشئ هذا حساب مشاهدة مؤقتًا للقراءة فقط لاستخدام مراجعة Google Play. تُرسل كلمة المرور مرة واحدة إلى WordPress ولا يتم حفظها في إعدادات البلجن أو رفعها إلى Git.',
        'Reviewer email' => 'بريد المراجع',
        'WordPress login' => 'اسم دخول WordPress',
        '(email login also works in WordPress)' => '(يمكن أيضًا تسجيل الدخول بالبريد الإلكتروني في WordPress)',
        'Role' => 'الدور',
        'SafeContracts Viewer — read-only / assigned-scope access' => 'مشاهد Safe Contracts — قراءة فقط / ضمن النطاق المسند',
        'Current status' => 'الحالة الحالية',
        'Ready' => 'جاهز',
        'Email already exists but is not managed by this tool' => 'البريد موجود بالفعل لكنه غير مُدار بواسطة هذه الأداة',
        'Not created yet' => 'لم يتم إنشاؤه بعد',
        'Create or reset reviewer credentials' => 'إنشاء أو إعادة ضبط بيانات دخول المراجع',
        'Enter the exact temporary password you want to give Google Play. For security, the plugin does not embed a fixed plaintext password in the public repository.' => 'أدخل كلمة المرور المؤقتة نفسها التي تريد إعطاءها لـ Google Play. لأسباب أمنية لا يضع البلجن كلمة مرور ثابتة بنص واضح داخل المستودع العام.',
        'Temporary review password' => 'كلمة مرور المراجعة المؤقتة',
        'If you choose a weak password such as 123456, keep this account enabled only for the shortest review window and disable it immediately after Google finishes reviewing the app.' => 'إذا اخترت كلمة مرور ضعيفة مثل 123456، اترك الحساب مفعّلًا فقط خلال أقصر فترة لازمة للمراجعة وعطله فور انتهاء Google من مراجعة التطبيق.',
        'Reset Review Password' => 'إعادة ضبط كلمة مرور المراجع',
        'Create Review Account' => 'إنشاء حساب المراجعة',
        'Disable Review Account' => 'تعطيل حساب المراجعة',
        'Review-data safety:' => 'أمان بيانات المراجعة:',
        'Assign only sanitized demo records to this viewer. Do not grant manager/admin access or expose production customer, supplier, contract, or payment data to the review account.' => 'اربط بهذا الحساب سجلات تجريبية منزوعة البيانات الحساسة فقط. لا تمنحه صلاحيات مدير أو مسؤول ولا تعرض له بيانات إنتاج حقيقية للعملاء أو الموردين أو العقود أو المدفوعات.',
        'You do not have permission to manage the Google Play review account.' => 'ليس لديك صلاحية إدارة حساب مراجعة Google Play.',
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
