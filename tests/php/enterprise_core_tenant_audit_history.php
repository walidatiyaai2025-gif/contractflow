<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Audit\AuditRepository;
use SafeContracts\Contracts\ContractHistoryRepository;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_audit_tenant_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_audit_last_query(): string
{
    if ($GLOBALS['sc_test_queries'] === []) {
        return '';
    }
    return (string) end($GLOBALS['sc_test_queries']);
}

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(17);

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_results'] = [['id' => '77']];
(new ContractHistoryRepository())->append(77, 'updated', 42, ['status' => 'active']);
$historyInsert = esc_audit_last_query();
esc_audit_tenant_assert(str_contains($historyInsert, 'INSERT INTO wp_safecontracts_contract_history (tenant_id,'), 'contract history inserts carry tenant ownership');
esc_audit_tenant_assert(str_contains($historyInsert, 'VALUES (17, 77,'), 'contract history tenant id is server-derived');

$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_results'] = [];
(new ContractHistoryRepository())->forContract(77, 20);
$historyRead = (string) end($GLOBALS['sc_test_read_queries']);
esc_audit_tenant_assert(str_contains($historyRead, 'WHERE contract_id = 77 AND tenant_id = 17'), 'contract history reads are tenant scoped');

$GLOBALS['sc_test_queries'] = [];
(new AuditRepository())->append(
    'contract',
    77,
    'contract.updated',
    42,
    null,
    ['status' => 'active'],
    ['source' => 'caller', 'tenant_id' => 999]
);
$auditInsert = esc_audit_last_query();
esc_audit_tenant_assert(str_contains($auditInsert, 'tenant_id'), 'audit context contains tenant attribution');
esc_audit_tenant_assert(! str_contains($auditInsert, '999'), 'caller cannot spoof audit tenant attribution');
esc_audit_tenant_assert(str_contains($auditInsert, '17'), 'audit tenant attribution comes from locked server context');

CoreTenantEnforcement::disable();
TenantContextStore::reset();

fwrite(STDOUT, "Enterprise tenant audit/history attribution passed ({$assertions} assertions).\n");
