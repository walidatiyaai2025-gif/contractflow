<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\CapabilityPresentation;
use SafeContracts\Roles\RoleRegistrar;
use Throwable;

final class UsersRolesPage
{
    public const SLUG = 'safecontracts-users-roles';
    public const SAVE_CAPABILITIES_ACTION = 'safecontracts_save_role_capabilities';
    public const ASSIGN_ROLE_ACTION = 'safecontracts_assign_user_role';

    public static function register(): void
    {
        add_submenu_page(AdminShell::SLUG, __('Users & Roles', 'safecontracts'), __('Users & Roles', 'safecontracts'), Capabilities::MANAGE_USERS, self::SLUG, [self::class, 'render']);
    }

    public static function handleSaveCapabilities(): void
    {
        self::requireManage();
        check_admin_referer(self::SAVE_CAPABILITIES_ACTION);
        $status = 'role_saved';
        try {
            $slug = sanitize_key((string) ($_POST['role_slug'] ?? ''));
            if (! array_key_exists($slug, self::roleDefinitions())) {
                throw new \InvalidArgumentException('Unsupported Safe Contracts role.');
            }
            $role = get_role($slug);
            if (! is_object($role) || ! method_exists($role, 'add_cap') || ! method_exists($role, 'remove_cap')) {
                throw new \RuntimeException('Safe Contracts role is unavailable.');
            }
            $selected = is_array($_POST['capabilities'] ?? null) ? array_map('sanitize_key', $_POST['capabilities']) : [];
            $allowed = array_flip(Capabilities::all());
            foreach ($selected as $capability) {
                if (! isset($allowed[$capability])) {
                    throw new \InvalidArgumentException('Unsupported Safe Contracts capability.');
                }
            }
            foreach (Capabilities::all() as $capability) {
                if (in_array($capability, $selected, true)) {
                    $role->add_cap($capability);
                } else {
                    $role->remove_cap($capability);
                }
            }
            do_action('safecontracts_role_capabilities_changed', $slug, $selected, get_current_user_id());
        } catch (Throwable) {
            $status = 'role_invalid';
        }
        self::redirect($status);
    }

    public static function handleAssignRole(): void
    {
        self::requireManage();
        check_admin_referer(self::ASSIGN_ROLE_ACTION);
        $status = 'user_role_saved';
        try {
            $userId = absint($_POST['user_id'] ?? 0);
            $slug = sanitize_key((string) ($_POST['role_slug'] ?? ''));
            if ($userId <= 0 || ($slug !== '' && ! array_key_exists($slug, self::roleDefinitions()))) {
                throw new \InvalidArgumentException('User or Safe Contracts role is invalid.');
            }
            $user = get_userdata($userId);
            if (! is_object($user) || ! method_exists($user, 'remove_role') || ! method_exists($user, 'add_role')) {
                throw new \InvalidArgumentException('WordPress user was not found.');
            }
            foreach (array_keys(self::roleDefinitions()) as $safeRole) {
                $user->remove_role($safeRole);
            }
            if ($slug !== '') {
                $user->add_role($slug);
            }
            do_action('safecontracts_user_role_changed', $userId, $slug, get_current_user_id());
        } catch (Throwable) {
            $status = 'user_role_invalid';
        }
        self::redirect($status);
    }

