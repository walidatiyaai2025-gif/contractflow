<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Notifications\SmtpSettings;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Translations\TranslationCatalog;
use Throwable;

final class DirectSmtpSettingsControl
{
    public const ACTION = 'safecontracts_direct_smtp_save';

    public static function register(): void
    {
        add_action('admin_notices', [self::class, 'render'], 24);
        add_action('admin_post_' . self::ACTION, [self::class, 'handle']);
        add_filter('gettext_safecontracts', [self::class, 'filterLegacyMailCopy'], 40, 3);
    }

    public static function handle(): void
    {
        self::requireManage();
        check_admin_referer(self::ACTION);
        $status = 'saved';
        try {
            (new SmtpSettings())->save([
                'host' => (string) ($_POST['host'] ?? ''),
                'port' => $_POST['port'] ?? 587,
                'encryption' => (string) ($_POST['encryption'] ?? 'tls'),
                'username' => (string) ($_POST['username'] ?? ''),
                'password' => (string) ($_POST['password'] ?? ''),
                'clear_password' => isset($_POST['clear_password']),
                'timeout' => $_POST['timeout'] ?? 15,
            ]);
        } catch (Throwable $error) {
            unset($error);
            $status = 'invalid';
        }

        wp_safe_redirect(add_query_arg([
            'page' => EmailSettingsPage::SLUG,
            'safecontracts_smtp_status' => $status,
        ], admin_url('admin.php')));
        exit;
    }

