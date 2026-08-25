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

$contractsPage = $read('wordpress-plugin/safecontracts/src/Admin/ContractsPage.php');
foreach ([
    'BULK_ASSIGN_ACTION',
    'handleBulkAssign',
    'Assign existing unassigned contracts',
    'user_can($accountantId, Capabilities::VIEW_ASSIGNED)',
    "! empty(\$contract['accountant_user_id'])",
    '$service->assignAccountant($contractId, $accountantId);',
] as $contractAdminContract) {
    $assertContains($contractAdminContract, $contractsPage, 'contracts admin must preserve responsible-accountant remediation: ' . $contractAdminContract);
}

$adminRead = $read('wordpress-plugin/safecontracts/src/Admin/AdminReadRepository.php');
$assertContains(".accountant_user_id = ' . get_current_user_id()", $adminRead, 'assigned-scope admin reads must remain bound to the current responsible accountant');
$assertContains('c.accountant_user_id', $adminRead, 'contract/payment projections must retain responsible accountant context');

$contractService = $read('wordpress-plugin/safecontracts/src/Contracts/ContractService.php');
foreach ([
    'Capabilities::ASSIGN_CONTRACTS',
    'assignAccountant(int $contractId, ?int $accountantUserId)',
    'assertEligibleAccountant($accountantUserId)',
    'Capabilities::VIEW_ASSIGNED',
    'safecontracts_contract_accountant_assigned',
] as $serviceContract) {
    $assertContains($serviceContract, $contractService, 'contract service must keep assignment authorization and eligibility: ' . $serviceContract);
}

$mutationController = $read('wordpress-plugin/safecontracts/src/Rest/ContractMutationController.php');
foreach ([
    "'/contracts/(?P<id>\\\\d+)/accountant'",
    "'permission_callback' => [self::class, 'canAssignContracts']",
    "count(\$body) !== 1 || ! array_key_exists('accountant_user_id', \$body)",
    '(new ContractService())->assignAccountant($contractId, $accountantUserId);',
] as $restContract) {
    $assertContains($restContract, $mutationController, 'mobile assignment endpoint must remain narrow and capability-gated: ' . $restContract);
}

$referenceData = $read('wordpress-plugin/safecontracts/src/Rest/ReferenceDataController.php');
foreach ([
    'current_user_can(Capabilities::ASSIGN_CONTRACTS)',
    "'role' => RoleRegistrar::ACCOUNTANT",
    'user_can($id, Capabilities::VIEW_ASSIGNED)',
    "'accountants' => \$accountants",
] as $referenceContract) {
    $assertContains($referenceContract, $referenceData, 'mobile accountant reference data must remain permission and role bounded: ' . $referenceContract);
}

$mobileEdit = $read('mobile/lib/features/contracts/contract_edit_screen.dart');
foreach ([
    "capabilities['safecontracts_assign_contracts'] == true",
    "'contracts/\$contractId/accountant'",
    "'accountant_user_id': accountantUserId",
    'Responsible accountant updated.',
] as $mobileContract) {
    $assertContains($mobileContract, $mobileEdit, 'mobile responsible-accountant workflow must remain wired: ' . $mobileContract);
}

$resolver = $read('wordpress-plugin/safecontracts/src/Notifications/RecipientResolver.php');
foreach ([
    "\$rule['target_assigned_accountant'] ?? false",
    '$assignedAccountantUserId !== null',
    '$this->userExists($assignedAccountantUserId)',
    '$ids[$assignedAccountantUserId] = $assignedAccountantUserId;',
] as $recipientContract) {
    $assertContains($recipientContract, $resolver, 'notification resolver must route to the assigned accountant only when valid: ' . $recipientContract);
}

// The approved release deliberately separates operational inbox, rule configuration and
// mail-transport configuration. Keep each responsibility on its own page.
$center = $read('wordpress-plugin/safecontracts/src/Admin/NotificationCenterPage.php');
foreach ([
    'Notification inbox',
    'read_state',
    'Mark all as read',
    'Notification Settings',
    'Email Settings',
] as $inboxContract) {
    $assertContains($inboxContract, $center, 'notification center must remain a focused inbox: ' . $inboxContract);
}
$assertNotContains('name="from_name"', $center, 'notification center must not render sender-name configuration');
$assertNotContains('name="from_address"', $center, 'notification center must not render sender-email configuration');

$notificationSettings = $read('wordpress-plugin/safecontracts/src/Admin/NotificationSettingsPage.php');
foreach ([
    "'target_assigned_accountant' => isset(\$_POST['target_assigned_accountant'])",
    "'email_enabled' => isset(\$_POST['email_enabled'])",
    'Assigned Accountant',
    'Delivery channels',
    'Email notification',
] as $notificationUiContract) {
    $assertContains($notificationUiContract, $notificationSettings, 'notification settings must expose assignment/channel controls: ' . $notificationUiContract);
}

