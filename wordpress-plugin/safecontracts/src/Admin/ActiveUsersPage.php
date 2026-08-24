<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Presence\PresenceService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Translations\TranslationCatalog;

final class ActiveUsersPage
{
    public const SLUG = 'safecontracts-active-users';

    public static function register(): void
    {
        add_submenu_page(
            AdminShell::SLUG,
            __('Active Users', 'safecontracts'),
            __('Active Users', 'safecontracts'),
            Capabilities::MANAGE_USERS,
            self::SLUG,
            [self::class, 'render']
        );
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_USERS)) {
            wp_die(__('You do not have permission to view active users.', 'safecontracts'));
        }

        $allUsers = self::users();
        $appActive = count(array_filter($allUsers, static fn (array $row): bool => $row['mobile_active']));
        $adminActive = count(array_filter($allUsers, static fn (array $row): bool => $row['admin_active']));
        $either = count(array_filter($allUsers, static fn (array $row): bool => $row['mobile_active'] || $row['admin_active']));

        $search = isset($_GET['user_search']) && is_scalar($_GET['user_search']) ? sanitize_text_field((string) $_GET['user_search']) : '';
        $activity = isset($_GET['activity']) && is_scalar($_GET['activity']) ? sanitize_key((string) $_GET['activity']) : '';
        if (! in_array($activity, ['', 'mobile', 'admin', 'either', 'inactive'], true)) {
            $activity = '';
        }
        $users = array_values(array_filter($allUsers, static function (array $row) use ($search, $activity): bool {
            $matchesActivity = match ($activity) {
                'mobile' => $row['mobile_active'],
                'admin' => $row['admin_active'],
                'either' => $row['mobile_active'] || $row['admin_active'],
                'inactive' => ! $row['mobile_active'] && ! $row['admin_active'],
                default => true,
            };
            if (! $matchesActivity) {
                return false;
            }
            if ($search === '') {
                return true;
            }
            $haystack = $row['name'] . ' ' . $row['email'] . ' ' . $row['roles'];
            return stripos($haystack, $search) !== false;
        }));

        $perPage = 25;
        $currentPage = max(1, absint($_GET['paged'] ?? 1));
        $totalPages = max(1, (int) ceil(count($users) / $perPage));
        $currentPage = min($currentPage, $totalPages);
        $visibleUsers = array_slice($users, ($currentPage - 1) * $perPage, $perPage);
        ?>
        <div class="wrap safecontracts-settings safecontracts-active-users" dir="auto">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Presence', 'safecontracts'); ?></p>
                    <h1><?php echo esc_html__('Active Users', 'safecontracts'); ?></h1>
                    <p><?php echo esc_html(self::text('Recent activity is based only on authenticated SafeContracts app/admin presence signals recorded by the system. It is not an inferred online-presence indicator.', 'يعتمد النشاط الحديث فقط على إشارات الحضور الموثقة من تطبيق وإدارة SafeContracts والمسجلة بواسطة النظام، ولا يمثل استنتاجًا لحالة اتصال المستخدم بالإنترنت.')); ?></p>
                </div>
            </div>
            <?php AdminSummaryCards::render([
                ['label' => self::text('Recently active on app', 'نشط مؤخرًا على التطبيق'), 'value' => $appActive, 'detail' => __('Seen in the last 5 minutes', 'safecontracts')],
                ['label' => self::text('Recently active on dashboard', 'نشط مؤخرًا على لوحة التحكم'), 'value' => $adminActive, 'detail' => __('Seen in the last 5 minutes', 'safecontracts')],
                ['label' => self::text('Recently active anywhere', 'نشط مؤخرًا في أي واجهة'), 'value' => $either],
                ['label' => __('Safe Contracts users', 'safecontracts'), 'value' => count($allUsers)],
            ]); ?>

            <form class="safecontracts-filter-bar" method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                <label><?php echo esc_html(self::text('Search users', 'بحث المستخدمين')); ?><input type="search" name="user_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr(self::text('Name, email or role', 'الاسم أو البريد أو الدور')); ?>"></label>
                <label><?php echo esc_html(self::text('Recent activity', 'النشاط الحديث')); ?><select name="activity"><option value=""><?php echo esc_html(self::text('All users', 'كل المستخدمين')); ?></option><option value="mobile" <?php selected($activity, 'mobile'); ?>><?php echo esc_html__('App', 'safecontracts'); ?></option><option value="admin" <?php selected($activity, 'admin'); ?>><?php echo esc_html__('Dashboard', 'safecontracts'); ?></option><option value="either" <?php selected($activity, 'either'); ?>><?php echo esc_html(self::text('App or dashboard', 'التطبيق أو لوحة التحكم')); ?></option><option value="inactive" <?php selected($activity, 'inactive'); ?>><?php echo esc_html(self::text('No recent activity', 'لا يوجد نشاط حديث')); ?></option></select></label>
                <button class="button button-primary" type="submit"><?php echo esc_html__('Apply filters', 'safecontracts'); ?></button>
                <a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html(self::text('Clear', 'مسح')); ?></a>
            </form>

            <section class="safecontracts-admin-card safecontracts-table-card">
                <div class="safecontracts-section-heading"><div><h2><?php echo esc_html(self::text('User activity', 'نشاط المستخدم')); ?></h2><p class="description"><?php echo esc_html(self::text('WordPress core does not provide an authoritative last-login timestamp here, so this screen shows only recorded SafeContracts activity. No session token or device-token value is exposed.', 'لا يوفر ووردبريس هنا وقت آخر تسجيل دخول موثوقًا، لذلك تعرض هذه الشاشة نشاط SafeContracts المسجل فقط. لا يتم عرض أي رمز جلسة أو رمز جهاز.')); ?></p></div></div>
                <table class="widefat striped"><thead><tr><th><?php echo esc_html__('User', 'safecontracts'); ?></th><th><?php echo esc_html(self::text('Role', 'الدور')); ?></th><th><?php echo esc_html(self::text('App activity', 'نشاط التطبيق')); ?></th><th><?php echo esc_html(self::text('Dashboard activity', 'نشاط لوحة التحكم')); ?></th><th><?php echo esc_html(self::text('Last observed activity', 'آخر نشاط مسجل')); ?></th></tr></thead><tbody>
                    <?php if ($visibleUsers === []) : ?><tr><td colspan="5"><?php echo esc_html(self::text('No Safe Contracts users match the selected filters.', 'لا يوجد مستخدمو Safe Contracts يطابقون الفلاتر المحددة.')); ?></td></tr><?php endif; ?>
                    <?php foreach ($visibleUsers as $row) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($row['name']); ?></strong><br><small dir="ltr"><?php echo esc_html($row['email']); ?></small></td>
                            <td><?php echo esc_html($row['roles'] !== '' ? $row['roles'] : self::text('No role', 'بدون دور')); ?></td>
                            <td><span class="safecontracts-state-chip <?php echo $row['mobile_active'] ? 'is-success' : ''; ?>"><?php echo esc_html($row['mobile_active'] ? self::text('Recently active', 'نشط مؤخرًا') : self::text('No recent activity', 'لا يوجد نشاط حديث')); ?></span><br><small><?php echo esc_html(self::formatTime($row['mobile_seen'])); ?></small></td>
                            <td><span class="safecontracts-state-chip <?php echo $row['admin_active'] ? 'is-success' : ''; ?>"><?php echo esc_html($row['admin_active'] ? self::text('Recently active', 'نشط مؤخرًا') : self::text('No recent activity', 'لا يوجد نشاط حديث')); ?></span><br><small><?php echo esc_html(self::formatTime($row['admin_seen'])); ?></small></td>
                            <td><?php echo esc_html(self::formatTime(max($row['mobile_seen'], $row['admin_seen']))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody></table>
                <?php if ($totalPages > 1) : ?><div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post((string) paginate_links(['base' => add_query_arg(['page' => self::SLUG, 'user_search' => $search, 'activity' => $activity, 'paged' => '%#%'], admin_url('admin.php')), 'format' => '', 'current' => $currentPage, 'total' => $totalPages, 'prev_text' => '‹', 'next_text' => '›'])); ?></div></div><?php endif; ?>
            </section>
        </div>
        <?php
    }

    /** @return list<array{id:int,name:string,email:string,roles:string,mobile_seen:int,admin_seen:int,mobile_active:bool,admin_active:bool}> */
    private static function users(): array
    {
        $rows = get_users(['fields' => ['ID', 'display_name', 'user_email'], 'orderby' => 'display_name', 'order' => 'ASC']);
        $result = [];
        foreach (is_array($rows) ? $rows : [] as $user) {
            if (! is_object($user)) {
                continue;
            }
            $id = (int) ($user->ID ?? 0);
            if ($id <= 0 || ! user_can($id, Capabilities::ACCESS)) {
                continue;
            }
            $mobile = (int) get_user_meta($id, PresenceService::MOBILE_META, true);
            $admin = (int) get_user_meta($id, PresenceService::ADMIN_META, true);
            $fullUser = get_userdata($id);
            $roles = is_object($fullUser) && isset($fullUser->roles) && is_array($fullUser->roles)
                ? array_map(static fn (mixed $role): string => ucwords(str_replace('_', ' ', (string) $role)), $fullUser->roles)
                : [];
            $result[] = [
                'id' => $id,
                'name' => trim((string) ($user->display_name ?? '')) ?: '#' . $id,
                'email' => (string) ($user->user_email ?? ''),
                'roles' => implode(', ', $roles),
                'mobile_seen' => $mobile,
                'admin_seen' => $admin,
                'mobile_active' => PresenceService::isActive($mobile),
                'admin_active' => PresenceService::isActive($admin),
            ];
        }
        usort($result, static function (array $a, array $b): int {
            $aActive = ($a['mobile_active'] || $a['admin_active']) ? 1 : 0;
            $bActive = ($b['mobile_active'] || $b['admin_active']) ? 1 : 0;
            return $bActive <=> $aActive ?: strcasecmp($a['name'], $b['name']);
        });
        return $result;
    }

    private static function formatTime(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '—';
        }
        if (function_exists('wp_date')) {
            return wp_date('Y-m-d H:i:s', $timestamp);
        }
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private static function text(string $english, string $arabic): string
    {
        return TranslationCatalog::currentLanguage() === 'ar' ? $arabic : __($english, 'safecontracts');
    }
}
