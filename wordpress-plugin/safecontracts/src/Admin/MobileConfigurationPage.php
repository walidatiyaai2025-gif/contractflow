<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;
use SafeContracts\Settings\MobileConfiguration;
use SafeContracts\Settings\MobileLandingContent;
use Throwable;

final class MobileConfigurationPage
{
    public const SLUG = 'safecontracts-mobile-configuration';
    public const SAVE_ACTION = 'safecontracts_save_mobile_configuration';

    public static function register(): void
    {
        add_submenu_page(AdminShell::SLUG, __('Mobile Configuration', 'safecontracts'), __('Mobile Configuration', 'safecontracts'), Capabilities::MANAGE_SYSTEM, self::SLUG, [self::class, 'render']);
    }

    public static function handleSave(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to manage mobile configuration.', 'safecontracts'));
        }
        check_admin_referer(self::SAVE_ACTION);
        $status = 'saved';
        try {
            (new MobileConfiguration())->save([
                'support_text' => $_POST['support_text'] ?? '',
                'default_page_size' => $_POST['default_page_size'] ?? 25,
                'excel_export_enabled' => isset($_POST['excel_export_enabled']),
                'push_notifications_enabled' => isset($_POST['push_notifications_enabled']),
                'collection_entry_enabled' => isset($_POST['collection_entry_enabled']),
            ]);
            (new MobileLandingContent())->save(self::landingInput($_POST));
        } catch (Throwable $error) {
            unset($error);
            $status = 'invalid';
        }
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'safecontracts_status' => $status], admin_url('admin.php')) . '#safecontracts-mobile-landing-content');
        exit;
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to manage mobile configuration.', 'safecontracts'));
        }
        $config = (new MobileConfiguration())->read();
        $landing = (new MobileLandingContent())->read();
        $phones = is_array($landing['contact']['phones'] ?? null) ? $landing['contact']['phones'] : [];
        $services = is_array($landing['services'] ?? null) ? $landing['services'] : [];
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Mobile workspace', 'safecontracts'); ?></p><h1><?php echo esc_html__('Mobile Configuration', 'safecontracts'); ?></h1></div></div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>

                <section class="safecontracts-admin-card safecontracts-settings-card">
                    <h2><?php echo esc_html__('Runtime configuration', 'safecontracts'); ?></h2>
                    <p><label><?php echo esc_html__('Support / footer text', 'safecontracts'); ?><textarea class="widefat" name="support_text" maxlength="500" rows="4"><?php echo esc_html($config['support_text']); ?></textarea></label></p>
                    <p><label><?php echo esc_html__('Default mobile page size', 'safecontracts'); ?><input type="number" min="10" max="200" name="default_page_size" value="<?php echo esc_attr((string) $config['default_page_size']); ?>"></label></p>
                    <fieldset><legend><?php echo esc_html__('Feature availability', 'safecontracts'); ?></legend>
                        <label class="safecontracts-check-row"><input type="checkbox" name="excel_export_enabled" value="1" <?php checked($config['excel_export_enabled']); ?>><?php echo esc_html__('Excel export', 'safecontracts'); ?></label>
                        <label class="safecontracts-check-row"><input type="checkbox" name="push_notifications_enabled" value="1" <?php checked($config['push_notifications_enabled']); ?>><?php echo esc_html__('Push notifications', 'safecontracts'); ?></label>
                        <label class="safecontracts-check-row"><input type="checkbox" name="collection_entry_enabled" value="1" <?php checked($config['collection_entry_enabled']); ?>><?php echo esc_html__('Collection entry', 'safecontracts'); ?></label>
                    </fieldset>
                </section>

                <section id="safecontracts-mobile-landing-content" class="safecontracts-admin-card safecontracts-settings-card safecontracts-landing-editor">
                    <div class="safecontracts-section-heading">
                        <div>
                            <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('App landing', 'safecontracts'); ?></p>
                            <h2><?php echo esc_html__('Mobile landing page content', 'safecontracts'); ?></h2>
                            <p class="description"><?php echo esc_html__('Edit the public pre-login page shown by Alkenzy ADV. Changes use the existing public landing endpoint and never expose contracts, users, payments or private configuration.', 'safecontracts'); ?></p>
                        </div>
                    </div>
                    <p class="safecontracts-landing-editor__hint"><?php echo esc_html__('Arabic and English values are stored together. The app shows the matching language the next time its landing content is refreshed.', 'safecontracts'); ?></p>

                    <div class="safecontracts-landing-editor__grid">
                        <?php self::textField('landing_brand_name', __('Brand name', 'safecontracts'), (string) ($landing['brand_name'] ?? ''), 80, true); ?>
                        <?php self::numberField('landing_experience_years', __('Experience years', 'safecontracts'), (int) ($landing['experience_years'] ?? 0), 0, 100); ?>

                        <?php self::textField('landing_agency_ar', __('Agency name — Arabic', 'safecontracts'), (string) ($landing['agency_name']['ar'] ?? ''), 120); ?>
                        <?php self::textField('landing_agency_en', __('Agency name — English', 'safecontracts'), (string) ($landing['agency_name']['en'] ?? ''), 120); ?>
                        <?php self::textField('landing_headline_ar', __('Headline — Arabic', 'safecontracts'), (string) ($landing['headline']['ar'] ?? ''), 160); ?>
                        <?php self::textField('landing_headline_en', __('Headline — English', 'safecontracts'), (string) ($landing['headline']['en'] ?? ''), 160); ?>
                        <?php self::textField('landing_highlight_ar', __('Highlight — Arabic', 'safecontracts'), (string) ($landing['highlight']['ar'] ?? ''), 180); ?>
                        <?php self::textField('landing_highlight_en', __('Highlight — English', 'safecontracts'), (string) ($landing['highlight']['en'] ?? ''), 180); ?>
                        <?php self::textareaField('landing_summary_ar', __('Summary — Arabic', 'safecontracts'), (string) ($landing['summary']['ar'] ?? ''), 700); ?>
                        <?php self::textareaField('landing_summary_en', __('Summary — English', 'safecontracts'), (string) ($landing['summary']['en'] ?? ''), 700); ?>

                        <?php foreach ($services as $service) : ?>
                            <?php if (! is_array($service)) { continue; } ?>
                            <?php $key = sanitize_key((string) ($service['key'] ?? '')); if ($key === '') { continue; } ?>
                            <div class="safecontracts-landing-editor__service">
                                <h3><?php echo esc_html(sprintf(__('Service: %s', 'safecontracts'), $key)); ?></h3>
                                <?php self::textField('landing_service_' . $key . '_title_ar', __('Title — Arabic', 'safecontracts'), (string) ($service['title']['ar'] ?? ''), 100); ?>
                                <?php self::textField('landing_service_' . $key . '_title_en', __('Title — English', 'safecontracts'), (string) ($service['title']['en'] ?? ''), 100); ?>
                                <?php self::textField('landing_service_' . $key . '_subtitle_ar', __('Subtitle — Arabic', 'safecontracts'), (string) ($service['subtitle']['ar'] ?? ''), 180); ?>
                                <?php self::textField('landing_service_' . $key . '_subtitle_en', __('Subtitle — English', 'safecontracts'), (string) ($service['subtitle']['en'] ?? ''), 180); ?>
                            </div>
                        <?php endforeach; ?>

                        <?php for ($index = 0; $index < 4; $index++) : ?>
                            <?php self::textField('landing_phone_' . ($index + 1), sprintf(__('Phone %d', 'safecontracts'), $index + 1), (string) ($phones[$index] ?? ''), 32); ?>
                        <?php endfor; ?>
                        <?php self::textField('landing_address_ar', __('Office address — Arabic', 'safecontracts'), (string) ($landing['contact']['office_address']['ar'] ?? ''), 240); ?>
                        <?php self::textField('landing_address_en', __('Office address — English', 'safecontracts'), (string) ($landing['contact']['office_address']['en'] ?? ''), 240); ?>
                        <?php self::textField('landing_sign_in_ar', __('Sign-in button — Arabic', 'safecontracts'), (string) ($landing['sign_in_label']['ar'] ?? ''), 60); ?>
                        <?php self::textField('landing_sign_in_en', __('Sign-in button — English', 'safecontracts'), (string) ($landing['sign_in_label']['en'] ?? ''), 60); ?>
                        <?php self::textField('landing_learn_more_ar', __('Learn-more button — Arabic', 'safecontracts'), (string) ($landing['learn_more_label']['ar'] ?? ''), 60); ?>
                        <?php self::textField('landing_learn_more_en', __('Learn-more button — English', 'safecontracts'), (string) ($landing['learn_more_label']['en'] ?? ''), 60); ?>
                    </div>
                </section>

                <?php submit_button(__('Save Mobile & Landing Configuration', 'safecontracts')); ?>
            </form>
        </div>
        <?php
    }

    /** @param array<string,mixed> $post @return array<string,mixed> */
    private static function landingInput(array $post): array
    {
        $defaults = MobileLandingContent::defaults();
        $services = [];
        foreach ($defaults['services'] as $service) {
            $key = sanitize_key((string) ($service['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $services[] = [
                'key' => $key,
                'title' => [
                    'ar' => $post['landing_service_' . $key . '_title_ar'] ?? '',
                    'en' => $post['landing_service_' . $key . '_title_en'] ?? '',
                ],
                'subtitle' => [
                    'ar' => $post['landing_service_' . $key . '_subtitle_ar'] ?? '',
                    'en' => $post['landing_service_' . $key . '_subtitle_en'] ?? '',
                ],
            ];
        }

        return [
            'brand_name' => $post['landing_brand_name'] ?? '',
            'agency_name' => ['ar' => $post['landing_agency_ar'] ?? '', 'en' => $post['landing_agency_en'] ?? ''],
            'headline' => ['ar' => $post['landing_headline_ar'] ?? '', 'en' => $post['landing_headline_en'] ?? ''],
            'highlight' => ['ar' => $post['landing_highlight_ar'] ?? '', 'en' => $post['landing_highlight_en'] ?? ''],
            'summary' => ['ar' => $post['landing_summary_ar'] ?? '', 'en' => $post['landing_summary_en'] ?? ''],
            'experience_years' => $post['landing_experience_years'] ?? 10,
            'services' => $services,
            'phones' => [
                $post['landing_phone_1'] ?? '',
                $post['landing_phone_2'] ?? '',
                $post['landing_phone_3'] ?? '',
                $post['landing_phone_4'] ?? '',
            ],
            'office_address' => ['ar' => $post['landing_address_ar'] ?? '', 'en' => $post['landing_address_en'] ?? ''],
            'sign_in_label' => ['ar' => $post['landing_sign_in_ar'] ?? '', 'en' => $post['landing_sign_in_en'] ?? ''],
            'learn_more_label' => ['ar' => $post['landing_learn_more_ar'] ?? '', 'en' => $post['landing_learn_more_en'] ?? ''],
        ];
    }

    private static function textField(string $name, string $label, string $value, int $maxLength, bool $full = false): void
    {
        ?>
        <div class="safecontracts-landing-editor__field<?php echo $full ? ' safecontracts-landing-editor__field--full' : ''; ?>">
            <label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label>
            <input class="widefat" id="<?php echo esc_attr($name); ?>" type="text" name="<?php echo esc_attr($name); ?>" maxlength="<?php echo esc_attr((string) $maxLength); ?>" value="<?php echo esc_attr($value); ?>">
        </div>
        <?php
    }

    private static function textareaField(string $name, string $label, string $value, int $maxLength): void
    {
        ?>
        <div class="safecontracts-landing-editor__field safecontracts-landing-editor__field--full">
            <label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label>
            <textarea class="widefat" id="<?php echo esc_attr($name); ?>" name="<?php echo esc_attr($name); ?>" maxlength="<?php echo esc_attr((string) $maxLength); ?>" rows="4"><?php echo esc_html($value); ?></textarea>
        </div>
        <?php
    }

    private static function numberField(string $name, string $label, int $value, int $minimum, int $maximum): void
    {
        ?>
        <div class="safecontracts-landing-editor__field">
            <label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label>
            <input id="<?php echo esc_attr($name); ?>" type="number" name="<?php echo esc_attr($name); ?>" min="<?php echo esc_attr((string) $minimum); ?>" max="<?php echo esc_attr((string) $maximum); ?>" value="<?php echo esc_attr((string) $value); ?>">
        </div>
        <?php
    }
}
