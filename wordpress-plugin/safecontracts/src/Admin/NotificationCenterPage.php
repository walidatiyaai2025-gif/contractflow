<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Notifications\DeliveryLogRepository;
use SafeContracts\Notifications\DirectNotificationService;
use SafeContracts\Notifications\EmailSettings;
use SafeContracts\Notifications\NotificationRule;
use SafeContracts\Notifications\NotificationRuleService;
use SafeContracts\Notifications\NotificationSuppressionRepository;
use SafeContracts\Notifications\NotificationTemplate;
use SafeContracts\Notifications\NotificationTemplateRepository;
use SafeContracts\Notifications\NotificationTemplateService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;
use Throwable;

final class NotificationCenterPage
{
    public const SLUG = 'safecontracts-notification-center';
    public const SAVE_RULE_ACTION = 'safecontracts_center_save_rule';
    public const SAVE_TEMPLATE_ACTION = 'safecontracts_center_save_template';
    public const SAVE_EMAIL_ACTION = 'safecontracts_center_save_email';
    public const DIRECT_SEND_ACTION = 'safecontracts_center_direct_send';
    public const SUPPRESSION_ACTION = 'safecontracts_center_suppression';

    public static function register(): void
    {
        add_submenu_page(
            AdminShell::SLUG,
            __('Notification Center', 'safecontracts'),
            __('Notification Center', 'safecontracts'),
            Capabilities::MANAGE_NOTIFICATIONS,
            self::SLUG,
            [self::class, 'render']
        );
    }

    public static function handleSaveRule(): void
    {
        self::requireManage();
        check_admin_referer(self::SAVE_RULE_ACTION);
        $status = 'rule_saved';
        try {
            $original = sanitize_key((string) ($_POST['original_code'] ?? ''));
            $code = $original !== '' ? $original : sanitize_key((string) ($_POST['code'] ?? ''));
            (new NotificationRuleService())->save([
                'code' => $code,
                'name' => sanitize_text_field((string) ($_POST['name'] ?? '')),
                'trigger_type' => sanitize_key((string) ($_POST['trigger_type'] ?? '')),
                'days_before' => $_POST['days_before'] ?? 0,
                'days_after' => $_POST['days_after'] ?? 0,
                'repeat_interval_days' => $_POST['repeat_interval_days'] ?? 0,
                'max_repeats' => $_POST['max_repeats'] ?? 0,
                'recipient_roles' => self::arrayInput($_POST['recipient_roles'] ?? []),
                'recipient_user_ids' => self::arrayInput($_POST['recipient_user_ids'] ?? []),
                'escalation_roles' => self::arrayInput($_POST['escalation_roles'] ?? []),
                'target_assigned_accountant' => isset($_POST['target_assigned_accountant']),
                'push_enabled' => isset($_POST['push_enabled']),
                'email_enabled' => isset($_POST['email_enabled']),
                'template_code' => sanitize_key((string) ($_POST['template_code'] ?? '')),
                'is_active' => isset($_POST['is_active']),
            ]);
        } catch (Throwable) {
            $status = 'rule_invalid';
        }
        self::redirect($status);
    }

    public static function handleSaveTemplate(): void
    {
        self::requireManage();
        check_admin_referer(self::SAVE_TEMPLATE_ACTION);
        $status = 'template_saved';
        try {
            $original = sanitize_key((string) ($_POST['original_code'] ?? ''));
            $code = $original !== '' ? $original : sanitize_key((string) ($_POST['code'] ?? ''));
            (new NotificationTemplateService())->save([
                'code' => $code,
                'title_template' => (string) ($_POST['title_template'] ?? ''),
                'body_template' => (string) ($_POST['body_template'] ?? ''),
                'email_subject_template' => (string) ($_POST['email_subject_template'] ?? ''),
                'email_body_template' => (string) ($_POST['email_body_template'] ?? ''),
                'icon_key' => sanitize_key((string) ($_POST['icon_key'] ?? 'contract_due')),
                'is_active' => isset($_POST['is_active']),
            ]);
        } catch (Throwable) {
            $status = 'template_invalid';
        }
        self::redirect($status);
    }

