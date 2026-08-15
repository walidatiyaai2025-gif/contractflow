<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Notifications\NotificationRule;
use SafeContracts\Notifications\NotificationRuleService;
use SafeContracts\Notifications\NotificationTemplateRepository;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;
use Throwable;

final class NotificationSettingsPage
{
    public const SLUG = 'safecontracts-notification-settings';
    public const SAVE_ACTION = 'safecontracts_save_notification_settings';

    public static function register(): void
    {
        add_submenu_page(AdminShell::SLUG, __('Notification Settings', 'safecontracts'), __('Notification Settings', 'safecontracts'), Capabilities::MANAGE_NOTIFICATIONS, self::SLUG, [self::class, 'render']);
    }

    public static function handleSave(): void
    {
        if (! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            wp_die(__('You do not have permission to manage notification settings.', 'safecontracts'));
        }
        check_admin_referer(self::SAVE_ACTION);
        $status = 'saved';
        try {
            $originalCode = sanitize_key((string) ($_POST['original_code'] ?? ''));
            $code = $originalCode !== '' ? $originalCode : sanitize_key((string) ($_POST['code'] ?? ''));
            $recipientRoles = is_array($_POST['recipient_roles'] ?? null) ? array_map('sanitize_key', $_POST['recipient_roles']) : [];
            $escalationRoles = is_array($_POST['escalation_roles'] ?? null) ? array_map('sanitize_key', $_POST['escalation_roles']) : [];
            (new NotificationRuleService())->save([
                'code' => $code,
                'name' => sanitize_text_field((string) ($_POST['name'] ?? '')),
                'trigger_type' => sanitize_key((string) ($_POST['trigger_type'] ?? '')),
                'days_before' => (int) ($_POST['days_before'] ?? 0),
                'days_after' => (int) ($_POST['days_after'] ?? 0),
                'repeat_interval_days' => (int) ($_POST['repeat_interval_days'] ?? 0),
                'max_repeats' => (int) ($_POST['max_repeats'] ?? 0),
                'recipient_roles' => $recipientRoles,
                'escalation_roles' => $escalationRoles,
                'target_assigned_accountant' => isset($_POST['target_assigned_accountant']),
                'template_code' => sanitize_key((string) ($_POST['template_code'] ?? '')),
                'is_active' => isset($_POST['is_active']),
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
        if (! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            wp_die(__('You do not have permission to manage notification settings.', 'safecontracts'));
        }
        $service = new NotificationRuleService();
        $rules = $service->all();
        $templates = (new NotificationTemplateRepository())->all(true);
        $selected = null;
        $selectedCode = sanitize_key((string) ($_GET['rule'] ?? ''));
        if ($selectedCode !== '') {
            $selected = $service->findByCode($selectedCode);
        }
        $roles = [
            RoleRegistrar::SYSTEM_ADMIN => __('System Administrator', 'safecontracts'),
            RoleRegistrar::MANAGER => __('Manager', 'safecontracts'),
            RoleRegistrar::ACCOUNTANT => __('Accountant', 'safecontracts'),
            RoleRegistrar::VIEWER => __('Viewer', 'safecontracts'),
        ];
        $selectedRecipients = is_array($selected['recipient_roles'] ?? null) ? $selected['recipient_roles'] : [];
        $selectedEscalations = is_array($selected['escalation_roles'] ?? null) ? $selected['escalation_roles'] : [];
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Notification configuration', 'safecontracts'); ?></p><h1><?php echo esc_html__('Notification Settings', 'safecontracts'); ?></h1></div></div>
            <div class="safecontracts-split-layout">
                <section class="safecontracts-admin-card safecontracts-table-card">
                    <h2><?php echo esc_html__('Rules', 'safecontracts'); ?></h2>
                    <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Code', 'safecontracts'); ?></th><th><?php echo esc_html__('Trigger', 'safecontracts'); ?></th><th><?php echo esc_html__('Template', 'safecontracts'); ?></th><th><?php echo esc_html__('State', 'safecontracts'); ?></th></tr></thead><tbody>
                    <?php foreach ($rules as $rule) : ?><tr><td><a href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'rule' => $rule['code']], admin_url('admin.php'))); ?>"><code><?php echo esc_html((string) $rule['code']); ?></code></a><br><?php echo esc_html((string) $rule['name']); ?></td><td><?php echo esc_html((string) $rule['trigger_type']); ?></td><td><?php echo esc_html((string) $rule['template_code']); ?></td><td><?php echo ! empty($rule['is_active']) ? esc_html__('Active', 'safecontracts') : esc_html__('Disabled', 'safecontracts'); ?></td></tr><?php endforeach; ?>
                    </tbody></table>
                </section>
                <section class="safecontracts-admin-card safecontracts-settings-card">
                    <h2><?php echo $selected ? esc_html__('Edit rule', 'safecontracts') : esc_html__('Add rule', 'safecontracts'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>
                        <input type="hidden" name="original_code" value="<?php echo esc_attr((string) ($selected['code'] ?? '')); ?>">
                        <?php if ($selected) : ?><p><strong><?php echo esc_html__('Stable code', 'safecontracts'); ?>:</strong> <code><?php echo esc_html((string) $selected['code']); ?></code></p><?php else : ?><p><label><?php echo esc_html__('Code', 'safecontracts'); ?><input class="widefat" name="code" maxlength="100" required></label></p><?php endif; ?>
                        <p><label><?php echo esc_html__('Name', 'safecontracts'); ?><input class="widefat" name="name" maxlength="191" required value="<?php echo esc_attr((string) ($selected['name'] ?? '')); ?>"></label></p>
                        <p><label><?php echo esc_html__('Trigger', 'safecontracts'); ?><select class="widefat" name="trigger_type"><?php foreach (NotificationRule::allowedTriggers() as $trigger) : ?><option value="<?php echo esc_attr($trigger); ?>" <?php selected((string) ($selected['trigger_type'] ?? NotificationRule::TRIGGER_BEFORE_DUE), $trigger); ?>><?php echo esc_html($trigger); ?></option><?php endforeach; ?></select></label></p>
                        <div class="safecontracts-field-row"><label><?php echo esc_html__('Days before', 'safecontracts'); ?><input type="number" min="0" max="365" name="days_before" value="<?php echo esc_attr((string) ($selected['days_before'] ?? 10)); ?>"></label><label><?php echo esc_html__('Days after', 'safecontracts'); ?><input type="number" min="0" max="365" name="days_after" value="<?php echo esc_attr((string) ($selected['days_after'] ?? 0)); ?>"></label></div>
                        <div class="safecontracts-field-row"><label><?php echo esc_html__('Repeat interval days', 'safecontracts'); ?><input type="number" min="0" max="365" name="repeat_interval_days" value="<?php echo esc_attr((string) ($selected['repeat_interval_days'] ?? 0)); ?>"></label><label><?php echo esc_html__('Max repeats', 'safecontracts'); ?><input type="number" min="0" max="50" name="max_repeats" value="<?php echo esc_attr((string) ($selected['max_repeats'] ?? 0)); ?>"></label></div>
                        <fieldset><legend><?php echo esc_html__('Recipients', 'safecontracts'); ?></legend><?php foreach ($roles as $slug => $label) : ?><label class="safecontracts-check-row"><input type="checkbox" name="recipient_roles[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $selectedRecipients, true)); ?>><?php echo esc_html($label); ?></label><?php endforeach; ?><label class="safecontracts-check-row"><input type="checkbox" name="target_assigned_accountant" value="1" <?php checked(! empty($selected['target_assigned_accountant'])); ?>><?php echo esc_html__('Assigned Accountant', 'safecontracts'); ?></label></fieldset>
                        <fieldset><legend><?php echo esc_html__('Escalation roles', 'safecontracts'); ?></legend><?php foreach ($roles as $slug => $label) : ?><label class="safecontracts-check-row"><input type="checkbox" name="escalation_roles[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $selectedEscalations, true)); ?>><?php echo esc_html($label); ?></label><?php endforeach; ?></fieldset>
                        <p><label><?php echo esc_html__('Template', 'safecontracts'); ?><select class="widefat" name="template_code" required><?php foreach ($templates as $template) : ?><option value="<?php echo esc_attr((string) $template['code']); ?>" <?php selected((string) ($selected['template_code'] ?? ''), (string) $template['code']); ?>><?php echo esc_html((string) $template['code']); ?></option><?php endforeach; ?></select></label></p>
                        <p><label><input type="checkbox" name="is_active" value="1" <?php checked($selected === null || ! empty($selected['is_active'])); ?>><?php echo esc_html__('Active', 'safecontracts'); ?></label></p>
                        <?php submit_button($selected ? __('Save Notification Rule', 'safecontracts') : __('Add Notification Rule', 'safecontracts')); ?>
                    </form>
                    <p class="description"><?php echo esc_html__('Settled-payment suppression, contractual due-date matching and recipient scope remain enforced by the notification engine.', 'safecontracts'); ?></p>
                </section>
            </div>
        </div>
        <?php
    }
}
