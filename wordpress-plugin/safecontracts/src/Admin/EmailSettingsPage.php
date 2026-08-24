<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Notifications\EmailSettings;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Translations\TranslationCatalog;
use Throwable;

final class EmailSettingsPage
{
    public const SLUG = 'safecontracts-email-settings';
    public const SAVE_ACTION = 'safecontracts_save_email_settings';

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'registerMenu'], 32);
        add_action('admin_post_' . self::SAVE_ACTION, [self::class, 'handleSave']);
    }

    public static function registerMenu(): void
    {
        add_submenu_page(
            AdminShell::SLUG,
            self::text('Email Settings', 'إعدادات البريد الإلكتروني'),
            self::text('Email Settings', 'إعدادات البريد الإلكتروني'),
            Capabilities::MANAGE_NOTIFICATIONS,
            self::SLUG,
            [self::class, 'render']
        );
    }

    public static function handleSave(): void
    {
        self::requireManage();
        check_admin_referer(self::SAVE_ACTION);
        $status = 'saved';
        try {
            (new EmailSettings())->save([
                'enabled' => isset($_POST['enabled']),
                'from_name' => (string) ($_POST['from_name'] ?? ''),
                'from_address' => (string) ($_POST['from_address'] ?? ''),
            ]);
        } catch (Throwable $error) {
            unset($error);
            $status = 'invalid';
        }

        wp_safe_redirect(add_query_arg([
            'page' => self::SLUG,
            'safecontracts_email_settings' => $status,
        ], admin_url('admin.php')));
        exit;
    }

    public static function render(): void
    {
        self::requireManage();
        $settings = (new EmailSettings())->get();
        $status = isset($_GET['safecontracts_email_settings']) && is_scalar($_GET['safecontracts_email_settings'])
            ? sanitize_key((string) $_GET['safecontracts_email_settings'])
            : '';
        $enabled = ! empty($settings['enabled']);
        ?>
        <div class="wrap safecontracts-settings safecontracts-email-settings" dir="auto">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html(self::text('Mail delivery configuration', 'تهيئة إرسال البريد')); ?></p>
                    <h1><?php echo esc_html(self::text('Email Settings', 'إعدادات البريد الإلكتروني')); ?></h1>
                    <p><?php echo esc_html(self::text('Manage the sender identity and whether SafeContracts email notifications are enabled. Mail transport remains the responsibility of WordPress and the server mail configuration.', 'إدارة هوية المرسل وما إذا كانت إشعارات البريد في SafeContracts مفعلة. تظل وسيلة الإرسال مسؤولية ووردبريس وإعدادات البريد على الخادم.')); ?></p>
                </div>
                <span class="safecontracts-state-chip <?php echo $enabled ? 'is-success' : 'is-warning'; ?>"><?php echo esc_html($enabled ? self::text('Email enabled', 'البريد مفعل') : self::text('Email disabled', 'البريد غير مفعل')); ?></span>
            </div>

            <?php if ($status === 'saved') : ?><div class="notice notice-success inline"><p><?php echo esc_html(self::text('Email settings saved.', 'تم حفظ إعدادات البريد الإلكتروني.')); ?></p></div><?php endif; ?>
            <?php if ($status === 'invalid') : ?><div class="notice notice-error inline"><p><?php echo esc_html(self::text('Email settings were not saved. Check the sender name and email address.', 'لم يتم حفظ إعدادات البريد الإلكتروني. راجع اسم المرسل وعنوان البريد.')); ?></p></div><?php endif; ?>

            <div class="safecontracts-split-layout">
                <section class="safecontracts-admin-card safecontracts-settings-card">
                    <div class="safecontracts-section-heading"><div><h2><?php echo esc_html(self::text('Sender and email notifications', 'المرسل وإشعارات البريد')); ?></h2><p class="description"><?php echo esc_html(self::text('Only non-secret sender settings are stored by this page.', 'تخزن هذه الصفحة إعدادات المرسل غير السرية فقط.')); ?></p></div></div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">
                        <?php wp_nonce_field(self::SAVE_ACTION); ?>
                        <p><label class="safecontracts-check-row"><input type="checkbox" name="enabled" value="1" <?php checked($enabled); ?>><?php echo esc_html(self::text('Enable email notifications', 'تفعيل إشعارات البريد الإلكتروني')); ?></label></p>
                        <p><label><?php echo esc_html(self::text('Sender name', 'اسم المرسل')); ?><input class="widefat" name="from_name" maxlength="191" required value="<?php echo esc_attr((string) $settings['from_name']); ?>"></label></p>
                        <p><label><?php echo esc_html(self::text('Sender email', 'بريد المرسل')); ?><input class="widefat" dir="ltr" type="email" name="from_address" required value="<?php echo esc_attr((string) $settings['from_address']); ?>"></label></p>
                        <?php submit_button(self::text('Save Email Settings', 'حفظ إعدادات البريد الإلكتروني')); ?>
                    </form>
                </section>

                <section class="safecontracts-admin-card">
                    <div class="safecontracts-section-heading"><div><h2><?php echo esc_html(self::text('Transport boundary', 'حدود وسيلة الإرسال')); ?></h2></div></div>
                    <p><?php echo esc_html(self::text('This screen does not expose SMTP passwords, API credentials, OAuth tokens or server secrets.', 'لا تعرض هذه الشاشة كلمات مرور SMTP أو بيانات اعتماد API أو رموز OAuth أو أسرار الخادم.')); ?></p>
                    <p><?php echo esc_html(self::text('Delivery uses the existing WordPress mail path. Configure transport at the WordPress/server layer when required; notification rule channels remain managed in Notification Settings.', 'يستخدم الإرسال مسار البريد الحالي في ووردبريس. عند الحاجة تتم تهيئة وسيلة الإرسال على مستوى ووردبريس أو الخادم، بينما تظل قنوات قواعد الإشعارات في صفحة إعدادات الإشعارات.')); ?></p>
                    <p><a class="button" href="<?php echo esc_url(add_query_arg(['page' => NotificationSettingsPage::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html(self::text('Open Notification Settings', 'فتح إعدادات الإشعارات')); ?></a></p>
                </section>
            </div>
        </div>
        <?php
    }

    private static function requireManage(): void
    {
        if (! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            wp_die(__('You do not have permission to manage notifications.', 'safecontracts'));
        }
    }

    private static function text(string $english, string $arabic): string
    {
        return TranslationCatalog::currentLanguage() === 'ar' ? $arabic : __($english, 'safecontracts');
    }
}
