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
        ?>
        <div class="wrap safecontracts-settings safecontracts-email-settings" dir="auto">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html(self::text('Mail delivery configuration', 'تهيئة إرسال البريد')); ?></p>
                    <h1><?php echo esc_html(self::text('Email Settings', 'إعدادات البريد الإلكتروني')); ?></h1>
                    <p><?php echo esc_html(self::text('Sender identity, Direct SMTP connection and delivery testing are managed here. Notification rules remain in Notification Settings.', 'تتم إدارة هوية المرسل واتصال SMTP المباشر واختبار الإرسال هنا. وتظل قواعد الإشعارات في إعدادات الإشعارات.')); ?></p>
                </div>
            </div>

            <?php if ($status === 'saved') : ?><div class="notice notice-success inline"><p><?php echo esc_html(self::text('Email settings saved.', 'تم حفظ إعدادات البريد الإلكتروني.')); ?></p></div><?php endif; ?>
            <?php if ($status === 'invalid') : ?><div class="notice notice-error inline"><p><?php echo esc_html(self::text('Email settings were not saved. Check the sender name and email address.', 'لم يتم حفظ إعدادات البريد الإلكتروني. راجع اسم المرسل وعنوان البريد.')); ?></p></div><?php endif; ?>

            <section class="safecontracts-admin-card safecontracts-settings-card">
                <h2><?php echo esc_html(self::text('Sender and email notifications', 'المرسل وإشعارات البريد')); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">
                    <?php wp_nonce_field(self::SAVE_ACTION); ?>
                    <p><label class="safecontracts-check-row"><input type="checkbox" name="enabled" value="1" <?php checked($settings['enabled']); ?>><?php echo esc_html(self::text('Enable email notifications', 'تفعيل إشعارات البريد الإلكتروني')); ?></label></p>
                    <p><label><?php echo esc_html(self::text('Sender name', 'اسم المرسل')); ?><input class="widefat" name="from_name" maxlength="191" required value="<?php echo esc_attr((string) $settings['from_name']); ?>"></label></p>
                    <p><label><?php echo esc_html(self::text('Sender email', 'بريد المرسل')); ?><input class="widefat" type="email" name="from_address" required value="<?php echo esc_attr((string) $settings['from_address']); ?>"></label></p>
                    <?php submit_button(self::text('Save Email Settings', 'حفظ إعدادات البريد الإلكتروني')); ?>
                </form>
            </section>
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