    public static function handleSaveEmail(): void
    {
        self::requireManage();
        check_admin_referer(self::SAVE_EMAIL_ACTION);
        $status = 'email_saved';
        try {
            (new EmailSettings())->save([
                'enabled' => isset($_POST['enabled']),
                'from_name' => (string) ($_POST['from_name'] ?? ''),
                'from_address' => (string) ($_POST['from_address'] ?? ''),
            ]);
        } catch (Throwable) {
            $status = 'email_invalid';
        }
        self::redirect($status);
    }

    public static function handleDirectSend(): void
    {
        self::requireManage();
        check_admin_referer(self::DIRECT_SEND_ACTION);
        $status = 'direct_sent';
        try {
            (new DirectNotificationService())->send(
                absint($_POST['user_id'] ?? 0),
                (string) ($_POST['title'] ?? ''),
                (string) ($_POST['body'] ?? ''),
                isset($_POST['push_enabled']),
                isset($_POST['email_enabled']),
                sanitize_key((string) ($_POST['icon_key'] ?? 'safe_contracts'))
            );
        } catch (Throwable) {
            $status = 'direct_failed';
        }
        self::redirect($status);
    }

    public static function handleSuppression(): void
    {
        self::requireManage();
        check_admin_referer(self::SUPPRESSION_ACTION);
        $status = 'suppression_saved';
        try {
            (new NotificationSuppressionRepository())->set(
                sanitize_key((string) ($_POST['scope_type'] ?? '')),
                absint($_POST['scope_id'] ?? 0),
                isset($_POST['suppressed']) && (string) $_POST['suppressed'] === '1',
                (string) ($_POST['reason'] ?? ''),
                get_current_user_id()
            );
        } catch (Throwable) {
            $status = 'suppression_invalid';
        }
        self::redirect($status);
    }

