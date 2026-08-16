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

$center = $read('wordpress-plugin/safecontracts/src/Admin/NotificationCenterPage.php');
foreach ([
    "'target_assigned_accountant' => isset(\$_POST['target_assigned_accountant'])",
    "'email_enabled' => isset(\$_POST['email_enabled'])",
    'Assigned Accountant',
    'Email delivery',
    'Sender name',
    'Sender email',
    'Send to one user',
    'Recent delivery attempts',
] as $notificationUiContract) {
    $assertContains($notificationUiContract, $center, 'notification center must expose assigned-accountant and email controls: ' . $notificationUiContract);
}

$email = $read('wordpress-plugin/safecontracts/src/Notifications/EmailDeliveryService.php');
foreach ([
    "\$user->user_email",
    "'From: ' . \$config['from_name'] . ' <' . \$config['from_address'] . '>'",
    'wp_mail(',
    "'recipient_email_unavailable'",
    "'wp_mail_failed'",
    "'email'",
] as $emailContract) {
    $assertContains($emailContract, $email, 'email delivery must use WordPress user email, configured sender identity and delivery logging: ' . $emailContract);
}

$plugin = $read('wordpress-plugin/safecontracts/src/Plugin.php');
foreach ([
    'ContractsPage::BULK_ASSIGN_ACTION',
    'ContractsPage::class, \'handleBulkAssign\'',
    'NotificationCenterPage::SAVE_EMAIL_ACTION',
    'NotificationCenterPage::DIRECT_SEND_ACTION',
] as $wireContract) {
    $assertContains($wireContract, $plugin, 'plugin bootstrap must keep responsible-accountant/email handlers wired: ' . $wireContract);
}

echo "Responsible accountant -> scoped data -> notification/email regression checks passed.\n";
