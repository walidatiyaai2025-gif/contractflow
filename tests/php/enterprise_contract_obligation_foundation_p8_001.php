<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0043EnterpriseContractObligations;
use SafeContracts\Obligations\ObligationPolicy;

$assertions = 0;
function esc_p8_obligation_foundation_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function esc_p8_obligation_foundation_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p8_obligation_foundation_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ': ' . $error->getMessage() . ')');
        return;
    }
    esc_p8_obligation_foundation_assert(false, $message . ' (no exception)');
}

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0043EnterpriseContractObligations.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Obligations/ObligationRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Obligations/ObligationService.php');
$routerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0043EnterpriseContractObligations())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);

esc_p8_obligation_foundation_assert(Migrator::LATEST_VERSION === '1.42.0', 'P8-001 is exact schema version 1.42.0');
esc_p8_obligation_foundation_assert(str_contains($migratorSource, "'1.42.0' => Migration0043EnterpriseContractObligations::class"), 'Migration0043 is registered exactly at 1.42.0');
esc_p8_obligation_foundation_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_contract_obligations'), 'dedicated obligation table is created');
esc_p8_obligation_foundation_assert(str_contains($schema, 'tenant_id bigint(20) unsigned NOT NULL'), 'obligation is tenant owned');
esc_p8_obligation_foundation_assert(str_contains($schema, 'uuid char(36) NOT NULL'), 'obligation stores server UUID');
esc_p8_obligation_foundation_assert(str_contains($schema, 'obligation_code varchar(100) NOT NULL'), 'obligation stores bounded machine code');
esc_p8_obligation_foundation_assert(str_contains($schema, 'due_date date NULL'), 'due_date uses DATE semantics');
esc_p8_obligation_foundation_assert(str_contains($schema, "status varchar(20) NOT NULL DEFAULT 'open'"), 'lifecycle starts open');
esc_p8_obligation_foundation_assert(str_contains($schema, 'completed_at datetime NULL') && str_contains($schema, 'cancelled_at datetime NULL'), 'terminal timestamp evidence is explicit');
esc_p8_obligation_foundation_assert(str_contains($schema, 'completed_by bigint(20) unsigned NULL') && str_contains($schema, 'cancelled_by bigint(20) unsigned NULL'), 'terminal actor evidence is explicit');
esc_p8_obligation_foundation_assert(str_contains($schema, 'UNIQUE KEY uuid (uuid)'), 'UUID is unique');
esc_p8_obligation_foundation_assert(str_contains($schema, 'UNIQUE KEY tenant_contract_code (tenant_id, contract_id, obligation_code)'), 'machine code is unique per tenant Contract');
esc_p8_obligation_foundation_assert(str_contains($schema, 'KEY tenant_contract_status_due (tenant_id, contract_id, status, due_date, id)'), 'bounded contract/status/due-date queries are indexed');
esc_p8_obligation_foundation_assert(! str_contains($migrationSource, 'ALTER TABLE') && ! str_contains($migrationSource, 'DROP TABLE'), 'P8 schema change is additive only');

$create = ObligationPolicy::normalizeCreate(['obligation_code' => ' Notice-01 ', 'title' => ' Renewal notice ', 'description' => ' Evidence ', 'due_date' => '2027-02-28']);
esc_p8_obligation_foundation_assert($create['obligation_code'] === 'notice-01' && $create['title'] === 'Renewal notice', 'create metadata is canonicalized');
esc_p8_obligation_foundation_assert($create['description'] === 'Evidence' && $create['due_date'] === '2027-02-28', 'optional metadata preserves valid values');
esc_p8_obligation_foundation_throws(static fn () => ObligationPolicy::normalizeCreate(['obligation_code'=>'ok','title'=>'x','status'=>'completed']), InvalidArgumentException::class, 'client cannot inject lifecycle status at create');
esc_p8_obligation_foundation_throws(static fn () => ObligationPolicy::normalizeMetadataUpdate(['title'=>'x','obligation_code'=>'new']), InvalidArgumentException::class, 'obligation_code is immutable after create');
esc_p8_obligation_foundation_throws(static fn () => ObligationPolicy::normalizeMetadataUpdate(['title'=>'x','status'=>'cancelled']), InvalidArgumentException::class, 'metadata update cannot bypass lifecycle transition');
esc_p8_obligation_foundation_throws(static fn () => ObligationPolicy::normalizeDueDate('2027-02-29'), InvalidArgumentException::class, 'invalid calendar date is rejected');
esc_p8_obligation_foundation_throws(static fn () => ObligationPolicy::normalizeTerminalTarget('open'), InvalidArgumentException::class, 'reopening is outside lifecycle');
esc_p8_obligation_foundation_assert(ObligationPolicy::normalizeTerminalTarget('completed') === 'completed' && ObligationPolicy::normalizeTerminalTarget('cancelled') === 'cancelled', 'only explicit terminal targets are accepted');
$uuid = ObligationPolicy::newUuid();
esc_p8_obligation_foundation_assert(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid) === 1, 'server UUID is RFC4122 v4 shaped');

esc_p8_obligation_foundation_assert(str_contains($repositorySource, 'TenantContextStore::context()->requireTenantId()'), 'repository derives tenant from locked tenant context');
esc_p8_obligation_foundation_assert(! str_contains($repositorySource, 'UPDATE wp_safecontracts_contracts') && ! str_contains($repositorySource, 'contract_workflow_instances') && ! str_contains($repositorySource, 'scheduled_payments'), 'P8 repository cannot mutate legacy Contract/workflow/financial state');
esc_p8_obligation_foundation_assert(str_contains($serviceSource, 'Capabilities::ACCESS') && str_contains($serviceSource, 'Capabilities::EDIT_CONTRACTS'), 'read and mutation capabilities remain separate');
esc_p8_obligation_foundation_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'Enterprise tenant role narrows global capability grants');
esc_p8_obligation_foundation_assert(str_contains($serviceSource, 'Capabilities::VIEW_ALL') && str_contains($serviceSource, 'Capabilities::VIEW_ASSIGNED'), 'existing Contract data scope is preserved');
esc_p8_obligation_foundation_assert(! str_contains($routerSource, 'Obligation') && ! str_contains($routerSource, '/obligations'), 'P8-001 creates no REST exposure');

echo "P8-001 Contract Obligation foundation checks passed ({$assertions} assertions).\n";