$emailSettingsPage = $read('wordpress-plugin/safecontracts/src/Admin/EmailSettingsPage.php');
foreach ([
    'Email Settings',
    'Sender name',
    'Sender email',
    'Enable email notifications',
] as $emailSettingsContract) {
    $assertContains($emailSettingsContract, $emailSettingsPage, 'standalone email settings must expose sender configuration: ' . $emailSettingsContract);
}

$email = $read('wordpress-plugin/safecontracts/src/Notifications/EmailDeliveryService.php');
$direct = $read('wordpress-plugin/safecontracts/src/Notifications/DirectNotificationService.php');
$smtpSettings = $read('wordpress-plugin/safecontracts/src/Notifications/SmtpSettings.php');
$smtpTransport = $read('wordpress-plugin/safecontracts/src/Notifications/DirectSmtpTransport.php');
foreach ([
    "\$user->user_email",
    'SmtpSettings',
    'DirectSmtpTransport',
    "'recipient_email_unavailable'",
    "'email'",
] as $emailContract) {
    $assertContains($emailContract, $email, 'email delivery must use WordPress user email with direct SMTP delivery logging: ' . $emailContract);
}
$assertContains('DirectSmtpTransport', $direct, 'direct one-user email must use the same direct SMTP transport');
$assertNotContains('wp_mail(', $email, 'scheduled email delivery must bypass WordPress wp_mail');
$assertNotContains('wp_mail(', $direct, 'direct email delivery must bypass WordPress wp_mail');
foreach (['safecontracts_notification_smtp_host', 'safecontracts_notification_smtp_port', 'safecontracts_notification_smtp_password', 'aes-256-gcm', 'password_configured'] as $smtpSettingContract) {
    $assertContains($smtpSettingContract, $smtpSettings, 'SMTP settings must retain encrypted connection configuration: ' . $smtpSettingContract);
}
foreach (['stream_socket_client(', 'STARTTLS', 'AUTH LOGIN', 'MAIL FROM:<', 'RCPT TO:<', 'Content-Type: text/plain; charset=UTF-8'] as $smtpTransportContract) {
    $assertContains($smtpTransportContract, $smtpTransport, 'direct SMTP transport must perform the SMTP protocol itself: ' . $smtpTransportContract);
}
$assertNotContains('wp_mail(', $smtpTransport, 'direct SMTP transport must never call WordPress wp_mail');

$smtpControl = $read('wordpress-plugin/safecontracts/src/Admin/DirectSmtpSettingsControl.php');
foreach ([
    "public const ACTION = 'safecontracts_direct_smtp_save'",
    'check_admin_referer(self::ACTION)',
    'Capabilities::MANAGE_NOTIFICATIONS',
    'SMTP host',
    'SMTP port',
    'SMTP username',
    'SMTP password',
    'Save Direct SMTP Settings',
    'WordPress wp_mail and WordPress SMTP plugins are bypassed.',
    'EmailSettingsPage::SLUG',
] as $smtpUiContract) {
    $assertContains($smtpUiContract, $smtpControl, 'Email Settings must expose guarded Direct SMTP settings: ' . $smtpUiContract);
}

$emailTest = $read('wordpress-plugin/safecontracts/src/Admin/NotificationEmailTestControl.php');
foreach ([
    "public const ACTION = 'safecontracts_notification_email_test'",
    'DirectSmtpSettingsControl::register();',
    "add_action('admin_post_' . self::ACTION, [self::class, 'handle'])",
    'check_admin_referer(self::ACTION)',
    'Capabilities::MANAGE_NOTIFICATIONS',
    '(new DirectNotificationService())->send(',
    'false,',
    'true,',
    'EmailSettings::validEmail($email)',
    'Send test email',
    'safecontracts_email_test',
    'Direct SMTP',
    'EmailSettingsPage::SLUG',
] as $emailTestContract) {
    $assertContains($emailTestContract, $emailTest, 'one-click email test must use the real guarded Direct SMTP path: ' . $emailTestContract);
}

$plugin = $read('wordpress-plugin/safecontracts/src/Plugin.php');
foreach ([
    'ContractsPage::BULK_ASSIGN_ACTION',
    'ContractsPage::class, \'handleBulkAssign\'',
    'NotificationCenterPage::SAVE_EMAIL_ACTION',
    'NotificationCenterPage::DIRECT_SEND_ACTION',
    'NotificationEmailTestControl::register()',
] as $wireContract) {
    $assertContains($wireContract, $plugin, 'plugin bootstrap must keep responsible-accountant/email handlers wired: ' . $wireContract);
}
$entry = $read('wordpress-plugin/safecontracts/safecontracts.php');
$assertContains('EmailSettingsPage::register()', $entry, 'plugin bootstrap must register the standalone Email Settings page');


echo "Responsible accountant -> scoped data -> separated notification/email settings -> direct SMTP regression checks passed.\n";
