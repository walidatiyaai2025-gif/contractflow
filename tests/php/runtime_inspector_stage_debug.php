<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Contracts\CounterpartyContractService;
use SafeContracts\Diagnostics\RuntimeInspector;
use SafeContracts\Roles\Capabilities;

RuntimeInspector::clear();
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::CREATE_CONTRACTS => true,
    Capabilities::VIEW_ALL => true,
];
$GLOBALS['sc_test_result_queue'] = [[]];

try {
    (new CounterpartyContractService())->create([
        'contract_number' => 'SUP-MISSING-DEBUG',
        'counterparty_type' => 'supplier',
        'counterparty_id' => 6201,
        'currency_code' => 'KWD',
    ]);
} catch (Throwable $error) {
    $latest = RuntimeInspector::recent()[0] ?? [];
    fwrite(STDOUT, 'RUNTIME_STAGE_DEBUG=' . (string) ($latest['stage'] ?? 'NONE') . PHP_EOL);
    fwrite(STDOUT, 'RUNTIME_OPERATION_DEBUG=' . (string) ($latest['operation'] ?? 'NONE') . PHP_EOL);
    fwrite(STDOUT, 'RUNTIME_EXCEPTION_DEBUG=' . get_class($error) . ':' . $error->getMessage() . PHP_EOL);
}
