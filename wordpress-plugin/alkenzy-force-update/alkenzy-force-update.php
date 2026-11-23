<?php
/**
 * Plugin Name: Alkenzy ADV — Force Update Bridge
 * Description: Independent authenticated admin controls + public mobile-version endpoint for Google Play mandatory updates. Does not replace SafeContracts.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Text Domain: alkenzy-force-update
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const ALKENZY_FORCE_OPTION = 'alkenzy_adv_force_update';
const ALKENZY_FORCE_PACKAGE = 'com.safecontracts.safecontracts_mobile';

function alkenzy_force_defaults(): array
{
    return [
        'enabled' => false,
        'min_build' => 0,
        'latest_build' => 0,
        'message_ar' => '',
        'message_en' => '',
    ];
}

function alkenzy_force_policy(): array
{
    $stored = get_option(ALKENZY_FORCE_OPTION, []);
    $stored = is_array($stored) ? $stored : [];
    $defaults = alkenzy_force_defaults();
    $min = filter_var($stored['min_build'] ?? 0, FILTER_VALIDATE_INT);
    $latest = filter_var($stored['latest_build'] ?? 0, FILTER_VALIDATE_INT);
    $min = $min !== false && $min >= 0 && $min <= 1000000000 ? $min : 0;
    $latest = $latest !== false && $latest >= 0 && $latest <= 1000000000 ? $latest : 0;
    $enabled = ($stored['enabled'] ?? false) === true && $min > 0 && $latest >= $min;
    return [
        'schema_version' => 1,
        'platform' => 'android',
        'enabled' => $enabled,
        'min_build' => $enabled ? $min : 0,
        'latest_build' => $latest,
        'store_url' => 'https://play.google.com/store/apps/details?id=' . ALKENZY_FORCE_PACKAGE,
        'message_ar' => is_string($stored['message_ar'] ?? null) ? mb_substr(strip_tags($stored['message_ar']), 0, 300) : '',
        'message_en' => is_string($stored['message_en'] ?? null) ? mb_substr(strip_tags($stored['message_en']), 0, 300) : '',
    ];
}

add_action('rest_api_init', static function (): void {
    // Yield to a newer SafeContracts implementation if it already owns the API.
    if (isset(rest_get_server()->get_routes()['/safecontracts/v1/mobile-version'])) {
        return;
    }
    register_rest_route('safecontracts/v1', '/mobile-version', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => static function (): WP_REST_Response {
            $response = new WP_REST_Response([
                'data' => alkenzy_force_policy(),
                'meta' => ['api_version' => 'v1'],
            ], 200);
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate');
            return $response;
        },
    ]);
}, 99);

add_action('admin_menu', static function (): void {
    add_options_page(
        'Alkenzy ADV — Force Update',
        'Alkenzy Force Update',
        'manage_options',
        'alkenzy-force-update',
        'alkenzy_force_settings_page'
    );
});

function alkenzy_force_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Not authorized.');
    }
    $v = alkenzy_force_policy();
    ?>
    <div class="wrap">
      <h1>Alkenzy ADV — Force Update / تحديث إجباري</h1>
      <?php if (isset($_GET['updated'])): ?><div class="notice notice-success"><p>Policy saved.</p></div><?php endif; ?>
      <?php if (isset($_GET['error'])): ?><div class="notice notice-error"><p>Invalid build numbers: the minimum must be positive and no higher than the latest published build when enabled.</p></div><?php endif; ?>
      <p><strong>IMPORTANT:</strong> Keep disabled until the minimum build is LIVE on Google Play. Do not enter a future build that is not yet published.</p>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('alkenzy_force_save'); ?>
        <input type="hidden" name="action" value="alkenzy_force_save">
        <table class="form-table">
          <tr><th>Enable mandatory update / تفعيل</th><td><label><input type="checkbox" name="enabled" value="1" <?php checked($v['enabled']); ?>> Block older builds</label></td></tr>
          <tr><th>Minimum supported Build</th><td><input type="number" min="0" max="1000000000" name="min_build" value="<?php echo esc_attr((string) $v['min_build']); ?>" required></td></tr>
          <tr><th>Latest published Build</th><td><input type="number" min="0" max="1000000000" name="latest_build" value="<?php echo esc_attr((string) $v['latest_build']); ?>" required></td></tr>
          <tr><th>Arabic message</th><td><textarea maxlength="300" rows="3" cols="65" name="message_ar"><?php echo esc_textarea($v['message_ar']); ?></textarea></td></tr>
          <tr><th>English message</th><td><textarea maxlength="300" rows="3" cols="65" name="message_en"><?php echo esc_textarea($v['message_en']); ?></textarea></td></tr>
        </table>
        <?php submit_button('Save Force Update Policy'); ?>
      </form>
      <p>Public endpoint: <code>/wp-json/safecontracts/v1/mobile-version</code>. No credentials or user data are exposed.</p>
    </div>
    <?php
}

add_action('admin_post_alkenzy_force_save', static function (): void {
    if (!current_user_can('manage_options')) {
        wp_die('Not authorized.', '', ['response' => 403]);
    }
    check_admin_referer('alkenzy_force_save');
    $min = filter_var(wp_unslash($_POST['min_build'] ?? 0), FILTER_VALIDATE_INT);
    $latest = filter_var(wp_unslash($_POST['latest_build'] ?? 0), FILTER_VALIDATE_INT);
    $enabled = isset($_POST['enabled']);
    if ($min === false || $latest === false || $min < 0 || $latest < 0 ||
        $min > 1000000000 || $latest > 1000000000 ||
        ($enabled && ($min < 1 || $min > $latest))) {
        wp_safe_redirect(add_query_arg(['page' => 'alkenzy-force-update', 'error' => '1'], admin_url('options-general.php')));
        exit;
    }
    update_option(ALKENZY_FORCE_OPTION, [
        'enabled' => $enabled,
        'min_build' => $min,
        'latest_build' => $latest,
        'message_ar' => sanitize_textarea_field(wp_unslash($_POST['message_ar'] ?? '')),
        'message_en' => sanitize_textarea_field(wp_unslash($_POST['message_en'] ?? '')),
    ], false);
    wp_safe_redirect(add_query_arg(['page' => 'alkenzy-force-update', 'updated' => '1'], admin_url('options-general.php')));
    exit;
});
