<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Attachments\EntityAttachmentRepository;
use SafeContracts\Attachments\EntityAttachmentService;
use SafeContracts\Database\Migrator;
use SafeContracts\Roles\Capabilities;

$GLOBALS['sc_test_post_types'] = [];
$GLOBALS['sc_test_titles'] = [];
if (! function_exists('get_post_type')) {
    function get_post_type(int $postId): string|false
    {
        return $GLOBALS['sc_test_post_types'][$postId] ?? false;
    }
}
if (! function_exists('get_the_title')) {
    function get_the_title(int $postId): string
    {
        return (string) ($GLOBALS['sc_test_titles'][$postId] ?? '');
    }
}

$tests = 0;
function sc_att_assert(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_att_expect(string $class, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        sc_att_assert($error instanceof $class, $message);
        return;
    }
    sc_att_assert(false, $message);
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_att_assert(is_callable($activate), 'attachment validation can activate plugin');
$activate();
sc_att_assert(Migrator::LATEST_VERSION === '1.22.0', 'plugin database contract advances to 1.22.0 while attachments remain the 1.21.0 migration');
$schemaSql = implode("\n", $GLOBALS['sc_test_dbdelta']);
sc_att_assert(str_contains($schemaSql, 'wp_safecontracts_entity_attachments'), 'entity attachment table is created');
sc_att_assert(str_contains($schemaSql, 'UNIQUE KEY entity_media (entity_type, entity_id, media_id)'), 'duplicate entity/media links are prevented');
sc_att_assert(str_contains($schemaSql, 'KEY entity_order (entity_type, entity_id, display_order, id)'), 'attachment listing has deterministic entity ordering index');
$querySql = implode("\n", $GLOBALS['sc_test_queries']);
sc_att_assert(str_contains($querySql, "SELECT 'contract', contract_id, media_id"), 'existing contract attachment links are copied forward');
sc_att_assert(str_contains($querySql, "SELECT 'collection', id, proof_media_id"), 'legacy collection proof links are copied forward');

$service = new EntityAttachmentService();
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::EDIT_CONTRACTS => true,
    Capabilities::CREATE_CONTRACTS => true,
    Capabilities::MANAGE_PAYMENTS => true,
    Capabilities::MANAGE_COLLECTIONS => true,
];
$GLOBALS['sc_test_post_types'][901] = 'attachment';
$GLOBALS['sc_test_post_types'][902] = 'attachment';
$GLOBALS['sc_test_titles'][901] = 'Signed contract.pdf';
$GLOBALS['sc_test_titles'][902] = 'Appendix A.pdf';

$GLOBALS['sc_test_result_queue'] = [[[
    'accountant_user_id' => '42', 'entity_is_archived' => '0', 'parent_is_archived' => '0',
]], [], []];
$service->attachMany(EntityAttachmentService::CONTRACT, 501, [901, 902, 902]);
$writes = implode("\n", array_slice($GLOBALS['sc_test_queries'], -4));
sc_att_assert(substr_count($writes, 'wp_safecontracts_entity_attachments') === 2, 'multi-file contract linking deduplicates media IDs');
sc_att_assert(str_contains($writes, 'wp_safecontracts_contract_attachments'), 'contract attachment compatibility table remains synchronized');

$GLOBALS['sc_test_result_queue'] = [[[
    'accountant_user_id' => '42', 'entity_is_archived' => '0', 'parent_is_archived' => '0',
]], [], [[
    'id' => '1', 'entity_type' => 'collection', 'entity_id' => '700', 'media_id' => '901', 'label' => 'Receipt', 'display_order' => '0', 'created_by' => '42', 'created_at' => '2026-08-23 10:00:00',
]]];
$service->attachMany(EntityAttachmentService::COLLECTION, 700, [901]);
sc_att_assert(str_contains(implode("\n", array_slice($GLOBALS['sc_test_queries'], -3)), 'proof_media_id = 901'), 'first collection attachment keeps legacy proof reference synchronized');

