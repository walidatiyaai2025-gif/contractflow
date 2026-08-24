<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;
use SafeContracts\Settings\MobileConfiguration;
use SafeContracts\Translations\TranslationCatalog;
use Throwable;

final class MobileConfigurationPage
{
    public const SLUG = 'safecontracts-mobile-configuration';
    public const SAVE_ACTION = 'safecontracts_save_mobile_configuration';

    public static function register(): void
    {
        add_submenu_page(AdminShell::SLUG, __('Mobile Configuration', 'safecontracts'), __('Mobile Configuration', 'safecontracts'), Capabilities::MANAGE_SYSTEM, self::SLUG, [self::class, 'render']);
    }

    public static function handleSave(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to manage mobile configuration.', 'safecontracts'));
        }
        check_admin_referer(self::SAVE_ACTION);
        $status = 'saved';
        try {
            (new MobileConfiguration())->save([
                'support_text' => $_POST['support_text'] ?? '',
                'default_page_size' => $_POST['default_page_size'] ?? 25,
                'excel_export_enabled' => isset($_POST['excel_export_enabled']),
                'push_notifications_enabled' => isset($_POST['push_notifications_enabled']),
                'collection_entry_enabled' => isset($_POST['collection_entry_enabled']),
            ]);
        } catch (Throwable $error) {
            unset($error);
            $status = 'invalid';
        }
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'safecontracts_status' => $status], admin_url('admin.php')));
        exit;
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to manage mobile configuration.', 'safecontracts'));
        }
        $config = (new MobileConfiguration())->read();
        $status = isset($_GET['safecontracts_status']) && is_scalar($_GET['safecontracts_status']) ? sanitize_key((string) $_GET['safecontracts_status']) : '';
        $enabledFeatures = (int) ! empty($config['excel_export_enabled']) + (int) ! empty($config['push_notifications_enabled']) + (int) ! empty($config['collection_entry_enabled']);
        ?>
        <div class="wrap safecontracts-settings safecontracts-mobile-configuration" dir="auto">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Mobile bootstrap', 'safecontracts'); ?></p>
                    <h1><?php echo esc_html__('Mobile Configuration', 'safecontracts'); ?></h1>
                    <p><?php echo esc_html(self::text('Manage the existing non-secret mobile bootstrap values and supported feature flags. Unsupported mobile settings are intentionally not invented here.', 'إدارة قيم تهيئة الموبايل غير السرية وأعلام الميزات المدعومة فقط، دون اختلاق إعدادات موبايل غير موجودة.')); ?></p>
                </div>
                <span class="safecontracts-state-chip is-success"><?php echo esc_html(sprintf(self::text('%d of 3 features enabled', 'تم تفعيل %d من 3 ميزات'), $enabledFeatures)); ?></span>
            </div>
            <?php if ($status === 'saved') : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html(self::text('Mobile configuration saved.', 'تم حفظ إعدادات الموبايل.')); ?></p></div><?php endif; ?>
            <?php if ($status === 'invalid') : ?><div class="notice notice-error is-dismissible"><p><?php echo esc_html(self::text('Mobile configuration could not be saved. Review the entered values.', 'تعذر حفظ إعدادات الموبايل. راجع القيم المدخلة.')); ?></p></div><?php endif; ?>

            <?php AdminSummaryCards::render([
                ['label' => __('Default page size', 'safecontracts'), 'value' => (int) $config['default_page_size']],
                ['label' => self::text('Enabled feature flags', 'أعلام الميزات المفعلة'), 'value' => $enabledFeatures],
                ['label' => self::text('Supported feature flags', 'أعلام الميزات المدعومة'), 'value' => 3],
            ]); ?>

            <section class="safecontracts-admin-card safecontracts-settings-card">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>

                    <div class="safecontracts-section-heading"><div><h2><?php echo esc_html(self::text('Mobile content', 'محتوى الموبايل')); ?></h2><p class="description"><?php echo esc_html(self::text('Non-secret text exposed through the existing mobile configuration model.', 'نصوص غير سرية يتم عرضها عبر نموذج إعدادات الموبايل الحالي.')); ?></p></div></div>
                    <p><label><?php echo esc_html__('Support / footer text', 'safecontracts'); ?><textarea class="widefat" name="support_text" maxlength="500" rows="4"><?php echo esc_textarea((string) $config['support_text']); ?></textarea></label></p>

                    <div class="safecontracts-section-heading"><div><h2><?php echo esc_html(self::text('Runtime defaults', 'الإعدادات الافتراضية للتشغيل')); ?></h2></div></div>
                    <p><label><?php echo esc_html__('Default mobile page size', 'safecontracts'); ?><input type="number" min="10" max="200" name="default_page_size" value="<?php echo esc_attr((string) $config['default_page_size']); ?>"></label></p>

                    <div class="safecontracts-section-heading"><div><h2><?php echo esc_html__('Feature availability', 'safecontracts'); ?></h2><p class="description"><?php echo esc_html(self::text('These switches are the complete set currently supported by the stored mobile configuration.', 'هذه المفاتيح هي المجموعة الكاملة المدعومة حاليًا في إعدادات الموبايل المخزنة.')); ?></p></div></div>
                    <fieldset>
                        <label class="safecontracts-check-row"><input type="checkbox" name="excel_export_enabled" value="1" <?php checked($config['excel_export_enabled']); ?>><span><strong><?php echo esc_html__('Excel export', 'safecontracts'); ?></strong><br><small class="description"><?php echo esc_html(self::text('Allow the mobile experience to expose its supported export flow.', 'السماح لتجربة الموبايل بإظهار مسار التصدير المدعوم.')); ?></small></span></label>
                        <label class="safecontracts-check-row"><input type="checkbox" name="push_notifications_enabled" value="1" <?php checked($config['push_notifications_enabled']); ?>><span><strong><?php echo esc_html__('Push notifications', 'safecontracts'); ?></strong><br><small class="description"><?php echo esc_html(self::text('Advertise push-notification availability to the mobile runtime.', 'إعلان توفر الإشعارات الفورية لتطبيق الموبايل.')); ?></small></span></label>
                        <label class="safecontracts-check-row"><input type="checkbox" name="collection_entry_enabled" value="1" <?php checked($config['collection_entry_enabled']); ?>><span><strong><?php echo esc_html__('Collection entry', 'safecontracts'); ?></strong><br><small class="description"><?php echo esc_html(self::text('Allow the existing mobile collection-entry capability when authorized.', 'السماح بإمكانية إدخال التحصيل الحالية من الموبايل عند وجود الصلاحية.')); ?></small></span></label>
                    </fieldset>
                    <?php submit_button(__('Save Mobile Configuration', 'safecontracts')); ?>
                </form>
                <p class="description"><?php echo esc_html(self::text('This page stores only non-secret bootstrap values in WordPress and does not add or bypass REST authorization, environment configuration or backend business rules.', 'تخزن هذه الصفحة قيم تهيئة غير سرية فقط في ووردبريس، ولا تضيف أو تتجاوز صلاحيات REST أو إعدادات البيئة أو قواعد الأعمال في الخادم.')); ?></p>
            </section>
        </div>
        <?php
    }

    private static function text(string $english, string $arabic): string
    {
        return TranslationCatalog::currentLanguage() === 'ar' ? $arabic : __($english, 'safecontracts');
    }
}
