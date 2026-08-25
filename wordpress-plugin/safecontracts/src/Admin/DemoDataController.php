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

    public static function handleCreate(): void
    {
        self::authorize(self::CREATE_ACTION);
        $status = 'created';
        $rows = 0;
        try {
            $registry = (new DemoDataService())->create();
            $rows = (int) ($registry['total_rows'] ?? 0);
        } catch (Throwable $error) {
            unset($error);
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
            unset($error);
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
        $tableCount = is_array($registry['tables'] ?? null) ? count($registry['tables']) : 22;
        $totalRows = (int) ($registry['total_rows'] ?? (DemoDataService::ROWS_PER_TABLE * $tableCount));
        ?>
        <section class="safecontracts-dashboard-demo" aria-labelledby="safecontracts-dashboard-demo-title">
            <div class="safecontracts-dashboard-demo__copy">
                <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html(self::text('Controlled load testing', 'اختبار تحميل متحكم به')); ?></p>
                <h3 id="safecontracts-dashboard-demo-title"><?php echo esc_html(self::text('Demo data workspace', 'بيانات الديمو')); ?></h3>
                <p><?php echo esc_html(sprintf(self::text('Create %1$d tagged rows in each of the %2$d SafeContracts plugin tables (%3$d rows total).', 'إنشاء %1$d سجل موسوم في كل جدول من جداول إضافة SafeContracts وعددها %2$d (%3$d سجل إجماليًا).'), DemoDataService::ROWS_PER_TABLE, $tableCount, $totalRows)); ?></p>
                <p class="safecontracts-dashboard-demo__safety"><span class="dashicons dashicons-shield" aria-hidden="true"></span><?php echo esc_html(self::text('Deletion uses the exact stored primary-key registry. It never truncates tables and never deletes unregistered rows.', 'الحذف يعتمد على سجل أرقام الصفوف المنشأة بدقة، ولا يستخدم تفريغ الجداول ولا يحذف أي صف غير مسجل كديمو.')); ?></p>
                <?php if ($registry !== null) : ?><dl><div><dt><?php echo esc_html(self::text('Batch', 'الدفعة')); ?></dt><dd><?php echo esc_html((string) ($registry['batch_id'] ?? '')); ?></dd></div><div><dt><?php echo esc_html(self::text('Created', 'تاريخ الإنشاء')); ?></dt><dd><?php echo esc_html((string) ($registry['created_at'] ?? '')); ?> UTC</dd></div><div><dt><?php echo esc_html(self::text('Registered rows', 'الصفوف المسجلة')); ?></dt><dd><?php echo esc_html(number_format_i18n((int) ($registry['total_rows'] ?? 0))); ?></dd></div></dl><?php endif; ?>
            </div>
            <div class="safecontracts-dashboard-demo__actions">
                <?php if ($registry === null) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::CREATE_ACTION); ?>">
                        <?php wp_nonce_field(self::CREATE_ACTION); ?>
                        <button type="submit" class="button button-primary" data-safecontracts-confirm="<?php echo esc_attr(self::text('Create 11,000 demo rows now? This may take a few seconds.', 'إنشاء 11,000 سجل ديمو الآن؟ قد تستغرق العملية بضع ثوانٍ.')); ?>"><span class="dashicons dashicons-database-add" aria-hidden="true"></span><?php echo esc_html(self::text('Create 500 rows per table', 'إنشاء 500 سجل لكل جدول')); ?></button>
                    </form>
                <?php else : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::DELETE_ACTION); ?>">
                        <?php wp_nonce_field(self::DELETE_ACTION); ?>
                        <button type="submit" class="button button-link-delete" data-safecontracts-confirm="<?php echo esc_attr(self::text('Delete only the exact registered demo rows?', 'حذف صفوف الديمو المسجلة فقط؟')); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span><?php echo esc_html(self::text('Delete demo data', 'حذف بيانات الديمو')); ?></button>
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
        $messages = [
            'created' => ['success', sprintf(self::text('%s demo rows were created and registered.', 'تم إنشاء وتسجيل %s سجل ديمو.'), number_format_i18n($rows))],
            'deleted' => ['success', sprintf(self::text('%s registered demo rows were deleted. Existing business data was not touched.', 'تم حذف %s سجل ديمو مسجل دون المساس ببيانات العمل الحالية.'), number_format_i18n($rows))],
            'create_failed' => ['error', self::text('Demo data was not created. The transaction was rolled back.', 'لم يتم إنشاء بيانات الديمو وتم التراجع عن العملية كاملة.')],
            'delete_failed' => ['error', self::text('Demo data was not deleted. The transaction was rolled back and the registry was retained.', 'لم يتم حذف بيانات الديمو وتم التراجع عن العملية مع الاحتفاظ بسجل الصفوف.')],
        ];
        if (! isset($messages[$status])) {
            return;
        }
        [$type, $message] = $messages[$status];
        ?><div class="notice notice-<?php echo esc_attr($type); ?> inline is-dismissible"><p><?php echo esc_html($message); ?></p></div><?php
    }

    private static function text(string $english, string $arabic): string
    {
        return TranslationCatalog::currentLanguage() === 'ar' ? $arabic : $english;
    }
}
