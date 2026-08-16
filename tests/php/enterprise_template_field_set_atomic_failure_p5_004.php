<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\CustomFields\TemplateFieldSetRepository;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p5_tfs_atomic_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

final class ESC_P5_TFS_AtomicWpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 0;
    /** @var list<string> */
    public array $queries = [];
    /** @var list<string> */
    public array $reads = [];
    /** @var list<int|false> */
    private array $queryResults = [1, 1, 0, 1];

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
        return $this->queryResults !== [] ? array_shift($this->queryResults) : 1;
    }

    public function get_results(string $sql, mixed $output = null): array
    {
        unset($output);
        $this->reads[] = $sql;
        return [['id' => '51']];
    }
}

$originalWpdb = $GLOBALS['wpdb'];
$wpdb = new ESC_P5_TFS_AtomicWpdb();
$GLOBALS['wpdb'] = $wpdb;

update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(17);

$repository = new TemplateFieldSetRepository();
$snapshot = [[
    'definition_id' => 61,
    'position_no' => 1,
    'field_code_snapshot' => 'project.region',
    'data_type_snapshot' => 'select',
    'label_snapshot' => 'Project Region',
    'help_text_snapshot' => 'Choose region',
    'definition_required_snapshot' => 1,
    'required_override' => null,
    'options_json_snapshot' => '[{"value":"north","label":"North"}]',
    'validation_json_snapshot' => '',
    'definition_config_hash' => str_repeat('a', 64),
]];

$caught = null;
try {
    $repository->replaceDraftFieldSet(41, 51, 31, $snapshot, 42);
} catch (Throwable $error) {
    $caught = $error;
}

esc_p5_tfs_atomic_assert($caught instanceof RuntimeException, 'zero-row snapshot insert fails closed with RuntimeException');
esc_p5_tfs_atomic_assert(str_contains((string) $caught?->getMessage(), 'changed concurrently'), 'failure identifies concurrent definition drift');
esc_p5_tfs_atomic_assert(($wpdb->queries[0] ?? '') === 'START TRANSACTION', 'transaction starts before destructive replacement');
esc_p5_tfs_atomic_assert(str_contains((string) ($wpdb->reads[0] ?? ''), 'FOR UPDATE'), 'Template Version row is locked before replacement');
esc_p5_tfs_atomic_assert(str_contains((string) ($wpdb->queries[1] ?? ''), 'DELETE FROM wp_safecontracts_contract_template_version_fields'), 'existing draft snapshots are deleted only inside transaction');
esc_p5_tfs_atomic_assert(str_contains((string) ($wpdb->queries[2] ?? ''), 'INSERT INTO wp_safecontracts_contract_template_version_fields'), 'snapshot INSERT is attempted with atomic definition predicate');
esc_p5_tfs_atomic_assert(($wpdb->queries[3] ?? '') === 'ROLLBACK', 'zero-row concurrent snapshot failure rolls transaction back');
esc_p5_tfs_atomic_assert(! in_array('COMMIT', $wpdb->queries, true), 'failed snapshot replacement never commits partial field set');

$GLOBALS['wpdb'] = $originalWpdb;
CoreTenantEnforcement::disable();
TenantContextStore::reset();

echo "P5-004 Enterprise Template Dynamic Field atomic rollback checks passed ({$assertions} assertions).\n";
