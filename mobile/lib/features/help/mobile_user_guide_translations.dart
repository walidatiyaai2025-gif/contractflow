import '../../core/localization/safecontracts_localizations.dart';

String mobileGuideText(
  SafeContractsLocalizations l10n,
  String english,
) {
  final translated = l10n.t(english);
  if (!l10n.isArabic || translated != english) return translated;
  return _arabic[english] ?? english;
}

const Map<String, String> _arabic = <String, String>{
  'User Guide': 'دليل المستخدم',
  'How to use Alkenzy ADV': 'كيفية استخدام Alkenzy ADV',
  'Only sections available to your account are shown.':
      'يتم عرض الأقسام المتاحة لحسابك فقط.',
  'Choose records by name from available lists. Internal IDs and system codes are not user inputs.':
      'اختر السجلات بالاسم من القوائم المتاحة. الأرقام الداخلية وأكواد النظام ليست مدخلات للمستخدم.',
  'What this area does': 'وظيفة هذا القسم',
  'Recommended steps': 'الخطوات المقترحة',
  'Go to {area}': 'الانتقال إلى {area}',
  'Dashboard shows your current operational position and the most important payment indicators.':
      'تعرض لوحة التحكم الوضع التشغيلي الحالي وأهم مؤشرات الدفعات.',
  'Review the indicators and active filters first.':
      'راجع المؤشرات والفلاتر النشطة أولاً.',
  'Open the related customer, contract or payment list when you need the source records.':
      'افتح قائمة العميل أو العقد أو الدفعة المرتبطة عندما تحتاج إلى السجلات المصدرية.',
  'Customers contains the customer records available in your authorized scope.':
      'يحتوي قسم العملاء على سجلات العملاء المتاحة ضمن نطاق صلاحياتك.',
  'Search for an existing customer before creating a new record.':
      'ابحث عن العميل الحالي قبل إنشاء سجل جديد.',
  'Open the customer to review its authorized details and related work.':
      'افتح العميل لمراجعة بياناته المصرح بها والعمل المرتبط به.',
  'Suppliers contains supplier records used by payable contracts and finance operations.':
      'يحتوي قسم الموردين على سجلات الموردين المستخدمة في العقود الدائنة والعمليات المالية.',
  'Find the supplier by name before starting supplier-side work.':
      'ابحث عن المورد بالاسم قبل بدء العمل الخاص بالمورد.',
  'Open Contracts or Finance for the supplier-related obligations.':
      'افتح العقود أو المالية لمراجعة الالتزامات المرتبطة بالمورد.',
  'Contracts contains customer receivable and supplier payable agreements available to your account.':
      'يحتوي قسم العقود على عقود العملاء المدينة وعقود الموردين الدائنة المتاحة لحسابك.',
  'Choose the business entity from the provided list instead of typing an internal ID.':
      'اختر جهة الأعمال من القائمة المتاحة بدلاً من كتابة رقم داخلي.',
  'Review dates, direction and financial values before saving or editing a contract.':
      'راجع التواريخ واتجاه العقد والقيم المالية قبل حفظ العقد أو تعديله.',
  'Payments contains contractual due schedule entries and their remaining balances.':
      'يحتوي قسم الدفعات على بنود جدول الاستحقاق التعاقدي وأرصدتها المتبقية.',
  'Use filters to find the required payment by business context.':
      'استخدم الفلاتر للوصول إلى الدفعة المطلوبة من خلال سياق الأعمال.',
  'Open collection entry only when the selected payment is the one you intend to settle.':
      'افتح تسجيل التحصيل فقط بعد التأكد أن الدفعة المختارة هي المطلوب تسويتها.',
  'Finance keeps Accounts Payable and Accounts Receivable separated by direction and currency.':
      'يفصل قسم المالية بين المستحقات الدائنة والمدينة حسب الاتجاه والعملة.',
  'Start from the summary, aging or work queue that matches your task.':
      'ابدأ من الملخص أو أعمار الديون أو قائمة العمل المناسبة لمهمتك.',
  'Open the related contract or counterparty when you need source details.':
      'افتح العقد أو جهة التعاقد المرتبطة عندما تحتاج إلى التفاصيل المصدرية.',
  'Collections records money received against authorized receivable payments.':
      'يسجل قسم التحصيلات الأموال المستلمة مقابل الدفعات المدينة المصرح بها.',
  'Choose the payment and payment method from the provided lists.':
      'اختر الدفعة وطريقة السداد من القوائم المتاحة.',
  'Review amount, date and reference before recording the receipt.':
      'راجع المبلغ والتاريخ والمرجع قبل تسجيل التحصيل.',
  'Follow-up tracks contact and escalation activity for outstanding receivables.':
      'يتابع قسم المتابعة التواصل والتصعيد للمستحقات المدينة القائمة.',
  'Choose the outstanding payment from the queue instead of entering a payment ID.':
      'اختر الدفعة القائمة من قائمة المتابعة بدلاً من إدخال رقم الدفعة.',
  'Review previous follow-up history before adding the next action.':
      'راجع سجل المتابعة السابق قبل إضافة الإجراء التالي.',
  'Notifications shows notification activity available to this mobile configuration.':
      'يعرض قسم الإشعارات نشاط الإشعارات المتاح وفق إعدادات الموبايل.',
  'Open a notification to follow its supported business destination.':
      'افتح الإشعار للانتقال إلى وجهة الأعمال المدعومة المرتبطة به.',
  'Use the destination screen for the actual business action.':
      'استخدم الشاشة المرتبطة لتنفيذ إجراء الأعمال الفعلي.',
  'Excel export creates authorized report output for the current scope.':
      'ينشئ تصدير Excel مخرجات التقارير المصرح بها ضمن النطاق الحالي.',
  'Review the active scope and filters before creating an export.':
      'راجع النطاق والفلاتر النشطة قبل إنشاء التصدير.',
  'Use the exported file only for the authorized business purpose.':
      'استخدم الملف المصدر فقط لغرض الأعمال المصرح به.',
  'Profile contains your session, language and mobile account settings.':
      'يحتوي الملف الشخصي على الجلسة واللغة وإعدادات حساب الموبايل.',
  'Use language settings here when you need to switch English or Arabic.':
      'استخدم إعداد اللغة هنا عند الحاجة للتبديل بين الإنجليزية والعربية.',
  'Use sign-out or session controls here rather than changing authentication data elsewhere.':
      'استخدم تسجيل الخروج أو عناصر التحكم في الجلسة هنا بدلاً من تغيير بيانات المصادقة في مكان آخر.',
};

Map<String, String> mobileGuideArabicDefaults() =>
    Map<String, String>.unmodifiable(_arabic);