$repo = new EntityAttachmentRepository();
$GLOBALS['sc_test_result_queue'] = [[
    ['id'=>'10','entity_type'=>'collection','entity_id'=>'700','media_id'=>'901','label'=>'Receipt','display_order'=>'0','created_by'=>'42','created_at'=>'2026-08-23 10:00:00'],
    ['id'=>'11','entity_type'=>'collection','entity_id'=>'701','media_id'=>'902','label'=>'Bank slip','display_order'=>'0','created_by'=>'42','created_at'=>'2026-08-23 10:01:00'],
]];
$grouped = $repo->allForMany(EntityAttachmentService::COLLECTION, [700, 701]);
sc_att_assert(count($grouped[700] ?? []) === 1 && count($grouped[701] ?? []) === 1, 'backend collection ledger can batch-load multi-file evidence without N+1 queries');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true, Capabilities::EDIT_CONTRACTS => true];
$GLOBALS['sc_test_result_queue'] = [[[
    'accountant_user_id' => '99', 'entity_is_archived' => '0', 'parent_is_archived' => '0',
]]];
sc_att_expect(DomainException::class, fn () => $service->assertCanManage(EntityAttachmentService::CONTRACT, 501), 'attachment writes respect assigned-accountant data scope');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true, Capabilities::EDIT_CONTRACTS => true];
$GLOBALS['sc_test_result_queue'] = [[[
    'accountant_user_id' => '42', 'entity_is_archived' => '1', 'parent_is_archived' => '0',
]]];
sc_att_expect(DomainException::class, fn () => $service->assertCanManage(EntityAttachmentService::CONTRACT, 501), 'archived records cannot receive attachments');

$contractsPage = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Admin/ContractsPage.php') ?: '';
$paymentsPage = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Admin/PaymentsPage.php') ?: '';
$collectionsPage = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Admin/CollectionsPage.php') ?: '';
sc_att_assert(str_contains($contractsPage, "name=\"base_value\"") && str_contains($contractsPage, 'Base contract value'), 'contract backend exposes editable base contract value');
sc_att_assert(str_contains($contractsPage, 'enctype="multipart/form-data"') && str_contains($contractsPage, "EntityAttachmentService::CONTRACT"), 'contract backend supports multi-file upload and listing');
sc_att_assert(str_contains($paymentsPage, 'enctype="multipart/form-data"') && str_contains($paymentsPage, "EntityAttachmentService::PAYMENT"), 'payment backend supports multi-file upload and listing');
sc_att_assert(str_contains($collectionsPage, 'enctype="multipart/form-data"') && str_contains($collectionsPage, "EntityAttachmentService::COLLECTION"), 'collection backend supports multi-file upload and listing');
sc_att_assert(str_contains($collectionsPage, "__('Files', 'safecontracts')"), 'collection ledger exposes attachments directly in backend table');

// Production at 1.20.0 must still traverse the guarded 1.21.0 attachment
// migration and the additive 1.22.0 import-errors repair in order.
$GLOBALS['sc_test_options'][Migrator::VERSION_OPTION] = '1.20.0';
$GLOBALS['sc_test_dbdelta'] = [];
$GLOBALS['sc_test_queries'] = [];
(new Migrator())->maybeMigrate();
sc_att_assert(($GLOBALS['sc_test_options'][Migrator::VERSION_OPTION] ?? '') === '1.22.0', 'production database 1.20.0 upgrades through 1.21.0 to latest 1.22.0');
sc_att_assert(str_contains(implode("\n", $GLOBALS['sc_test_dbdelta']), 'wp_safecontracts_entity_attachments'), '1.20 to latest upgrade creates attachment schema at the 1.21.0 stage');
sc_att_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'CREATE TABLE wp_safecontracts_import_errors'), '1.20 to latest upgrade also executes the 1.22.0 import-errors repair stage');

echo "SafeContracts entity attachment tests passed ({$tests} assertions).\n";