    public static function render(): void
    {
        self::requireManage();
        $ruleService = new NotificationRuleService();
        $rules = $ruleService->all();
        $templateRepository = new NotificationTemplateRepository();
        $templates = $templateRepository->all(false);
        $emailSettings = (new EmailSettings())->get();
        $suppressions = (new NotificationSuppressionRepository())->active(250);
        $deliveries = (new DeliveryLogRepository())->recent(100);
        $users = self::users();
        $selectedRule = self::selectedRule($ruleService);
        $selectedTemplate = self::selectedTemplate($templateRepository);
        $failed = count(array_filter($deliveries, static fn (array $row): bool => (string) ($row['status'] ?? '') === 'failed'));
        $activeRules = count(array_filter($rules, static fn (array $row): bool => ! empty($row['is_active'])));
        $activeTemplates = count(array_filter($templates, static fn (array $row): bool => ! empty($row['is_active'])));
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Notification operations', 'safecontracts'); ?></p><h1><?php echo esc_html__('Notification Center', 'safecontracts'); ?></h1></div></div>
            <?php self::notice(); ?>
            <?php AdminSummaryCards::render([
                ['label' => __('Active rules', 'safecontracts'), 'value' => $activeRules, 'detail' => sprintf(__('%d total rules', 'safecontracts'), count($rules))],
                ['label' => __('Active templates', 'safecontracts'), 'value' => $activeTemplates, 'detail' => sprintf(__('%d total templates', 'safecontracts'), count($templates))],
                ['label' => __('Suppressed records', 'safecontracts'), 'value' => count($suppressions), 'detail' => __('Contracts and payments', 'safecontracts')],
                ['label' => __('Recent failed deliveries', 'safecontracts'), 'value' => $failed, 'detail' => __('Last 100 delivery attempts', 'safecontracts')],
            ]); ?>

            <div class="safecontracts-split-layout">
                <section class="safecontracts-admin-card safecontracts-settings-card">
                    <h2><?php echo esc_html__('Email delivery', 'safecontracts'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_EMAIL_ACTION); ?>"><?php wp_nonce_field(self::SAVE_EMAIL_ACTION); ?>
                        <p><label class="safecontracts-check-row"><input type="checkbox" name="enabled" value="1" <?php checked($emailSettings['enabled']); ?>><?php echo esc_html__('Enable email notifications', 'safecontracts'); ?></label></p>
                        <p><label><?php echo esc_html__('Sender name', 'safecontracts'); ?><input class="widefat" name="from_name" maxlength="191" required value="<?php echo esc_attr($emailSettings['from_name']); ?>"></label></p>
                        <p><label><?php echo esc_html__('Sender email', 'safecontracts'); ?><input class="widefat" type="email" name="from_address" required value="<?php echo esc_attr($emailSettings['from_address']); ?>"></label></p>
                        <p class="description"><?php echo esc_html__('Safe Contracts sends through WordPress wp_mail. SMTP/API credentials remain managed by the WordPress mail transport or hosting configuration, not stored in Safe Contracts.', 'safecontracts'); ?></p>
                        <?php submit_button(__('Save Email Settings', 'safecontracts')); ?>
                    </form>
                </section>

                <section class="safecontracts-admin-card safecontracts-settings-card">
                    <h2><?php echo esc_html__('Send to one user', 'safecontracts'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::DIRECT_SEND_ACTION); ?>"><?php wp_nonce_field(self::DIRECT_SEND_ACTION); ?>
                        <p><label><?php echo esc_html__('User', 'safecontracts'); ?><select class="widefat" name="user_id" required><option value=""><?php echo esc_html__('Select user', 'safecontracts'); ?></option><?php foreach ($users as $user) : ?><option value="<?php echo esc_attr((string) $user['id']); ?>"><?php echo esc_html($user['label']); ?></option><?php endforeach; ?></select></label></p>
                        <p><label><?php echo esc_html__('Title', 'safecontracts'); ?><input class="widefat" name="title" maxlength="191" required></label></p>
                        <p><label><?php echo esc_html__('Message', 'safecontracts'); ?><textarea class="widefat" name="body" rows="5" maxlength="4000" required></textarea></label></p>
                        <p><label><?php echo esc_html__('Icon', 'safecontracts'); ?><select class="widefat" name="icon_key"><?php foreach (NotificationTemplate::allowedIconKeys() as $icon) : ?><option value="<?php echo esc_attr($icon); ?>"><?php echo esc_html(ucwords(str_replace('_', ' ', $icon))); ?></option><?php endforeach; ?></select></label></p>
                        <p><label class="safecontracts-check-row"><input type="checkbox" name="push_enabled" value="1" checked><?php echo esc_html__('Push / Firebase', 'safecontracts'); ?></label><label class="safecontracts-check-row"><input type="checkbox" name="email_enabled" value="1"><?php echo esc_html__('Email', 'safecontracts'); ?></label></p>
                        <?php submit_button(__('Send notification', 'safecontracts')); ?>
                    </form>
                </section>
            </div>

            <section class="safecontracts-admin-card safecontracts-table-card">
                <h2><?php echo esc_html__('Notification rules', 'safecontracts'); ?></h2>
                <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Rule', 'safecontracts'); ?></th><th><?php echo esc_html__('Trigger', 'safecontracts'); ?></th><th><?php echo esc_html__('Recipients', 'safecontracts'); ?></th><th><?php echo esc_html__('Channels', 'safecontracts'); ?></th><th><?php echo esc_html__('State', 'safecontracts'); ?></th><th><?php echo esc_html__('Action', 'safecontracts'); ?></th></tr></thead><tbody>
                    <?php foreach ($rules as $rule) : ?><tr><td><strong><?php echo esc_html((string) $rule['name']); ?></strong><br><code><?php echo esc_html((string) $rule['code']); ?></code></td><td><?php echo esc_html(ucwords(str_replace('_', ' ', (string) $rule['trigger_type']))); ?></td><td><?php echo esc_html(self::recipientSummary($rule)); ?></td><td><?php echo esc_html(self::channelSummary($rule)); ?></td><td><?php echo ! empty($rule['is_active']) ? esc_html__('Active', 'safecontracts') : esc_html__('Disabled', 'safecontracts'); ?></td><td><a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'edit_rule' => $rule['code']], admin_url('admin.php'))); ?>"><?php echo esc_html__('Edit', 'safecontracts'); ?></a></td></tr><?php endforeach; ?>
                </tbody></table>
            </section>

            <section class="safecontracts-admin-card safecontracts-settings-card">
                <h2><?php echo $selectedRule ? esc_html__('Edit notification rule', 'safecontracts') : esc_html__('Add notification rule', 'safecontracts'); ?></h2>
                <?php self::ruleForm($selectedRule, $templates, $users); ?>
            </section>

            <div class="safecontracts-split-layout">
                <section class="safecontracts-admin-card safecontracts-table-card">
                    <h2><?php echo esc_html__('Message templates', 'safecontracts'); ?></h2>
                    <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Template', 'safecontracts'); ?></th><th><?php echo esc_html__('Icon', 'safecontracts'); ?></th><th><?php echo esc_html__('State', 'safecontracts'); ?></th><th><?php echo esc_html__('Action', 'safecontracts'); ?></th></tr></thead><tbody><?php foreach ($templates as $template) : ?><tr><td><code><?php echo esc_html((string) $template['code']); ?></code><br><?php echo esc_html((string) $template['title_template']); ?></td><td><?php echo esc_html((string) $template['icon_key']); ?></td><td><?php echo ! empty($template['is_active']) ? esc_html__('Active', 'safecontracts') : esc_html__('Disabled', 'safecontracts'); ?></td><td><a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'edit_template' => $template['code']], admin_url('admin.php'))); ?>"><?php echo esc_html__('Edit', 'safecontracts'); ?></a></td></tr><?php endforeach; ?></tbody></table>
                </section>
                <section class="safecontracts-admin-card safecontracts-settings-card">
                    <h2><?php echo $selectedTemplate ? esc_html__('Edit message template', 'safecontracts') : esc_html__('Add message template', 'safecontracts'); ?></h2>
                    <?php self::templateForm($selectedTemplate); ?>
                </section>
            </div>

            <div class="safecontracts-split-layout">
                <section class="safecontracts-admin-card safecontracts-settings-card">
                    <h2><?php echo esc_html__('Stop notifications for a contract or payment', 'safecontracts'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SUPPRESSION_ACTION); ?>"><?php wp_nonce_field(self::SUPPRESSION_ACTION); ?>
                        <p><label><?php echo esc_html__('Type', 'safecontracts'); ?><select class="widefat" name="scope_type"><option value="contract"><?php echo esc_html__('Contract', 'safecontracts'); ?></option><option value="payment"><?php echo esc_html__('Payment', 'safecontracts'); ?></option></select></label></p>
                        <p><label><?php echo esc_html__('ID', 'safecontracts'); ?><input class="widefat" type="number" min="1" name="scope_id" required></label></p>
                        <p><label><?php echo esc_html__('Reason', 'safecontracts'); ?><input class="widefat" name="reason" maxlength="191"></label></p>
                        <input type="hidden" name="suppressed" value="1">
                        <?php submit_button(__('Stop notifications', 'safecontracts')); ?>
                    </form>
                </section>
                <section class="safecontracts-admin-card safecontracts-table-card">
                    <h2><?php echo esc_html__('Current suppressions', 'safecontracts'); ?></h2>
                    <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Type', 'safecontracts'); ?></th><th><?php echo esc_html__('ID', 'safecontracts'); ?></th><th><?php echo esc_html__('Reason', 'safecontracts'); ?></th><th><?php echo esc_html__('Action', 'safecontracts'); ?></th></tr></thead><tbody>
                    <?php if ($suppressions === []) : ?><tr><td colspan="4"><?php echo esc_html__('No contracts or payments are suppressed.', 'safecontracts'); ?></td></tr><?php endif; ?>
                    <?php foreach ($suppressions as $item) : ?><tr><td><?php echo esc_html(ucfirst((string) $item['scope_type'])); ?></td><td>#<?php echo esc_html((string) $item['scope_id']); ?></td><td><?php echo esc_html((string) ($item['reason'] ?? '')); ?></td><td><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="<?php echo esc_attr(self::SUPPRESSION_ACTION); ?>"><input type="hidden" name="scope_type" value="<?php echo esc_attr((string) $item['scope_type']); ?>"><input type="hidden" name="scope_id" value="<?php echo esc_attr((string) $item['scope_id']); ?>"><input type="hidden" name="suppressed" value="0"><?php wp_nonce_field(self::SUPPRESSION_ACTION); ?><button class="button" type="submit"><?php echo esc_html__('Resume notifications', 'safecontracts'); ?></button></form></td></tr><?php endforeach; ?>
                    </tbody></table>
                </section>
            </div>

            <section class="safecontracts-admin-card safecontracts-table-card">
                <h2><?php echo esc_html__('Recent delivery attempts', 'safecontracts'); ?></h2>
                <table class="widefat striped"><thead><tr><th><?php echo esc_html__('When', 'safecontracts'); ?></th><th><?php echo esc_html__('User', 'safecontracts'); ?></th><th><?php echo esc_html__('Channel', 'safecontracts'); ?></th><th><?php echo esc_html__('Payment', 'safecontracts'); ?></th><th><?php echo esc_html__('Result', 'safecontracts'); ?></th></tr></thead><tbody><?php foreach ($deliveries as $delivery) : ?><tr><td><?php echo esc_html((string) $delivery['created_at']); ?></td><td>#<?php echo esc_html((string) $delivery['user_id']); ?></td><td><?php echo esc_html(ucfirst((string) ($delivery['channel'] ?? 'push'))); ?></td><td><?php echo (int) ($delivery['payment_id'] ?? 0) > 0 ? '#' . esc_html((string) $delivery['payment_id']) : '—'; ?></td><td><?php echo esc_html(ucfirst((string) $delivery['status'])); ?><?php if (! empty($delivery['error_code'])) : ?><br><code><?php echo esc_html((string) $delivery['error_code']); ?></code><?php endif; ?></td></tr><?php endforeach; ?></tbody></table>
            </section>
        </div>
        <?php
    }

    /** @param array<string,mixed>|null $selected @param list<array<string,mixed>> $templates @param list<array{id:int,label:string}> $users */
    private static function ruleForm(?array $selected, array $templates, array $users): void
    {
        $roles = [
            RoleRegistrar::SYSTEM_ADMIN => __('System Administrator', 'safecontracts'),
            RoleRegistrar::MANAGER => __('Manager', 'safecontracts'),
            RoleRegistrar::ACCOUNTANT => __('Accountant', 'safecontracts'),
            RoleRegistrar::VIEWER => __('Viewer', 'safecontracts'),
        ];
        $selectedRoles = is_array($selected['recipient_roles'] ?? null) ? $selected['recipient_roles'] : [];
        $selectedUsers = is_array($selected['recipient_user_ids'] ?? null) ? array_map('intval', $selected['recipient_user_ids']) : [];
        $selectedEscalation = is_array($selected['escalation_roles'] ?? null) ? $selected['escalation_roles'] : [];
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_RULE_ACTION); ?>"><?php wp_nonce_field(self::SAVE_RULE_ACTION); ?><input type="hidden" name="original_code" value="<?php echo esc_attr((string) ($selected['code'] ?? '')); ?>">
            <?php if ($selected === null) : ?><p><label><?php echo esc_html__('Code', 'safecontracts'); ?><input class="widefat" name="code" maxlength="100" required></label></p><?php else : ?><p><strong><?php echo esc_html__('Stable code', 'safecontracts'); ?>:</strong> <code><?php echo esc_html((string) $selected['code']); ?></code></p><?php endif; ?>
            <p><label><?php echo esc_html__('Name', 'safecontracts'); ?><input class="widefat" name="name" maxlength="191" required value="<?php echo esc_attr((string) ($selected['name'] ?? '')); ?>"></label></p>
            <p><label><?php echo esc_html__('Trigger', 'safecontracts'); ?><select class="widefat" name="trigger_type"><?php foreach (NotificationRule::allowedTriggers() as $trigger) : ?><option value="<?php echo esc_attr($trigger); ?>" <?php selected((string) ($selected['trigger_type'] ?? NotificationRule::TRIGGER_BEFORE_DUE), $trigger); ?>><?php echo esc_html(ucwords(str_replace('_', ' ', $trigger))); ?></option><?php endforeach; ?></select></label></p>
            <div class="safecontracts-field-row"><label><?php echo esc_html__('Days before', 'safecontracts'); ?><input type="number" min="0" max="365" name="days_before" value="<?php echo esc_attr((string) ($selected['days_before'] ?? 10)); ?>"></label><label><?php echo esc_html__('Days after', 'safecontracts'); ?><input type="number" min="0" max="365" name="days_after" value="<?php echo esc_attr((string) ($selected['days_after'] ?? 0)); ?>"></label><label><?php echo esc_html__('Repeat every days', 'safecontracts'); ?><input type="number" min="0" max="365" name="repeat_interval_days" value="<?php echo esc_attr((string) ($selected['repeat_interval_days'] ?? 0)); ?>"></label><label><?php echo esc_html__('Max repeats', 'safecontracts'); ?><input type="number" min="0" max="50" name="max_repeats" value="<?php echo esc_attr((string) ($selected['max_repeats'] ?? 0)); ?>"></label></div>
            <fieldset><legend><strong><?php echo esc_html__('Recipients', 'safecontracts'); ?></strong></legend><?php foreach ($roles as $slug => $label) : ?><label class="safecontracts-check-row"><input type="checkbox" name="recipient_roles[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $selectedRoles, true)); ?>><?php echo esc_html($label); ?></label><?php endforeach; ?><label class="safecontracts-check-row"><input type="checkbox" name="target_assigned_accountant" value="1" <?php checked(! empty($selected['target_assigned_accountant'])); ?>><?php echo esc_html__('Assigned Accountant', 'safecontracts'); ?></label></fieldset>
            <p><label><?php echo esc_html__('Specific users', 'safecontracts'); ?><select class="widefat" name="recipient_user_ids[]" multiple size="7"><?php foreach ($users as $user) : ?><option value="<?php echo esc_attr((string) $user['id']); ?>" <?php selected(in_array($user['id'], $selectedUsers, true)); ?>><?php echo esc_html($user['label']); ?></option><?php endforeach; ?></select></label></p>
            <fieldset><legend><strong><?php echo esc_html__('Escalation roles', 'safecontracts'); ?></strong></legend><?php foreach ($roles as $slug => $label) : ?><label class="safecontracts-check-row"><input type="checkbox" name="escalation_roles[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $selectedEscalation, true)); ?>><?php echo esc_html($label); ?></label><?php endforeach; ?></fieldset>
            <p><label><?php echo esc_html__('Template', 'safecontracts'); ?><select class="widefat" name="template_code" required><?php foreach ($templates as $template) : ?><option value="<?php echo esc_attr((string) $template['code']); ?>" <?php selected((string) ($selected['template_code'] ?? ''), (string) $template['code']); ?>><?php echo esc_html((string) $template['code']); ?></option><?php endforeach; ?></select></label></p>
            <p><strong><?php echo esc_html__('Delivery channels', 'safecontracts'); ?></strong><br><label class="safecontracts-check-row"><input type="checkbox" name="push_enabled" value="1" <?php checked($selected === null || ! empty($selected['push_enabled'])); ?>><?php echo esc_html__('Push / Firebase', 'safecontracts'); ?></label><label class="safecontracts-check-row"><input type="checkbox" name="email_enabled" value="1" <?php checked(! empty($selected['email_enabled'])); ?>><?php echo esc_html__('Email', 'safecontracts'); ?></label></p>
            <p><label class="safecontracts-check-row"><input type="checkbox" name="is_active" value="1" <?php checked($selected === null || ! empty($selected['is_active'])); ?>><?php echo esc_html__('Rule active', 'safecontracts'); ?></label></p>
            <?php submit_button($selected ? __('Save rule', 'safecontracts') : __('Add rule', 'safecontracts')); ?>
        </form>
        <?php
    }

    /** @param array<string,mixed>|null $selected */
    private static function templateForm(?array $selected): void
    {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_TEMPLATE_ACTION); ?>"><?php wp_nonce_field(self::SAVE_TEMPLATE_ACTION); ?><input type="hidden" name="original_code" value="<?php echo esc_attr((string) ($selected['code'] ?? '')); ?>">
            <?php if ($selected === null) : ?><p><label><?php echo esc_html__('Code', 'safecontracts'); ?><input class="widefat" name="code" maxlength="100" required></label></p><?php else : ?><p><strong><?php echo esc_html__('Stable code', 'safecontracts'); ?>:</strong> <code><?php echo esc_html((string) $selected['code']); ?></code></p><?php endif; ?>
            <p><label><?php echo esc_html__('Push title', 'safecontracts'); ?><input class="widefat" name="title_template" maxlength="191" required value="<?php echo esc_attr((string) ($selected['title_template'] ?? '')); ?>"></label></p>
            <p><label><?php echo esc_html__('Push body', 'safecontracts'); ?><textarea class="widefat" name="body_template" rows="4" required><?php echo esc_textarea((string) ($selected['body_template'] ?? '')); ?></textarea></label></p>
            <p><label><?php echo esc_html__('Email subject', 'safecontracts'); ?><input class="widefat" name="email_subject_template" maxlength="191" required value="<?php echo esc_attr((string) ($selected['email_subject_template'] ?? $selected['title_template'] ?? '')); ?>"></label></p>
            <p><label><?php echo esc_html__('Email body', 'safecontracts'); ?><textarea class="widefat" name="email_body_template" rows="6" required><?php echo esc_textarea((string) ($selected['email_body_template'] ?? $selected['body_template'] ?? '')); ?></textarea></label></p>
            <p><label><?php echo esc_html__('Notification icon', 'safecontracts'); ?><select class="widefat" name="icon_key"><?php foreach (NotificationTemplate::allowedIconKeys() as $icon) : ?><option value="<?php echo esc_attr($icon); ?>" <?php selected((string) ($selected['icon_key'] ?? 'contract_due'), $icon); ?>><?php echo esc_html(ucwords(str_replace('_', ' ', $icon))); ?></option><?php endforeach; ?></select></label></p>
            <p><label class="safecontracts-check-row"><input type="checkbox" name="is_active" value="1" <?php checked($selected === null || ! empty($selected['is_active'])); ?>><?php echo esc_html__('Template active', 'safecontracts'); ?></label></p>
            <p class="description"><?php echo esc_html__('Supported placeholders:', 'safecontracts'); ?> <code><?php echo esc_html(implode(', ', array_map(static fn (string $key): string => '{{' . $key . '}}', NotificationTemplate::allowedPlaceholders()))); ?></code></p>
            <?php submit_button($selected ? __('Save template', 'safecontracts') : __('Add template', 'safecontracts')); ?>
        </form>
        <?php
    }

    /** @return list<array{id:int,label:string}> */
    private static function users(): array
    {
        $rows = get_users(['fields' => ['ID', 'display_name', 'user_email'], 'orderby' => 'display_name', 'order' => 'ASC']);
        $result = [];
        foreach (is_array($rows) ? $rows : [] as $user) {
            if (! is_object($user)) {
                continue;
            }
            $id = (int) ($user->ID ?? 0);
            if ($id <= 0) {
                continue;
            }
            $name = trim((string) ($user->display_name ?? ''));
            $email = trim((string) ($user->user_email ?? ''));
            $result[] = ['id' => $id, 'label' => ($name !== '' ? $name : '#' . $id) . ($email !== '' ? ' — ' . $email : '')];
        }
        return $result;
    }

    private static function selectedRule(NotificationRuleService $service): ?array
    {
        $code = isset($_GET['edit_rule']) && is_scalar($_GET['edit_rule']) ? sanitize_key((string) $_GET['edit_rule']) : '';
        if ($code === '') {
            return null;
        }
        try {
            return $service->findByCode($code);
        } catch (Throwable) {
            return null;
        }
    }

    private static function selectedTemplate(NotificationTemplateRepository $repository): ?array
    {
        $code = isset($_GET['edit_template']) && is_scalar($_GET['edit_template']) ? sanitize_key((string) $_GET['edit_template']) : '';
        if ($code === '') {
            return null;
        }
        try {
            return $repository->findByCode($code);
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string,mixed> $rule */
    private static function recipientSummary(array $rule): string
    {
        $parts = [];
        foreach (is_array($rule['recipient_roles'] ?? null) ? $rule['recipient_roles'] : [] as $role) {
            $parts[] = ucwords(str_replace(['safecontracts_', '_'], ['', ' '], (string) $role));
        }
        if (! empty($rule['target_assigned_accountant'])) {
            $parts[] = __('Assigned Accountant', 'safecontracts');
        }
        $specific = is_array($rule['recipient_user_ids'] ?? null) ? count($rule['recipient_user_ids']) : 0;
        if ($specific > 0) {
            $parts[] = sprintf(__('%d selected user(s)', 'safecontracts'), $specific);
        }
        return $parts !== [] ? implode(', ', $parts) : __('None', 'safecontracts');
    }

    /** @param array<string,mixed> $rule */
    private static function channelSummary(array $rule): string
    {
        $channels = [];
        if (! empty($rule['push_enabled'])) {
            $channels[] = 'Push';
        }
        if (! empty($rule['email_enabled'])) {
            $channels[] = 'Email';
        }
        return $channels !== [] ? implode(' + ', $channels) : '—';
    }

    /** @return list<mixed> */
    private static function arrayInput(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private static function requireManage(): void
    {
        if (! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            wp_die(__('You do not have permission to manage notifications.', 'safecontracts'));
        }
    }

    private static function redirect(string $status): never
    {
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'safecontracts_status' => $status], admin_url('admin.php')));
        exit;
    }

    private static function notice(): void
    {
        $status = isset($_GET['safecontracts_status']) && is_scalar($_GET['safecontracts_status']) ? sanitize_key((string) $_GET['safecontracts_status']) : '';
        $message = match ($status) {
            'rule_saved' => __('Notification rule saved.', 'safecontracts'),
            'rule_invalid' => __('Notification rule could not be saved. Check recipients, channels and timing.', 'safecontracts'),
            'template_saved' => __('Notification template saved.', 'safecontracts'),
            'template_invalid' => __('Notification template could not be saved.', 'safecontracts'),
            'email_saved' => __('Email notification settings saved.', 'safecontracts'),
            'email_invalid' => __('Email notification settings are invalid.', 'safecontracts'),
            'direct_sent' => __('Direct notification dispatch completed. Review the delivery log for each channel result.', 'safecontracts'),
            'direct_failed' => __('Direct notification could not be dispatched.', 'safecontracts'),
            'suppression_saved' => __('Notification suppression updated.', 'safecontracts'),
            'suppression_invalid' => __('Notification suppression could not be updated.', 'safecontracts'),
            default => '',
        };
        if ($message !== '') {
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }
}
