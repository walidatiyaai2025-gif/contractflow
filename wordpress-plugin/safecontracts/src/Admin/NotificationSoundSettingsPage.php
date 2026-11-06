<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Notifications\NotificationSoundSettings;
use SafeContracts\Roles\Capabilities;

final class NotificationSoundSettingsPage
{
    public const SLUG = 'safecontracts-notification-sounds';
    public const SAVE_ACTION = 'safecontracts_save_notification_sounds';

    public static function register(): void
    {
        add_submenu_page(
            AdminShell::SLUG,
            self::label('أصوات الإشعارات', 'Notification Sounds'),
            self::label('أصوات الإشعارات', 'Notification Sounds'),
            Capabilities::MANAGE_NOTIFICATIONS,
            self::SLUG,
            [self::class, 'render']
        );
    }

    public static function handleSave(): void
    {
        if (! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            wp_die(self::label('ليس لديك صلاحية لإدارة أصوات الإشعارات.', 'You do not have permission to manage notification sounds.'));
        }
        check_admin_referer(self::SAVE_ACTION);
        $settings = new NotificationSoundSettings();
        $settings->save([
            'enabled' => isset($_POST['enabled']),
            'default_sound' => self::postValue('default_sound'),
            NotificationSoundSettings::CATEGORY_CONTRACT_PAYMENT => self::postValue(NotificationSoundSettings::CATEGORY_CONTRACT_PAYMENT),
            NotificationSoundSettings::CATEGORY_COLLECTION => self::postValue(NotificationSoundSettings::CATEGORY_COLLECTION),
            NotificationSoundSettings::CATEGORY_SETTLEMENT => self::postValue(NotificationSoundSettings::CATEGORY_SETTLEMENT),
            NotificationSoundSettings::CATEGORY_REVERSAL_REFUND => self::postValue(NotificationSoundSettings::CATEGORY_REVERSAL_REFUND),
            NotificationSoundSettings::CATEGORY_DUE_REMINDER => self::postValue(NotificationSoundSettings::CATEGORY_DUE_REMINDER),
        ]);
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'safecontracts_status' => 'saved'], admin_url('admin.php')));
        exit;
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            wp_die(self::label('ليس لديك صلاحية لإدارة أصوات الإشعارات.', 'You do not have permission to manage notification sounds.'));
        }
        $service = new NotificationSoundSettings();
        $settings = $service->get();
        $rows = [
            'default_sound' => self::label('الصوت الافتراضي', 'Default fallback sound'),
            NotificationSoundSettings::CATEGORY_CONTRACT_PAYMENT => self::label('إشعارات دفعات العقود', 'Contract payment notifications'),
            NotificationSoundSettings::CATEGORY_COLLECTION => self::label('إشعارات التحصيل', 'Collection notifications'),
            NotificationSoundSettings::CATEGORY_SETTLEMENT => self::label('إشعارات السداد والتسوية', 'Settlement notifications'),
            NotificationSoundSettings::CATEGORY_REVERSAL_REFUND => self::label('إشعارات الإلغاء والاسترجاع', 'Reversal / refund notifications'),
            NotificationSoundSettings::CATEGORY_DUE_REMINDER => self::label('إشعارات المتابعة والاستحقاق', 'Follow-up / due reminders'),
        ];
        $soundLabels = [
            NotificationSoundSettings::SOUND_DEFAULT => self::label('صوت النظام الافتراضي', 'System default'),
            NotificationSoundSettings::SOUND_BANKNOTE_COUNTER => 'freesound_community-banknote-counter-106014.mp3(2).mpeg',
            NotificationSoundSettings::SOUND_CASHIER_KA_CHING => 'u_byub5wd934-cashier-quotka-chingquot.mp3.mpeg',
            NotificationSoundSettings::SOUND_COIN_DROP => 'universfield-coin-drop-229314.mp3(2).mpeg',
        ];
        $soundUrls = [];
        foreach (NotificationSoundSettings::soundKeys() as $key) {
            $filename = NotificationSoundSettings::sourceFilename($key);
            $soundUrls[$key] = $filename === null ? '' : SAFECONTRACTS_URL . 'assets/sounds/' . rawurlencode($filename);
        }
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <h1><?php echo esc_html(self::label('أصوات الإشعارات', 'Notification Sounds')); ?></h1>
            <p><?php echo esc_html(self::label('اختر الصوت المستخدم لكل نوع من إشعارات ALKENZY ADV. على Android 8 وما بعده يتم استخدام قناة مستقلة لكل صوت.', 'Choose the sound used by each ALKENZY ADV notification category. Android 8+ uses a separate channel for each sound.')); ?></p>
            <?php if (($_GET['safecontracts_status'] ?? '') === 'saved') : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html(self::label('تم حفظ إعدادات أصوات الإشعارات.', 'Notification sound settings saved.')); ?></p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">
                <?php wp_nonce_field(self::SAVE_ACTION); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html(self::label('تفعيل الأصوات المخصصة', 'Enable custom sounds')); ?></th>
                        <td><label><input type="checkbox" name="enabled" value="1" <?php checked($settings['enabled']); ?>> <?php echo esc_html(self::label('استخدم التوزيع أدناه بدل صوت النظام الافتراضي.', 'Use the mapping below instead of the system default sound.')); ?></label></td>
                    </tr>
                    <?php foreach ($rows as $field => $label) : ?>
                        <tr>
                            <th scope="row"><label for="sc-sound-<?php echo esc_attr($field); ?>"><?php echo esc_html($label); ?></label></th>
                            <td>
                                <select id="sc-sound-<?php echo esc_attr($field); ?>" name="<?php echo esc_attr($field); ?>">
                                    <?php foreach ($soundLabels as $key => $soundLabel) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($settings[$field], $key); ?>><?php echo esc_html($soundLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="button sc-test-sound" data-select="sc-sound-<?php echo esc_attr($field); ?>"><?php echo esc_html(self::label('اختبار الصوت', 'Test sound')); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <?php submit_button(self::label('حفظ إعدادات الأصوات', 'Save sound settings')); ?>
            </form>
            <audio id="sc-notification-sound-preview" preload="none"></audio>
        </div>
        <script>
        (() => {
            const urls = <?php echo wp_json_encode($soundUrls); ?>;
            const player = document.getElementById('sc-notification-sound-preview');
            document.querySelectorAll('.sc-test-sound').forEach((button) => {
                button.addEventListener('click', () => {
                    const select = document.getElementById(button.dataset.select || '');
                    const url = select ? urls[select.value] : '';
                    if (!url || !player) return;
                    player.pause();
                    player.currentTime = 0;
                    player.src = url;
                    void player.play();
                });
            });
        })();
        </script>
        <?php
    }

    private static function postValue(string $key): string
    {
        $value = $_POST[$key] ?? '';
        return is_scalar($value) ? sanitize_key((string) $value) : '';
    }

    private static function label(string $ar, string $en): string
    {
        $locale = function_exists('get_locale') ? strtolower((string) get_locale()) : 'en';
        return str_starts_with($locale, 'ar') ? $ar : $en;
    }
}
