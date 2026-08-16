import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';

import '../../features/config/mobile_config.dart';
import 'runtime_translation_overrides.dart';

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

  String t(String english) {
    final runtime = SafeContractsRuntimeTranslations.lookup(
      isArabic ? 'ar' : 'en',
      english,
    );
    if (runtime != null) return runtime;
    return isArabic ? (_arabic[english] ?? english) : english;
  }

  String template(String english, Map<String, Object> replacements) {
    var value = t(english);
    for (final entry in replacements.entries) {
      value = value.replaceAll('{${entry.key}}', entry.value.toString());
    }
    return value;
  }

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
    final value = _twoDecimalMoney(rawValue);
    final token = currency.displayToken.trim();
    if (token.isEmpty || value.isEmpty || value == '—') return value;
    return isArabic ? '$value $token' : '$token $value';
  }

  String pageShown(int page, int count) => template(
        'Page {page} • {count} shown',
        <String, Object>{'page': page, 'count': count},
      );

  String pageNumber(int page) =>
      template('Page {page}', <String, Object>{'page': page});

  String paymentNumber(int id) =>
      template('Payment #{id}', <String, Object>{'id': id});

  String customerNumber(int id) =>
      template('Customer #{id}', <String, Object>{'id': id});

  String contractNumber(int id) =>
      template('Contract #{id}', <String, Object>{'id': id});

  String collectionRecorded(int id) => template(
        'Collection #{id} recorded.',
        <String, Object>{'id': id},
      );

  String followUpRecorded(int id) => template(
        'Follow-up #{id} recorded.',
        <String, Object>{'id': id},
      );

  String loadingCustomer(int id) => template(
        'Loading customer #{id}…',
        <String, Object>{'id': id},
      );

  String rawMessage(String message) => t(message);
}

