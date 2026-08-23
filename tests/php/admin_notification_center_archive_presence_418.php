<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    $content = file_get_contents($path);
    if (! is_string($content)) {
        throw new RuntimeException('Unable to read ' . $relative);
    }
    return $content;
};
$assertContains = static function (string $needle, string $haystack, string $message): void {
    if (! str_contains($haystack, $needle)) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};
$assertNotContains = static function (string $needle, string $haystack, string $message): void {
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$migrator = $read('wordpress-plugin/safecontracts/src/Database/Migrator.php');
$migration = $read('wordpress-plugin/safecontracts/src/Database/Migrations/Migration0015NotificationCenter.php');
$assertContains("'1.15.0' => Migration0016MobileCrudCapabilities::class", $migrator, 'mobile CRUD capability migration must remain registered at schema version 1.15.0');
$assertContains('Migration0015NotificationCenter::class', $migrator, 'notification center migration must remain registered');
$assertContains('Migration0016MobileCrudCapabilities::class', $migrator, 'mobile CRUD capability migration must be registered');
foreach (['recipient_user_ids_json', 'push_enabled', 'email_enabled', 'email_subject_template', 'email_body_template', 'icon_key', 'safecontracts_notification_suppressions', 'channel varchar(20)'] as $schemaContract) {
    $assertContains($schemaContract, $migration, 'migration must contain ' . $schemaContract);
}

$rule = $read('wordpress-plugin/safecontracts/src/Notifications/NotificationRule.php');
$resolver = $read('wordpress-plugin/safecontracts/src/Notifications/RecipientResolver.php');
$assertContains('recipient_user_ids', $rule, 'rules must support selected individual users');
$assertContains('push_enabled', $rule, 'rules must support Push channel selection');
$assertContains('email_enabled', $rule, 'rules must support Email channel selection');
$assertContains('specificUsers', $resolver, 'recipient resolution must include explicit users');

$email = $read('wordpress-plugin/safecontracts/src/Notifications/EmailDeliveryService.php');
$smtp = $read('wordpress-plugin/safecontracts/src/Notifications/DirectSmtpTransport.php');
$smtpSettings = $read('wordpress-plugin/safecontracts/src/Notifications/SmtpSettings.php');
$delivery = $read('wordpress-plugin/safecontracts/src/Notifications/NotificationDeliveryService.php');
$logs = $read('wordpress-plugin/safecontracts/src/Notifications/DeliveryLogRepository.php');
$assertContains('DirectSmtpTransport', $email, 'email notification delivery must use the direct SMTP transport');
$assertContains('stream_socket_client(', $smtp, 'direct SMTP transport must open its own SMTP connection');
$assertContains('STARTTLS', $smtp, 'direct SMTP transport must support STARTTLS');
$assertContains('AUTH LOGIN', $smtp, 'direct SMTP transport must support authenticated SMTP');
$assertContains('aes-256-gcm', $smtpSettings, 'SMTP password must be encrypted at rest');
$assertNotContains('wp_mail(', $email, 'email notification delivery must bypass WordPress wp_mail');
$assertNotContains('wp_mail(', $smtp, 'direct SMTP transport must not depend on WordPress wp_mail');
$assertContains("'email'", $email, 'email attempts must be channel-labelled in delivery logs');
$assertContains('PushDeliveryService', $delivery, 'unified delivery service must retain Push');
$assertContains('EmailDeliveryService', $delivery, 'unified delivery service must include Email');
$assertContains('channel', $logs, 'delivery log must expose channel');

$suppression = $read('wordpress-plugin/safecontracts/src/Notifications/NotificationSuppressionRepository.php');
$engine = $read('wordpress-plugin/safecontracts/src/Notifications/NotificationEngine.php');
$assertContains("public const CONTRACT = 'contract'", $suppression, 'contract suppression must be supported');
$assertContains("public const PAYMENT = 'payment'", $suppression, 'payment suppression must be supported');
$assertContains('isSuppressed($paymentId, $contractId)', $engine, 'notification engine must enforce administrative suppressions');

$center = $read('wordpress-plugin/safecontracts/src/Admin/NotificationCenterPage.php');
foreach (['Notification Center', 'Notification inbox', 'read_state', 'Mark all as read', 'Notification Settings', 'Email Settings'] as $uiContract) {
    $assertContains($uiContract, $center, 'notification inbox must expose ' . $uiContract);
}
foreach (['check_admin_referer(self::SAVE_RULE_ACTION)', 'check_admin_referer(self::SAVE_TEMPLATE_ACTION)', 'check_admin_referer(self::DIRECT_SEND_ACTION)', 'check_admin_referer(self::SUPPRESSION_ACTION)', 'check_admin_referer(self::READ_ACTION)'] as $nonceContract) {
    $assertContains($nonceContract, $center, 'notification-center write must be nonce protected');
}
$assertContains('Capabilities::MANAGE_NOTIFICATIONS', $center, 'notification center must be permission gated');
$assertNotContains('name="from_address"', $center, 'notification center must not render sender email settings');
$assertNotContains('name="from_name"', $center, 'notification center must not render sender name settings');

$emailSettingsPage = $read('wordpress-plugin/safecontracts/src/Admin/EmailSettingsPage.php');
foreach (['Email Settings', 'from_address', 'from_name', 'Enable email notifications'] as $emailUiContract) {
    $assertContains($emailUiContract, $emailSettingsPage, 'standalone Email Settings page must expose ' . $emailUiContract);
}

$smtpControl = $read('wordpress-plugin/safecontracts/src/Admin/DirectSmtpSettingsControl.php');
foreach (['Direct SMTP connection', 'SMTP host', 'SMTP port', 'SMTP username', 'SMTP password', 'Save Direct SMTP Settings', 'check_admin_referer(self::ACTION)', 'Capabilities::MANAGE_NOTIFICATIONS', 'EmailSettingsPage::SLUG'] as $smtpUiContract) {
    $assertContains($smtpUiContract, $smtpControl, 'standalone email settings SMTP control must expose ' . $smtpUiContract);
}

$roles = $read('wordpress-plugin/safecontracts/src/Admin/UsersRolesPage.php');
$assertContains('SAVE_CAPABILITIES_ACTION', $roles, 'role capabilities must be editable');
$assertContains('ASSIGN_ROLE_ACTION', $roles, 'Safe Contracts role membership must be editable');
$assertContains('remove_cap(', $roles, 'role capability removal must be supported');
$assertContains('add_role(', $roles, 'user role assignment must be supported');

$archive = $read('wordpress-plugin/safecontracts/src/Admin/ArchivePage.php');
$adminRead = $read('wordpress-plugin/safecontracts/src/Admin/AdminReadRepository.php');
$assertContains("public const SLUG = 'safecontracts-archive'", $archive, 'archive page must have a stable dashboard link');
$assertContains("c.is_archived = 0", $adminRead, 'normal contract reads must exclude archived contracts');
$assertContains("p.is_archived = 0", $adminRead, 'normal payment reads must exclude archived payments');
$assertContains("cl.is_archived = 0", $adminRead, 'normal collection reads must exclude archived collections');
$assertContains("cu.is_active = 1", $adminRead, 'normal reads must exclude archived customers');
$assertNotContains('WHERE 1 = 1 ORDER BY c.is_archived', $adminRead, 'operational contract list must not sort archived rows into normal pages');

$presence = $read('wordpress-plugin/safecontracts/src/Presence/PresenceService.php');
$activeUsers = $read('wordpress-plugin/safecontracts/src/Admin/ActiveUsersPage.php');
$mobileAuth = $read('wordpress-plugin/safecontracts/src/Auth/MobileBearerAuthentication.php');
$assertContains('ACTIVE_WINDOW_SECONDS = 300', $presence, 'active presence window must be five minutes');
$assertContains('heartbeat_received', $presence, 'dashboard presence must use WordPress heartbeat');
$assertContains('touchMobile($resolved)', $mobileAuth, 'authenticated mobile API activity must update mobile presence');
$assertContains('Active on app', $activeUsers, 'active-users page must distinguish app presence');
$assertContains('Active on dashboard', $activeUsers, 'active-users page must distinguish dashboard presence');
$assertNotContains('token_hash', $activeUsers, 'active-users page must not expose token material');

$summaries = $read('wordpress-plugin/safecontracts/src/Admin/AdminPageSummaryInjector.php');
$assertContains('CustomersPage::SLUG', $summaries, 'customer page must retain summary cards');
$assertContains('PaymentsPage::SLUG', $summaries, 'payment page must retain summary cards');
$assertContains('CollectionsPage::SLUG', $summaries, 'collection page must retain summary cards');
$assertContains('ReportsPage::SLUG', $summaries, 'reports page must retain summary cards');
$assertContains('NotificationsPage::SLUG', $summaries, 'notifications page must retain summary cards');
$assertContains('ContractsPage::SLUG', $summaries, 'contract page must be explicitly recognized by the summary injector');
$assertNotContains('if ($page === ContractsPage::SLUG)', $summaries, 'contract page must not create its own top summary card set');

$fcm = $read('wordpress-plugin/safecontracts/src/Notifications/FirebasePushTransport.php');
$bootstrap = $read('scripts/bootstrap_android.sh');
$activity = $read('mobile/android-release/MainActivity.kt');
$presenter = $read('mobile/lib/features/notifications/notification_presenter.dart');
$main = $read('mobile/lib/main.dart');
$assertContains("'priority' => 'high'", $fcm, 'FCM must request high-priority Android delivery');
$assertContains("'channel_id' => 'safe_contracts_alerts'", $fcm, 'FCM must target the visible Safe Contracts notification channel');
$assertContains('safe_contracts_alerts', $activity, 'Android must create the Safe Contracts notification channel');
$assertContains('IMPORTANCE_HIGH', $activity, 'Android notification channel must be high importance');
$assertContains('safecontracts/notifications', $activity, 'native foreground notification bridge must be present');
$assertContains('FirebaseMessaging.onMessage', $presenter, 'foreground Firebase messages must be presented');
$assertContains('MobileNotificationPresenter.start()', $main, 'foreground notification presenter must start at app bootstrap');
$assertContains('default_notification_channel_id', $bootstrap, 'release Android manifest must declare the default FCM channel');

$plugin = $read('wordpress-plugin/safecontracts/src/Plugin.php');
$bootstrapPlugin = $read('wordpress-plugin/safecontracts/safecontracts.php');
foreach (['NotificationCenterPage::class', 'ArchivePage::class', 'ActiveUsersPage::class', 'AdminPageSummaryInjector::register()', 'PresenceService::register()', 'NotificationCenterArabicDefaults::register()', 'NotificationCenterAuditRecorder::register()'] as $wire) {
    $assertContains($wire, $plugin, 'plugin must wire ' . $wire);
}
$assertContains('EmailSettingsPage::register()', $bootstrapPlugin, 'plugin bootstrap must wire standalone Email Settings');
$assertContains('NotificationCenterPage::registerInboxActions()', $bootstrapPlugin, 'plugin bootstrap must wire notification inbox read actions');

$criticalAudit = $read('wordpress-plugin/safecontracts/src/Audit/AuditRecorder.php');
$newAudit = $read('wordpress-plugin/safecontracts/src/Audit/NotificationCenterAuditRecorder.php');
foreach (['safecontracts_notification_rule_saved', 'safecontracts_notification_suppression_changed', 'safecontracts_direct_notification_sent', 'safecontracts_role_capabilities_changed', 'safecontracts_user_role_changed'] as $event) {
    $assertContains($event, $newAudit, 'notification-center audit must record ' . $event);
    $assertNotContains($event, $criticalAudit, 'P10 critical audit registry must remain unchanged for ' . $event);
}
$assertNotContains('token_hash', $newAudit, 'notification-center audit must not record device-token material');
$assertNotContains('authorization', strtolower($newAudit), 'notification-center audit must not record authorization material');

echo "Admin notification center/archive/presence regression checks passed.\n";
