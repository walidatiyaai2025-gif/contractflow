<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Notifications\DeliveryLogRepository;
use SafeContracts\Notifications\DirectNotificationService;
use SafeContracts\Notifications\EmailSettings;
use SafeContracts\Notifications\NotificationInboxState;
use SafeContracts\Notifications\NotificationRuleService;
use SafeContracts\Notifications\NotificationSuppressionRepository;
use SafeContracts\Notifications\NotificationTemplateService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Translations\TranslationCatalog;
use Throwable;

final class NotificationCenterPage
{
    public const SLUG = 'safecontracts-notification-center';
    public const SAVE_RULE_ACTION = 'safecontracts_center_save_rule';
    public const SAVE_TEMPLATE_ACTION = 'safecontracts_center_save_template';
    public const SAVE_EMAIL_ACTION = 'safecontracts_center_save_email';
    public const DIRECT_SEND_ACTION = 'safecontracts_center_direct_send';
    public const SUPPRESSION_ACTION = 'safecontracts_center_suppression';
    public const READ_ACTION = 'safecontracts_center_mark_read';

    public static function register(): void
    {
        add_submenu_page(
            AdminShell::SLUG,
            self::text('Notification Center', 'مركز الإشعارات'),
            self::text('Notification Center', 'مركز الإشعارات'),
            Capabilities::MANAGE_NOTIFICATIONS,
            self::SLUG,
            [self::class, 'render']
        );
        add_action('admin_enqueue_scripts', [self::class, 'enqueueOwnedScreenStyles'], 30);
    }

    public static function enqueueOwnedScreenStyles(): void
    {
        $page = sanitize_key((string) ($_GET['page'] ?? ''));
        if (! in_array($page, [
            self::SLUG,
            NotificationsPage::SLUG,
            NotificationSchedulePage::SLUG,
            NotificationSettingsPage::SLUG,
            EmailSettingsPage::SLUG,
            ActiveUsersPage::SLUG,
            UsersRolesPage::SLUG,
            FirebaseSettingsPage::SLUG,
            MobileConfigurationPage::SLUG,
            TranslationsPage::SLUG,
            UserGuidePage::SLUG,
        ], true)) {
            return;
        }

        wp_add_inline_style(
            AdminShell::STYLE_HANDLE,
            '.safecontracts-settings .safecontracts-summary-cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:12px 0 18px}' .
            '.safecontracts-settings .safecontracts-summary-card{box-sizing:border-box;display:flex;min-width:0;min-height:118px;flex-direction:column;gap:6px;padding:17px 18px;border:1px solid var(--sc-ui-line,#e1d4c7);border-radius:var(--sc-ui-radius-md,14px);background:#fff;box-shadow:var(--sc-ui-shadow-xs,0 4px 14px rgba(26,43,58,.06))}' .
            '.safecontracts-settings .safecontracts-summary-card__label{color:var(--sc-ui-ink-500,#687481);font-size:12px;font-weight:800;line-height:1.4}' .
            '.safecontracts-settings .safecontracts-summary-card__value{color:var(--sc-ui-navy-950,#102d46);font-size:29px;line-height:1.15;font-weight:800;overflow-wrap:anywhere}' .
            '.safecontracts-settings .safecontracts-summary-card__detail{margin-top:auto;color:var(--sc-ui-ink-500,#687481);font-size:11px;line-height:1.45}' .
            '@media(max-width:1180px){.safecontracts-settings .safecontracts-summary-cards{grid-template-columns:repeat(2,minmax(0,1fr))}}' .
            '@media(max-width:782px){.safecontracts-settings .safecontracts-summary-cards{grid-template-columns:1fr}.safecontracts-settings .safecontracts-summary-card{min-height:0}}'
        );
    }

    public static function registerInboxActions(): void
    {
        add_action('admin_post_' . self::READ_ACTION, [self::class, 'handleRead']);
    }

