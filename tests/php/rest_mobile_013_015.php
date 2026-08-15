<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Rest\ContractMutationController;
use SafeContracts\Rest\Router;
use SafeContracts\Roles\Capabilities;

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

fwrite(STDOUT, "SafeContracts P9 SC-P9-013..015 checks passed ({$tests}).\n");
