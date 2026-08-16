<?php
/**
 * Safe Contracts visual identity for wp-admin and wp-login.
 *
 * This file changes presentation only. It does not alter plugin permissions,
 * routes, data, or business logic.
 *
 * @package SafeContracts_OnePage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load branded admin styles.
 *
 * @return void
 */
function safecontracts_enqueue_admin_branding() {
	$version = wp_get_theme()->get( 'Version' );
	wp_enqueue_style(
		'safecontracts-admin',
		get_theme_file_uri( '/assets/css/admin.css' ),
		array(),
		$version
	);
	wp_enqueue_style(
		'safecontracts-brand',
		get_theme_file_uri( '/assets/css/brand.css' ),
		array( 'safecontracts-admin' ),
		$version
	);
}
add_action( 'admin_enqueue_scripts', 'safecontracts_enqueue_admin_branding' );

/**
 * Add a stable class for scoped backend styles.
 *
 * @param string $classes Existing admin body classes.
 * @return string
 */
function safecontracts_admin_body_class( $classes ) {
	return trim( $classes . ' safecontracts-admin-ui' );
}
add_filter( 'admin_body_class', 'safecontracts_admin_body_class' );

/**
 * Replace the WordPress logo item with a Safe Contracts home shortcut.
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin toolbar.
 * @return void
 */
function safecontracts_brand_admin_bar( $wp_admin_bar ) {
	$wp_admin_bar->remove_node( 'wp-logo' );
	$wp_admin_bar->add_node(
		array(
			'id'    => 'safecontracts-brand',
			'title' => '<img class="sc-adminbar-brand-image" src="' . esc_attr( safecontracts_brand_icon_data_uri() ) . '" alt=""><span class="sc-adminbar-brand-text">' . esc_html( safecontracts_brand_name() ) . '</span>',
			'href'  => admin_url(),
			'meta'  => array(
				'class' => 'safecontracts-adminbar-brand',
				'title' => safecontracts_brand_name() . ' Dashboard',
			),
		)
	);
}
add_action( 'admin_bar_menu', 'safecontracts_brand_admin_bar', 5 );

/**
 * Branded admin footer.
 *
 * @return string
 */
function safecontracts_admin_footer_text() {
	return '<strong>' . esc_html( safecontracts_brand_name() ) . '</strong> · Smart & Secure Contract Management';
}
add_filter( 'admin_footer_text', 'safecontracts_admin_footer_text' );

/**
 * Add a focused Safe Contracts dashboard card without removing core widgets.
 *
 * @return void
 */
function safecontracts_register_dashboard_widget() {
	wp_add_dashboard_widget(
		'safecontracts_dashboard_overview',
		safecontracts_brand_name(),
		'safecontracts_render_dashboard_widget'
	);
}
add_action( 'wp_dashboard_setup', 'safecontracts_register_dashboard_widget' );

/**
 * Render Safe Contracts dashboard overview.
 *
 * @return void
 */
function safecontracts_render_dashboard_widget() {
	$is_arabic = 0 === strpos( strtolower( determine_locale() ), 'ar' );
	$home_url  = home_url( '/' );
	$edit_url  = admin_url( 'themes.php?page=safecontracts-home' );
	?>
	<div class="sc-dashboard-welcome" dir="<?php echo $is_arabic ? 'rtl' : 'ltr'; ?>">
		<div class="sc-dashboard-welcome-head">
			<img class="sc-dashboard-brand-image" src="<?php echo esc_attr( safecontracts_brand_icon_data_uri() ); ?>" alt="" aria-hidden="true">
			<div>
				<strong><?php echo esc_html( safecontracts_brand_name() ); ?></strong>
				<span><?php echo esc_html( $is_arabic ? 'إدارة العقود بذكاء وأمان' : 'Smart & Secure Contract Management' ); ?></span>
			</div>
		</div>
		<p><?php echo esc_html( $is_arabic ? 'واجهة الإدارة تستخدم نفس الهوية البصرية للواجهة العامة مع الحفاظ على وظائف ووردبريس والنظام كما هي.' : 'The admin workspace now uses the same visual identity as the public site while preserving WordPress and Safe Contracts functionality.' ); ?></p>
		<div class="sc-dashboard-actions">
			<a class="button button-primary" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $is_arabic ? 'تعديل الصفحة الرئيسية' : 'Edit Homepage' ); ?></a>
			<a class="button" href="<?php echo esc_url( $home_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $is_arabic ? 'عرض الموقع' : 'View Website' ); ?></a>
		</div>
	</div>
	<?php
}

/**
 * Load login-page branding from the active theme.
 *
 * @return void
 */
function safecontracts_enqueue_login_branding() {
	$version = wp_get_theme()->get( 'Version' );
	wp_enqueue_style(
		'safecontracts-login',
		get_theme_file_uri( '/assets/css/login.css' ),
		array(),
		$version
	);
	wp_enqueue_style(
		'safecontracts-brand',
		get_theme_file_uri( '/assets/css/brand.css' ),
		array( 'safecontracts-login' ),
		$version
	);
	wp_add_inline_style(
		'safecontracts-brand',
		'body.login h1 a{background-image:url("' . safecontracts_brand_icon_data_uri() . '") !important;}'
	);
}
add_action( 'login_enqueue_scripts', 'safecontracts_enqueue_login_branding' );

/**
 * Point the login logo to the public site.
 *
 * @return string
 */
function safecontracts_login_logo_url() {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'safecontracts_login_logo_url' );

/**
 * Login logo accessible title.
 *
 * @return string
 */
function safecontracts_login_logo_title() {
	return safecontracts_brand_name();
}
add_filter( 'login_headertext', 'safecontracts_login_logo_title' );
