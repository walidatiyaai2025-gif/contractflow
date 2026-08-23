<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Notifications\NotificationRule;
use SafeContracts\Notifications\NotificationRuleService;
use SafeContracts\Notifications\NotificationTemplateRepository;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;
use SafeContracts\Support\Input;
use SafeContracts\Translations\RuntimeLabels;
use Throwable;

final class NotificationSettingsPage
{
    public const SLUG = 'safecontracts-notification-settings';
    public const SAVE_ACTION = 'safecontracts_save_notification_settings';
    public const TOGGLE_ACTION = 'safecontracts_toggle_notification_rule';
    public const DELETE_ACTION = 'safecontracts_delete_notification_rule';

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
            $originalCode = sanitize_key(Input::string($_POST['original_code'] ?? '', 'Original notification rule code'));
            $code = $originalCode !== '' ? $originalCode : sanitize_key(Input::string($_POST['code'] ?? '', 'Notification rule code'));
            $recipientRoles = self::normalizeRoleInput($_POST['recipient_roles'] ?? []);
            $escalationRoles = self::normalizeRoleInput($_POST['escalation_roles'] ?? []);
            (new NotificationRuleService())->saveAndReconcile([
                'code' => $code,
                'name' => sanitize_text_field(Input::string($_POST['name'] ?? '', 'Notification rule name')),
                'trigger_type' => sanitize_key(Input::string($_POST['trigger_type'] ?? '', 'Notification trigger')),
                'days_before' => $_POST['days_before'] ?? 0,
                'days_after' => $_POST['days_after'] ?? 0,
                'repeat_interval_days' => $_POST['repeat_interval_days'] ?? 0,
                'max_repeats' => $_POST['max_repeats'] ?? 0,
                'recipient_roles' => $recipientRoles,
                'escalation_roles' => $escalationRoles,
                'target_assigned_accountant' => isset($_POST['target_assigned_accountant']),
                'template_code' => sanitize_key(Input::string($_POST['template_code'] ?? '', 'Notification template code')),
                'is_active' => isset($_POST['is_active']),
            ]);
        } catch (Throwable $error) {
            unset($error);
            $status = 'invalid';
        }
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'safecontracts_status' => $status], admin_url('admin.php')));
        exit;
    }

    public static function handleToggle(): void
    {
        if (! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            wp_die(__('You do not have permission to manage notification settings.', 'safecontracts'));
        }
        check_admin_referer(self::TOGGLE_ACTION);
        $status = 'invalid';
        try {
            $code = sanitize_key(Input::string($_POST['code'] ?? '', 'Notification rule code'));
            $active = (new NotificationRuleService())->toggleActiveWithSchedule($code);
            $status = $active ? 'activated' : 'deactivated';
        } catch (Throwable $error) {
            unset($error);
        }
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'safecontracts_status' => $status], admin_url('admin.php')));
        exit;
    }

    public static function handleDelete(): void
    {
        if (! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            wp_die(__('You do not have permission to manage notification settings.', 'safecontracts'));
        }
        check_admin_referer(self::DELETE_ACTION);
        $status = 'deleted';
        try {
            $code = sanitize_key(Input::string($_POST['code'] ?? '', 'Notification rule code'));
            (new NotificationRuleService())->deleteWithSchedule($code);
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
        try {
            $selectedCode = sanitize_key(Input::string($_GET['rule'] ?? '', 'Notification rule code'));
        } catch (Throwable $error) {
            unset($error);
            $selectedCode = '';
        }
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
                    <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Code', 'safecontracts'); ?></th><th><?php echo esc_html__('Trigger', 'safecontracts'); ?></th><th><?php echo esc_html__('Template', 'safecontracts'); ?></th><th><?php echo esc_html__('State', 'safecontracts'); ?></th><th><?php echo esc_html__('Actions', 'safecontracts'); ?></th></tr></thead><tbody>
                    <?php foreach ($rules as $rule) : ?>
                        <tr>
                            <td><a href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'rule' => $rule['code']], admin_url('admin.php'))); ?>"><code><?php echo esc_html((string) $rule['code']); ?></code></a><br><?php echo esc_html((string) $rule['name']); ?></td>
                            <td><?php echo esc_html(self::triggerLabel((string) $rule['trigger_type'])); ?></td>
                            <td><?php echo esc_html((string) $rule['template_code']); ?></td>
                            <td><?php echo ! empty($rule['is_active']) ? esc_html__('Active', 'safecontracts') : esc_html__('Disabled', 'safecontracts'); ?></td>
                            <td>
                                <a class="button button-small" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'rule' => $rule['code']], admin_url('admin.php'))); ?>"><?php echo esc_html__('Edit', 'safecontracts'); ?></a>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin:0 4px;">
                                    <input type="hidden" name="action" value="<?php echo esc_attr(self::TOGGLE_ACTION); ?>">
                                    <input type="hidden" name="code" value="<?php echo esc_attr((string) $rule['code']); ?>">
                                    <?php wp_nonce_field(self::TOGGLE_ACTION); ?>
                                    <button class="button button-small" type="submit"><?php echo ! empty($rule['is_active']) ? esc_html__('Deactivate', 'safecontracts') : esc_html__('Activate', 'safecontracts'); ?></button>
                                </form>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin:0;" onsubmit="return confirm('<?php echo esc_js(__('Delete this notification rule and all of its scheduled occurrences? Delivery history already sent by the transport is not erased.', 'safecontracts')); ?>');">
                                    <input type="hidden" name="action" value="<?php echo esc_attr(self::DELETE_ACTION); ?>">
                                    <input type="hidden" name="code" value="<?php echo esc_attr((string) $rule['code']); ?>">
                                    <?php wp_nonce_field(self::DELETE_ACTION); ?>
                                    <button class="button button-small" type="submit" style="color:#b32d2e;border-color:#b32d2e;"><?php echo esc_html__('Delete', 'safecontracts'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table>
                    <p class="description"><?php echo esc_html__('Editing a rule rebuilds its future schedule. Deactivating or deleting a rule clears all scheduled occurrences for that rule. In-flight sends must finish before the change is accepted.', 'safecontracts'); ?></p>
                </section>
                <section class="safecontracts-admin-card safecontracts-settings-card">
                    <h2><?php echo $selected ? esc_html__('Edit rule', 'safecontracts') : esc_html__('Add rule', 'safecontracts'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>
                        <input type="hidden" name="original_code" value="<?php echo esc_attr((string) ($selected['code'] ?? '')); ?>">
                        <?php if ($selected) : ?><p><strong><?php echo esc_html__('Stable code', 'safecontracts'); ?>:</strong> <code><?php echo esc_html((string) $selected['code']); ?></code></p><?php else : ?><p><label><?php echo esc_html__('Code', 'safecontracts'); ?><input class="widefat" name="code" maxlength="100" required></label></p><?php endif; ?>
                        <p><label><?php echo esc_html__('Name', 'safecontracts'); ?><input class="widefat" name="name" maxlength="191" required value="<?php echo esc_attr((string) ($selected['name'] ?? '')); ?>"></label></p>
                        <p><label><?php echo esc_html__('Trigger', 'safecontracts'); ?><select class="widefat" name="trigger_type"><?php foreach (NotificationRule::allowedTriggers() as $trigger) : ?><option value="<?php echo esc_attr($trigger); ?>" <?php selected((string) ($selected['trigger_type'] ?? NotificationRule::TRIGGER_BEFORE_DUE), $trigger); ?>><?php echo esc_html(self::triggerLabel($trigger)); ?></option><?php endforeach; ?></select></label></p>
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

    /** @return list<string> */
    private static function normalizeRoleInput(mixed $value): array
    {
        if (! is_array($value)) {
            throw new \InvalidArgumentException('Notification role selection must be an array.');
        }

        return array_map(
            static fn (mixed $role): string => sanitize_key(Input::string($role, 'Notification recipient role')),
            $value
        );
    }

    private static function triggerLabel(string $trigger): string
    {
        $normalized = strtolower(trim($trigger));
        $source = match ($normalized) {
            'before_due', 'due_before' => 'Before due',
            'due_today', 'on_due' => 'Due today',
            'overdue', 'after_due' => 'Overdue',
            default => ucwords(str_replace('_', ' ', $normalized)),
        };
        return RuntimeLabels::text($source);
    }
}
