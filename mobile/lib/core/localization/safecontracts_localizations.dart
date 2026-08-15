import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';

import '../../features/config/mobile_config.dart';

final class SafeContractsLocalizations {
  const SafeContractsLocalizations(this.locale);

  final Locale locale;

  static const supportedLocales = <Locale>[Locale('en'), Locale('ar')];

  static const LocalizationsDelegate<SafeContractsLocalizations> delegate =
      _SafeContractsLocalizationsDelegate();

  static SafeContractsLocalizations of(BuildContext context) {
    return Localizations.of<SafeContractsLocalizations>(
          context,
          SafeContractsLocalizations,
        ) ??
        const SafeContractsLocalizations(Locale('en'));
  }

  bool get isArabic => locale.languageCode.toLowerCase() == 'ar';

  String t(String english) => isArabic ? (_arabic[english] ?? english) : english;

  String status(String value) {
    final normalized = value.trim().toLowerCase();
    final english = <String, String>{
          'draft': 'Draft',
          'active': 'Active',
          'completed': 'Completed',
          'cancelled': 'Cancelled',
          'upcoming': 'Upcoming',
          'due_soon': 'Due soon',
          'due': 'Due',
          'overdue': 'Overdue',
          'partially_paid': 'Partially paid',
          'paid': 'Paid',
          'inactive': 'Inactive',
          'archived': 'Archived',
          'none': 'None',
          'note': 'Note',
          'promise': 'Promise',
          'issue': 'Issue',
          'defer': 'Defer',
          'escalate': 'Escalate',
        }[normalized] ??
        value;
    return t(english);
  }

  String yesNo(bool value) => t(value ? 'Yes' : 'No');

  String money(String rawValue, MobileCurrencyConfig currency) {
    final value = rawValue.trim();
    final token = currency.displayToken.trim();
    if (token.isEmpty || value.isEmpty || value == '—') return value;
    return isArabic ? '$value $token' : '$token $value';
  }

  String pageShown(int page, int count) => isArabic
      ? 'الصفحة $page • معروض $count'
      : 'Page $page • $count shown';

  String pageNumber(int page) => isArabic ? 'الصفحة $page' : 'Page $page';

  String paymentNumber(int id) => isArabic ? 'دفعة #$id' : 'Payment #$id';

  String customerNumber(int id) => isArabic ? 'عميل #$id' : 'Customer #$id';

  String contractNumber(int id) => isArabic ? 'عقد #$id' : 'Contract #$id';

  String collectionRecorded(int id) => isArabic
      ? 'تم تسجيل التحصيل #$id.'
      : 'Collection #$id recorded.';

  String followUpRecorded(int id) => isArabic
      ? 'تم تسجيل المتابعة #$id.'
      : 'Follow-up #$id recorded.';

  String loadingCustomer(int id) => isArabic
      ? 'جارٍ تحميل العميل #$id…'
      : 'Loading customer #$id…';

  String rawMessage(String message) => isArabic ? (_arabic[message] ?? message) : message;
}

extension SafeContractsLocalizationContext on BuildContext {
  SafeContractsLocalizations get scL10n => SafeContractsLocalizations.of(this);
}

final class _SafeContractsLocalizationsDelegate
    extends LocalizationsDelegate<SafeContractsLocalizations> {
  const _SafeContractsLocalizationsDelegate();

  @override
  bool isSupported(Locale locale) =>
      SafeContractsLocalizations.supportedLocales.any(
        (supported) => supported.languageCode == locale.languageCode,
      );

  @override
  Future<SafeContractsLocalizations> load(Locale locale) =>
      SynchronousFuture<SafeContractsLocalizations>(
        SafeContractsLocalizations(locale),
      );

  @override
  bool shouldReload(_SafeContractsLocalizationsDelegate old) => false;
}

