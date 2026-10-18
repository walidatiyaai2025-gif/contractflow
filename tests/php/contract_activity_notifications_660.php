<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static function (string $relative) use ($root): string {
    $content = file_get_contents($root . '/' . $relative);
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

$dispatcher = $read('wordpress-plugin/safecontracts/src/Notifications/ContractActivityNotificationDispatcher.php');
foreach ([
    'safecontracts_contract_created',
    'safecontracts_contract_edited',
    'safecontracts_contract_dates_changed',
    'safecontracts_contract_base_value_changed',
    'safecontracts_contract_currency_changed',
    'safecontracts_contract_financial_item_added',
    'safecontracts_contract_adjustment_added',
    'safecontracts_contract_attachment_added',
    'safecontracts_contract_attachment_removed',
    'safecontracts_contract_customer_assigned',
    'safecontracts_contract_counterparty_assigned',
    'safecontracts_contract_accountant_assigned',
    'safecontracts_contract_status_changed',
    'safecontracts_contract_archived',
    'safecontracts_payment_created',
    'safecontracts_payment_details_changed',
    'safecontracts_payment_dates_changed',
    'safecontracts_payment_status_changed',
    'safecontracts_payment_archived',
    'safecontracts_financial_settlement_recorded',
    'safecontracts_collection_archived',
    'safecontracts_followup_recorded',
    'safecontracts_entity_attachment_added',
    'safecontracts_entity_attachment_removed',
    "\$contract['accountant_user_id']",
    "'resource_type' => \$resourceType",
    "'contract_id' => \$contractId",
    "'payment_id' => \$paymentId",
] as $marker) {
    $assertContains($marker, $dispatcher, 'activity dispatcher must preserve event/recipient coverage: ' . $marker);
}
$assertContains("if (\$oldValue === '0.0000')", $dispatcher, 'contract creation must not duplicate the base-value activity notification');
$assertContains('paymentDetailsHandled', $dispatcher, 'payment editable saves must suppress duplicate date notifications');
$assertContains("if (\$entityType === 'contract')", $dispatcher, 'contract entity attachments must notify the responsible accountant');
$assertContains("if (\$entityType === 'payment')", $dispatcher, 'payment entity attachments must notify the responsible accountant');
$assertContains("if (\$entityType === 'collection')", $dispatcher, 'settlement entity attachments must resolve back to the contract payment');

$direct = $read('wordpress-plugin/safecontracts/src/Notifications/DirectNotificationService.php');
foreach ([
    "'event_code' => \$normalized['event_code']",
    "'resource_type' => \$normalized['resource_type'] ?? ''",
    "\$normalized['resource_id']",
    "\$normalized['contract_id']",
] as $marker) {
    $assertContains($marker, $direct, 'direct notification transport must carry activity context: ' . $marker);
}

$delivery = $read('wordpress-plugin/safecontracts/src/Notifications/DeliveryLogRepository.php');
foreach (['resource_type', 'resource_id', 'contract_id', 'Notification resource type is invalid.'] as $marker) {
    $assertContains($marker, $delivery, 'delivery evidence must persist generic notification context: ' . $marker);
}

$controller = $read('wordpress-plugin/safecontracts/src/Rest/NotificationsController.php');
foreach ([
    "'contract' => 'contracts'",
    "'payment' => 'payments'",
    "'followup' => 'followups'",
    "'payment_id' => \$paymentId",
    "'resource_type' => \$resourceType !== '' ? \$resourceType : null",
] as $marker) {
    $assertContains($marker, $controller, 'mobile notification API must expose generic deep links safely: ' . $marker);
}

$migrator = $read('wordpress-plugin/safecontracts/src/Database/Migrator.php');
$assertContains("public const LATEST_VERSION = '1.23.0';", $migrator, 'notification activity context must be a forward-only migration');
$assertContains('Migration0024NotificationActivityContext::class', $migrator, 'notification activity migration must be registered');

$plugin = $read('wordpress-plugin/safecontracts/src/Plugin.php');
$assertContains('ContractActivityNotificationDispatcher::register();', $plugin, 'activity dispatcher must be wired at plugin boot');

$mobile = $read('mobile/lib/features/notifications/notifications.dart');
$assertContains('_nonNegativeInt(', $mobile, 'mobile inbox must accept direct/contract notifications with payment_id=0');
$assertContains('SafeContractsDeepLinkDestination.payments', $mobile, 'payment deep-link authorization must remain strict');

echo "Contract/payment/settlement/follow-up/attachment activity notification coverage checks passed.\n";