    public static function render(): void
    {
        $page = isset($_GET['page']) && is_scalar($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
        if ($page !== EmailSettingsPage::SLUG || ! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            return;
        }

        $settings = (new SmtpSettings())->get();
        $status = isset($_GET['safecontracts_smtp_status']) && is_scalar($_GET['safecontracts_smtp_status'])
            ? sanitize_key((string) $_GET['safecontracts_smtp_status'])
            : '';
        if ($status === 'saved') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(self::text('Direct SMTP settings saved.', 'تم حفظ إعدادات SMTP المباشر.')) . '</p></div>';
        } elseif ($status === 'invalid') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(self::text('Direct SMTP settings were not saved. Check host, port, encryption and credentials.', 'لم يتم حفظ إعدادات SMTP المباشر. راجع الخادم والمنفذ والتشفير وبيانات الدخول.')) . '</p></div>';
        }
        ?>
        <div class="notice notice-info" style="padding: 14px 18px; border-inline-start-width: 4px;">
            <p><strong><?php echo esc_html(self::text('Direct SMTP connection', 'اتصال SMTP المباشر')); ?></strong></p>
            <p><?php echo esc_html(self::text('Email is delivered directly from Safe Contracts to this SMTP server. WordPress wp_mail and WordPress SMTP plugins are bypassed.', 'يتم إرسال البريد مباشرة من Safe Contracts إلى خادم SMTP هذا. لا يتم استخدام wp_mail أو إضافات SMTP الخاصة بووردبريس.')); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" autocomplete="off">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                <?php wp_nonce_field(self::ACTION); ?>
                <table class="form-table" role="presentation" style="margin-top: 0;">
                    <tr><th scope="row"><label for="safecontracts-smtp-host"><?php echo esc_html(self::text('SMTP host', 'خادم SMTP')); ?></label></th><td><input id="safecontracts-smtp-host" class="regular-text" name="host" maxlength="253" required value="<?php echo esc_attr($settings['host']); ?>" placeholder="smtp.example.com"></td></tr>
                    <tr><th scope="row"><label for="safecontracts-smtp-port"><?php echo esc_html(self::text('SMTP port', 'منفذ SMTP')); ?></label></th><td><input id="safecontracts-smtp-port" type="number" min="1" max="65535" name="port" required value="<?php echo esc_attr((string) $settings['port']); ?>"></td></tr>
                    <tr><th scope="row"><label for="safecontracts-smtp-encryption"><?php echo esc_html(self::text('Encryption', 'التشفير')); ?></label></th><td><select id="safecontracts-smtp-encryption" name="encryption"><option value="tls" <?php selected($settings['encryption'], 'tls'); ?>>STARTTLS / TLS</option><option value="ssl" <?php selected($settings['encryption'], 'ssl'); ?>>SSL / TLS</option><option value="none" <?php selected($settings['encryption'], 'none'); ?>><?php echo esc_html(self::text('None', 'بدون')); ?></option></select></td></tr>
                    <tr><th scope="row"><label for="safecontracts-smtp-username"><?php echo esc_html(self::text('SMTP username', 'اسم مستخدم SMTP')); ?></label></th><td><input id="safecontracts-smtp-username" class="regular-text" name="username" maxlength="191" value="<?php echo esc_attr($settings['username']); ?>" autocomplete="off"><p class="description"><?php echo esc_html(self::text('Leave username and password empty only if the SMTP server explicitly allows unauthenticated relay.', 'اترك اسم المستخدم وكلمة المرور فارغين فقط إذا كان خادم SMTP يسمح صراحة بالإرسال بدون مصادقة.')); ?></p></td></tr>
                    <tr><th scope="row"><label for="safecontracts-smtp-password"><?php echo esc_html(self::text('SMTP password', 'كلمة مرور SMTP')); ?></label></th><td><input id="safecontracts-smtp-password" class="regular-text" type="password" name="password" maxlength="1024" value="" autocomplete="new-password" placeholder="<?php echo esc_attr($settings['password_configured'] ? self::text('Saved — leave blank to keep', 'محفوظة — اتركها فارغة للاحتفاظ بها') : ''); ?>"><?php if ($settings['password_configured']) : ?><p><label><input type="checkbox" name="clear_password" value="1"> <?php echo esc_html(self::text('Clear saved SMTP password', 'حذف كلمة مرور SMTP المحفوظة')); ?></label></p><?php endif; ?><p class="description"><?php echo esc_html(self::text('The SMTP password is encrypted before it is stored in the WordPress database and is never displayed back in this form.', 'يتم تشفير كلمة مرور SMTP قبل حفظها في قاعدة بيانات ووردبريس ولا يتم عرضها مرة أخرى في هذه الصفحة.')); ?></p></td></tr>
                    <tr><th scope="row"><label for="safecontracts-smtp-timeout"><?php echo esc_html(self::text('Connection timeout', 'مهلة الاتصال')); ?></label></th><td><input id="safecontracts-smtp-timeout" type="number" min="3" max="60" name="timeout" required value="<?php echo esc_attr((string) $settings['timeout']); ?>"> <?php echo esc_html(self::text('seconds', 'ثانية')); ?></td></tr>
                </table>
                <?php submit_button(self::text('Save Direct SMTP Settings', 'حفظ إعدادات SMTP المباشر'), 'secondary', 'submit', false); ?>
            </form>
        </div>
        <?php
    }

    public static function filterLegacyMailCopy(string $translation, string $text, string $domain = 'safecontracts'): string
    {
        if ($domain !== 'safecontracts') {
            return $translation;
        }
        $legacy = 'Safe Contracts sends through WordPress wp_mail. SMTP/API credentials remain managed by the WordPress mail transport or hosting configuration, not stored in Safe Contracts.';
        if ($text !== $legacy) {
            return $translation;
        }
        return self::text(
            'Safe Contracts sends email through the Direct SMTP connection configured on the Email Settings page. WordPress wp_mail and WordPress SMTP plugins are not used.',
            'يرسل Safe Contracts البريد من خلال اتصال SMTP المباشر المضبوط في صفحة إعدادات البريد الإلكتروني. لا يتم استخدام wp_mail أو إضافات SMTP الخاصة بووردبريس.'
        );
    }

    private static function requireManage(): void
    {
        if (! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            wp_die(__('You do not have permission to manage notifications.', 'safecontracts'));
        }
    }

    private static function text(string $english, string $arabic): string
    {
        return TranslationCatalog::currentLanguage() === 'ar' ? $arabic : $english;
    }
}
