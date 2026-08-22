<?php

declare(strict_types=1);

namespace SafeContracts\PublicSite;

final class AppStorePages
{
    /** @var array<string,string> */
    private const PATHS = [
        'privacy' => '/alkenzy-adv/privacy/',
        'terms' => '/alkenzy-adv/terms/',
        'deletion' => '/alkenzy-adv/account-deletion/',
        'support' => '/alkenzy-adv/support/',
    ];

    public static function register(): void
    {
        add_action('template_redirect', [self::class, 'maybeRender'], 0);
    }

    /** @return array{privacy:string,terms:string,deletion:string,support:string} */
    public static function urls(): array
    {
        return [
            'privacy' => home_url(self::PATHS['privacy']),
            'terms' => home_url(self::PATHS['terms']),
            'deletion' => home_url(self::PATHS['deletion']),
            'support' => home_url(self::PATHS['support']),
        ];
    }

    public static function maybeRender(): void
    {
        $requestPath = wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if (! is_string($requestPath) || $requestPath === '') {
            return;
        }

        $normalized = trailingslashit('/' . ltrim($requestPath, '/'));
        foreach (self::PATHS as $page => $path) {
            if ($normalized !== $path) {
                continue;
            }
            self::render($page);
            exit;
        }
    }

    private static function render(string $page): void
    {
        status_header(200);
        nocache_headers();
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));

        $siteName = (string) get_bloginfo('name');
        $siteName = $siteName !== '' ? $siteName : 'Alkenzy ADV';
        $adminEmail = sanitize_email((string) get_option('admin_email', ''));
        $adminEmail = $adminEmail !== '' ? $adminEmail : 'support@alkenzy.com';
        $urls = self::urls();
        $content = self::content($page, $siteName, $adminEmail, $urls);
        $title = $content['title'];
        $body = $content['body'];
        ?>
