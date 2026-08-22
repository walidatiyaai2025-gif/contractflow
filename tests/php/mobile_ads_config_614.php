<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use InvalidArgumentException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Settings\MobileConfiguration;

$assertions = 0;
$config = new MobileConfiguration();
$defaults = MobileConfiguration::defaults();

if ($defaults['ads_enabled'] !== false || $defaults['ads_test_mode'] !== true || $defaults['ads_banner_enabled'] !== true || $defaults['ads_banner_unit_id'] !== '') {
    fwrite(STDERR, "FAIL: mobile advertising defaults must be disabled and test-safe.\n");
    exit(1);
}
$assertions += 4;

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_SYSTEM] = true;
$saved = $config->save([
    'support_text' => 'Support',
    'default_page_size' => 25,
    'ads_enabled' => true,
    'ads_test_mode' => true,
    'ads_banner_enabled' => true,
    'ads_banner_unit_id' => '',
]);
if ($saved['ads_enabled'] !== true || $saved['ads_test_mode'] !== true || $saved['ads_banner_enabled'] !== true) {
    fwrite(STDERR, "FAIL: test advertising controls were not persisted.\n");
    exit(1);
}
$assertions += 3;

$productionUnit = 'ca-app-pub-1234567890123456/1234567890';
$saved = $config->save([
    'support_text' => 'Support',
    'default_page_size' => 25,
    'ads_enabled' => true,
    'ads_test_mode' => false,
    'ads_banner_enabled' => true,
    'ads_banner_unit_id' => $productionUnit,
]);
if ($saved['ads_banner_unit_id'] !== $productionUnit || $config->read()['ads_banner_unit_id'] !== $productionUnit) {
    fwrite(STDERR, "FAIL: production banner unit ID did not round-trip.\n");
    exit(1);
}
$assertions += 2;

try {
    $config->save([
        'support_text' => 'Support',
        'default_page_size' => 25,
        'ads_enabled' => true,
        'ads_test_mode' => false,
        'ads_banner_enabled' => true,
        'ads_banner_unit_id' => '',
    ]);
    fwrite(STDERR, "FAIL: production ads accepted an empty banner unit ID.\n");
    exit(1);
} catch (InvalidArgumentException) {
    $assertions++;
}

try {
    $config->save([
        'support_text' => 'Support',
        'default_page_size' => 25,
        'ads_enabled' => true,
        'ads_test_mode' => false,
        'ads_banner_enabled' => true,
        'ads_banner_unit_id' => 'bad-unit-id',
    ]);
    fwrite(STDERR, "FAIL: malformed AdMob unit ID was accepted.\n");
    exit(1);
} catch (InvalidArgumentException) {
    $assertions++;
}

fwrite(STDOUT, "Alkenzy AdMob configuration #614 passed ({$assertions} assertions).\n");
