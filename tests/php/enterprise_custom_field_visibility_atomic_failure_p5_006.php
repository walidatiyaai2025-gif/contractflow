<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\CustomFields\CustomFieldVisibilityRepository;
use SafeContracts\CustomFields\CustomFieldValuePolicy;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p5_vis_atomic_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

final class ESC_P5_VisibilityAtomicWpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 0;
    /** @var list<string> */
    public array $queries = [];
    /** @var list<string> */
    public array $reads = [];

    public function __construct(private string $failureMode)
    {
    }

    public function prepare(string $query, mixed ...$args): string
    {
        $prepared = array_map(
            static fn (mixed $value): mixed => is_int($value) ? $value : "'" . addslashes((string) $value) . "'",
            $args
        );
        return vsprintf($query, $prepared);
    }

    public function query(string $sql): int|false
    {
        $this->queries[] = $sql;
        if (str_starts_with($sql, 'START TRANSACTION') || str_starts_with($sql, 'ROLLBACK') || str_starts_with($sql, 'COMMIT')) {
            return 1;
        }
        if (str_contains($sql, 'INSERT INTO wp_safecontracts_custom_field_visibility_rules')) {
            if ($this->failureMode === 'target') {
                $this->insert_id = 0;
                return 0;
            }
            $this->insert_id = 501;
            return 1;
        }
        if (str_contains($sql, 'DELETE FROM wp_safecontracts_custom_field_visibility_conditions')) {
            return 1;
        }
        if (str_contains($sql, 'INSERT INTO wp_safecontracts_custom_field_visibility_conditions')) {
            return $this->failureMode === 'source' ? 0 : 1;
        }
        return 1;
    }

    public function get_results(string $sql, mixed $output = null): array
    {
        unset($output);
        $this->reads[] = $sql;
        if (str_contains($sql, 'ORDER BY id ASC FOR UPDATE')) {
            return [['id' => '61'], ['id' => '62']];
        }
        return [];
    }
}

function esc_p5_vis_atomic_target(): array
{
    return [
        'id' => 61,
        'contract_type_id' => 31,
        'field_code' => 'field.61',
        'data_type' => 'text',
        'status' => 'active',
        'options_json' => '',
        'validation_json' => '',
    ];
}

function esc_p5_vis_atomic_source(): array
{
    return [
        'id' => 62,
        'contract_type_id' => 31,
        'field_code' => 'field.62',
        'data_type' => 'decimal',
        'status' => 'active',
        'options_json' => '',
        'validation_json' => '{"min":0,"max":1000}',
    ];
}

function esc_p5_vis_atomic_condition(): array
{
    $source = esc_p5_vis_atomic_source();
    return [[
        'position_no' => 1,
        'source_definition_id' => 62,
        'operator_code' => 'gt',
        'operand_json' => '"10"',
        'source_definition' => $source,
    ]];
}

$originalWpdb = $GLOBALS['wpdb'];
update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(17);

$targetFailDb = new ESC_P5_VisibilityAtomicWpdb('target');
$GLOBALS['wpdb'] = $targetFailDb;
$repository = new CustomFieldVisibilityRepository();
$targetError = null;
try {
    $repository->replaceRule(esc_p5_vis_atomic_target(), 'all', esc_p5_vis_atomic_condition(), 42);
} catch (Throwable $error) {
    $targetError = $error;
}
esc_p5_vis_atomic_assert($targetError instanceof RuntimeException, 'zero-row target snapshot persistence fails closed');
esc_p5_vis_atomic_assert(str_contains((string) $targetError?->getMessage(), 'target definition changed concurrently'), 'target zero-row failure identifies target drift');
esc_p5_vis_atomic_assert(($targetFailDb->queries[0] ?? '') === 'START TRANSACTION', 'target-drift path starts transaction');
esc_p5_vis_atomic_assert(str_contains((string) ($targetFailDb->reads[0] ?? ''), 'ORDER BY id ASC FOR UPDATE'), 'target/source definitions are locked before target snapshot persistence');
esc_p5_vis_atomic_assert(in_array('ROLLBACK', $targetFailDb->queries, true), 'target snapshot drift rolls transaction back');
esc_p5_vis_atomic_assert(! in_array('COMMIT', $targetFailDb->queries, true), 'target snapshot drift never commits');
esc_p5_vis_atomic_assert(! array_filter($targetFailDb->queries, static fn ($q) => str_contains($q, 'DELETE FROM wp_safecontracts_custom_field_visibility_conditions')), 'target drift fails before destructive condition replacement');

$sourceFailDb = new ESC_P5_VisibilityAtomicWpdb('source');
$GLOBALS['wpdb'] = $sourceFailDb;
$repository = new CustomFieldVisibilityRepository();
$sourceError = null;
try {
    $repository->replaceRule(esc_p5_vis_atomic_target(), 'all', esc_p5_vis_atomic_condition(), 42);
} catch (Throwable $error) {
    $sourceError = $error;
}
esc_p5_vis_atomic_assert($sourceError instanceof RuntimeException, 'zero-row source condition persistence fails closed');
esc_p5_vis_atomic_assert(str_contains((string) $sourceError?->getMessage(), 'source definition changed concurrently'), 'source zero-row failure identifies source drift');
esc_p5_vis_atomic_assert(($sourceFailDb->queries[0] ?? '') === 'START TRANSACTION', 'source-drift path starts transaction');
esc_p5_vis_atomic_assert(str_contains((string) ($sourceFailDb->queries[2] ?? ''), 'DELETE FROM wp_safecontracts_custom_field_visibility_conditions'), 'source-drift path deletes old conditions only inside transaction');
esc_p5_vis_atomic_assert(str_contains((string) ($sourceFailDb->queries[3] ?? ''), 'INSERT INTO wp_safecontracts_custom_field_visibility_conditions'), 'source-drift path attempts guarded condition insert');
esc_p5_vis_atomic_assert(in_array('ROLLBACK', $sourceFailDb->queries, true), 'source condition drift rolls transaction back');
esc_p5_vis_atomic_assert(! in_array('COMMIT', $sourceFailDb->queries, true), 'source condition drift never commits partial rule');

$GLOBALS['wpdb'] = $originalWpdb;
CoreTenantEnforcement::disable();
TenantContextStore::reset();

echo "P5-006 Enterprise Dynamic Field visibility atomic rollback checks passed ({$assertions} assertions).\n";
