<?php
/**
 * WordPress admin editor for SafeContracts public-home content and button colors.
 *
 * @package SafeContracts_OnePage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SAFECONTRACTS_HOME_CONTENT_OPTION = 'safecontracts_home_content';
const SAFECONTRACTS_THEME_COLORS_OPTION = 'safecontracts_theme_button_colors';

/**
 * Button color defaults.
 *
 * @return array<string,string>
 */
function safecontracts_default_button_colors() {
	return array(
		'primary_start'       => '#0b5cc7',
		'primary_end'         => '#062d5b',
		'primary_text'        => '#ffffff',
		'secondary_background'=> '#ffffff',
		'secondary_border'    => '#9fb6d1',
		'secondary_text'      => '#062d5b',
		'cta_start'           => '#08a6a6',
		'cta_end'             => '#18c3b7',
		'cta_text'            => '#ffffff',
	);
}

/**
 * Recursively merge saved editable strings onto theme defaults.
 *
 * @param array<string,mixed> $defaults  Default copy.
 * @param array<string,mixed> $overrides Saved copy.
 * @return array<string,mixed>
 */
function safecontracts_merge_home_copy( $defaults, $overrides ) {
	foreach ( $defaults as $key => $default_value ) {
		if ( ! array_key_exists( $key, $overrides ) ) {
			continue;
		}

		if ( is_array( $default_value ) && is_array( $overrides[ $key ] ) ) {
			$defaults[ $key ] = safecontracts_merge_home_copy( $default_value, $overrides[ $key ] );
			continue;
		}

		if ( is_string( $default_value ) && is_string( $overrides[ $key ] ) ) {
			$defaults[ $key ] = $overrides[ $key ];
		}
	}

	return $defaults;
}

/**
 * Apply saved backend content to the currently requested public language.
 *
 * @param array<string,mixed> $defaults Language defaults.
 * @param string              $lang     ar|en.
 * @return array<string,mixed>
 */
function safecontracts_apply_home_overrides( $defaults, $lang ) {
	$saved = get_option( SAFECONTRACTS_HOME_CONTENT_OPTION, array() );
	if ( ! is_array( $saved ) || empty( $saved[ $lang ] ) || ! is_array( $saved[ $lang ] ) ) {
		return $defaults;
	}

	return safecontracts_merge_home_copy( $defaults, $saved[ $lang ] );
}

/**
 * Sanitize an editable content tree against the known theme structure.
 * Image/icon implementation keys are intentionally not editable here.
 *
 * @param mixed $input    User input.
 * @param mixed $defaults Known structure.
 * @return mixed
 */
function safecontracts_sanitize_content_tree( $input, $defaults ) {
	if ( ! is_array( $defaults ) ) {
		return is_scalar( $input ) ? sanitize_textarea_field( wp_unslash( (string) $input ) ) : '';
	}

	$input = is_array( $input ) ? $input : array();
	$clean = array();

	foreach ( $defaults as $key => $default_value ) {
		if ( in_array( (string) $key, array( 'icon', 'image' ), true ) ) {
			continue;
		}
		if ( ! array_key_exists( $key, $input ) ) {
			continue;
		}
		$clean[ $key ] = safecontracts_sanitize_content_tree( $input[ $key ], $default_value );
	}

	return $clean;
}

/**
 * Sanitize the bilingual homepage option.
 *
 * @param mixed $input Raw option value.
 * @return array<string,mixed>
 */
function safecontracts_sanitize_home_content( $input ) {
	$catalog = safecontracts_copy_catalog();
	$input   = is_array( $input ) ? $input : array();
	$clean   = array();

	foreach ( array( 'ar', 'en' ) as $lang ) {
		if ( isset( $input[ $lang ] ) && is_array( $input[ $lang ] ) ) {
			$clean[ $lang ] = safecontracts_sanitize_content_tree( $input[ $lang ], $catalog[ $lang ] );
		}
	}

	return $clean;
}

/**
 * Sanitize button colors.
 *
 * @param mixed $input Raw option value.
 * @return array<string,string>
 */
