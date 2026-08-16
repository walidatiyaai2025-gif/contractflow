<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use InvalidArgumentException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\TenantMembershipAdminService;
use SafeContracts\Tenancy\TenantRolePolicy;
use Throwable;

final class TenantMembersPage
{
    public const SLUG = 'safecontracts-tenant-members';
    public const ASSIGN_ACTION = 'safecontracts_esc_assign_tenant_member';
    public const DEACTIVATE_ACTION = 'safecontracts_esc_deactivate_tenant_member';

    public static function register(): void
    {
        add_submenu_page(
            AdminShell::SLUG,
            __('Tenant Members', 'safecontracts'),
            __('Tenant Members', 'safecontracts'),
            Capabilities::MANAGE_USERS,
            self::SLUG,
            [self::class, 'render']
        );
    }

    public static function handleAssign(): void
    {
        self::requireManage();
        check_admin_referer(self::ASSIGN_ACTION);

        $status = 'tenant_member_saved';
        try {
            $targetUserId = absint($_POST['user_id'] ?? 0);
            $roleCode = sanitize_key((string) ($_POST['role_code'] ?? ''));
            (new TenantMembershipAdminService())->assignRole(
                $targetUserId,
                $roleCode,
                get_current_user_id()
            );
        } catch (Throwable) {
            $status = 'tenant_member_invalid';
        }

        self::redirect($status);
    }

    public static function handleDeactivate(): void
    {
        self::requireManage();
        check_admin_referer(self::DEACTIVATE_ACTION);

        $status = 'tenant_member_deactivated';
        try {
            $targetUserId = absint($_POST['user_id'] ?? 0);
            if ($targetUserId <= 0) {
                throw new InvalidArgumentException('A tenant member is required.');
            }

            $actorUserId = get_current_user_id();
            $service = new TenantMembershipAdminService();
            $target = self::membershipByUserId($service->listForCurrentTenant($actorUserId), $targetUserId);
            if ($target === null || (bool) ($target['is_owner'] ?? false)) {
                // Owner deactivation/transfer is deliberately outside this generic UI,
                // even though the domain service can preserve last-owner safety for a
                // future dedicated ownership workflow.
                throw new InvalidArgumentException('Owner memberships are read-only in this interface.');
            }

            $service->deactivate($targetUserId, $actorUserId);
        } catch (Throwable) {
            $status = 'tenant_member_invalid';
        }

        self::redirect($status);
    }

    public static function render(): void
    {
        self::requireManage();
        $actorUserId = get_current_user_id();

        try {
            $memberships = (new TenantMembershipAdminService())->listForCurrentTenant($actorUserId);
        } catch (Throwable) {
            wp_die(__('Select an active Enterprise tenant before managing tenant members.', 'safecontracts'));
        }

        $users = self::wordpressUsers();
        $active = count(array_filter($memberships, static fn (array $row): bool => ($row['status'] ?? '') === 'active'));
        $owners = count(array_filter($memberships, static fn (array $row): bool => ! empty($row['is_owner'])));
        $inactive = count($memberships) - $active;
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Enterprise tenant authorization', 'safecontracts'); ?></p>
                    <h1><?php echo esc_html__('Tenant Members', 'safecontracts'); ?></h1>
                </div>
            </div>

            <?php self::notice(); ?>
            <?php AdminSummaryCards::render([
                ['label' => __('Tenant memberships', 'safecontracts'), 'value' => count($memberships)],
                ['label' => __('Active members', 'safecontracts'), 'value' => $active],
                ['label' => __('Owners', 'safecontracts'), 'value' => $owners],
                ['label' => __('Inactive members', 'safecontracts'), 'value' => $inactive],
            ]); ?>

            <section class="safecontracts-admin-card safecontracts-settings-card">
                <h2><?php echo esc_html__('Add or reactivate tenant member', 'safecontracts'); ?></h2>
                <p class="description"><?php echo esc_html__('This assigns an existing WordPress user to the currently selected Enterprise tenant. Ownership cannot be granted here.', 'safecontracts'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ASSIGN_ACTION); ?>">
                    <?php wp_nonce_field(self::ASSIGN_ACTION); ?>
                    <div class="safecontracts-field-row">
                        <label>
                            <?php echo esc_html__('WordPress user', 'safecontracts'); ?>
                            <select name="user_id" required>
                                <option value=""><?php echo esc_html__('Select user', 'safecontracts'); ?></option>
                                <?php foreach ($users as $userId => $label) : ?>
                                    <option value="<?php echo esc_attr((string) $userId); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <?php echo esc_html__('Tenant role', 'safecontracts'); ?>
                            <select name="role_code" required>
                                <?php self::renderAssignableRoleOptions(null); ?>
                            </select>
                        </label>
                    </div>
                    <?php submit_button(__('Save tenant member', 'safecontracts')); ?>
                </form>
            </section>

            <section class="safecontracts-admin-card safecontracts-table-card">
                <h2><?php echo esc_html__('Current tenant memberships', 'safecontracts'); ?></h2>
                <p class="description"><?php echo esc_html__('Owner memberships are read-only here. Ownership transfer or owner removal requires a separate deliberate workflow.', 'safecontracts'); ?></p>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('User', 'safecontracts'); ?></th>
                            <th><?php echo esc_html__('Tenant role', 'safecontracts'); ?></th>
                            <th><?php echo esc_html__('Status', 'safecontracts'); ?></th>
                            <th><?php echo esc_html__('Ownership', 'safecontracts'); ?></th>
                            <th><?php echo esc_html__('Actions', 'safecontracts'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($memberships === []) : ?>
                        <tr><td colspan="5"><?php echo esc_html__('No tenant memberships are available.', 'safecontracts'); ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($memberships as $membership) : ?>
                        <?php
                        $userId = (int) ($membership['user_id'] ?? 0);
                        $roleCode = (string) ($membership['role_code'] ?? '');
                        $status = (string) ($membership['status'] ?? 'inactive');
                        $isOwner = (bool) ($membership['is_owner'] ?? false);
                        ?>
                        <tr>
                            <td><?php echo esc_html($users[$userId] ?? ('#' . $userId)); ?></td>
                            <td>
                                <?php if ($isOwner) : ?>
                                    <code><?php echo esc_html($roleCode); ?></code>
                                <?php else : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                        <input type="hidden" name="action" value="<?php echo esc_attr(self::ASSIGN_ACTION); ?>">
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $userId); ?>">
                                        <?php wp_nonce_field(self::ASSIGN_ACTION); ?>
                                        <select name="role_code" required>
                                            <?php self::renderAssignableRoleOptions($roleCode); ?>
                                        </select>
                                        <?php submit_button($status === 'active' ? __('Save role', 'safecontracts') : __('Reactivate', 'safecontracts'), 'secondary', 'submit', false); ?>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($status === 'active' ? __('Active', 'safecontracts') : __('Inactive', 'safecontracts')); ?></td>
                            <td><?php echo $isOwner ? esc_html__('Owner — read only', 'safecontracts') : '—'; ?></td>
                            <td>
                                <?php if ($isOwner) : ?>
                                    <span><?php echo esc_html__('Ownership workflow required', 'safecontracts'); ?></span>
                                <?php elseif ($status === 'active') : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <input type="hidden" name="action" value="<?php echo esc_attr(self::DEACTIVATE_ACTION); ?>">
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $userId); ?>">
                                        <?php wp_nonce_field(self::DEACTIVATE_ACTION); ?>
                                        <?php submit_button(__('Deactivate', 'safecontracts'), 'secondary', 'submit', false); ?>
                                    </form>
                                <?php else : ?>
                                    <span><?php echo esc_html__('Inactive', 'safecontracts'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>
        <?php
    }

