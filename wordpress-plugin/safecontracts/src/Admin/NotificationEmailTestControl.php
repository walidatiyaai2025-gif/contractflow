<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Notifications\DirectNotificationService;
use SafeContracts\Notifications\EmailSettings;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Translations\TranslationCatalog;
use Throwable;

final class NotificationEmailTestControl
{
    public const ACTION = 'safecontracts_notification_email_test';

    public static function register(): void
    {
        DirectSmtpSettingsControl::register();
        add_action('admin_notices', [self::class, 'render'], 25);
        add_action('admin_post_' . self::ACTION, [self::class, 'handle']);
    }

    public static function handle(): void
    {
        self::requireManage();
        check_admin_referer(self::ACTION);

        $status = 'failed';
        try {
            $userId = get_current_user_id();
            if ($userId <= 0) {
                throw new \RuntimeException('Email test requires an authenticated WordPress user.');
            }
            $result = (new DirectNotificationService())->send(
                $userId,
                self::text('Safe Contracts email test', 'اختبار بريد Safe Contracts'),
                self::text(
                    'This test confirms that the saved Safe Contracts sender settings and direct SMTP connection can deliver email.',
                    'تؤكد هذه الرسالة أن إعدادات مرسل Safe Contracts المحفوظة واتصال SMTP المباشر قادران على إرسال البريد الإلكتروني.'
                ),
                false,
                true,
                'safe_contracts'
            );
            if ((int) ($result['email_sent'] ?? 0) > 0 && (int) ($result['email_failed'] ?? 0) === 0) {
                $status = 'sent';
            }
        } catch (Throwable $error) {
            unset($error);
            $status = 'failed';
        }

        wp_safe_redirect(add_query_arg([
            'page' => EmailSettingsPage::SLUG,
            'safecontracts_email_test' => $status,
        ], admin_url('admin.php')));
        exit;
    }

    public static function render(): void
    {
        $page = isset($_GET['page']) && is_scalar($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
        if ($page !== EmailSettingsPage::SLUG || ! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            return;
        }

        $status = isset($_GET['safecontracts_email_test']) && is_scalar($_GET['safecontracts_email_test'])
            ? sanitize_key((string) $_GET['safecontracts_email_test'])
            : '';
        if ($status === 'sent') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(self::text('Test email sent successfully through Direct SMTP. Review your inbox and Recent delivery attempts.', 'تم إرسال رسالة الاختبار بنجاح عبر SMTP المباشر. راجع بريدك ومحاولات الإرسال الأخيرة.')) . '</p></div>';
        } elseif ($status === 'failed') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(self::text('Test email failed. Verify email delivery is enabled, your WordPress profile email is valid, and the Direct SMTP host, port, encryption and credentials are correct.', 'فشل إرسال رسالة الاختبار. تأكد من تفعيل البريد وصحة بريد حساب WordPress وصحة خادم ومنفذ وتشفير وبيانات دخول SMTP المباشر.')) . '</p></div>';
        }

        $user = function_exists('get_userdata') ? get_userdata(get_current_user_id()) : false;
        $rawEmail = is_object($user) ? (string) ($user->user_email ?? '') : '';
        $email = function_exists('sanitize_email') ? sanitize_email($rawEmail) : trim($rawEmail);
        $canTest = EmailSettings::validEmail($email);
        ?>
        <div class="notice notice-info">
            <p><strong><?php echo esc_html(self::text('Email delivery test', 'اختبار إرسال البريد')); ?></strong></p>
            <?php if ($canTest) : ?>
                <p><?php echo esc_html(sprintf(self::text('Send a real test through the saved Safe Contracts Direct SMTP settings to your profile email: %s', 'أرسل اختباراً فعلياً باستخدام إعدادات SMTP المباشر المحفوظة في Safe Contracts إلى بريد حسابك: %s'), $email)); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin: 0 0 12px;">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                    <?php wp_nonce_field(self::ACTION); ?>
                    <button type="submit" class="button button-secondary"><?php echo esc_html(self::text('Send test email', 'إرسال رسالة اختبار')); ?></button>
                </form>
            <?php else : ?>
                <p><?php echo esc_html(self::text('Your current WordPress profile does not have a valid email address. Add one before testing email delivery.', 'لا يحتوي حساب WordPress الحالي على بريد إلكتروني صالح. أضف بريداً صحيحاً قبل اختبار الإرسال.')); ?></p>
            <?php endif; ?>
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
