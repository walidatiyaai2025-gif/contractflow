<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Rest\ContractMutationController;
use SafeContracts\Rest\MobileConfigController;
use SafeContracts\Rest\Router;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Settings\GeneralSettings;

$tests = 0;
function sc_p9_013_015_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

SafeContracts\Plugin::instance()->boot();
Router::register();

$route = Router::NAMESPACE . '/contracts/(?P<id>\\d+)/light';
sc_p9_013_015_assert(
    isset($GLOBALS['sc_test_routes'][$route]),
    'SC-P9-013 contract light-edit route is registered'
);
sc_p9_013_015_assert(
    ($GLOBALS['sc_test_routes'][$route]['methods'] ?? '') === 'PATCH',
    'SC-P9-013 contract light-edit route is PATCH-only'
);

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ASSIGNED => true,
];
sc_p9_013_015_assert(
    ContractMutationController::canEditContracts() instanceof WP_Error,
    'SC-P9-013 requires edit capability in addition to SafeContracts access'
);

$GLOBALS['sc_test_current_caps'][Capabilities::EDIT_CONTRACTS] = true;
sc_p9_013_015_assert(
    ContractMutationController::canEditContracts() === true,
    'SC-P9-013 permits an authorized edit session'
);

$partialDates = ContractMutationController::editContract(new WP_REST_Request([
    'id' => '11',
    'start_date' => '2026-01-01',
]));
sc_p9_013_015_assert(
    $partialDates instanceof WP_Error && ($partialDates->data['status'] ?? 0) === 422,
    'SC-P9-013 partial date edits fail closed'
);

$invalidDate = ContractMutationController::editContract(new WP_REST_Request([
    'id' => '11',
    'start_date' => '2026-13-01',
    'end_date' => '2026-12-31',
]));
sc_p9_013_015_assert(
    $invalidDate instanceof WP_Error && ($invalidDate->data['status'] ?? 0) === 422,
    'SC-P9-013 invalid dates fail before domain mutation'
);

$financialField = ContractMutationController::editContract(new WP_REST_Request([
    'id' => '11',
    'base_value' => '999.0000',
]));
sc_p9_013_015_assert(
    $financialField instanceof WP_Error && ($financialField->data['status'] ?? 0) === 422,
    'SC-P9-013 financial fields cannot enter the light-edit surface'
);

$source = file_get_contents(
    dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Rest/ContractMutationController.php'
) ?: '';
sc_p9_013_015_assert(
    ! str_contains($source, '$wpdb'),
    'SC-P9-013 REST mutation boundary contains no direct SQL'
);
sc_p9_013_015_assert(
    str_contains($source, 'new ContractService()'),
    'SC-P9-013 delegates authorization/scope/audit behavior to ContractService'
);
sc_p9_013_015_assert(
    str_contains($source, '409'),
    'SC-P9-013 archived contract conflicts are surfaced distinctly'
);

// #396 — the backend remains the single source of truth for display currency.
$GLOBALS['sc_test_current_caps'] = [Capabilities::MANAGE_SYSTEM => true];
$settings = (new GeneralSettings())->save([
    'organization_name' => 'SafeContracts',
    'currency_code' => 'kwd',
    'currency_symbol' => 'د.ك',
    'admin_page_size' => 50,
]);
sc_p9_013_015_assert(
    $settings['currency_code'] === 'KWD' && $settings['currency_symbol'] === 'د.ك',
    '#396 currency code and symbol normalize and persist together'
);
$stored = (new GeneralSettings())->read();
sc_p9_013_015_assert(
    $stored['currency_code'] === 'KWD' && $stored['currency_symbol'] === 'د.ك',
    '#396 currency metadata round-trips through GeneralSettings'
);

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
$configResponse = MobileConfigController::show(new WP_REST_Request());
sc_p9_013_015_assert(
    $configResponse instanceof WP_REST_Response,
    '#396 authenticated mobile config returns a REST response'
);
$configCurrency = $configResponse->data['data']['currency'] ?? null;
sc_p9_013_015_assert(
    is_array($configCurrency)
        && ($configCurrency['code'] ?? null) === 'KWD'
        && ($configCurrency['symbol'] ?? null) === 'د.ك',
    '#396 mobile config exposes the configured currency code and symbol'
);

$mobileConfigSource = file_get_contents(
    dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Rest/MobileConfigController.php'
) ?: '';
sc_p9_013_015_assert(
    str_contains($mobileConfigSource, "'currency'")
        && str_contains($mobileConfigSource, "'symbol'")
        && ! str_contains($mobileConfigSource, 'KWD')
        && ! str_contains($mobileConfigSource, 'د.ك'),
    '#396 mobile config reads currency metadata from settings instead of hard-coding a business currency'
);

fwrite(STDOUT, "SafeContracts P9 SC-P9-013..015 checks passed ({$tests}).\n");