    /** @param list<array<string,mixed>> $memberships */
    private static function membershipByUserId(array $memberships, int $userId): ?array
    {
        foreach ($memberships as $membership) {
            if ((int) ($membership['user_id'] ?? 0) === $userId) {
                return $membership;
            }
        }
        return null;
    }

    /** @return array<int,string> */
    private static function wordpressUsers(): array
    {
        $rows = get_users(['orderby' => 'display_name', 'order' => 'ASC']);
        $users = [];
        foreach (is_array($rows) ? $rows : [] as $user) {
            $userId = is_object($user) ? (int) ($user->ID ?? 0) : (int) ($user['ID'] ?? 0);
            if ($userId <= 0) {
                continue;
            }
            $name = is_object($user) ? (string) ($user->display_name ?? '') : (string) ($user['display_name'] ?? '');
            $email = is_object($user) ? (string) ($user->user_email ?? '') : (string) ($user['user_email'] ?? '');
            $identity = trim($name . ($email !== '' ? ' — ' . $email : ''));
            $users[$userId] = '#' . $userId . ($identity !== '' ? ' · ' . $identity : '');
        }
        return $users;
    }

    private static function renderAssignableRoleOptions(?string $selectedRole): void
    {
        $selectedRole = $selectedRole === null ? null : TenantRolePolicy::normalize($selectedRole);
        if ($selectedRole !== null && ! TenantRolePolicy::isAssignable($selectedRole)) {
            echo '<option value="" selected disabled>' . esc_html__('Legacy role — choose a supported tenant role', 'safecontracts') . '</option>';
        }

        foreach (TenantRolePolicy::assignableRoles() as $roleCode) {
            $label = match ($roleCode) {
                TenantRolePolicy::TENANT_ADMIN => __('Tenant Administrator', 'safecontracts'),
                TenantRolePolicy::MANAGER => __('Manager', 'safecontracts'),
                TenantRolePolicy::ACCOUNTANT => __('Accountant', 'safecontracts'),
                TenantRolePolicy::VIEWER => __('Viewer', 'safecontracts'),
                default => $roleCode,
            };
            echo '<option value="' . esc_attr($roleCode) . '" ' . selected($selectedRole, $roleCode, false) . '>' . esc_html($label) . '</option>';
        }
    }

    private static function requireManage(): void
    {
        if (! current_user_can(Capabilities::MANAGE_USERS)) {
            wp_die(__('You do not have permission to manage members of this Enterprise tenant.', 'safecontracts'));
        }
    }

    private static function redirect(string $status): never
    {
        wp_safe_redirect(add_query_arg([
            'page' => self::SLUG,
            'safecontracts_status' => $status,
        ], admin_url('admin.php')));
        exit;
    }

    private static function notice(): void
    {
        $status = isset($_GET['safecontracts_status']) && is_scalar($_GET['safecontracts_status'])
            ? sanitize_key((string) $_GET['safecontracts_status'])
            : '';
        $message = match ($status) {
            'tenant_member_saved' => __('Tenant member saved.', 'safecontracts'),
            'tenant_member_deactivated' => __('Tenant member deactivated.', 'safecontracts'),
            'tenant_member_invalid' => __('Tenant membership could not be changed.', 'safecontracts'),
            default => '',
        };
        if ($message !== '') {
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }
}
