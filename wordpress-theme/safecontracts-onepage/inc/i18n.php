<?php
/**
 * Bilingual copy for the public SafeContracts landing page.
 *
 * @package SafeContracts_OnePage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the requested public language.
 *
 * The one-page site intentionally keeps language selection theme-local so it
 * does not interfere with the SafeContracts plugin or WordPress admin locale.
 *
 * @return string ar|en
 */
function safecontracts_current_lang() {
	if ( isset( $_GET['lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$lang = sanitize_key( wp_unslash( $_GET['lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( in_array( $lang, array( 'ar', 'en' ), true ) ) {
			return $lang;
		}
	}

	$locale = determine_locale();
	return 0 === strpos( strtolower( $locale ), 'ar' ) ? 'ar' : 'en';
}

/**
 * Get current document direction.
 *
 * @return string rtl|ltr
 */
function safecontracts_direction() {
	return 'ar' === safecontracts_current_lang() ? 'rtl' : 'ltr';
}

/**
 * Landing-page translations.
 *
 * @return array<string,mixed>
 */
function safecontracts_copy() {
	$copy = array(
		'ar' => array(
			'brand_tagline' => 'إدارة العقود بذكاء وأمان',
			'nav' => array(
				'home' => 'الرئيسية',
				'benefits' => 'المزايا',
				'use_cases' => 'الاستخدامات',
				'dashboards' => 'لوحات التحكم',
				'faq' => 'الأسئلة الشائعة',
				'contact' => 'تواصل معنا',
			),
			'actions' => array(
				'demo' => 'اطلب عرضًا',
				'login' => 'تسجيل الدخول',
				'get_started' => 'ابدأ الآن',
				'explore' => 'شاهد المزايا',
			),
			'hero' => array(
				'eyebrow' => 'منصة موحدة لدورة حياة العقد',
				'title' => 'إدارة العقود بذكاء وأمان',
				'text' => 'نظام موحد لإدارة العقود والمتابعة والصلاحيات والتنبيهات والتقارير والتوقيع الرقمي من مكان واحد.',
				'points' => array( 'صلاحيات مرنة', 'تنبيهات تلقائية', 'تقارير فورية', 'أمان عالي' ),
			),
			'benefits_title' => 'فوائد النظام',
			'benefits_intro' => 'كل ما تحتاجه فرق الإدارة والقانونية والمشتريات لمتابعة العقود بوضوح ومن دون تشتيت.',
			'benefits' => array(
				array( 'title' => 'إدارة مركزية للعقود', 'text' => 'تنظيم كل العقود والمرفقات في مكان واحد.', 'icon' => 'folder' ),
				array( 'title' => 'صلاحيات حسب الدور', 'text' => 'عرض البيانات والأوامر حسب المستخدم وصلاحياته.', 'icon' => 'users' ),
				array( 'title' => 'تنبيهات ومواعيد', 'text' => 'تنبيهات ذكية قبل الانتهاء أو التجديد والاستحقاقات.', 'icon' => 'bell' ),
				array( 'title' => 'لوحات تحكم وتقارير', 'text' => 'مؤشرات فورية وتقارير قابلة للتصفية والتصدير.', 'icon' => 'chart' ),
				array( 'title' => 'سير عمل الموافقات', 'text' => 'مراجعة واعتماد العقود بخطوات واضحة وقابلة للتتبع.', 'icon' => 'workflow' ),
				array( 'title' => 'أرشفة وبحث سريع', 'text' => 'الوصول السريع لأي عقد أو مرفق أو معلومة مهمة.', 'icon' => 'search' ),
			),
			'use_cases_title' => 'استخدامات النظام',
			'use_cases_intro' => 'مصمم ليتعامل مع دورة العقد اليومية من لحظة الإنشاء وحتى التجديد والأرشفة.',
			'use_cases' => array(
				array( 'title' => 'إدارة عقود العملاء', 'text' => 'إدارة العلاقة التعاقدية والمتطلبات والموافقات في مساحة واحدة.', 'image' => 'handshake.svg' ),
				array( 'title' => 'متابعة التجديدات والانتهاء', 'text' => 'مراقبة تواريخ الاستحقاق والتجديد مع تنبيهات مبكرة.', 'image' => 'reminders.svg' ),
				array( 'title' => 'إدارة المرفقات والمستندات', 'text' => 'حفظ الملفات والإصدارات والمرفقات وربطها بالعقد الصحيح.', 'image' => 'documents.svg' ),
				array( 'title' => 'التقارير والملخصات للإدارة', 'text' => 'مؤشرات تساعد الإدارة على معرفة الحالة والمخاطر والأداء سريعًا.', 'image' => 'analytics.svg' ),
			),
			'workflow_title' => 'كيف يعمل؟',
			'workflow' => array(
				array( 'title' => 'إنشاء العقد', 'text' => 'إدخال بيانات العقد ورفع المرفقات.', 'icon' => 'document' ),
				array( 'title' => 'المراجعة', 'text' => 'مراجعة المعنيين وإضافة الملاحظات.', 'icon' => 'users' ),
				array( 'title' => 'الاعتماد', 'text' => 'اعتماد العقد من الجهات المخولة.', 'icon' => 'shield' ),
				array( 'title' => 'المتابعة والتجديد', 'text' => 'متابعة الالتزامات والتنبيهات والتجديد.', 'icon' => 'refresh' ),
			),
			'dashboard_title' => 'لوحات تحكم ذكية',
			'dashboard_text' => 'رؤية سريعة لحالة العقود مع فلاتر حسب العميل أو العقد أو الحالة، من الكمبيوتر أو الجوال.',
			'dashboard_points' => array( 'نظرة عامة على جميع العقود', 'العقود النشطة والمنتهية', 'تنبيهات ومواعيد قادمة', 'تصدير التقارير إلى Excel', 'اختيار عميل أو عقد محدد', 'صلاحيات حسب الدور', 'متوافق مع الجوال' ),
			'security_title' => 'الأمان والتحكم',
			'security_intro' => 'طبقات واضحة للتحكم في الوصول، التتبع، النسخ الاحتياطي وحماية معلومات العقود.',
			'security' => array(
				array( 'title' => 'صلاحيات متعددة المستويات', 'text' => 'تحديد صلاحيات دقيقة لكل مستخدم حسب الدور والإدارة.' ),
				array( 'title' => 'سجل نشاط كامل', 'text' => 'تتبع العمليات والتغييرات المهمة لدعم المراجعة والمساءلة.' ),
				array( 'title' => 'نسخ احتياطي وأرشفة', 'text' => 'حماية المستندات والإصدارات وسهولة الرجوع إليها.' ),
				array( 'title' => 'حماية البيانات', 'text' => 'تصميم يراعي الوصول المصرح والخصوصية وممارسات الأمان.' ),
			),
			'faq_title' => 'الأسئلة الشائعة',
			'faq' => array(
				array( 'q' => 'هل يدعم النظام تعدد المستخدمين؟', 'a' => 'نعم، ويمكن توزيع الوصول والوظائف حسب الدور والصلاحيات الممنوحة لكل مستخدم.' ),
				array( 'q' => 'هل يمكن تصدير التقارير؟', 'a' => 'نعم، صُممت لوحات التحكم لتدعم التقارير والمرشحات وخيارات التصدير المتاحة في النظام.' ),
				array( 'q' => 'هل يعمل على الجوال؟', 'a' => 'نعم، الواجهة العامة متجاوبة، كما أن SafeContracts يدعم تجربة مخصصة للوصول من الهاتف حسب صلاحيات المستخدم.' ),
				array( 'q' => 'هل يمكن متابعة انتهاء وتجديد العقود؟', 'a' => 'نعم، يركز النظام على تواريخ الاستحقاق والتنبيهات والمتابعة لتقليل مخاطر فوات المواعيد.' ),
			),
			'cta' => array(
				'title' => 'جاهز لتنظيم عقودك بشكل احترافي؟',
				'text' => 'ابدأ مع SafeContracts واستفد من إدارة أسهل، متابعة أدق، وقرارات أسرع.',
				'button' => 'احجز عرضًا توضيحيًا',
			),
			'footer' => array( 'about' => 'عن النظام', 'support' => 'الدعم', 'privacy' => 'الخصوصية', 'contact' => 'اتصل بنا' ),
		),
		'en' => array(
			'brand_tagline' => 'Smart & Secure Contract Management',
			'nav' => array(
				'home' => 'Home',
				'benefits' => 'Features',
				'use_cases' => 'Use Cases',
				'dashboards' => 'Dashboards',
				'faq' => 'FAQ',
				'contact' => 'Contact Us',
			),
			'actions' => array(
				'demo' => 'Request Demo',
				'login' => 'Login',
				'get_started' => 'Get Started',
				'explore' => 'Explore Features',
			),
			'hero' => array(
				'eyebrow' => 'One workspace for the contract lifecycle',
				'title' => 'Smart & Secure Contract Management',
				'text' => 'Centralize contracts, approvals, permissions, reminders, reporting and digital workflows in one organized workspace.',
				'points' => array( 'Flexible Permissions', 'Automated Reminders', 'Instant Reports', 'Strong Security' ),
			),
			'benefits_title' => 'Benefits',
			'benefits_intro' => 'Everything management, legal and procurement teams need to keep contracts visible, controlled and moving.',
			'benefits' => array(
				array( 'title' => 'Centralized Contract Repository', 'text' => 'Keep contracts and related attachments organized in one place.', 'icon' => 'folder' ),
				array( 'title' => 'Role-Based Permissions', 'text' => 'Show data and actions according to each user’s assigned access.', 'icon' => 'users' ),
				array( 'title' => 'Smart Reminders', 'text' => 'Surface renewal, expiry and obligation dates before they become urgent.', 'icon' => 'bell' ),
				array( 'title' => 'Reports & Analytics', 'text' => 'Use filterable dashboards and export-ready management views.', 'icon' => 'chart' ),
				array( 'title' => 'Approval Workflows', 'text' => 'Move contracts through review and approval with a traceable process.', 'icon' => 'workflow' ),
				array( 'title' => 'Fast Search & Archive', 'text' => 'Reach the right contract, attachment or key detail quickly.', 'icon' => 'search' ),
			),
			'use_cases_title' => 'Use Cases',
			'use_cases_intro' => 'Built around the daily contract lifecycle, from creation and review to renewals and archiving.',
			'use_cases' => array(
				array( 'title' => 'Client Contract Management', 'text' => 'Manage contractual relationships, requirements and approvals in one workspace.', 'image' => 'handshake.svg' ),
				array( 'title' => 'Renewals & Expirations', 'text' => 'Track due dates and renewal windows with early reminders.', 'image' => 'reminders.svg' ),
				array( 'title' => 'Documents & Attachments', 'text' => 'Keep files, versions and supporting documents attached to the right contract.', 'image' => 'documents.svg' ),
				array( 'title' => 'Management Reporting', 'text' => 'Give decision-makers a fast view of contract status, risk and performance.', 'image' => 'analytics.svg' ),
			),
			'workflow_title' => 'How It Works',
			'workflow' => array(
				array( 'title' => 'Create or Upload', 'text' => 'Add contract data and supporting documents.', 'icon' => 'document' ),
				array( 'title' => 'Review', 'text' => 'Collaborate with stakeholders and capture feedback.', 'icon' => 'users' ),
				array( 'title' => 'Approve', 'text' => 'Route the contract to authorized approvers.', 'icon' => 'shield' ),
				array( 'title' => 'Track & Renew', 'text' => 'Monitor obligations, reminders and renewal dates.', 'icon' => 'refresh' ),
			),
			'dashboard_title' => 'Smart Dashboards',
			'dashboard_text' => 'See contract status at a glance and filter by client, contract or status from desktop or mobile.',
			'dashboard_points' => array( 'Portfolio-wide overview', 'Active and expired contracts', 'Upcoming reminders and dates', 'Excel-ready reporting', 'Client or contract filtering', 'Role-based visibility', 'Mobile-friendly access' ),
			'security_title' => 'Security & Control',
			'security_intro' => 'Clear layers for access control, traceability, backup and protection of contract information.',
			'security' => array(
				array( 'title' => 'Multi-Level Permissions', 'text' => 'Assign precise access according to role and organizational responsibility.' ),
				array( 'title' => 'Complete Activity Trail', 'text' => 'Track important actions and changes for review and accountability.' ),
				array( 'title' => 'Backup & Archiving', 'text' => 'Protect documents and versions while keeping them easy to retrieve.' ),
				array( 'title' => 'Data Protection', 'text' => 'Designed around authorized access, privacy and sound security practices.' ),
			),
			'faq_title' => 'Frequently Asked Questions',
			'faq' => array(
				array( 'q' => 'Does SafeContracts support multiple users?', 'a' => 'Yes. Access and actions can be scoped according to the role and permissions assigned to each user.' ),
				array( 'q' => 'Can reports be exported?', 'a' => 'Yes. The reporting experience is designed around filtered management views and the export capabilities available in the system.' ),
				array( 'q' => 'Does it work on mobile?', 'a' => 'Yes. The public website is responsive and SafeContracts also supports a dedicated mobile access experience based on user permissions.' ),
				array( 'q' => 'Can it track contract expiry and renewals?', 'a' => 'Yes. Due dates, reminders and lifecycle follow-up are core parts of the contract-management workflow.' ),
			),
			'cta' => array(
				'title' => 'Ready to manage contracts professionally?',
				'text' => 'Start with SafeContracts for easier administration, more accurate follow-up and faster decisions.',
				'button' => 'Request a Demo',
			),
			'footer' => array( 'about' => 'About', 'support' => 'Support', 'privacy' => 'Privacy', 'contact' => 'Contact' ),
		),
	);

	return $copy[ safecontracts_current_lang() ];
}
