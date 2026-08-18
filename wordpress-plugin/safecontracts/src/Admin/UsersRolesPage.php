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
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Authorization directory', 'safecontracts'); ?></p><h1><?php echo esc_html__('Users & Roles', 'safecontracts'); ?></h1></div></div>
            <?php self::notice(); ?>
            <?php AdminSummaryCards::render([
                ['label' => __('Safe Contracts roles', 'safecontracts'), 'value' => count($definitions)],
                ['label' => __('WordPress users', 'safecontracts'), 'value' => is_array($allUsers) ? count($allUsers) : 0],
                ['label' => __('Available permissions', 'safecontracts'), 'value' => count(Capabilities::all())],
            ]); ?>

            <section class="safecontracts-admin-card safecontracts-settings-card">
                <h2><?php echo esc_html__('Assign Safe Contracts role to user', 'safecontracts'); ?></h2>
                <p class="description"><?php echo esc_html__('Choose a user and a business role from the lists below. Internal user IDs and permission codes are handled by the system and are not required from the administrator.', 'safecontracts'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ASSIGN_ROLE_ACTION); ?>"><?php wp_nonce_field(self::ASSIGN_ROLE_ACTION); ?>
                    <div class="safecontracts-field-row">
                        <label><?php echo esc_html__('User', 'safecontracts'); ?><select name="user_id" required><option value=""><?php echo esc_html__('Select user', 'safecontracts'); ?></option><?php foreach (is_array($allUsers) ? $allUsers : [] as $user) : ?><option value="<?php echo esc_attr((string) ($user->ID ?? 0)); ?>"><?php echo esc_html(self::userLabel($user)); ?></option><?php endforeach; ?></select></label>
                        <label><?php echo esc_html__('Safe Contracts role', 'safecontracts'); ?><select name="role_slug"><option value=""><?php echo esc_html__('No Safe Contracts role', 'safecontracts'); ?></option><?php foreach ($definitions as $slug => $label) : ?><option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <p class="description"><?php echo esc_html__('This changes only Safe Contracts role membership. Other WordPress roles are preserved.', 'safecontracts'); ?></p>
                    <?php submit_button(__('Save user role', 'safecontracts')); ?>
                </form>
            </section>

            <div class="safecontracts-role-grid">
            <?php foreach ($definitions as $slug => $label) : ?>
                <?php
                $role = get_role($slug);
                $grants = is_object($role) && isset($role->capabilities) && is_array($role->capabilities) ? $role->capabilities : [];
                $users = get_users(['role' => $slug]);
                ?>
                <section class="safecontracts-admin-card">
                    <h2><?php echo esc_html($label); ?></h2>
                    <p><?php echo esc_html(sprintf(__('%d users', 'safecontracts'), count($users))); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_CAPABILITIES_ACTION); ?>"><input type="hidden" name="role_slug" value="<?php echo esc_attr($slug); ?>"><?php wp_nonce_field(self::SAVE_CAPABILITIES_ACTION); ?>
                        <h3><?php echo esc_html__('Business permissions', 'safecontracts'); ?></h3>
                        <p class="description"><?php echo esc_html__('Select the business actions this role may perform. Technical permission codes remain internal to the system.', 'safecontracts'); ?></p>
                        <div class="safecontracts-capability-list">
                        <?php foreach (self::groupedCapabilities() as $group => $items) : ?>
                            <div class="safecontracts-capability-group">
                                <h4><?php echo esc_html($group); ?></h4>
                                <?php foreach ($items as $capability => $presentation) : ?>
                                    <label class="safecontracts-check-row">
                                        <input type="checkbox" name="capabilities[]" value="<?php echo esc_attr($capability); ?>" <?php checked(! empty($grants[$capability])); ?>>
                                        <span><strong><?php echo esc_html($presentation['label']); ?></strong><br><span class="description"><?php echo esc_html($presentation['description']); ?></span></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
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

    /** @return array<string,array<string,array{group:string,label:string,description:string}>> */
    private static function groupedCapabilities(): array
    {
        $groups = [];
        foreach (Capabilities::all() as $capability) {
            $presentation = CapabilityPresentation::describe($capability);
            $groups[$presentation['group']][$capability] = $presentation;
        }
        return $groups;
    }

    private static function userLabel(mixed $user): string
    {
        if (is_object($user)) {
            $name = isset($user->display_name) ? (string) $user->display_name : '';
            $email = isset($user->user_email) ? (string) $user->user_email : '';
        } elseif (is_array($user)) {
            $name = (string) ($user['display_name'] ?? $user['name'] ?? '');
            $email = (string) ($user['user_email'] ?? $user['email'] ?? '');
        } else {
            $name = '';
            $email = '';
        }
        $identity = trim($name . ($email !== '' ? ' — ' . $email : ''));
        return $identity !== '' ? $identity : __('Unnamed user', 'safecontracts');
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
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }
}