function safecontracts_sanitize_button_colors( $input ) {
	$defaults = safecontracts_default_button_colors();
	$input    = is_array( $input ) ? $input : array();
	$clean    = array();

	foreach ( $defaults as $key => $default ) {
		$value         = isset( $input[ $key ] ) ? sanitize_hex_color( wp_unslash( $input[ $key ] ) ) : '';
		$clean[ $key ] = $value ? $value : $default;
	}

	return $clean;
}

/**
 * Register settings and the theme admin page.
 *
 * @return void
 */
function safecontracts_register_home_admin_settings() {
	register_setting(
		'safecontracts_home_settings',
		SAFECONTRACTS_HOME_CONTENT_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'safecontracts_sanitize_home_content',
			'default'           => array(),
		)
	);

	register_setting(
		'safecontracts_home_settings',
		SAFECONTRACTS_THEME_COLORS_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'safecontracts_sanitize_button_colors',
			'default'           => safecontracts_default_button_colors(),
		)
	);
}
add_action( 'admin_init', 'safecontracts_register_home_admin_settings' );

/**
 * Add editor under Appearance.
 *
 * @return void
 */
function safecontracts_add_home_admin_page() {
	add_theme_page(
		__( 'SafeContracts Home', 'safecontracts-onepage' ),
		__( 'SafeContracts Home', 'safecontracts-onepage' ),
		'edit_theme_options',
		'safecontracts-home',
		'safecontracts_render_home_admin_page'
	);
}
add_action( 'admin_menu', 'safecontracts_add_home_admin_page' );

/**
 * Human-friendly field labels.
 *
 * @param string     $key  Current key.
 * @param int|string $index Optional list index.
 * @return string
 */
function safecontracts_admin_field_label( $key, $index = '' ) {
	$labels = array(
		'brand_tagline'    => 'Brand tagline / وصف العلامة',
		'home'             => 'Home / الرئيسية',
		'benefits'         => 'Benefits / المزايا',
		'use_cases'        => 'Use Cases / الاستخدامات',
		'dashboards'       => 'Dashboards / لوحات التحكم',
		'faq'              => 'FAQ / الأسئلة الشائعة',
		'contact'          => 'Contact / تواصل معنا',
		'demo'             => 'Request demo button / زر طلب العرض',
		'login'            => 'Login button / زر تسجيل الدخول',
		'get_started'      => 'Primary hero button / زر ابدأ الآن',
		'explore'          => 'Secondary hero button / زر شاهد المزايا',
		'eyebrow'          => 'Small heading / العنوان الصغير',
		'title'            => 'Title / العنوان',
		'text'             => 'Description / الوصف',
		'q'                => 'Question / السؤال',
		'a'                => 'Answer / الإجابة',
		'button'           => 'Button text / نص الزر',
		'benefits_title'   => 'Benefits title / عنوان المزايا',
		'benefits_intro'   => 'Benefits intro / مقدمة المزايا',
		'use_cases_title'  => 'Use cases title / عنوان الاستخدامات',
		'use_cases_intro'  => 'Use cases intro / مقدمة الاستخدامات',
		'workflow_title'   => 'Workflow title / عنوان كيف يعمل',
		'dashboard_title'  => 'Dashboard title / عنوان لوحات التحكم',
		'dashboard_text'   => 'Dashboard description / وصف لوحات التحكم',
		'security_title'   => 'Security title / عنوان الأمان',
		'security_intro'   => 'Security intro / مقدمة الأمان',
		'faq_title'        => 'FAQ title / عنوان الأسئلة الشائعة',
		'about'            => 'About / عن النظام',
		'support'          => 'Support / الدعم',
		'privacy'          => 'Privacy / الخصوصية',
	);

	if ( is_numeric( $key ) ) {
		return sprintf( '#%d', (int) $key + 1 );
	}

	$label = isset( $labels[ $key ] ) ? $labels[ $key ] : ucwords( str_replace( '_', ' ', (string) $key ) );
	return '' !== (string) $index ? $label . ' ' . $index : $label;
}

