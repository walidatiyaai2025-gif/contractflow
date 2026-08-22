<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Roles\Capabilities;
use SafeContracts\Settings\MobileConfiguration;

$assertions = 0;
$config = new MobileConfiguration();
$defaults = MobileConfiguration::defaults();

if (
    $defaults['ads_enabled'] !== false
    || $defaults['ads_test_mode'] !== true
    || $defaults['ads_banner_enabled'] !== true
    || $defaults['ads_provider'] !== MobileConfiguration::AD_PROVIDER_ADMOB
    || $defaults['ads_admob_banner_unit_id'] !== ''
    || $defaults['ads_applovin_sdk_key'] !== ''
    || $defaults['ads_applovin_banner_unit_id'] !== ''
) {
    fwrite(STDERR, "FAIL: mobile advertising defaults must be disabled, AdMob-first and test-safe.\n");
    exit(1);
}
$assertions += 7;

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_SYSTEM] = true;
$saved = $config->save([
    'support_text' => 'Support',
    'default_page_size' => 25,
    'ads_enabled' => true,
    'ads_test_mode' => true,
    'ads_banner_enabled' => true,
    'ads_provider' => 'admob',
    'ads_admob_banner_unit_id' => '',
]);
if ($saved['ads_enabled'] !== true || $saved['ads_test_mode'] !== true || $saved['ads_provider'] !== 'admob') {
    fwrite(STDERR, "FAIL: AdMob test controls were not persisted.\n");
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
    'ads_provider' => 'admob',
    'ads_admob_banner_unit_id' => $productionUnit,
]);
$read = $config->read();
if ($saved['ads_admob_banner_unit_id'] !== $productionUnit || $read['ads_banner_unit_id'] !== $productionUnit) {
    fwrite(STDERR, "FAIL: production AdMob banner unit ID did not round-trip with legacy compatibility.\n");
    exit(1);
}
$assertions += 2;

$appLovinSdkKey = 'sdk-key-value-long-enough-for-runtime-configuration-123456';
$appLovinBanner = 'bannerUnit1234';
$saved = $config->save([
    'support_text' => 'Support',
    'default_page_size' => 25,
    'ads_enabled' => true,
    'ads_test_mode' => true,
    'ads_banner_enabled' => true,
    'ads_provider' => 'applovin',
    'ads_applovin_sdk_key' => $appLovinSdkKey,
    'ads_applovin_banner_unit_id' => $appLovinBanner,
]);
if (
    $saved['ads_provider'] !== MobileConfiguration::AD_PROVIDER_APPLOVIN
    || $saved['ads_applovin_sdk_key'] !== $appLovinSdkKey
    || $saved['ads_applovin_banner_unit_id'] !== $appLovinBanner
) {
    fwrite(STDERR, "FAIL: AppLovin provider settings did not persist.\n");
    exit(1);
}
$assertions += 3;

try {
    $config->save([
        'support_text' => 'Support',
        'default_page_size' => 25,
        'ads_enabled' => true,
        'ads_test_mode' => false,
        'ads_banner_enabled' => true,
        'ads_provider' => 'admob',
        'ads_admob_banner_unit_id' => '',
    ]);
    fwrite(STDERR, "FAIL: production AdMob accepted an empty banner unit ID.\n");
    exit(1);
} catch (\InvalidArgumentException) {
    $assertions++;
}

try {
    $config->save([
        'support_text' => 'Support',
        'default_page_size' => 25,
        'ads_enabled' => true,
        'ads_test_mode' => false,
        'ads_banner_enabled' => true,
        'ads_provider' => 'admob',
        'ads_admob_banner_unit_id' => 'bad-unit-id',
    ]);
    fwrite(STDERR, "FAIL: malformed AdMob unit ID was accepted.\n");
    exit(1);
} catch (\InvalidArgumentException) {
    $assertions++;
}

try {
    $config->save([
        'support_text' => 'Support',
        'default_page_size' => 25,
        'ads_enabled' => true,
        'ads_test_mode' => true,
        'ads_banner_enabled' => true,
        'ads_provider' => 'applovin',
        'ads_applovin_sdk_key' => '',
        'ads_applovin_banner_unit_id' => $appLovinBanner,
    ]);
    fwrite(STDERR, "FAIL: AppLovin was enabled without an SDK key.\n");
    exit(1);
} catch (\InvalidArgumentException) {
    $assertions++;
}

try {
    $config->save([
        'support_text' => 'Support',
        'default_page_size' => 25,
        'ads_enabled' => true,
        'ads_test_mode' => true,
        'ads_banner_enabled' => true,
        'ads_provider' => 'unknown-network',
    ]);
    fwrite(STDERR, "FAIL: unknown advertising provider was accepted.\n");
    exit(1);
} catch (\InvalidArgumentException) {
    $assertions++;
}

fwrite(STDOUT, "Alkenzy switchable advertising configuration #614 passed ({$assertions} assertions).\n");
