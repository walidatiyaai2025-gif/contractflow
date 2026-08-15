<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Import\ColumnMapping;
use SafeContracts\Import\DuplicateStrategy;
use SafeContracts\Import\ImportExecutionService;
use SafeContracts\Import\ImportPreviewService;
use SafeContracts\Import\ImportRunRepository;
use SafeContracts\Import\ImportUploadService;
use SafeContracts\Import\PrivateImportStorage;
use SafeContracts\Roles\Capabilities;
use Throwable;

final class ImportsPage
{
    public const SLUG = 'safecontracts-imports';
    public const UPLOAD_ACTION = 'safecontracts_import_upload';
    public const MAP_ACTION = 'safecontracts_import_mapping';
    public const EXECUTE_ACTION = 'safecontracts_import_execute';

    public static function register(): void
    {
        add_submenu_page(AdminShell::SLUG, __('Import', 'safecontracts'), __('Import', 'safecontracts'), Capabilities::RUN_IMPORTS, self::SLUG, [self::class, 'render']);
    }

    public static function handleUpload(): void
    {
        self::requireImportCapability();
        check_admin_referer(self::UPLOAD_ACTION);
        $status = 'uploaded';
        $runId = 0;
        try {
            $file = $_FILES['workbook'] ?? null;
            if (! is_array($file)) {
                throw new \InvalidArgumentException('Workbook upload is required.');
            }
            $result = (new ImportUploadService())->accept($file);
            $runId = (int) $result['run_id'];
        } catch (Throwable $error) {
            unset($error);
            $status = 'invalid_upload';
        }
        self::redirect($runId, $status);
    }

    public static function handleMapping(): void
    {
        self::requireImportCapability();
        check_admin_referer(self::MAP_ACTION);
        $runId = self::positiveInt($_POST['run_id'] ?? null);
        $status = 'mapped';
        try {
            if ($runId <= 0) {
                throw new \InvalidArgumentException('Import run ID is required.');
            }
            $sheetName = self::scalarText($_POST['selected_sheet'] ?? null, 191);
            $mappingInput = $_POST['mapping'] ?? [];
            if (! is_array($mappingInput)) {
                throw new \InvalidArgumentException('Import mapping is malformed.');
            }
            $runs = new ImportRunRepository();
            $run = $runs->find($runId);
            if ($run === null) {
                throw new \InvalidArgumentException('Import run was not found.');
            }
            $sheet = ColumnMapping::sheet($run['discovery'], $sheetName);
            $mapping = (new ColumnMapping())->validate($mappingInput, $sheet);
            $runs->saveMapping($runId, $sheetName, $mapping);
        } catch (Throwable $error) {
            unset($error);
            $status = 'invalid_mapping';
        }
        self::redirect($runId, $status);
    }

    public static function handleExecute(): void
    {
        self::requireImportCapability();
        check_admin_referer(self::EXECUTE_ACTION);
        $runId = self::positiveInt($_POST['run_id'] ?? null);
        $status = 'executed';
        try {
            if ($runId <= 0) {
                throw new \InvalidArgumentException('Import run ID is required.');
            }
            $result = (new ImportExecutionService())->execute($runId, $_POST['duplicate_strategy'] ?? DuplicateStrategy::FAIL);
            $status = (string) $result['status'];
        } catch (Throwable $error) {
            unset($error);
            $status = 'execution_failed';
        }
        self::redirect($runId, $status);
    }