/**
 * Read a nested saved value while falling back to the default.
 *
 * @param mixed                $saved   Saved tree.
 * @param array<int|string>    $path    Nested path.
 * @param string               $default Default string.
 * @return string
 */
function safecontracts_admin_nested_value( $saved, $path, $default ) {
	$value = $saved;
	foreach ( $path as $part ) {
		if ( ! is_array( $value ) || ! array_key_exists( $part, $value ) ) {
			return $default;
		}
		$value = $value[ $part ];
	}
	return is_string( $value ) ? $value : $default;
}

/**
 * Convert a nested path to an option field name.
 *
 * @param string            $lang Language.
 * @param array<int|string> $path Path.
 * @return string
 */
function safecontracts_admin_field_name( $lang, $path ) {
	$name = SAFECONTRACTS_HOME_CONTENT_OPTION . '[' . $lang . ']';
	foreach ( $path as $part ) {
		$name .= '[' . $part . ']';
	}
	return $name;
}

/**
 * Render editable fields recursively.
 *
 * @param mixed             $defaults Default structure.
 * @param mixed             $saved    Saved structure.
 * @param string            $lang     ar|en.
 * @param array<int|string> $path     Current path.
 * @return void
 */
function safecontracts_render_content_tree( $defaults, $saved, $lang, $path = array() ) {
	if ( ! is_array( $defaults ) ) {
		$key       = end( $path );
		$value     = safecontracts_admin_nested_value( $saved, $path, (string) $defaults );
		$name      = safecontracts_admin_field_name( $lang, $path );
		$is_long   = in_array( (string) $key, array( 'text', 'a' ), true ) || mb_strlen( (string) $defaults ) > 85;
		$direction = 'ar' === $lang ? 'rtl' : 'ltr';
		?>
		<div class="sc-admin-field">
			<label><?php echo esc_html( safecontracts_admin_field_label( (string) $key ) ); ?></label>
			<?php if ( $is_long ) : ?>
				<textarea name="<?php echo esc_attr( $name ); ?>" rows="3" dir="<?php echo esc_attr( $direction ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
			<?php else : ?>
				<input type="text" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" dir="<?php echo esc_attr( $direction ); ?>">
			<?php endif; ?>
		</div>
		<?php
		return;
	}

	foreach ( $defaults as $key => $default_value ) {
		if ( in_array( (string) $key, array( 'icon', 'image' ), true ) ) {
			continue;
		}

		$child_path = array_merge( $path, array( $key ) );
		if ( is_array( $default_value ) ) {
			$is_list_item = is_numeric( $key );
			?>
			<div class="sc-admin-nested <?php echo $is_list_item ? 'is-list-item' : ''; ?>">
				<h4><?php echo esc_html( safecontracts_admin_field_label( (string) $key ) ); ?></h4>
				<?php safecontracts_render_content_tree( $default_value, $saved, $lang, $child_path ); ?>
			</div>
			<?php
		} else {
			safecontracts_render_content_tree( $default_value, $saved, $lang, $child_path );
		}
	}
}

/**
 * Render a grouped language editor.
 *
 * @param string              $lang     ar|en.
 * @param array<string,mixed> $defaults Defaults.
 * @param array<string,mixed> $saved    Saved tree.
 * @return void
 */
