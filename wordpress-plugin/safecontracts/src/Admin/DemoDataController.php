<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;
use SafeContracts\Support\DemoDataService;
use SafeContracts\Translations\TranslationCatalog;
use Throwable;

final class DemoDataController
{
    public const CREATE_ACTION = 'safecontracts_create_demo_data';
    public const DELETE_ACTION = 'safecontracts_delete_demo_data';
    private const ERROR_TRANSIENT_PREFIX = 'safecontracts_demo_error_';

    public static function handleCreate(): void
    {
        self::authorize(self::CREATE_ACTION);
        $status = 'created';
        $rows = 0;
        try {
            $registry = (new DemoDataService())->create();
            $rows = (int) ($registry['total_rows'] ?? 0);
        } catch (Throwable $error) {
            self::rememberError($error);
            $status = 'create_failed';
        }
        self::redirect($status, $rows);
    }

    public static function handleDelete(): void
    {
        self::authorize(self::DELETE_ACTION);
        $status = 'deleted';
        $rows = 0;
        try {
            $result = (new DemoDataService())->delete();
            $rows = (int) ($result['deleted_rows'] ?? 0);
        } catch (Throwable $error) {
            self::rememberError($error);
            $status = 'delete_failed';
        }
        self::redirect($status, $rows);
    }