    public static function render(): void
    {
        self::requireImportCapability();
        $runs = new ImportRunRepository();
        $recent = $runs->recent(20);
        $runId = self::positiveInt($_GET['run_id'] ?? null);
        $run = $runId > 0 ? $runs->find($runId) : null;
        $errors = $runId > 0 ? $runs->errors($runId, 500) : [];
        $preview = [];
        $previewError = '';
        if ($run !== null && $run['mapping'] !== [] && (string) ($run['selected_sheet'] ?? '') !== '') {
            try {
                $sheet = ColumnMapping::sheet($run['discovery'], (string) $run['selected_sheet']);
                $path = (new PrivateImportStorage())->pathForKey((string) $run['storage_key']);
                $preview = (new ImportPreviewService())->preview($path, (string) $run['selected_sheet'], (int) $sheet['header_row'], $run['mapping'], 20);
            } catch (Throwable $error) {
                $previewError = $error->getMessage();
            }
        }
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Controlled data onboarding', 'safecontracts'); ?></p><h1><?php echo esc_html__('Excel Import', 'safecontracts'); ?></h1></div></div>
            <section class="safecontracts-admin-card safecontracts-settings-card">
                <h2><?php echo esc_html__('Upload workbook', 'safecontracts'); ?></h2>
                <p class="description"><?php echo esc_html__('Only .xlsx files up to 20 MiB are accepted. Macros, external links, workbook connections and formula cells are rejected; uploads are staged in private server storage.', 'safecontracts'); ?></p>
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="<?php echo esc_attr(self::UPLOAD_ACTION); ?>"><?php wp_nonce_field(self::UPLOAD_ACTION); ?><p><input type="file" name="workbook" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></p><?php submit_button(__('Upload & inspect workbook', 'safecontracts')); ?></form>
            </section>

            <section class="safecontracts-admin-card safecontracts-table-card"><h2><?php echo esc_html__('Recent import runs', 'safecontracts'); ?></h2><table class="widefat striped"><thead><tr><th><?php echo esc_html__('Run', 'safecontracts'); ?></th><th><?php echo esc_html__('Workbook', 'safecontracts'); ?></th><th><?php echo esc_html__('Status', 'safecontracts'); ?></th><th><?php echo esc_html__('Imported / skipped / errors', 'safecontracts'); ?></th><th><?php echo esc_html__('Created', 'safecontracts'); ?></th></tr></thead><tbody>
            <?php foreach ($recent as $item) : ?><tr><td><a href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'run_id' => (int) $item['id']], admin_url('admin.php'))); ?>">#<?php echo esc_html((string) $item['id']); ?></a></td><td><?php echo esc_html((string) $item['original_filename']); ?></td><td><?php echo esc_html((string) $item['status']); ?></td><td><?php echo esc_html((string) $item['imported_rows'] . ' / ' . (string) $item['skipped_rows'] . ' / ' . (string) $item['error_rows']); ?></td><td><?php echo esc_html((string) $item['created_at']); ?></td></tr><?php endforeach; ?>
            </tbody></table></section>

            <?php if ($run !== null) : ?>
                <section class="safecontracts-admin-card safecontracts-settings-card"><h2><?php echo esc_html(sprintf(__('Run #%d — column mapping', 'safecontracts'), (int) $run['id'])); ?></h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="<?php echo esc_attr(self::MAP_ACTION); ?>"><input type="hidden" name="run_id" value="<?php echo esc_attr((string) $run['id']); ?>"><?php wp_nonce_field(self::MAP_ACTION); ?>
                <p><label><?php echo esc_html__('Worksheet', 'safecontracts'); ?><select class="widefat" name="selected_sheet" required><?php foreach ($run['discovery']['sheets'] ?? [] as $sheet) : ?><option value="<?php echo esc_attr((string) $sheet['name']); ?>" <?php selected((string) ($run['selected_sheet'] ?? ''), (string) $sheet['name']); ?>><?php echo esc_html((string) $sheet['name']); ?></option><?php endforeach; ?></select></label></p>
                <div class="safecontracts-role-grid"><?php $selectedSheet = self::selectedSheet($run); $headers = is_array($selectedSheet['headers'] ?? null) ? $selectedSheet['headers'] : []; foreach (ColumnMapping::fields() as $field => $definition) : ?><label><?php echo esc_html($definition['label'] . ($definition['required'] ? ' *' : '')); ?><select class="widefat" name="mapping[<?php echo esc_attr($field); ?>]"><option value=""><?php echo esc_html__('Ignore / not mapped', 'safecontracts'); ?></option><?php foreach ($headers as $header) : ?><option value="<?php echo esc_attr((string) $header['column']); ?>" <?php selected((string) ($run['mapping'][$field] ?? ''), (string) $header['column']); ?>><?php echo esc_html((string) $header['column'] . ' — ' . (string) $header['original']); ?></option><?php endforeach; ?></select></label><?php endforeach; ?></div><?php submit_button(__('Save mapping', 'safecontracts')); ?></form></section>

                <?php if ($run['mapping'] !== []) : ?>
                <section class="safecontracts-admin-card safecontracts-table-card"><h2><?php echo esc_html__('Import preview', 'safecontracts'); ?></h2><?php if ($previewError !== '') : ?><p><?php echo esc_html($previewError); ?></p><?php elseif ($preview === []) : ?><p><?php echo esc_html__('No data rows found after the header.', 'safecontracts'); ?></p><?php else : ?><table class="widefat striped"><thead><tr><th><?php echo esc_html__('Row', 'safecontracts'); ?></th><?php foreach (array_keys($run['mapping']) as $field) : ?><th><?php echo esc_html($field); ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($preview as $row) : ?><tr><td><?php echo esc_html((string) $row['row_number']); ?></td><?php foreach (array_keys($run['mapping']) as $field) : ?><td><?php echo esc_html((string) ($row['data'][$field] ?? '')); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table><?php endif; ?><p class="description"><?php echo esc_html__('Preview is read-only. All rows are validated before any business mutation.', 'safecontracts'); ?></p></section>

                <section class="safecontracts-admin-card safecontracts-settings-card"><h2><?php echo esc_html__('Validate & execute', 'safecontracts'); ?></h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="<?php echo esc_attr(self::EXECUTE_ACTION); ?>"><input type="hidden" name="run_id" value="<?php echo esc_attr((string) $run['id']); ?>"><?php wp_nonce_field(self::EXECUTE_ACTION); ?><p><label><?php echo esc_html__('Duplicate strategy', 'safecontracts'); ?><select name="duplicate_strategy"><option value="fail"><?php echo esc_html__('Fail duplicate row', 'safecontracts'); ?></option><option value="skip"><?php echo esc_html__('Skip duplicate row', 'safecontracts'); ?></option><option value="update"><?php echo esc_html__('Update safe fields only', 'safecontracts'); ?></option></select></label></p><p class="description"><?php echo esc_html__('Execution re-reads the private workbook and mapping server-side. Validation errors prevent all business writes. Successful rows run inside database transactions.', 'safecontracts'); ?></p><?php submit_button(__('Validate & execute import', 'safecontracts')); ?></form></section>
                <?php endif; ?>

                <?php if ($errors !== []) : ?><section class="safecontracts-admin-card safecontracts-table-card"><h2><?php echo esc_html__('Row errors', 'safecontracts'); ?></h2><table class="widefat striped"><thead><tr><th><?php echo esc_html__('Row', 'safecontracts'); ?></th><th><?php echo esc_html__('Field', 'safecontracts'); ?></th><th><?php echo esc_html__('Code', 'safecontracts'); ?></th><th><?php echo esc_html__('Message', 'safecontracts'); ?></th></tr></thead><tbody><?php foreach ($errors as $error) : ?><tr><td><?php echo esc_html((string) $error['row_number']); ?></td><td><?php echo esc_html((string) ($error['field_name'] ?? '')); ?></td><td><code><?php echo esc_html((string) $error['error_code']); ?></code></td><td><?php echo esc_html((string) $error['message']); ?></td></tr><?php endforeach; ?></tbody></table></section><?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function requireImportCapability(): void
    {
        if (! current_user_can(Capabilities::RUN_IMPORTS)) {
            wp_die(__('You do not have permission to run SafeContracts imports.', 'safecontracts'));
        }
    }

    private static function redirect(int $runId, string $status): never
    {
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'run_id' => $runId, 'safecontracts_status' => $status], admin_url('admin.php')));
        exit;
    }

    private static function positiveInt(mixed $value): int
    {
        if (! is_scalar($value) || is_bool($value)) { return 0; }
        $filtered = filter_var((string) $value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $filtered === false ? 0 : (int) $filtered;
    }

    private static function scalarText(mixed $value, int $max): string
    {
        if (! is_scalar($value) || is_bool($value)) { throw new \InvalidArgumentException('Import field must be scalar text.'); }
        $text = trim(strip_tags((string) $value));
        if ($text === '' || strlen($text) > $max) { throw new \InvalidArgumentException('Import field is empty or too long.'); }
        return $text;
    }

    private static function selectedSheet(array $run): ?array
    {
        $name = (string) ($run['selected_sheet'] ?? ($run['discovery']['sheets'][0]['name'] ?? ''));
        if ($name === '') { return null; }
        try { return ColumnMapping::sheet($run['discovery'], $name); } catch (Throwable $error) { unset($error); return null; }
    }
}
