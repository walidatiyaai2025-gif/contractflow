<?php
/**
 * Backend-editable public homepage button destinations.
 *
 * @package SafeContracts_OnePage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SAFECONTRACTS_THEME_BUTTON_LINKS_OPTION = 'safecontracts_theme_button_links';

/**
 * Resolve default destinations while preserving legacy Customizer settings.
 *
 * @return array<string,string>
 */
function safecontracts_default_button_links() {
	$demo_url  = get_theme_mod( 'safecontracts_demo_url', '#contact' );
	$login_url = get_theme_mod( 'safecontracts_login_url', wp_login_url() );

	return array(
		'header_demo'    => $demo_url ? (string) $demo_url : '#contact',
		'header_login'   => $login_url ? (string) $login_url : wp_login_url(),
		'hero_primary'   => $demo_url ? (string) $demo_url : '#contact',
		'hero_secondary' => '#benefits',
		'final_cta'      => $demo_url ? (string) $demo_url : '#contact',
	);
}

/**
 * Sanitize a public button destination.
 *
 * Supports full URLs, site-relative paths and same-page anchors.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function safecontracts_sanitize_button_link_value( $value ) {
	$value = is_scalar( $value ) ? trim( wp_unslash( (string) $value ) ) : '';
	if ( '' === $value ) {
		return '';
	}

	if ( '#' === $value[0] ) {
		$anchor = preg_replace( '/[^A-Za-z0-9_-]/', '', substr( $value, 1 ) );
		return $anchor ? '#' . $anchor : '#';
	}

	return esc_url_raw( $value );
}

/**
 * Sanitize saved link map.
 *
 * @param mixed $input Raw option.
 * @return array<string,string>
 */
function safecontracts_sanitize_button_links( $input ) {
	$defaults = safecontracts_default_button_links();
	$input    = is_array( $input ) ? $input : array();
	$clean    = array();

	foreach ( $defaults as $key => $default ) {
		$value         = isset( $input[ $key ] ) ? safecontracts_sanitize_button_link_value( $input[ $key ] ) : '';
		$clean[ $key ] = '' !== $value ? $value : $default;
	}

	return $clean;
}

/**
 * Get all effective button links.
 *
 * @return array<string,string>
 */
function safecontracts_button_links() {
	$saved = get_option( SAFECONTRACTS_THEME_BUTTON_LINKS_OPTION, array() );
	$saved = is_array( $saved ) ? $saved : array();
	return wp_parse_args( $saved, safecontracts_default_button_links() );
}

/**
 * Get one effective button destination.
 *
 * @param string $key Destination key.
 * @return string
 */
function safecontracts_button_link( $key ) {
	$links = safecontracts_button_links();
	return isset( $links[ $key ] ) ? (string) $links[ $key ] : '#';
}

/**
 * Register link settings in the existing SafeContracts Home settings group.
 *
 * @return void
 */
function safecontracts_register_button_link_settings() {
	register_setting(
		'safecontracts_home_settings',
		SAFECONTRACTS_THEME_BUTTON_LINKS_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'safecontracts_sanitize_button_links',
			'default'           => safecontracts_default_button_links(),
		)
	);
}
add_action( 'admin_init', 'safecontracts_register_button_link_settings' );

/**
 * Add a fourth Links tab to Appearance > SafeContracts Home without coupling
 * the existing content/color editor implementation to URL handling.
 *
 * @return void
 */
function safecontracts_render_button_links_admin_extension() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'appearance_page_safecontracts-home' !== $screen->id || ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	$links  = safecontracts_button_links();
	$fields = array(
		'header_demo'    => array( 'Request Demo — Header / طلب عرض — الهيدر', 'Header request-demo button destination.' ),
		'header_login'   => array( 'Login / تسجيل الدخول', 'Login button destination.' ),
		'hero_primary'   => array( 'Hero Primary / زر الهيرو الأساسي', 'Main hero button destination.' ),
		'hero_secondary' => array( 'Hero Secondary / زر الهيرو الثانوي', 'Secondary hero button destination, e.g. #benefits.' ),
		'final_cta'      => array( 'Final CTA / زر الدعوة الأخير', 'Bottom call-to-action button destination.' ),
	);
	?>
	<template id="sc-links-panel-template">
		<div class="sc-admin-card sc-admin-design-card">
			<h3>Button Links / روابط الأزرار</h3>
			<p>Use a full URL, a site-relative path, or a same-page anchor such as <code>#benefits</code>. / استخدم رابط كامل أو مسار داخل الموقع أو Anchor داخل الصفحة.</p>
			<?php foreach ( $fields as $key => $meta ) : ?>
				<div class="sc-admin-field">
					<label for="sc-link-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $meta[0] ); ?></label>
					<div>
						<input id="sc-link-<?php echo esc_attr( $key ); ?>" type="text" class="regular-text" name="<?php echo esc_attr( SAFECONTRACTS_THEME_BUTTON_LINKS_OPTION . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $links[ $key ] ); ?>" dir="ltr">
						<p class="description"><?php echo esc_html( $meta[1] ); ?></p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</template>
	<script>
	(function(){
		const tabs=document.querySelector('.sc-admin-tabs');
		const form=tabs?tabs.closest('form'):null;
		const template=document.getElementById('sc-links-panel-template');
		if(!tabs||!form||!template){return;}

		const button=document.createElement('button');
		button.type='button';
		button.className='button';
		button.dataset.scTab='links';
		button.textContent='Links / الروابط';
		tabs.appendChild(button);

		const panel=document.createElement('div');
		panel.className='sc-admin-panel';
		panel.dataset.scPanel='links';
		panel.appendChild(template.content.cloneNode(true));
		const submit=form.querySelector('.submit');
		form.insertBefore(panel,submit||null);

		button.addEventListener('click',function(){
			document.querySelectorAll('[data-sc-tab]').forEach(function(item){item.classList.remove('button-primary','is-active');});
			document.querySelectorAll('[data-sc-panel]').forEach(function(item){item.classList.remove('is-active');});
			button.classList.add('button-primary','is-active');
			panel.classList.add('is-active');
		});

		document.querySelectorAll('[data-sc-tab]:not([data-sc-tab="links"])').forEach(function(oldButton){
			oldButton.addEventListener('click',function(){button.classList.remove('button-primary','is-active');panel.classList.remove('is-active');});
		});
	})();
	</script>
	<?php
}
add_action( 'admin_footer', 'safecontracts_render_button_links_admin_extension' );
