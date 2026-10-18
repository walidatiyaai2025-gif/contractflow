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

        $operator = 'Alkenzy Advertising';
        $siteUrl = home_url('/');
        $adminEmail = sanitize_email((string) get_option('admin_email', ''));
        $adminEmail = $adminEmail !== '' ? $adminEmail : 'playreview@alkenzy.com';
        $urls = self::urls();
        $content = self::content($page, $operator, $siteUrl, $adminEmail, $urls);
        $title = $content['title'];
        $body = $content['body'];
        ?>
<!doctype html>
<html lang="en" dir="auto">
<head>
    <meta charset="<?php echo esc_attr((string) get_bloginfo('charset')); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($title . ' — Alkenzy ADV'); ?></title>
    <style>
        :root{color-scheme:light dark}body{margin:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f5f7fb;color:#172033;line-height:1.65}.sc-page{max-width:880px;margin:0 auto;padding:32px 18px 64px}.sc-card{background:#fff;border:1px solid #dfe5ef;border-radius:18px;padding:28px;box-shadow:0 12px 35px rgba(18,35,64,.08)}h1{margin-top:0;font-size:2rem}h2{margin-top:2rem;font-size:1.2rem}a{color:#1459c7}.sc-meta{color:#5c667a;font-size:.92rem}.sc-ar{direction:rtl;text-align:right;border-top:1px solid #e6eaf0;margin-top:28px;padding-top:24px}@media(prefers-color-scheme:dark){body{background:#111827;color:#e5e7eb}.sc-card{background:#182234;border-color:#2b3a52}.sc-meta{color:#aeb8c8}.sc-ar{border-color:#334155}a{color:#7db2ff}}
    </style>
</head>
<body>
<main class="sc-page"><article class="sc-card">
    <h1><?php echo esc_html($title); ?></h1>
    <p class="sc-meta">Alkenzy ADV · Last updated 2026-08-22</p>
    <?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- constructed only from escaped values below. ?>
    <p class="sc-meta"><a href="<?php echo esc_url($urls['privacy']); ?>">Privacy</a> · <a href="<?php echo esc_url($urls['terms']); ?>">Terms</a> · <a href="<?php echo esc_url($urls['deletion']); ?>">Account deletion</a> · <a href="<?php echo esc_url($urls['support']); ?>">Support</a></p>
</article></main>
</body>
</html>
        <?php
    }

    /** @param array{privacy:string,terms:string,deletion:string,support:string} $urls @return array{title:string,body:string} */
    private static function content(string $page, string $operator, string $siteUrl, string $adminEmail, array $urls): array
    {
        $email = esc_html(antispambot($adminEmail));
        $mailtoDeletion = esc_url('mailto:' . $adminEmail . '?subject=' . rawurlencode('Alkenzy ADV account deletion request'));
        $mailtoSupport = esc_url('mailto:' . $adminEmail . '?subject=' . rawurlencode('Alkenzy ADV support request'));
        $operatorName = esc_html($operator);
        $website = esc_url($siteUrl);

        if ($page === 'deletion') {
            return [
                'title' => 'Alkenzy ADV Account & Data Deletion',
                'body' => '<p><strong>App:</strong> Alkenzy ADV &nbsp; <strong>Operator:</strong> ' . $operatorName . ' &nbsp; <strong>Android package:</strong> com.safecontracts.safecontracts_mobile</p>'
                    . '<p>You can initiate deletion of your Alkenzy ADV account and associated personal data by emailing <a href="' . $mailtoDeletion . '">' . $email . '</a>. Send the request from, or clearly identify, the email address/username connected to the account so ownership can be verified.</p>'
                    . '<h2>What is deleted</h2><p>After verification, account profile data that is not required to keep the service secure, active mobile sessions, push-notification device registrations, and support identifiers tied only to the account are deleted or de-identified when no longer required.</p>'
                    . '<h2>What may be retained</h2><p>Business records such as customer/supplier records, contracts, payment/collection records, security logs, and audit history may be retained only where required by applicable law, accounting/audit obligations, fraud-prevention/security needs, or the customer organization’s contract. Retained records are limited to the relevant purpose and kept no longer than the applicable statutory, contractual, accounting, security, or audit retention period.</p>'
                    . '<h2>Request steps</h2><ol><li>Email <a href="' . $mailtoDeletion . '">' . $email . '</a>.</li><li>Include your Alkenzy ADV username/email and organization.</li><li>State: “Delete my account and associated personal data”.</li><li>Complete any ownership-verification step requested by the administrator.</li></ol>'
                    . '<p>Privacy policy: <a href="' . esc_url($urls['privacy']) . '">' . esc_html($urls['privacy']) . '</a></p>'
                    . '<div class="sc-ar"><h2>طلب حذف الحساب والبيانات</h2><p>يمكنك بدء طلب حذف حساب Alkenzy ADV والبيانات الشخصية المرتبطة به عبر البريد <a href="' . $mailtoDeletion . '">' . $email . '</a>. بعد التحقق من ملكية الحساب، يتم حذف أو إخفاء هوية بيانات الحساب والجلسات وتسجيلات الجهاز للإشعارات عندما لا تعد مطلوبة. قد يتم الاحتفاظ بسجلات العقود والمدفوعات والتدقيق والأمان فقط للمدة التي تفرضها الالتزامات القانونية أو المحاسبية أو التعاقدية أو الأمنية.</p></div>',
            ];
        }

        if ($page === 'support') {
            return [
                'title' => 'Alkenzy ADV Support',
                'body' => '<p><strong>Operator:</strong> ' . $operatorName . '<br><strong>Website:</strong> <a href="' . $website . '">' . esc_html($siteUrl) . '</a><br><strong>Support / privacy contact:</strong> <a href="' . $mailtoSupport . '">' . $email . '</a></p>'
                    . '<p>For Alkenzy ADV application support, sign-in problems, notification issues, contract/payment data questions, privacy requests, or account deletion assistance, contact the address above.</p>'
                    . '<p>Please include the app version, device model, a short description of the issue, and screenshots only when they do not expose passwords, tokens, or confidential contract data.</p>'
                    . '<div class="sc-ar"><h2>الدعم الفني</h2><p>للدعم الفني الخاص بتطبيق Alkenzy ADV أو مشاكل تسجيل الدخول والإشعارات وطلبات الخصوصية وحذف الحساب، تواصل عبر <a href="' . $mailtoSupport . '">' . $email . '</a>. لا ترسل كلمات مرور أو رموز دخول أو بيانات عقود سرية داخل رسالة الدعم.</p></div>',
            ];
        }

        if ($page === 'terms') {
            return [
                'title' => 'Alkenzy ADV Terms of Use',
                'body' => '<p>Alkenzy ADV is a business application operated by ' . $operatorName . ' for authorized organizational users. Android package: <code>com.safecontracts.safecontracts_mobile</code>.</p>'
                    . '<p>Access credentials are personal to the authorized user. Users must not bypass permissions, alter authoritative financial records outside permitted workflows, or disclose confidential customer, supplier, contract, payment, notification, or audit data.</p>'
                    . '<p>Service availability, data retention, and organizational responsibilities may also be governed by the customer organization’s contract and applicable law. Advertising, when enabled by the administrator, may be supplied by Google AdMob or AppLovin MAX and remains subject to the selected provider’s applicable terms and consent requirements.</p>'
                    . '<div class="sc-ar"><h2>شروط الاستخدام</h2><p>Alkenzy ADV تطبيق أعمال تديره ' . $operatorName . ' ومخصص للمستخدمين المصرح لهم. بيانات الدخول شخصية ولا يجوز تجاوز الصلاحيات أو كشف بيانات العملاء أو الموردين أو العقود أو الدفعات أو الإشعارات أو سجلات التدقيق. عند تفعيل الإعلانات من مسؤول النظام قد يتم استخدام Google AdMob أو AppLovin MAX وفق إعدادات النظام ومتطلبات الموافقة والخصوصية.</p></div>',
            ];
        }

        return [
            'title' => 'Alkenzy ADV Privacy Policy',
            'body' => '<p><strong>Data controller / service operator:</strong> ' . $operatorName . '<br><strong>Product:</strong> Alkenzy ADV<br><strong>Android package:</strong> com.safecontracts.safecontracts_mobile<br><strong>Website:</strong> <a href="' . $website . '">' . esc_html($siteUrl) . '</a><br><strong>Privacy contact:</strong> <a href="mailto:' . esc_attr($adminEmail) . '">' . $email . '</a></p>'
                . '<p>This privacy policy explains how Alkenzy ADV processes information when the mobile application is used with the Alkenzy ADV service.</p>'
                . '<h2>Information processed</h2><p>The service may process account identifiers, username, name/contact details configured by the organization, authorized customer/supplier information, contracts, payment/collection information, assigned-workflow data, device registration identifiers used for push notifications, IP/network information, technical diagnostics, app interactions required for service operation, and security/audit events. Passwords are used for authentication but are not stored by the mobile app.</p>'
                . '<h2>Advertising and device data</h2><p>When advertising is enabled, the active advertising provider may process device or advertising identifiers, consent choices, approximate/coarse location or other device/network signals permitted by the device and provider settings for ad delivery, measurement, frequency control, fraud prevention, and—where consent allows—personalization. Alkenzy ADV can use Google AdMob or AppLovin MAX; only the provider selected by the administrator is requested to serve ads at runtime.</p>'
                . '<h2>Service providers</h2><p>Alkenzy ADV may use WordPress hosting/infrastructure, Firebase Cloud Messaging for push notifications, Google AdMob, and AppLovin MAX. These providers process data under their own terms and privacy obligations where applicable.</p>'
                . '<h2>Purposes</h2><p>Information is processed to authenticate authorized users; provide contract, supplier/customer, payment, collection, finance, reporting, and follow-up workflows; deliver notifications; maintain security and auditability; provide support; and, when enabled, serve and measure advertising.</p>'
                . '<h2>Security</h2><p>The production mobile client requires HTTPS for the Alkenzy ADV API. Access is authenticated and role/scope restricted. Users should not share credentials or transmit confidential contract data through support messages.</p>'
                . '<h2>Retention and deletion</h2><p>Account/session/device-registration data that is no longer required is deleted or de-identified. Business, accounting, contractual, security, fraud-prevention, and audit records may be retained only for the period required by applicable law, customer contract, accounting/audit duties, or legitimate security requirements. You can request account/data deletion at <a href="' . esc_url($urls['deletion']) . '">' . esc_html($urls['deletion']) . '</a>.</p>'
                . '<h2>Your privacy choices</h2><p>Advertising consent and privacy options are shown when required by the active provider and jurisdiction. You may request access, correction, support, or deletion using the contact information above, subject to applicable legal and organizational obligations.</p>'
                . '<h2>Age</h2><p>Alkenzy ADV is a business application intended for users aged 18 and over and is not designed for children.</p>'
                . '<h2>Changes</h2><p>This policy may be updated when app functionality, service providers, or legal requirements change. The “Last updated” date at the top identifies the current version.</p>'
                . '<div class="sc-ar"><h2>سياسة الخصوصية</h2><p><strong>مشغل الخدمة:</strong> ' . $operatorName . '<br><strong>المنتج:</strong> Alkenzy ADV<br><strong>معرّف تطبيق أندرويد:</strong> com.safecontracts.safecontracts_mobile<br><strong>جهة التواصل للخصوصية:</strong> <a href="mailto:' . esc_attr($adminEmail) . '">' . $email . '</a></p><p>قد يعالج التطبيق بيانات الحساب والاسم وبيانات الاتصال التي تضبطها المؤسسة، وبيانات العملاء والموردين والعقود والمدفوعات والتحصيل، ومعرفات الجهاز اللازمة للإشعارات، ومعلومات الشبكة والتشخيص وسجلات الأمان والتدقيق. لا يخزن تطبيق الهاتف كلمة مرور المستخدم.</p><p>عند تفعيل الإعلانات قد يعالج مزود الإعلانات المحدد من المسؤول—Google AdMob أو AppLovin MAX—معرفات الجهاز والإعلانات وخيارات الموافقة والإشارات المسموح بها لعرض الإعلانات وقياسها ومنع الاحتيال، وللتخصيص عندما تسمح الموافقة بذلك.</p><p>قد يتم الاحتفاظ بالسجلات المحاسبية والتعاقدية وسجلات الأمان والتدقيق فقط للمدة المطلوبة قانونياً أو تعاقدياً أو محاسبياً أو أمنياً. التطبيق مخصص للمستخدمين بعمر 18 سنة فأكثر وليس موجهاً للأطفال. يمكن طلب حذف الحساب والبيانات من <a href="' . esc_url($urls['deletion']) . '">صفحة حذف الحساب</a>.</p></div>',
        ];
    }
}