    public static function render(): void
    {
        self::requireManage();
        $definitions = self::roleDefinitions();
        $allUsers = get_users(['orderby' => 'display_name', 'order' => 'ASC']);
        $allUsers = is_array($allUsers) ? $allUsers : [];
        $assignedCount = count(array_filter($allUsers, static fn (mixed $user): bool => self::safeRoleForUser($user) !== ''));

        $search = isset($_GET['user_search']) && is_scalar($_GET['user_search']) ? sanitize_text_field((string) $_GET['user_search']) : '';
        $roleFilter = isset($_GET['role_filter']) && is_scalar($_GET['role_filter']) ? sanitize_key((string) $_GET['role_filter']) : '';
        if ($roleFilter !== '' && $roleFilter !== 'none' && ! array_key_exists($roleFilter, $definitions)) {
            $roleFilter = '';
        }
        $directoryUsers = array_values(array_filter($allUsers, static function (mixed $user) use ($search, $roleFilter): bool {
            $role = self::safeRoleForUser($user);
            if ($roleFilter === 'none' && $role !== '') {
                return false;
            }
            if ($roleFilter !== '' && $roleFilter !== 'none' && $role !== $roleFilter) {
                return false;
            }
            if ($search === '') {
                return true;
            }
            return stripos(self::userLabel($user) . ' ' . $role, $search) !== false;
        }));
        $perPage = 25;
        $currentPage = max(1, absint($_GET['paged'] ?? 1));
        $totalPages = max(1, (int) ceil(count($directoryUsers) / $perPage));
        $currentPage = min($currentPage, $totalPages);
        $visibleUsers = array_slice($directoryUsers, ($currentPage - 1) * $perPage, $perPage);
        ?>
        <div class="wrap safecontracts-settings safecontracts-users-roles" dir="auto">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Authorization directory', 'safecontracts'); ?></p>
                    <h1><?php echo esc_html__('Users & Roles', 'safecontracts'); ?></h1>
                    <p><?php echo esc_html__('Manage SafeContracts role membership and business capabilities without weakening existing WordPress authorization boundaries.', 'safecontracts'); ?></p>
                </div>
            </div>
            <?php self::notice(); ?>
            <?php AdminSummaryCards::render([
                ['label' => __('Safe Contracts roles', 'safecontracts'), 'value' => count($definitions)],
                ['label' => __('WordPress users', 'safecontracts'), 'value' => count($allUsers)],
                ['label' => __('Users with SafeContracts role', 'safecontracts'), 'value' => $assignedCount],
                ['label' => __('Editable SafeContracts capabilities', 'safecontracts'), 'value' => count(Capabilities::all())],
            ]); ?>

            <section class="safecontracts-admin-card safecontracts-settings-card">
                <div class="safecontracts-section-heading"><div><h2><?php echo esc_html__('Assign SafeContracts role', 'safecontracts'); ?></h2><p class="description"><?php echo esc_html__('Only SafeContracts role membership changes here. Other WordPress roles are preserved.', 'safecontracts'); ?></p></div></div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ASSIGN_ROLE_ACTION); ?>"><?php wp_nonce_field(self::ASSIGN_ROLE_ACTION); ?>
                    <div class="safecontracts-field-row">
                        <label><?php echo esc_html__('User', 'safecontracts'); ?><select name="user_id" required><option value=""><?php echo esc_html__('Select user', 'safecontracts'); ?></option><?php foreach ($allUsers as $user) : ?><option value="<?php echo esc_attr((string) ($user->ID ?? 0)); ?>"><?php echo esc_html(self::userLabel($user)); ?></option><?php endforeach; ?></select></label>
                        <label><?php echo esc_html__('Safe Contracts role', 'safecontracts'); ?><select name="role_slug"><option value=""><?php echo esc_html__('No Safe Contracts role', 'safecontracts'); ?></option><?php foreach ($definitions as $slug => $label) : ?><option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <?php submit_button(__('Save user role', 'safecontracts')); ?>
                </form>
            </section>

            <section class="safecontracts-admin-card safecontracts-table-card">
                <div class="safecontracts-section-heading"><div><h2><?php echo esc_html__('User directory', 'safecontracts'); ?></h2><p class="description"><?php echo esc_html__('This table reflects current WordPress users and their current SafeContracts role membership.', 'safecontracts'); ?></p></div></div>
                <form class="safecontracts-filter-bar" method="get">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                    <label><?php echo esc_html__('Search users', 'safecontracts'); ?><input type="search" name="user_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr__('Name or email', 'safecontracts'); ?>"></label>
                    <label><?php echo esc_html__('SafeContracts role', 'safecontracts'); ?><select name="role_filter"><option value=""><?php echo esc_html__('All', 'safecontracts'); ?></option><?php foreach ($definitions as $slug => $label) : ?><option value="<?php echo esc_attr($slug); ?>" <?php selected($roleFilter, $slug); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?><option value="none" <?php selected($roleFilter, 'none'); ?>><?php echo esc_html__('No SafeContracts role', 'safecontracts'); ?></option></select></label>
                    <button class="button button-primary" type="submit"><?php echo esc_html__('Apply filters', 'safecontracts'); ?></button>
                    <a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Clear', 'safecontracts'); ?></a>
                </form>
                <table class="widefat striped"><thead><tr><th><?php echo esc_html__('User', 'safecontracts'); ?></th><th><?php echo esc_html__('SafeContracts role', 'safecontracts'); ?></th><th><?php echo esc_html__('State', 'safecontracts'); ?></th></tr></thead><tbody>
                    <?php if ($visibleUsers === []) : ?><tr><td colspan="3"><?php echo esc_html__('No users match the selected filters.', 'safecontracts'); ?></td></tr><?php endif; ?>
                    <?php foreach ($visibleUsers as $user) : $safeRole = self::safeRoleForUser($user); ?>
                        <tr><td><strong><?php echo esc_html(self::userLabel($user)); ?></strong></td><td><?php echo $safeRole !== '' ? esc_html((string) ($definitions[$safeRole] ?? $safeRole)) : '—'; ?></td><td><span class="safecontracts-state-chip <?php echo $safeRole !== '' ? 'is-success' : 'is-warning'; ?>"><?php echo $safeRole !== '' ? esc_html__('Assigned', 'safecontracts') : esc_html__('Not assigned', 'safecontracts'); ?></span></td></tr>
                    <?php endforeach; ?>
                </tbody></table>
                <?php if ($totalPages > 1) : ?><div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post((string) paginate_links(['base' => add_query_arg(['page' => self::SLUG, 'user_search' => $search, 'role_filter' => $roleFilter, 'paged' => '%#%'], admin_url('admin.php')), 'format' => '', 'current' => $currentPage, 'total' => $totalPages, 'prev_text' => '‹', 'next_text' => '›'])); ?></div></div><?php endif; ?>
            </section>

