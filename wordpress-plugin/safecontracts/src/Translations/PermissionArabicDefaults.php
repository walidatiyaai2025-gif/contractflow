<?php

declare(strict_types=1);

namespace SafeContracts\Translations;

final class PermissionArabicDefaults
{
    /** @var array<string,string> */
    private const MAP = [
        'General access' => 'الوصول العام',
        'System administration' => 'إدارة النظام',
        'Users & access' => 'المستخدمون والصلاحيات',
        'Data access' => 'الوصول إلى البيانات',
        'Customers' => 'العملاء',
        'Suppliers' => 'الموردون',
        'Contracts' => 'العقود',
        'Payments' => 'الدفعات',
        'Finance' => 'المالية',
        'Collections' => 'التحصيلات',
        'Follow-up' => 'المتابعة',
        'Reports' => 'التقارير',
        'Notifications' => 'الإشعارات',
        'Imports' => 'الاستيراد',
        'Audit' => 'سجل التدقيق',
        'Other permissions' => 'صلاحيات أخرى',
        'Additional system permission' => 'صلاحية نظام إضافية',
        'Allows access to an additional Safe Contracts function.' => 'تتيح الوصول إلى وظيفة إضافية في Safe Contracts.',

        'Access Safe Contracts' => 'الدخول إلى Safe Contracts',
        'Open and use the Safe Contracts application.' => 'فتح نظام Safe Contracts واستخدامه.',
        'Manage system settings' => 'إدارة إعدادات النظام',
        'Change system-wide Safe Contracts configuration and administrative settings.' => 'تعديل إعدادات Safe Contracts العامة وإعدادات الإدارة.',
        'Manage reference lists' => 'إدارة القوائم المرجعية',
        'Maintain controlled lists used by forms, filters and business workflows.' => 'إدارة القوائم المحددة التي تستخدمها النماذج والفلاتر وإجراءات العمل.',
        'Manage users and roles' => 'إدارة المستخدمين والأدوار',
        'Assign Safe Contracts roles and control business permissions for users.' => 'تعيين أدوار Safe Contracts والتحكم في صلاحيات العمل للمستخدمين.',
        'View all business records' => 'عرض جميع سجلات العمل',
        'View records across all customers, suppliers and assigned owners.' => 'عرض السجلات لجميع العملاء والموردين والمسؤولين المسندين.',
        'View assigned records' => 'عرض السجلات المسندة',
        'View records assigned to the signed-in user.' => 'عرض السجلات المسندة إلى المستخدم الحالي.',
        'Create customers' => 'إضافة عملاء',
        'Add new customer records.' => 'إضافة سجلات عملاء جديدة.',
        'Edit customers' => 'تعديل العملاء',
        'Update existing customer details.' => 'تحديث بيانات العملاء الحاليين.',
        'View suppliers' => 'عرض الموردين',
        'View supplier records and supplier-related information.' => 'عرض سجلات الموردين والمعلومات المرتبطة بهم.',
        'Create suppliers' => 'إضافة موردين',
        'Add new supplier records.' => 'إضافة سجلات موردين جديدة.',
        'Edit suppliers' => 'تعديل الموردين',
        'Update existing supplier details.' => 'تحديث بيانات الموردين الحاليين.',
        'Archive suppliers' => 'أرشفة الموردين',
        'Archive supplier records that should no longer be active.' => 'أرشفة الموردين الذين لا يجب أن يظلوا نشطين.',
        'Manage supplier operations' => 'إدارة عمليات الموردين',
        'Perform supplier administration beyond normal create and edit actions.' => 'تنفيذ مهام إدارة الموردين المتقدمة بخلاف الإضافة والتعديل المعتادين.',
        'Create contracts' => 'إنشاء العقود',
        'Add new customer or supplier contracts.' => 'إضافة عقود جديدة للعملاء أو الموردين.',
        'Edit contracts' => 'تعديل العقود',
        'Update contract details and permitted contract configuration.' => 'تحديث بيانات العقد والإعدادات المسموح بها.',
        'Assign contracts' => 'إسناد العقود',
        'Assign contracts to responsible users or teams.' => 'إسناد العقود إلى المستخدمين أو الفرق المسؤولة.',
        'Create payment schedules' => 'إنشاء جداول الدفعات',
        'Create payment or collection obligations for contracts.' => 'إنشاء التزامات الدفع أو التحصيل الخاصة بالعقود.',
        'Edit payment schedules' => 'تعديل جداول الدفعات',
        'Update permitted payment schedule details.' => 'تحديث تفاصيل جدول الدفعات المسموح بتعديلها.',
        'Manage payments' => 'إدارة الدفعات',
        'Perform advanced payment administration and payment lifecycle actions.' => 'تنفيذ عمليات الإدارة المتقدمة للدفعات ودورة حياتها.',
        'View finance' => 'عرض المالية',
        'View financial summaries, balances and finance work areas.' => 'عرض الملخصات المالية والأرصدة وشاشات العمل المالي.',
        'Manage finance' => 'إدارة المالية',
        'Perform financial settlement and finance administration actions.' => 'تنفيذ عمليات التسوية والإدارة المالية.',
        'View supplier payables' => 'عرض مستحقات الموردين',
        'View amounts the organization owes to suppliers.' => 'عرض المبالغ المطلوب دفعها للموردين.',
        'View customer receivables' => 'عرض مستحقات العملاء',
        'View amounts customers owe to the organization.' => 'عرض المبالغ المستحقة على العملاء للمنشأة.',
        'Manage collections' => 'إدارة التحصيلات',
        'Record and manage customer collection activity.' => 'تسجيل وإدارة عمليات تحصيل العملاء.',
        'Manage follow-ups' => 'إدارة المتابعات',
        'Create and update collection and payment follow-up actions.' => 'إنشاء وتحديث إجراءات متابعة التحصيل والدفعات.',
        'View reports' => 'عرض التقارير',
        'Open Safe Contracts operational and financial reports.' => 'فتح تقارير Safe Contracts التشغيلية والمالية.',
        'Export reports' => 'تصدير التقارير',
        'Export permitted reports and business data.' => 'تصدير التقارير وبيانات العمل المسموح بها.',
        'Manage notifications' => 'إدارة الإشعارات',
        'Configure notification rules, schedules and delivery settings.' => 'إعداد قواعد الإشعارات والجداول وإعدادات الإرسال.',
        'Run data imports' => 'تشغيل استيراد البيانات',
        'Upload, preview and execute supported Safe Contracts imports.' => 'رفع ومعاينة وتنفيذ عمليات الاستيراد المدعومة في Safe Contracts.',
        'View audit history' => 'عرض سجل التدقيق',
        'View audit events and change history available to the user.' => 'عرض أحداث التدقيق وسجل التغييرات المتاح للمستخدم.',

        'Available permissions' => 'الصلاحيات المتاحة',
        'Choose a user and a business role from the lists below. Internal user IDs and permission codes are handled by the system and are not required from the administrator.' => 'اختر المستخدم ودور العمل من القوائم التالية. يتعامل النظام داخلياً مع أرقام المستخدمين وأكواد الصلاحيات ولا يحتاج المسؤول إلى إدخالها.',
        'Business permissions' => 'صلاحيات العمل',
        'Select the business actions this role may perform. Technical permission codes remain internal to the system.' => 'اختر إجراءات العمل التي يستطيع هذا الدور تنفيذها. تظل أكواد الصلاحيات التقنية داخلية في النظام.',
        'Unnamed user' => 'مستخدم بدون اسم',
    ];

    public static function register(): void
    {
        add_filter('gettext_safecontracts', [self::class, 'filterGettext'], 25, 3);
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
        return self::MAP[$text] ?? $text;
    }
}
