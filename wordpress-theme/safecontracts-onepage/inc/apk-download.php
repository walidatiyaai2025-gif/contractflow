<?php
/**
 * Safe Contracts public APK download configuration.
 *
 * The public CTA deliberately points only to this repository's HTTPS GitHub
 * release assets. CI artifacts are not used because they expire and the
 * candidate APK is signed with a short-lived CI-only key.
 *
 * @package SafeContracts_OnePage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SAFECONTRACTS_APK_DOWNLOAD_OPTION = 'safecontracts_apk_download';
const SAFECONTRACTS_APK_RELEASE_URL = 'https://github.com/walidatiyaai2025-gif/contractflow/releases/download/mobile-latest/SafeContracts-Mobile.apk';

/**
 * @return array{enabled:bool,url:string,label_ar:string,label_en:string}
 */
function safecontracts_apk_download_defaults() {
	return array(
		'enabled'  => false,
		'url'      => SAFECONTRACTS_APK_RELEASE_URL,
		'label_ar' => 'تحميل تطبيق أندرويد',
		'label_en' => 'Download Android App',
	);
}

/**
 * Accept only the canonical repository release path over HTTPS.
 *
 * @param mixed $url Raw URL.
 * @return string
 */
function safecontracts_sanitize_apk_download_url( $url ) {
	$url = is_scalar( $url ) && ! is_bool( $url ) ? trim( wp_unslash( (string) $url ) ) : '';
	if ( '' === $url ) {
		return '';
	}

	$clean  = esc_url_raw( $url, array( 'https' ) );
	$parsed = wp_parse_url( $clean );
	if ( ! is_array( $parsed ) ) {
		return '';
	}

	$scheme = strtolower( (string) ( $parsed['scheme'] ?? '' ) );
	$host   = strtolower( (string) ( $parsed['host'] ?? '' ) );
	$path   = (string) ( $parsed['path'] ?? '' );
	$prefix = '/walidatiyaai2025-gif/contractflow/releases/';
	if ( 'https' !== $scheme || 'github.com' !== $host || ! str_starts_with( $path, $prefix ) ) {
		return '';
	}

	return $clean;
}

/**
 * @param mixed $input Raw option.
 * @return array{enabled:bool,url:string,label_ar:string,label_en:string}
 */
function safecontracts_sanitize_apk_download_settings( $input ) {
	$defaults = safecontracts_apk_download_defaults();
	$input    = is_array( $input ) ? $input : array();
	$url      = safecontracts_sanitize_apk_download_url( $input['url'] ?? '' );

	return array(
		'enabled'  => ! empty( $input['enabled'] ) && '' !== $url,
		'url'      => $url,
		'label_ar' => sanitize_text_field( wp_unslash( (string) ( $input['label_ar'] ?? $defaults['label_ar'] ) ) ),
		'label_en' => sanitize_text_field( wp_unslash( (string) ( $input['label_en'] ?? $defaults['label_en'] ) ) ),
	);
}

/**
 * @return array{enabled:bool,url:string,label_ar:string,label_en:string}
 */
function safecontracts_apk_download_settings() {
	$saved = get_option( SAFECONTRACTS_APK_DOWNLOAD_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array_merge( safecontracts_apk_download_defaults(), $saved );
}

/**
 * @return string Empty when the CTA is disabled or not trusted.
 */
function safecontracts_apk_download_url() {
	$settings = safecontracts_apk_download_settings();
	if ( empty( $settings['enabled'] ) ) {
		return '';
	}
	return safecontracts_sanitize_apk_download_url( $settings['url'] );
}

/**
 * @return string
 */
function safecontracts_apk_download_label() {
	$settings = safecontracts_apk_download_settings();
	$key      = 'ar' === safecontracts_current_lang() ? 'label_ar' : 'label_en';
	$label    = trim( (string) ( $settings[ $key ] ?? '' ) );
	if ( '' !== $label ) {
		return $label;
	}
	$defaults = safecontracts_apk_download_defaults();
	return $defaults[ $key ];
}

/**
 * @return void
 */
function safecontracts_register_apk_download_setting() {
	register_setting(
		'safecontracts_apk_settings',
		SAFECONTRACTS_APK_DOWNLOAD_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'safecontracts_sanitize_apk_download_settings',
			'default'           => safecontracts_apk_download_defaults(),
		)
	);
}
add_action( 'admin_init', 'safecontracts_register_apk_download_setting' );

/**
 * @return void
 */
function safecontracts_add_apk_download_admin_page() {
	add_theme_page(
		__( 'Safe Contracts APK', 'safecontracts-onepage' ),
		__( 'Safe Contracts APK', 'safecontracts-onepage' ),
		'edit_theme_options',
		'safecontracts-apk',
		'safecontracts_render_apk_download_admin_page'
	);
}
add_action( 'admin_menu', 'safecontracts_add_apk_download_admin_page' );

/**
 * @return void
 */
function safecontracts_render_apk_download_admin_page() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	$settings = safecontracts_apk_download_settings();
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'Safe Contracts APK download', 'safecontracts-onepage' ); ?></h1>
		<p><?php echo esc_html__( 'Controls the public Android download button. Keep it disabled until the GitHub release asset is production-signed with the same application signing identity as the installed app.', 'safecontracts-onepage' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'safecontracts_apk_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php echo esc_html__( 'Public download button', 'safecontracts-onepage' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( SAFECONTRACTS_APK_DOWNLOAD_OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> <?php echo esc_html__( 'Show the APK download button on the public site', 'safecontracts-onepage' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="safecontracts-apk-url"><?php echo esc_html__( 'GitHub release URL', 'safecontracts-onepage' ); ?></label></th>
					<td><input id="safecontracts-apk-url" class="regular-text code" type="url" required name="<?php echo esc_attr( SAFECONTRACTS_APK_DOWNLOAD_OPTION ); ?>[url]" value="<?php echo esc_attr( (string) $settings['url'] ); ?>"><p class="description"><?php echo esc_html__( 'Only HTTPS release URLs under this Safe Contracts GitHub repository are accepted.', 'safecontracts-onepage' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="safecontracts-apk-label-en"><?php echo esc_html__( 'English button label', 'safecontracts-onepage' ); ?></label></th>
					<td><input id="safecontracts-apk-label-en" class="regular-text" type="text" maxlength="80" name="<?php echo esc_attr( SAFECONTRACTS_APK_DOWNLOAD_OPTION ); ?>[label_en]" value="<?php echo esc_attr( (string) $settings['label_en'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="safecontracts-apk-label-ar"><?php echo esc_html__( 'Arabic button label', 'safecontracts-onepage' ); ?></label></th>
					<td><input id="safecontracts-apk-label-ar" class="regular-text" type="text" maxlength="80" dir="rtl" name="<?php echo esc_attr( SAFECONTRACTS_APK_DOWNLOAD_OPTION ); ?>[label_ar]" value="<?php echo esc_attr( (string) $settings['label_ar'] ); ?>"></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