    public static function renderControls(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            return;
        }
        $registry = (new DemoDataService())->registry();
        $status = isset($_GET['safecontracts_demo_status']) && is_scalar($_GET['safecontracts_demo_status'])
            ? sanitize_key((string) $_GET['safecontracts_demo_status'])
            : '';
        $affected = isset($_GET['safecontracts_demo_rows']) && is_scalar($_GET['safecontracts_demo_rows'])
            ? max(0, (int) $_GET['safecontracts_demo_rows'])
            : 0;
        self::notice($status, $affected);
        $tableCount = (int) ($registry['table_count'] ?? (is_array($registry['tables'] ?? null) ? count($registry['tables']) : 22));
        $batchCount = (int) ($registry['batch_count'] ?? ($registry === null ? 0 : 1));
        $totalRows = (int) ($registry['total_rows'] ?? 0);
        $rowsPerBatch = DemoDataService::ROWS_PER_TABLE * $tableCount;
        ?>
        <section class="safecontracts-dashboard-demo" aria-labelledby="safecontracts-dashboard-demo-title">
            <div class="safecontracts-dashboard-demo__copy">
                <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html(self::text('Controlled load testing', 'اختبار تحميل متحكم به')); ?></p>
                <h3 id="safecontracts-dashboard-demo-title"><?php echo esc_html(self::text('Demo data workspace', 'بيانات الديمو')); ?></h3>
                <p><?php echo esc_html(sprintf(self::text('Every press adds a new visible batch: %1$d realistic rows in each of %2$d plugin tables (%3$d rows per batch).', 'كل ضغطة تضيف دفعة جديدة ظاهرة: %1$d سجل واقعي في كل جدول من جداول الإضافة وعددها %2$d (%3$d سجل في الدفعة).'), DemoDataService::ROWS_PER_TABLE, $tableCount, $rowsPerBatch)); ?></p>
                <p class="safecontracts-dashboard-demo__safety"><span class="dashicons dashicons-shield" aria-hidden="true"></span><?php echo esc_html(self::text('Deletion uses the exact stored primary-key registry. It never truncates tables and never deletes unregistered rows.', 'الحذف يعتمد على سجل أرقام الصفوف المنشأة بدقة، ولا يستخدم تفريغ الجداول ولا يحذف أي صف غير مسجل كديمو.')); ?></p>
                <?php if ($registry !== null) : ?>
                    <dl>
                        <div><dt><?php echo esc_html(self::text('Batches', 'عدد الدفعات')); ?></dt><dd><?php echo esc_html(number_format_i18n($batchCount)); ?></dd></div>
                        <div><dt><?php echo esc_html(self::text('Rows per table', 'الصفوف في كل جدول')); ?></dt><dd><?php echo esc_html(number_format_i18n($batchCount * DemoDataService::ROWS_PER_TABLE)); ?></dd></div>
                        <div><dt><?php echo esc_html(self::text('Registered demo rows', 'إجمالي صفوف الديمو')); ?></dt><dd><?php echo esc_html(number_format_i18n($totalRows)); ?></dd></div>
                        <div><dt><?php echo esc_html(self::text('Latest batch', 'آخر دفعة')); ?></dt><dd><?php echo esc_html((string) ($registry['batch_id'] ?? '')); ?></dd></div>
                        <div><dt><?php echo esc_html(self::text('Latest creation', 'آخر إنشاء')); ?></dt><dd><?php echo esc_html((string) ($registry['created_at'] ?? '')); ?> UTC</dd></div>
                    </dl>
                    <nav class="safecontracts-dashboard-demo__links" aria-label="<?php echo esc_attr(self::text('Open demo data screens', 'فتح شاشات بيانات الديمو')); ?>">
                        <?php foreach (self::screenLinks() as $link) : ?><a class="button button-secondary" href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a><?php endforeach; ?>
                    </nav>
                <?php endif; ?>
            </div>
            <div class="safecontracts-dashboard-demo__actions">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::CREATE_ACTION); ?>">
                    <?php wp_nonce_field(self::CREATE_ACTION); ?>
                    <button type="submit" class="button button-primary" data-safecontracts-confirm="<?php echo esc_attr(sprintf(self::text('Add another %s-row demo batch now?', 'إضافة دفعة ديمو جديدة من %s سجل الآن؟'), number_format_i18n($rowsPerBatch))); ?>" data-safecontracts-busy-label="<?php echo esc_attr(self::text('Creating and verifying rows…', 'جارٍ الإنشاء والتحقق من الصفوف…')); ?>"><span class="dashicons dashicons-database-add" aria-hidden="true"></span><?php echo esc_html(self::text('Add 500 rows per table', 'إضافة 500 سجل لكل جدول')); ?></button>
                </form>
                <?php if ($registry !== null) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::DELETE_ACTION); ?>">
                        <?php wp_nonce_field(self::DELETE_ACTION); ?>
                        <button type="submit" class="button button-link-delete" data-safecontracts-confirm="<?php echo esc_attr(sprintf(self::text('Delete all %1$d registered demo batches (%2$s rows) without touching real data?', 'حذف كل دفعات الديمو المسجلة وعددها %1$d (%2$s سجل) دون لمس البيانات الحقيقية؟'), $batchCount, number_format_i18n($totalRows))); ?>" data-safecontracts-busy-label="<?php echo esc_attr(self::text('Deleting registered demo rows…', 'جارٍ حذف صفوف الديمو المسجلة…')); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span><?php echo esc_html(self::text('Delete all demo data', 'حذف كل بيانات الديمو')); ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
        <?php
    }

    private static function authorize(string $action): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to manage SafeContracts demo data.', 'safecontracts'));
        }
        check_admin_referer($action);
    }

    private static function redirect(string $status, int $rows): never
    {
        wp_safe_redirect(add_query_arg([
            'page' => AdminShell::SLUG,
            'safecontracts_demo_status' => $status,
            'safecontracts_demo_rows' => $rows,
        ], admin_url('admin.php')));
        exit;
    }

    private static function notice(string $status, int $rows): void
    {
        $failureDetail = in_array($status, ['create_failed', 'delete_failed'], true) ? self::takeError() : '';
        $messages = [
            'created' => ['success', sprintf(self::text('%s demo rows were created, verified and are now visible in the linked screens.', 'تم إنشاء والتحقق من %s سجل ديمو وهي ظاهرة الآن في الشاشات المرتبطة.'), number_format_i18n($rows))],
            'deleted' => ['success', sprintf(self::text('%s registered demo rows were deleted. Existing business data was not touched.', 'تم حذف %s سجل ديمو مسجل دون المساس ببيانات العمل الحالية.'), number_format_i18n($rows))],
            'create_failed' => ['error', self::text('Demo data was not created. The transaction was rolled back.', 'لم يتم إنشاء بيانات الديمو وتم التراجع عن العملية كاملة.')],
            'delete_failed' => ['error', self::text('Demo data was not deleted. The transaction was rolled back and the registry was retained.', 'لم يتم حذف بيانات الديمو وتم التراجع عن العملية مع الاحتفاظ بسجل الصفوف.')],
        ];
        if (! isset($messages[$status])) {
            return;
        }
        [$type, $message] = $messages[$status];
        ?><div class="notice notice-<?php echo esc_attr($type); ?> inline is-dismissible"><p><?php echo esc_html($message); ?><?php if ($failureDetail !== '') : ?> <strong><?php echo esc_html(self::text('Technical reason:', 'السبب التقني:')); ?></strong> <code><?php echo esc_html($failureDetail); ?></code><?php endif; ?></p></div><?php
    }

    /** @return list<array{label:string,url:string}> */
    private static function screenLinks(): array
    {
        return [
            ['label' => self::text('Customers', 'العملاء'), 'url' => add_query_arg(['page' => CustomersPage::SLUG], admin_url('admin.php'))],
            ['label' => self::text('Suppliers', 'الموردون'), 'url' => add_query_arg(['page' => SuppliersPage::SLUG], admin_url('admin.php'))],
            ['label' => self::text('Contracts', 'العقود'), 'url' => add_query_arg(['page' => ContractsPage::SLUG], admin_url('admin.php'))],
            ['label' => self::text('Payments', 'الدفعات'), 'url' => add_query_arg(['page' => PaymentsPage::SLUG], admin_url('admin.php'))],
            ['label' => self::text('Follow-ups', 'المتابعات'), 'url' => add_query_arg(['page' => FollowUpsPage::SLUG], admin_url('admin.php'))],
        ];
    }

    private static function rememberError(Throwable $error): void
    {
        $detail = sanitize_text_field(substr($error->getMessage(), 0, 500));
        set_transient(self::ERROR_TRANSIENT_PREFIX . get_current_user_id(), $detail, 5 * MINUTE_IN_SECONDS);
    }

    private static function takeError(): string
    {
        $key = self::ERROR_TRANSIENT_PREFIX . get_current_user_id();
        $detail = get_transient($key);
        delete_transient($key);
        return is_scalar($detail) ? (string) $detail : '';
    }

    private static function text(string $english, string $arabic): string
    {
        return TranslationCatalog::currentLanguage() === 'ar' ? $arabic : $english;
    }
}