    public static function handleRead(): void
    {
        self::requireManage();
        check_admin_referer(self::READ_ACTION);
        $userId = get_current_user_id();
        $delivery = new DeliveryLogRepository();
        $state = new NotificationInboxState();
        $markAll = isset($_POST['mark_all']) && (string) $_POST['mark_all'] === '1';
        if ($markAll) {
            $ids = [];
            foreach ($delivery->recent(500) as $row) {
                if ((int) ($row['user_id'] ?? 0) === $userId && (string) ($row['status'] ?? '') === 'sent') {
                    $ids[] = (int) ($row['id'] ?? 0);
                }
            }
            $state->markManyRead($userId, $ids);
        } else {
            $id = absint($_POST['delivery_id'] ?? 0);
            if ($id > 0 && $delivery->hasSentForUser($id, $userId)) {
                $state->markRead($userId, $id);
            }
        }
        self::redirect('read_updated');
    }

    public static function handleSaveRule(): void
    {
        self::requireManage();
        check_admin_referer(self::SAVE_RULE_ACTION);
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
            self::redirect('rule_saved', NotificationSettingsPage::SLUG);
        } catch (Throwable $error) {
            unset($error);
            self::redirect('rule_invalid', NotificationSettingsPage::SLUG);
        }
    }

    public static function handleSaveTemplate(): void
    {
        self::requireManage();
        check_admin_referer(self::SAVE_TEMPLATE_ACTION);
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
            self::redirect('template_saved', NotificationSettingsPage::SLUG);
        } catch (Throwable $error) {
            unset($error);
            self::redirect('template_invalid', NotificationSettingsPage::SLUG);
        }
    }

    public static function handleSaveEmail(): void
    {
        self::requireManage();
        check_admin_referer(self::SAVE_EMAIL_ACTION);
        $status = 'saved';
        try {
            (new EmailSettings())->save([
                'enabled' => isset($_POST['enabled']),
                'from_name' => (string) ($_POST['from_name'] ?? ''),
                'from_address' => (string) ($_POST['from_address'] ?? ''),
            ]);
        } catch (Throwable $error) {
            unset($error);
            $status = 'invalid';
        }
        self::redirect('email_' . $status, EmailSettingsPage::SLUG);
    }

    public static function handleDirectSend(): void
    {
        self::requireManage();
        check_admin_referer(self::DIRECT_SEND_ACTION);
        try {
            (new DirectNotificationService())->send(
                absint($_POST['user_id'] ?? 0),
                (string) ($_POST['title'] ?? ''),
                (string) ($_POST['body'] ?? ''),
                isset($_POST['push_enabled']),
                isset($_POST['email_enabled']),
                sanitize_key((string) ($_POST['icon_key'] ?? 'safe_contracts'))
            );
            self::redirect('direct_sent');
        } catch (Throwable $error) {
            unset($error);
            self::redirect('direct_failed');
        }
    }

    public static function handleSuppression(): void
    {
        self::requireManage();
        check_admin_referer(self::SUPPRESSION_ACTION);
        try {
            (new NotificationSuppressionRepository())->set(
                sanitize_key((string) ($_POST['scope_type'] ?? '')),
                absint($_POST['scope_id'] ?? 0),
                isset($_POST['suppressed']) && (string) $_POST['suppressed'] === '1',
                (string) ($_POST['reason'] ?? ''),
                get_current_user_id()
            );
            self::redirect('suppression_saved');
        } catch (Throwable $error) {
            unset($error);
            self::redirect('suppression_invalid');
        }
    }

    public static function render(): void
    {
        self::requireManage();
        $filters = DashboardFilters::normalize($_GET);
        $channel = isset($_GET['channel']) && is_scalar($_GET['channel']) ? sanitize_key((string) $_GET['channel']) : '';
        if (! in_array($channel, ['', 'push', 'email'], true)) {
            $channel = '';
        }
        $readFilter = isset($_GET['read_state']) && is_scalar($_GET['read_state']) ? sanitize_key((string) $_GET['read_state']) : '';
        if (! in_array($readFilter, ['', 'unread', 'read'], true)) {
            $readFilter = '';
        }

        $userId = get_current_user_id();
        $state = new NotificationInboxState();
        $readIds = array_flip($state->readIds($userId));
        $rows = empty($filters['date_range_error'])
            ? (new DeliveryLogRepository())->recent(500, $filters['date_from'], $filters['date_to'])
            : [];
        $rows = array_values(array_filter($rows, static function (array $row) use ($userId, $channel, $readFilter, $readIds): bool {
            if ((int) ($row['user_id'] ?? 0) !== $userId || (string) ($row['status'] ?? '') !== 'sent') {
                return false;
            }
            if ($channel !== '' && (string) ($row['channel'] ?? '') !== $channel) {
                return false;
            }
            $isRead = isset($readIds[(int) ($row['id'] ?? 0)]);
            return $readFilter === '' || ($readFilter === 'read' ? $isRead : ! $isRead);
        }));

        $total = count($rows);
        $unread = count(array_filter($rows, static fn (array $row): bool => ! isset($readIds[(int) ($row['id'] ?? 0)])));
        $read = $total - $unread;
        $perPage = 25;
        $currentPage = max(1, absint($_GET['paged'] ?? 1));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $currentPage = min($currentPage, $totalPages);
        $visibleRows = array_slice($rows, ($currentPage - 1) * $perPage, $perPage);
        ?>
        <div class="wrap safecontracts-settings safecontracts-notification-inbox" dir="auto">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html(self::text('Notification inbox', 'صندوق الإشعارات')); ?></p>
                    <h1><?php echo esc_html(self::text('Notification Center', 'مركز الإشعارات')); ?></h1>
                    <p><?php echo esc_html(self::text('Your server-confirmed sent notifications, with read state, channel, date and related payment context.', 'إشعاراتك المؤكد إرسالها من الخادم مع حالة القراءة والقناة والتاريخ وسياق الدفعة المرتبطة.')); ?></p>
                </div>
                <div class="safecontracts-heading-actions">
                    <a class="button" href="<?php echo esc_url(add_query_arg(['page' => NotificationSettingsPage::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html(self::text('Notification Settings', 'إعدادات الإشعارات')); ?></a>
                    <a class="button" href="<?php echo esc_url(add_query_arg(['page' => EmailSettingsPage::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html(self::text('Email Settings', 'إعدادات البريد الإلكتروني')); ?></a>
                </div>
            </div>

            <?php AdminSummaryCards::render([
                ['label' => self::text('Unread', 'غير مقروء'), 'value' => $unread, 'detail' => self::text('Needs your review', 'بحاجة إلى مراجعتك')],
                ['label' => self::text('Read', 'مقروء'), 'value' => $read],
                ['label' => self::text('Filtered notifications', 'الإشعارات بعد الفلترة'), 'value' => $total],
            ]); ?>

            <form class="safecontracts-filter-bar" method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                <?php AdminPeriodFilter::renderFields($filters); ?>
                <label><?php echo esc_html(self::text('Channel', 'القناة')); ?><select name="channel"><option value=""><?php echo esc_html(self::text('All channels', 'كل القنوات')); ?></option><option value="push" <?php selected($channel, 'push'); ?>>Push</option><option value="email" <?php selected($channel, 'email'); ?>>Email</option></select></label>
                <label><?php echo esc_html(self::text('Read state', 'حالة القراءة')); ?><select name="read_state"><option value=""><?php echo esc_html(self::text('All', 'الكل')); ?></option><option value="unread" <?php selected($readFilter, 'unread'); ?>><?php echo esc_html(self::text('Unread', 'غير مقروء')); ?></option><option value="read" <?php selected($readFilter, 'read'); ?>><?php echo esc_html(self::text('Read', 'مقروء')); ?></option></select></label>
                <button class="button button-primary" type="submit"><?php echo esc_html(self::text('Apply filters', 'تطبيق الفلاتر')); ?></button>
                <a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html(self::text('Clear', 'مسح')); ?></a>
            </form>

            <?php if ($rows !== []) : ?>
                <div class="safecontracts-section-heading">
                    <div><h2><?php echo esc_html(self::text('Inbox activity', 'نشاط صندوق الإشعارات')); ?></h2><p class="description"><?php echo esc_html(self::text('Delivery status is not inferred: this inbox includes only rows confirmed as sent by the delivery log. Severity is not stored in this log and is therefore not fabricated.', 'لا يتم استنتاج حالة التسليم: يعرض هذا الصندوق فقط السجلات التي يؤكد سجل التسليم إرسالها. مستوى الأهمية غير مخزن في هذا السجل ولذلك لا يتم اختلاقه.')); ?></p></div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="<?php echo esc_attr(self::READ_ACTION); ?>"><input type="hidden" name="mark_all" value="1"><?php wp_nonce_field(self::READ_ACTION); ?><button class="button" type="submit"><?php echo esc_html(self::text('Mark all as read', 'تحديد الكل كمقروء')); ?></button></form>
                </div>
                <section class="safecontracts-admin-card safecontracts-table-card">
                    <table class="widefat striped">
                        <thead><tr><th><?php echo esc_html(self::text('When', 'الوقت')); ?></th><th><?php echo esc_html(self::text('Notification', 'الإشعار')); ?></th><th><?php echo esc_html(self::text('Channel', 'القناة')); ?></th><th><?php echo esc_html(self::text('Related entity', 'العنصر المرتبط')); ?></th><th><?php echo esc_html(self::text('Read state', 'حالة القراءة')); ?></th><th><?php echo esc_html(self::text('Action', 'إجراء')); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($visibleRows as $row) : $id = (int) ($row['id'] ?? 0); $isRead = isset($readIds[$id]); ?>
                            <tr>
                                <td><?php echo esc_html((string) ($row['created_at'] ?? '')); ?></td>
                                <td><strong><code dir="ltr"><?php echo esc_html((string) ($row['template_code'] ?? '')); ?></code></strong><br><small><?php echo esc_html(self::text('Delivery confirmed', 'تم تأكيد التسليم')); ?></small></td>
                                <td><span class="safecontracts-state-chip is-success"><?php echo esc_html(strtoupper((string) ($row['channel'] ?? ''))); ?></span></td>
                                <td><?php $paymentId = (int) ($row['payment_id'] ?? 0); echo $paymentId > 0 ? esc_html(self::text('Payment', 'دفعة') . ' #' . $paymentId) : '—'; ?></td>
                                <td><span class="safecontracts-state-chip <?php echo $isRead ? 'is-success' : 'is-warning'; ?>"><?php echo esc_html($isRead ? self::text('Read', 'مقروء') : self::text('Unread', 'غير مقروء')); ?></span></td>
                                <td><?php if (! $isRead) : ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="<?php echo esc_attr(self::READ_ACTION); ?>"><input type="hidden" name="delivery_id" value="<?php echo esc_attr((string) $id); ?>"><?php wp_nonce_field(self::READ_ACTION); ?><button class="button button-small" type="submit"><?php echo esc_html(self::text('Mark read', 'تحديد كمقروء')); ?></button></form><?php else : ?>—<?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
                <?php if ($totalPages > 1) : ?>
                    <div class="tablenav"><div class="tablenav-pages">
                        <?php
                        $baseUrl = add_query_arg([
                            'page' => self::SLUG,
                            'date_from' => (string) ($filters['date_from'] ?? ''),
                            'date_to' => (string) ($filters['date_to'] ?? ''),
                            'channel' => $channel,
                            'read_state' => $readFilter,
                            'paged' => '%#%',
                        ], admin_url('admin.php'));
                        echo wp_kses_post((string) paginate_links([
                            'base' => $baseUrl,
                            'format' => '',
                            'current' => $currentPage,
                            'total' => $totalPages,
                            'prev_text' => '‹',
                            'next_text' => '›',
                        ]));
                        ?>
                    </div></div>
                <?php endif; ?>
            <?php else : ?>
                <section class="safecontracts-admin-card"><h2><?php echo esc_html(self::text('No notifications found', 'لا توجد إشعارات')); ?></h2><p><?php echo esc_html(self::text('No sent notifications match the selected filters. Change the period, channel or read state to widen the result.', 'لا توجد إشعارات مرسلة مطابقة للفلاتر المحددة. غيّر الفترة أو القناة أو حالة القراءة لتوسيع النتائج.')); ?></p></section>
            <?php endif; ?>
        </div>
        <?php
    }

    /** @return list<mixed> */
    private static function arrayInput(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private static function redirect(string $status, string $page = self::SLUG): never
    {
        wp_safe_redirect(add_query_arg(['page' => $page, 'safecontracts_status' => $status], admin_url('admin.php')));
        exit;
    }

    private static function requireManage(): void
    {
        if (! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            wp_die(__('You do not have permission to manage notifications.', 'safecontracts'));
        }
    }

    private static function text(string $english, string $arabic): string
    {
        return TranslationCatalog::currentLanguage() === 'ar' ? $arabic : __($english, 'safecontracts');
    }
}