const Map<String, String> _arabic = <String, String>{
  'Dashboard': 'لوحة التحكم',
  'Customers': 'العملاء',
  'Contracts': 'العقود',
  'Payments': 'الدفعات',
  'Collections': 'التحصيلات',
  'Follow-up': 'المتابعة',
  'Notifications': 'الإشعارات',
  'Excel export': 'تصدير Excel',
  'Profile': 'الملف الشخصي',
  'SafeContracts': 'SafeContracts',
  'Remote mobile configuration is unavailable. Safe defaults are active.':
      'تعذر تحميل إعدادات الموبايل من الخادم. يتم استخدام الإعدادات الآمنة الافتراضية.',
  'Sign in with your WordPress username and password':
      'سجّل الدخول باسم مستخدم وكلمة مرور WordPress',
  'Username': 'اسم المستخدم',
  'Password': 'كلمة المرور',
  'Enter your username.': 'أدخل اسم المستخدم.',
  'Enter your password.': 'أدخل كلمة المرور.',
  'Signing in…': 'جارٍ تسجيل الدخول…',
  'Sign in': 'تسجيل الدخول',
  'Operational overview': 'نظرة تشغيلية',
  'Contracts': 'العقود',
  'Scheduled': 'المجدول',
  'Remaining': 'المتبقي',
  'Overdue': 'متأخر',
  'Collected': 'المحصّل',
  'Refresh dashboard': 'تحديث لوحة التحكم',
  'All customers': 'كل العملاء',
  'All contracts': 'كل العقود',
  'Any status': 'أي حالة',
  'Customer': 'العميل',
  'Contract': 'العقد',
  'Status': 'الحالة',
  'Due from': 'استحقاق من',
  'Due to': 'استحقاق إلى',
  'Apply filters': 'تطبيق الفلاتر',
  'Recent payments': 'أحدث الدفعات',
  'No payment records match the current filters.':
      'لا توجد دفعات تطابق الفلاتر الحالية.',
  'Refresh customers': 'تحديث العملاء',
  'Customers are not loaded yet.': 'لم يتم تحميل العملاء بعد.',
  'Unable to load customers.': 'تعذر تحميل العملاء.',
  'Customer refresh failed.': 'فشل تحديث العملاء.',
  'No customers are available in your scope.': 'لا يوجد عملاء ضمن صلاحياتك.',
  'Active': 'نشط',
  'Inactive': 'غير نشط',
  'Previous': 'السابق',
  'Next': 'التالي',
  'Select a customer to view authorized details.':
      'اختر عميلاً لعرض التفاصيل المسموح بها.',
  'Unable to load customer.': 'تعذر تحميل العميل.',
  'Customer ID': 'رقم العميل',
  'Internal code': 'الكود الداخلي',
  'Contact name': 'اسم جهة الاتصال',
  'Email': 'البريد الإلكتروني',
  'Phone': 'الهاتف',
  'Only server-authorized customer fields are shown.':
      'يتم عرض بيانات العميل المصرح بها من الخادم فقط.',
  'Retry': 'إعادة المحاولة',
  'All statuses': 'كل الحالات',
  'Draft': 'مسودة',
  'Completed': 'مكتمل',
  'Cancelled': 'ملغي',
  'Sort': 'الترتيب',
  'Refresh contracts': 'تحديث العقود',
  'Unable to load contracts.': 'تعذر تحميل العقود.',
  'Contracts are not loaded yet.': 'لم يتم تحميل العقود بعد.',
  'No contracts match the current filters.': 'لا توجد عقود تطابق الفلاتر الحالية.',
  'Contract refresh failed.': 'فشل تحديث العقود.',
  'Start': 'البداية',
  'End': 'النهاية',
  'Value': 'القيمة',
  'Archived': 'مؤرشف',
  'Contract details': 'تفاصيل العقد',
  'Edit contract': 'تعديل العقد',
  'Contract not found': 'العقد غير موجود',
  'This contract was not found in your authorized scope.':
      'العقد غير موجود ضمن نطاق صلاحياتك.',
  'Contract access denied': 'غير مسموح بالوصول للعقد',
  'You do not have permission to view this contract.':
      'ليست لديك صلاحية لعرض هذا العقد.',
  'Unable to load contract': 'تعذر تحميل العقد',
  'SafeContracts could not load this contract.': 'تعذر على SafeContracts تحميل هذا العقد.',
  'Contract ID': 'رقم العقد',
  'Start date': 'تاريخ البداية',
  'End date': 'تاريخ النهاية',
  'Customer & assignment': 'العميل والتكليف',
  'Assigned accountant user ID': 'رقم مستخدم المحاسب المكلّف',
  'Financial values': 'القيم المالية',
  'Base value': 'القيمة الأساسية',
  'Status and financial values are displayed exactly as returned by the SafeContracts server. The mobile app does not recalculate them.':
      'يتم عرض الحالة والقيم المالية كما يعيدها خادم SafeContracts دون إعادة حساب داخل تطبيق الموبايل.',
  'This contract is read-only for the current session.':
      'هذا العقد للقراءة فقط في الجلسة الحالية.',
  'Contract details are unavailable.': 'تفاصيل العقد غير متاحة.',
  'Contract number': 'رقم العقد',
  'Update start/end dates': 'تحديث تاريخ البداية والنهاية',
  'Start date YYYY-MM-DD': 'تاريخ البداية YYYY-MM-DD',
  'End date YYYY-MM-DD': 'تاريخ النهاية YYYY-MM-DD',
  'Save supported fields': 'حفظ الحقول المدعومة',
  'Status, assignment and financial values are not editable here. Server scope, validation and audit remain authoritative.':
      'الحالة والتكليف والقيم المالية غير قابلة للتعديل هنا. تظل الصلاحيات والتحقق وسجل التدقيق على الخادم هي المرجع.',
  'Expected payment date': 'تاريخ الدفع المتوقع',
  'YYYY-MM-DD (blank clears)': 'YYYY-MM-DD (اتركه فارغاً للمسح)',
  'Cancel': 'إلغاء',
  'Save': 'حفظ',
  'Expected payment date updated.': 'تم تحديث تاريخ الدفع المتوقع.',
  'Validation': 'تحقق البيانات',
  'Forbidden': 'غير مسموح',
  'Conflict': 'تعارض',
  'Error': 'خطأ',
  'No payments match the authorized filters.': 'لا توجد دفعات تطابق الفلاتر المسموح بها.',
  'Remaining:': 'المتبقي:',
  'Previous page': 'الصفحة السابقة',
  'Next page': 'الصفحة التالية',
  'Payment access denied': 'غير مسموح بالوصول للدفعة',
  'Payment not found': 'الدفعة غير موجودة',
  'Unable to load payment': 'تعذر تحميل الدفعة',
  'Payment details': 'تفاصيل الدفعة',
  'SafeContracts request failed.': 'فشل طلب SafeContracts.',
  'Payment not found.': 'الدفعة غير موجودة.',
  'Contractual due date': 'تاريخ الاستحقاق التعاقدي',
  'Original amount': 'المبلغ الأصلي',
  'Paid amount': 'المبلغ المدفوع',
  'Remaining amount': 'المبلغ المتبقي',
  'Contract archived': 'العقد مؤرشف',
  'Yes': 'نعم',
  'No': 'لا',
  'Dates, balances and status are server-authoritative. Mobile does not recalculate receivables.':
      'التواريخ والأرصدة والحالة معتمدة من الخادم. تطبيق الموبايل لا يعيد حساب المستحقات.',
  'Edit expected payment date': 'تعديل تاريخ الدفع المتوقع',
  'Record collection': 'تسجيل تحصيل',
  'Unable to load payments': 'تعذر تحميل الدفعات',
  'No follow-up items match the authorized filters.':
      'لا توجد عناصر متابعة تطابق الفلاتر المسموح بها.',
  'Due': 'مستحق',
  'Due soon': 'استحقاق قريب',
  'Upcoming': 'قادم',
  'Partially paid': 'مدفوع جزئياً',
  'Paid': 'مدفوع',
  'None': 'لا يوجد',
  'Add follow-up': 'إضافة متابعة',
  'No follow-up history yet.': 'لا يوجد سجل متابعة حتى الآن.',
  'Promised:': 'موعد السداد الموعود:',
  'Deferred until:': 'مؤجل حتى:',
  'Operational follow-up': 'متابعة تشغيلية',
  'Action': 'الإجراء',
  'Note': 'ملاحظة',
  'Note (optional)': 'ملاحظة (اختياري)',
  'Promised date YYYY-MM-DD': 'تاريخ السداد الموعود YYYY-MM-DD',
  'Deferred until YYYY-MM-DD': 'مؤجل حتى YYYY-MM-DD',
  'Record': 'تسجيل',
  'A note is required for this follow-up action.': 'الملاحظة مطلوبة لهذا الإجراء.',
  'A valid YYYY-MM-DD date is required.': 'مطلوب تاريخ صحيح بصيغة YYYY-MM-DD.',
  'Follow-up note cannot exceed 5000 characters.': 'لا يمكن أن تتجاوز ملاحظة المتابعة 5000 حرف.',
  'Promise': 'وعد بالسداد',
  'Issue': 'مشكلة',
  'Defer': 'تأجيل',
  'Escalate': 'تصعيد',
  'Language': 'اللغة',
  'Arabic': 'العربية',
  'English': 'English',
  'Currency': 'العملة',
  'Currency code': 'رمز العملة',
  'Currency symbol': 'علامة العملة',
  'Not configured': 'غير مهيأ',
  'Session': 'الجلسة',
  'User ID': 'رقم المستخدم',
  'Data scope': 'نطاق البيانات',
  'Default page size': 'حجم الصفحة الافتراضي',
  'Support': 'الدعم',
  'Capabilities': 'الصلاحيات',
  'Registered devices': 'الأجهزة المسجلة',
  'Push notifications': 'إشعارات Push',
  'Clear local session': 'مسح الجلسة المحلية',
  'Refresh': 'تحديث',
  'No notifications are available.': 'لا توجد إشعارات متاحة.',
  'No notifications yet.': 'لا توجد إشعارات حتى الآن.',
  'Mark as read': 'تحديد كمقروء',
  'Mark all as read': 'تحديد الكل كمقروء',
  'Export': 'تصدير',
  'Download Excel': 'تنزيل Excel',
  'Preparing export…': 'جارٍ تجهيز التصدير…',
  'No export data is available.': 'لا توجد بيانات متاحة للتصدير.',
  'Close': 'إغلاق',
  'Amount': 'المبلغ',
  'Collection date': 'تاريخ التحصيل',
  'Payment method': 'طريقة الدفع',
  'Reference': 'المرجع',
  'Details': 'التفاصيل',
  'Proof attachment': 'مرفق الإثبات',
  'Select': 'اختيار',
};