function safecontracts_render_language_editor( $lang, $defaults, $saved ) {
	$groups = array(
		'Brand & Navigation / الهوية والتنقل' => array( 'brand_tagline', 'nav', 'actions' ),
		'Hero / الهيرو'                       => array( 'hero' ),
		'Benefits / المزايا'                  => array( 'benefits_title', 'benefits_intro', 'benefits' ),
		'Use Cases / الاستخدامات'             => array( 'use_cases_title', 'use_cases_intro', 'use_cases' ),
		'How It Works / كيف يعمل'             => array( 'workflow_title', 'workflow' ),
		'Dashboards / لوحات التحكم'            => array( 'dashboard_title', 'dashboard_text', 'dashboard_points' ),
		'Security / الأمان'                    => array( 'security_title', 'security_intro', 'security' ),
		'FAQ / الأسئلة الشائعة'                => array( 'faq_title', 'faq' ),
		'Final CTA / الدعوة النهائية'           => array( 'cta' ),
		'Footer / الفوتر'                      => array( 'footer' ),
	);
	?>
	<div class="sc-admin-language" data-language-panel="<?php echo esc_attr( $lang ); ?>">
		<?php foreach ( $groups as $group_title => $keys ) : ?>
			<details class="sc-admin-card" <?php echo 'Hero / الهيرو' === $group_title ? 'open' : ''; ?>>
				<summary><?php echo esc_html( $group_title ); ?></summary>
				<div class="sc-admin-card-body">
					<?php
					foreach ( $keys as $key ) {
						if ( array_key_exists( $key, $defaults ) ) {
							safecontracts_render_content_tree( array( $key => $defaults[ $key ] ), $saved, $lang );
						}
					}
					?>
				</div>
			</details>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Render button color controls.
 *
 * @param array<string,string> $colors Colors.
 * @return void
 */
function safecontracts_render_color_editor( $colors ) {
	$fields = array(
		'primary_start'        => 'Primary gradient start / بداية لون الزر الأساسي',
		'primary_end'          => 'Primary gradient end / نهاية لون الزر الأساسي',
		'primary_text'         => 'Primary text / نص الزر الأساسي',
		'secondary_background' => 'Secondary background / خلفية الزر الثانوي',
		'secondary_border'     => 'Secondary border / حدود الزر الثانوي',
		'secondary_text'       => 'Secondary text / نص الزر الثانوي',
		'cta_start'            => 'CTA gradient start / بداية لون زر CTA',
		'cta_end'              => 'CTA gradient end / نهاية لون زر CTA',
		'cta_text'             => 'CTA text / نص زر CTA',
	);
	?>
	<div class="sc-admin-card sc-admin-design-card">
		<h3>Button Colors / ألوان الأزرار</h3>
		<p>These colors control the public homepage buttons only. / هذه الألوان تتحكم في أزرار الصفحة الرئيسية فقط.</p>
		<div class="sc-admin-color-grid">
			<?php foreach ( $fields as $key => $label ) : ?>
				<div class="sc-admin-field sc-admin-color-field">
					<label for="sc-color-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
					<input id="sc-color-<?php echo esc_attr( $key ); ?>" type="color" name="<?php echo esc_attr( SAFECONTRACTS_THEME_COLORS_OPTION . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $colors[ $key ] ); ?>">
					<code><?php echo esc_html( $colors[ $key ] ); ?></code>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

/**
 * Render Appearance > SafeContracts Home.
 *
 * @return void
 */
function safecontracts_render_home_admin_page() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	$catalog = safecontracts_copy_catalog();
	$saved   = get_option( SAFECONTRACTS_HOME_CONTENT_OPTION, array() );
	$saved   = is_array( $saved ) ? $saved : array();
	$colors  = wp_parse_args( get_option( SAFECONTRACTS_THEME_COLORS_OPTION, array() ), safecontracts_default_button_colors() );
	?>
	<div class="wrap sc-admin-wrap">
		<h1>SafeContracts Home</h1>
		<p class="description">Edit homepage content in Arabic and English and change public button colors without editing code. / عدّل نصوص الهوم بالعربي والإنجليزي وألوان الأزرار بدون تعديل الكود.</p>
		<?php settings_errors(); ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'safecontracts_home_settings' ); ?>
			<div class="sc-admin-tabs" role="tablist">
				<button type="button" class="button button-primary is-active" data-sc-tab="ar">العربية</button>
				<button type="button" class="button" data-sc-tab="en">English</button>
				<button type="button" class="button" data-sc-tab="design">Design / الألوان</button>
			</div>

			<div class="sc-admin-panel is-active" data-sc-panel="ar">
				<?php safecontracts_render_language_editor( 'ar', $catalog['ar'], isset( $saved['ar'] ) ? $saved['ar'] : array() ); ?>
			</div>
			<div class="sc-admin-panel" data-sc-panel="en">
				<?php safecontracts_render_language_editor( 'en', $catalog['en'], isset( $saved['en'] ) ? $saved['en'] : array() ); ?>
			</div>
			<div class="sc-admin-panel" data-sc-panel="design">
				<?php safecontracts_render_color_editor( $colors ); ?>
			</div>

			<?php submit_button( __( 'Save Homepage Changes', 'safecontracts-onepage' ) ); ?>
		</form>
	</div>
	<style>
		.sc-admin-wrap{max-width:1180px}.sc-admin-tabs{display:flex;gap:8px;margin:20px 0}.sc-admin-panel{display:none}.sc-admin-panel.is-active{display:block}.sc-admin-card{margin:12px 0;border:1px solid #dcdcde;border-radius:10px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.03)}.sc-admin-card>summary{padding:16px 18px;cursor:pointer;font-size:15px;font-weight:700}.sc-admin-card-body{padding:0 18px 18px}.sc-admin-field{display:grid;grid-template-columns:minmax(210px,280px) minmax(0,1fr);gap:16px;align-items:start;margin:13px 0}.sc-admin-field label{font-weight:600;padding-top:8px}.sc-admin-field input[type=text],.sc-admin-field textarea{width:100%;max-width:760px}.sc-admin-nested{margin:12px 0;padding:12px 14px;border-inline-start:3px solid #dbeafe;background:#f8fafc;border-radius:6px}.sc-admin-nested.is-list-item{border-inline-start-color:#99f6e4}.sc-admin-nested h4{margin:0 0 8px}.sc-admin-color-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 24px}.sc-admin-design-card{padding:20px}.sc-admin-color-field{grid-template-columns:1fr auto auto;align-items:center}.sc-admin-color-field input[type=color]{width:54px;height:38px;padding:2px}.sc-admin-color-field code{min-width:76px;text-align:center}@media(max-width:782px){.sc-admin-field,.sc-admin-color-field{grid-template-columns:1fr}.sc-admin-color-grid{grid-template-columns:1fr}}
	</style>
	<script>
	(function(){
		const buttons=document.querySelectorAll('[data-sc-tab]');
		const panels=document.querySelectorAll('[data-sc-panel]');
		buttons.forEach(function(button){button.addEventListener('click',function(){
			buttons.forEach(function(item){item.classList.remove('button-primary','is-active');});
			panels.forEach(function(panel){panel.classList.remove('is-active');});
			button.classList.add('button-primary','is-active');
			const panel=document.querySelector('[data-sc-panel="'+button.dataset.scTab+'"]');
			if(panel){panel.classList.add('is-active');}
		});});
	})();
	</script>
	<?php
}

/**
 * Apply saved button colors after the main theme stylesheet.
 *
 * @return void
 */
function safecontracts_apply_saved_button_colors() {
	$colors = wp_parse_args( get_option( SAFECONTRACTS_THEME_COLORS_OPTION, array() ), safecontracts_default_button_colors() );
	foreach ( $colors as $key => $value ) {
		$colors[ $key ] = sanitize_hex_color( $value ) ? sanitize_hex_color( $value ) : safecontracts_default_button_colors()[ $key ];
	}

	$css = sprintf(
		'.sc-btn-solid{color:%1$s!important;background:linear-gradient(135deg,%2$s,%3$s)!important}.sc-btn-outline,.sc-btn-ghost{color:%4$s!important;border-color:%5$s!important;background:%6$s!important}.sc-btn-teal{color:%7$s!important;background:linear-gradient(135deg,%8$s,%9$s)!important}',
		$colors['primary_text'],
		$colors['primary_start'],
		$colors['primary_end'],
		$colors['secondary_text'],
		$colors['secondary_border'],
		$colors['secondary_background'],
		$colors['cta_text'],
		$colors['cta_start'],
		$colors['cta_end']
	);
	wp_add_inline_style( 'safecontracts-theme', $css );
}
add_action( 'wp_enqueue_scripts', 'safecontracts_apply_saved_button_colors', 20 );