String _twoDecimalMoney(String rawValue) {
  final value = rawValue.trim();
  final match = RegExp(r'^([+-]?)(\d+)(?:\.(\d+))?$').firstMatch(value);
  if (match == null) return value;

  final rawSign = match.group(1) ?? '';
  var whole = BigInt.parse(match.group(2)!);
  final fraction = match.group(3) ?? '';
  final firstTwo = (fraction + '00').substring(0, 2);
  var cents = int.parse(firstTwo);

  if (fraction.length > 2 && int.parse(fraction[2]) >= 5) {
    cents += 1;
    if (cents == 100) {
      whole += BigInt.one;
      cents = 0;
    }
  }

  final isZero = whole == BigInt.zero && cents == 0;
  final sign = rawSign == '-' && !isZero ? '-' : rawSign == '+' ? '+' : '';
  return '$sign$whole.${cents.toString().padLeft(2, '0')}';
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
  'SafeContracts': 'SafeContracts',
  'Dashboard': 'لوحة التحكم',
  'Customers': 'العملاء',
  'Contracts': 'العقود',
  'Payments': 'الدفعات',
  'Collections': 'التحصيلات',
  'Follow-up': 'المتابعة',
  'Notifications': 'الإشعارات',
  'Excel export': 'تصدير Excel',
  'Profile': 'الملف الشخصي',
  'Environment': 'البيئة',
  'Retry session': 'إعادة محاولة الجلسة',
  'SafeContracts mobile is unavailable.':
      'تطبيق SafeContracts غير متاح حالياً.',
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
  'Remember me': 'تذكرني',
  'Keep me signed in on this device. Your password is never stored.':
      'احتفظ بتسجيل الدخول على هذا الجهاز. لا يتم حفظ كلمة المرور.',
  'Loading': 'جارٍ التحميل',
  'Retry': 'إعادة المحاولة',
  'Refresh': 'تحديث',
  'Close': 'إغلاق',
  'Cancel': 'إلغاء',
  'Save': 'حفظ',
  'Yes': 'نعم',
  'No': 'لا',
  'New': 'جديد',
  'Read': 'مقروء',
  'Previous': 'السابق',
  'Next': 'التالي',
  'Previous page': 'الصفحة السابقة',
  'Next page': 'الصفحة التالية',
  'Page {page} • {count} shown': 'الصفحة {page} • معروض {count}',
  'Page {page}': 'الصفحة {page}',
  'Payment #{id}': 'دفعة #{id}',
  'Customer #{id}': 'عميل #{id}',
  'Contract #{id}': 'عقد #{id}',
  'Collection #{id} recorded.': 'تم تسجيل التحصيل #{id}.',
  'Follow-up #{id} recorded.': 'تم تسجيل المتابعة #{id}.',
  'Loading customer #{id}…': 'جارٍ تحميل العميل #{id}…',
  'Dashboard filters': 'فلاتر لوحة التحكم',
  'Dashboard is not loaded yet.': 'لم يتم تحميل لوحة التحكم بعد.',
  'Unable to load dashboard.': 'تعذر تحميل لوحة التحكم.',
  'Dashboard refresh failed.': 'فشل تحديث لوحة التحكم.',
  'Scheduled': 'المجدول',
  'Remaining': 'المتبقي',
  'Overdue': 'متأخر',
  'Collected': 'المحصّل',
  'Customer': 'العميل',
  'Contract': 'العقد',
  'Status': 'الحالة',
  'All customers': 'كل العملاء',
  'All contracts': 'كل العقود',
  'All statuses': 'كل الحالات',
  'Any status': 'أي حالة',
  'Due from': 'استحقاق من',
  'Due to': 'استحقاق إلى',
  'No records match the current filters.':
      'لا توجد سجلات تطابق الفلاتر الحالية.',
  'Draft': 'مسودة',
  'Active': 'نشط',
  'Completed': 'مكتمل',
  'Cancelled': 'ملغي',
  'Upcoming': 'قادم',
  'Due soon': 'استحقاق قريب',
  'Due': 'مستحق',
  'Partially paid': 'مدفوع جزئياً',
  'Paid': 'مدفوع',
  'Inactive': 'غير نشط',
  'Archived': 'مؤرشف',
  'None': 'لا يوجد',
  'Note': 'ملاحظة',
  'Promise': 'وعد بالسداد',
  'Issue': 'مشكلة',
  'Defer': 'تأجيل',
  'Escalate': 'تصعيد',
  'Refresh customers': 'تحديث العملاء',
  'Customers are not loaded yet.': 'لم يتم تحميل العملاء بعد.',
  'Unable to load customers.': 'تعذر تحميل العملاء.',
  'Customer refresh failed.': 'فشل تحديث العملاء.',
  'No customers are available in your scope.': 'لا يوجد عملاء ضمن صلاحياتك.',
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
  'Sort': 'الترتيب',
  'Newest': 'الأحدث',
  'Contract number': 'رقم العقد',
  'Start date': 'تاريخ البداية',
  'End date': 'تاريخ النهاية',
  'Refresh contracts': 'تحديث العقود',
  'Unable to load contracts.': 'تعذر تحميل العقود.',
  'Contracts are not loaded yet.': 'لم يتم تحميل العقود بعد.',
  'No contracts match the current filters.':
      'لا توجد عقود تطابق الفلاتر الحالية.',
  'Contract refresh failed.': 'فشل تحديث العقود.',
  'Start': 'البداية',
  'End': 'النهاية',
  'Value': 'القيمة',
  'Contract details': 'تفاصيل العقد',
  'Edit contract': 'تعديل العقد',
  'Contract not found': 'العقد غير موجود',
  'This contract was not found in your authorized scope.':
      'العقد غير موجود ضمن نطاق صلاحياتك.',
  'Contract access denied': 'غير مسموح بالوصول للعقد',
  'You do not have permission to view this contract.':
      'ليست لديك صلاحية لعرض هذا العقد.',
  'Unable to load contract': 'تعذر تحميل العقد',
  'SafeContracts could not load this contract.':
      'تعذر على SafeContracts تحميل هذا العقد.',
  'Contract ID': 'رقم العقد',
  'Customer & assignment': 'العميل والتكليف',
  'Assigned accountant user ID': 'رقم مستخدم المحاسب المكلّف',
  'Financial values': 'القيم المالية',
  'Base value': 'القيمة الأساسية',
  'Status and financial values are displayed exactly as returned by the SafeContracts server. The mobile app does not recalculate them.':
      'يتم عرض الحالة والقيم المالية كما يعيدها خادم SafeContracts دون إعادة حساب داخل تطبيق الموبايل.',
  'This contract is read-only for the current session.':
      'هذا العقد للقراءة فقط في الجلسة الحالية.',
  'Contract details are unavailable.': 'تفاصيل العقد غير متاحة.',
  'Update start/end dates': 'تحديث تاريخ البداية والنهاية',
  'Start date YYYY-MM-DD': 'تاريخ البداية YYYY-MM-DD',
  'End date YYYY-MM-DD': 'تاريخ النهاية YYYY-MM-DD',
  'Save supported fields': 'حفظ الحقول المدعومة',
  'Status, assignment and financial values are not editable here. Server scope, validation and audit remain authoritative.':
      'الحالة والتكليف والقيم المالية غير قابلة للتعديل هنا. تظل الصلاحيات والتحقق وسجل التدقيق على الخادم هي المرجع.',
  'Contract editing is not authorized for this session.':
      'تعديل العقود غير مسموح به في هذه الجلسة.',
  'Contract number must contain 1 to 100 characters.':
      'يجب أن يحتوي رقم العقد على 1 إلى 100 حرف.',
  'Contract dates must use YYYY-MM-DD or be blank.':
      'يجب أن تكون تواريخ العقد بصيغة YYYY-MM-DD أو فارغة.',
  'Contract end date cannot precede start date.':
      'لا يمكن أن يسبق تاريخ نهاية العقد تاريخ البداية.',
  'Expected payment date': 'تاريخ الدفع المتوقع',
  'YYYY-MM-DD (blank clears)': 'YYYY-MM-DD (اتركه فارغاً للمسح)',
  'Expected payment date updated.': 'تم تحديث تاريخ الدفع المتوقع.',
  'Validation': 'تحقق البيانات',
  'Forbidden': 'غير مسموح',
  'Conflict': 'تعارض',
  'Error': 'خطأ',
  'No payments match the authorized filters.':
      'لا توجد دفعات تطابق الفلاتر المسموح بها.',
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
  'Dates, balances and status are server-authoritative. Mobile does not recalculate receivables.':
      'التواريخ والأرصدة والحالة معتمدة من الخادم. تطبيق الموبايل لا يعيد حساب المستحقات.',
  'Edit expected payment date': 'تعديل تاريخ الدفع المتوقع',
  'Record collection': 'تسجيل تحصيل',
  'Unable to load payments': 'تعذر تحميل الدفعات',
  'Amount': 'المبلغ',
  'Collection date': 'تاريخ التحصيل',
  'Collection date YYYY-MM-DD': 'تاريخ التحصيل YYYY-MM-DD',
  'Payment method': 'طريقة الدفع',
  'Reference': 'المرجع',
  'Reference (optional)': 'المرجع (اختياري)',
  'Proof media ID (optional)': 'رقم مرفق الإثبات (اختياري)',
  'Retry methods': 'إعادة تحميل طرق الدفع',
  'The server validates scope, payment balance, settlement status and audit history. Mobile performs input-shape checks only.':
      'يتحقق الخادم من الصلاحيات ورصيد الدفعة وحالة التسوية وسجل التدقيق. تطبيق الموبايل يتحقق من شكل الإدخال فقط.',
  'No active payment methods are available.': 'لا توجد طرق دفع نشطة متاحة.',
  'Enter a positive amount with up to 4 decimal places.':
      'أدخل مبلغاً موجباً بحد أقصى 4 منازل عشرية.',
  'Collection date must be valid YYYY-MM-DD.':
      'يجب أن يكون تاريخ التحصيل صحيحاً بصيغة YYYY-MM-DD.',
  'Choose an active payment method.': 'اختر طريقة دفع نشطة.',
  'Reference cannot exceed 191 characters.':
      'لا يمكن أن يتجاوز المرجع 191 حرفاً.',
  'Proof media ID must be a positive integer.':
      'يجب أن يكون رقم مرفق الإثبات عدداً صحيحاً موجباً.',
  'No follow-up items match the authorized filters.':
      'لا توجد عناصر متابعة تطابق الفلاتر المسموح بها.',
  'Add follow-up': 'إضافة متابعة',
  'No follow-up history yet.': 'لا يوجد سجل متابعة حتى الآن.',
  'Promised:': 'موعد السداد الموعود:',
  'Deferred until:': 'مؤجل حتى:',
  'Operational follow-up': 'متابعة تشغيلية',
  'Action': 'الإجراء',
  'Note (optional)': 'ملاحظة (اختياري)',
  'Promised date YYYY-MM-DD': 'تاريخ السداد الموعود YYYY-MM-DD',
  'Deferred until YYYY-MM-DD': 'مؤجل حتى YYYY-MM-DD',
  'Record': 'تسجيل',
  'A note is required for this follow-up action.':
      'الملاحظة مطلوبة لهذا الإجراء.',
  'A valid YYYY-MM-DD date is required.': 'مطلوب تاريخ صحيح بصيغة YYYY-MM-DD.',
  'Follow-up note cannot exceed 5000 characters.':
      'لا يمكن أن تتجاوز ملاحظة المتابعة 5000 حرف.',
  'Loading notifications…': 'جارٍ تحميل الإشعارات…',
  'Notifications are unavailable.': 'الإشعارات غير متاحة.',
  'No notifications are available for this account.':
      'لا توجد إشعارات متاحة لهذا الحساب.',
  'The workbook is generated by SafeContracts on the server using your current authorized dashboard filters.':
      'يتم إنشاء ملف Excel على خادم SafeContracts باستخدام فلاتر لوحة التحكم الحالية المسموح بها.',
  'Current filters': 'الفلاتر الحالية',
  'Any date': 'أي تاريخ',
  'Generating Excel…': 'جارٍ إنشاء ملف Excel…',
  'Download Excel': 'تنزيل Excel',
  'Excel export is not authorized for this session.':
      'تصدير Excel غير مسموح به في هذه الجلسة.',
  'Excel export ready': 'ملف Excel جاهز',
  'Saved in app cache:': 'تم الحفظ في ذاكرة التطبيق المؤقتة:',
  'Rows exported': 'الصفوف المصدرة',
  'Clear result': 'مسح النتيجة',
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
  'Push registration': 'تسجيل الإشعارات',
  'Granted capabilities': 'الصلاحيات الممنوحة',
  'Registered devices': 'الأجهزة المسجلة',
  'Clear local session state': 'مسح حالة الجلسة المحلية',
  'Push notifications are disabled by mobile configuration.':
      'إشعارات Push معطلة من إعدادات الموبايل.',
  'Enable Push notifications in SafeContracts → Mobile Configuration.':
      'فعّل إشعارات Push من SafeContracts ← إعدادات الموبايل.',
  'Device registered with SafeContracts': 'الجهاز مسجل في SafeContracts',
  'Device registration is not complete': 'تسجيل الجهاز غير مكتمل',
  'Notification permission': 'إذن الإشعارات',
  'FCM token acquired': 'تم الحصول على رمز FCM',
  'Backend registration': 'التسجيل على الخادم',
  'Diagnostic code': 'كود التشخيص',
  'Android notification permission is denied. The device can still register, but notification display remains blocked until permission is enabled.':
      'إذن إشعارات Android مرفوض. يمكن تسجيل الجهاز، لكن عرض الإشعارات سيظل متوقفاً حتى يتم السماح بالإذن.',
  'Retry device registration': 'إعادة محاولة تسجيل الجهاز',
  'Loading device state…': 'جارٍ تحميل حالة الجهاز…',
  'Device state is unavailable.': 'حالة الجهاز غير متاحة.',
  'No registered devices are currently visible.':
      'لا توجد أجهزة مسجلة ظاهرة حالياً.',
  'No last-seen timestamp': 'لا يوجد وقت لآخر ظهور',
  'Last seen': 'آخر ظهور',
  'Allowed': 'مسموح',
  'Provisional': 'مؤقت',
  'Denied': 'مرفوض',
  'Unknown': 'غير معروف',
  'Not started': 'لم يبدأ',
  'Registering…': 'جارٍ التسجيل…',
  'Registered': 'مسجل',
  'Failed': 'فشل',
};