<!doctype html>
<html lang="en" dir="auto">
<head>
    <meta charset="<?php echo esc_attr((string) get_bloginfo('charset')); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($title . ' — ' . $siteName); ?></title>
    <style>
        :root{color-scheme:light dark}body{margin:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f5f7fb;color:#172033;line-height:1.65}.sc-page{max-width:880px;margin:0 auto;padding:32px 18px 64px}.sc-card{background:#fff;border:1px solid #dfe5ef;border-radius:18px;padding:28px;box-shadow:0 12px 35px rgba(18,35,64,.08)}h1{margin-top:0;font-size:2rem}h2{margin-top:2rem;font-size:1.2rem}a{color:#1459c7}.sc-meta{color:#5c667a;font-size:.92rem}.sc-ar{direction:rtl;text-align:right;border-top:1px solid #e6eaf0;margin-top:28px;padding-top:24px}@media(prefers-color-scheme:dark){body{background:#111827;color:#e5e7eb}.sc-card{background:#182234;border-color:#2b3a52}.sc-meta{color:#aeb8c8}.sc-ar{border-color:#334155}a{color:#7db2ff}}
    </style>
</head>
<body>
<main class="sc-page"><article class="sc-card">
    <h1><?php echo esc_html($title); ?></h1>
    <p class="sc-meta">Alkenzy ADV · <?php echo esc_html(gmdate('Y-m-d')); ?></p>
    <?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- constructed only from escaped values below. ?>
    <p class="sc-meta"><a href="<?php echo esc_url($urls['privacy']); ?>">Privacy</a> · <a href="<?php echo esc_url($urls['terms']); ?>">Terms</a> · <a href="<?php echo esc_url($urls['deletion']); ?>">Account deletion</a> · <a href="<?php echo esc_url($urls['support']); ?>">Support</a></p>
</article></main>
</body>
</html>
        <?php
    }

    /** @param array{privacy:string,terms:string,deletion:string,support:string} $urls @return array{title:string,body:string} */
    private static function content(string $page, string $siteName, string $adminEmail, array $urls): array
    {
        $email = esc_html(antispambot($adminEmail));
        $mailtoDeletion = esc_url('mailto:' . $adminEmail . '?subject=' . rawurlencode('Alkenzy ADV account deletion request'));
        $mailtoSupport = esc_url('mailto:' . $adminEmail . '?subject=' . rawurlencode('Alkenzy ADV support request'));
        $site = esc_html($siteName);

        if ($page === 'deletion') {
            return [
                'title' => 'Alkenzy ADV Account Deletion',
                'body' => '<p>You can initiate deletion of your Alkenzy ADV account and associated personal data by emailing <a href="' . $mailtoDeletion . '">' . $email . '</a>. Use the email address or username connected to your account so ownership can be verified. We may ask for additional verification before processing the request.</p>'
                    . '<p>After verification, account data that is not required for legitimate accounting, contractual, security, fraud-prevention, audit, or legal obligations will be deleted or de-identified. Any data that must be retained will be limited to the required purpose and retention period described in the privacy policy.</p>'
                    . '<h2>Request steps</h2><ol><li>Open the email link above.</li><li>Include your Alkenzy ADV username and the organization using the service.</li><li>Write “Delete my account and associated personal data”.</li><li>Complete any ownership-verification step sent by the administrator.</li></ol>'
                    . '<div class="sc-ar"><h2>طلب حذف الحساب</h2><p>يمكنك بدء طلب حذف حساب Alkenzy ADV والبيانات الشخصية المرتبطة به عبر البريد <a href="' . $mailtoDeletion . '">' . $email . '</a>. أرسل الطلب من البريد أو اذكر اسم المستخدم المرتبط بالحساب حتى يمكن التحقق من الملكية. بعد التحقق، سيتم حذف أو إخفاء هوية البيانات التي لا يلزم الاحتفاظ بها لأسباب محاسبية أو تعاقدية أو أمنية أو قانونية أو لأغراض التدقيق.</p></div>',
            ];
        }

        if ($page === 'support') {
            return [
                'title' => 'Alkenzy ADV Support',
                'body' => '<p>For Alkenzy ADV application support, sign-in problems, notification issues, billing/contract data questions, privacy requests, or account deletion assistance, contact <a href="' . $mailtoSupport . '">' . $email . '</a>.</p>'
                    . '<p>Please include the app version, device model, a short description of the issue, and screenshots only when they do not expose passwords, tokens, or confidential contract data.</p>'
                    . '<div class="sc-ar"><h2>الدعم الفني</h2><p>للدعم الفني الخاص بتطبيق Alkenzy ADV أو مشاكل تسجيل الدخول والإشعارات وطلبات الخصوصية وحذف الحساب، تواصل عبر <a href="' . $mailtoSupport . '">' . $email . '</a>. لا ترسل كلمات مرور أو رموز دخول أو بيانات عقود سرية داخل رسالة الدعم.</p></div>',
            ];
        }

        if ($page === 'terms') {
            return [
                'title' => 'Alkenzy ADV Terms of Use',
                'body' => '<p>Alkenzy ADV is a business application for authorized users of ' . $site . '. Access credentials are personal to the authorized user. Users must not attempt to bypass permissions, alter authoritative financial records outside permitted workflows, or disclose confidential customer, supplier, contract, payment, or notification data.</p>'
                    . '<p>Service availability, data retention, and organizational responsibilities may also be governed by the customer organization’s contract and applicable law. Advertising, when enabled by the administrator, may be supplied by Google AdMob or AppLovin MAX and remains subject to the selected provider’s applicable terms and consent requirements.</p>'
                    . '<div class="sc-ar"><h2>شروط الاستخدام</h2><p>Alkenzy ADV تطبيق أعمال مخصص للمستخدمين المصرح لهم. بيانات الدخول شخصية ولا يجوز تجاوز الصلاحيات أو كشف بيانات العملاء أو الموردين أو العقود أو الدفعات أو الإشعارات. عند تفعيل الإعلانات من مسؤول النظام قد يتم استخدام Google AdMob أو AppLovin MAX وفق إعدادات النظام ومتطلبات الموافقة والخصوصية.</p></div>',
            ];
        }

        return [
            'title' => 'Alkenzy ADV Privacy Policy',
            'body' => '<p>This privacy policy explains how Alkenzy ADV processes information when the mobile application is used with ' . $site . '.</p>'
                . '<h2>Information processed</h2><p>The service may process account identifiers, name/contact details configured by the organization, authorized customer/supplier/contract/payment information, device registration identifiers used for push notifications, technical diagnostics, IP/network information, and security/audit events. When advertising is enabled, the active advertising provider may process device and advertising identifiers, consent choices, coarse location or other signals permitted by the device and provider settings for ad delivery, measurement, fraud prevention, and—where consent allows—personalization.</p>'
                . '<h2>Service providers</h2><p>Alkenzy ADV may use WordPress hosting/infrastructure, Firebase Cloud Messaging for push notifications, Google AdMob, and AppLovin MAX. Only the advertising provider selected by the administrator is requested to serve ads by the app. Provider SDKs and policies may change independently, so the organization must keep its Google Play Data Safety answers consistent with the deployed configuration.</p>'
                . '<h2>Purpose and retention</h2><p>Information is processed to authenticate users, provide contract and finance workflows, deliver notifications, secure and audit the service, provide support, and—when enabled—serve and measure advertising. Business and audit records may be retained when required for accounting, contractual, security, fraud-prevention, or legal obligations. Other personal data is deleted or de-identified when no longer required.</p>'
                . '<h2>Your choices</h2><p>Advertising consent/privacy options are shown when required by the active provider and jurisdiction. You may also request support or account deletion using the links below.</p>'
                . '<p><a href="' . esc_url($urls['deletion']) . '">Account deletion request</a> · <a href="' . esc_url($urls['support']) . '">Support</a></p>'
                . '<div class="sc-ar"><h2>سياسة الخصوصية</h2><p>قد يعالج Alkenzy ADV بيانات الحساب والبيانات التشغيلية المصرح بها وبيانات تسجيل الجهاز للإشعارات وسجلات الأمان والتشخيص. عند تفعيل الإعلانات قد يعالج مزود الإعلانات المحدد من مسؤول النظام—Google AdMob أو AppLovin MAX—معرفات الجهاز والإعلانات وخيارات الموافقة والإشارات المسموح بها لعرض الإعلانات وقياسها ومنع الاحتيال، وللتخصيص عندما تسمح الموافقة بذلك.</p><p>يمكن الاحتفاظ بسجلات الأعمال والتدقيق عندما يكون ذلك مطلوباً لأغراض محاسبية أو تعاقدية أو أمنية أو قانونية. ويمكن طلب حذف الحساب والبيانات الشخصية عبر صفحة حذف الحساب.</p></div>',
        ];
    }
}