            <div class="safecontracts-role-grid">
            <?php foreach ($definitions as $slug => $label) : ?>
                <?php
                $role = get_role($slug);
                $grants = is_object($role) && isset($role->capabilities) && is_array($role->capabilities) ? $role->capabilities : [];
                $users = get_users(['role' => $slug]);
                $grantedCount = count(array_filter(Capabilities::all(), static fn (string $capability): bool => ! empty($grants[$capability])));
                ?>
                <section class="safecontracts-admin-card">
                    <div class="safecontracts-section-heading"><div><h2><?php echo esc_html($label); ?></h2><p><?php echo esc_html(sprintf(__('%d users · %d permissions', 'safecontracts'), count($users), $grantedCount)); ?></p></div></div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_CAPABILITIES_ACTION); ?>"><input type="hidden" name="role_slug" value="<?php echo esc_attr($slug); ?>"><?php wp_nonce_field(self::SAVE_CAPABILITIES_ACTION); ?>
                        <h3><?php echo esc_html__('Role permissions', 'safecontracts'); ?></h3>
                        <p class="description"><?php echo esc_html__('Choose only supported SafeContracts business permissions. Internal capability codes are intentionally hidden.', 'safecontracts'); ?></p>
                        <div class="safecontracts-capability-list">
                        <?php foreach (Capabilities::all() as $capability) : ?>
                            <label class="safecontracts-check-row">
                                <input type="checkbox" name="capabilities[]" value="<?php echo esc_attr($capability); ?>" <?php checked(! empty($grants[$capability])); ?>>
                                <span><strong><?php echo esc_html(CapabilityPresentation::label($capability)); ?></strong><br><small class="description"><?php echo esc_html(CapabilityPresentation::description($capability)); ?></small></span>
                            </label>
                        <?php endforeach; ?>
                        </div>
                        <?php submit_button(__('Save role permissions', 'safecontracts'), 'secondary'); ?>
                    </form>
                    <?php if ($users !== []) : ?><h3><?php echo esc_html__('Members', 'safecontracts'); ?></h3><ul><?php foreach ($users as $user) : ?><li><?php echo esc_html(self::userLabel($user)); ?></li><?php endforeach; ?></ul><?php endif; ?>
                </section>
            <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /** @return array<string,string> */
    private static function roleDefinitions(): array
    {
        return [
            RoleRegistrar::SYSTEM_ADMIN => __('System Administrator', 'safecontracts'),
            RoleRegistrar::MANAGER => __('Manager', 'safecontracts'),
            RoleRegistrar::ACCOUNTANT => __('Accountant', 'safecontracts'),
            RoleRegistrar::VIEWER => __('Viewer', 'safecontracts'),
        ];
    }

    private static function safeRoleForUser(mixed $user): string
    {
        if (! is_object($user)) {
            return '';
        }
        $roles = isset($user->roles) && is_array($user->roles) ? $user->roles : [];
        if ($roles === [] && isset($user->ID)) {
            $full = get_userdata((int) $user->ID);
            $roles = is_object($full) && isset($full->roles) && is_array($full->roles) ? $full->roles : [];
        }
        foreach (array_keys(self::roleDefinitions()) as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }
        return '';
    }

    private static function userLabel(mixed $user): string
    {
        if (is_object($user)) {
            $name = isset($user->display_name) ? trim((string) $user->display_name) : '';
            $email = isset($user->user_email) ? trim((string) $user->user_email) : '';
        } elseif (is_array($user)) {
            $name = trim((string) ($user['display_name'] ?? $user['name'] ?? ''));
            $email = trim((string) ($user['user_email'] ?? $user['email'] ?? ''));
        } else {
            $name = '';
            $email = '';
        }

        if ($name !== '' && $email !== '') {
            return $name . ' — ' . $email;
        }
        if ($name !== '') {
            return $name;
        }
        if ($email !== '') {
            return $email;
        }
        return __('Unnamed WordPress user', 'safecontracts');
    }

    private static function requireManage(): void
    {
        if (! current_user_can(Capabilities::MANAGE_USERS)) {
            wp_die(__('You do not have permission to manage Safe Contracts users and roles.', 'safecontracts'));
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
            'role_saved' => __('Role permissions saved.', 'safecontracts'),
            'role_invalid' => __('Role permissions could not be saved.', 'safecontracts'),
            'user_role_saved' => __('User Safe Contracts role saved.', 'safecontracts'),
            'user_role_invalid' => __('User Safe Contracts role could not be saved.', 'safecontracts'),
            default => '',
        };
        if ($message !== '') {
            $class = in_array($status, ['role_invalid', 'user_role_invalid'], true) ? 'notice-error' : 'notice-success';
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }
}
