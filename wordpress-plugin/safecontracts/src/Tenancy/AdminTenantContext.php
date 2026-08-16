<?php

declare(strict_types=1);

namespace SafeContracts\Tenancy;

use RuntimeException;
use SafeContracts\Admin\AdminShell;
use SafeContracts\Roles\Capabilities;
use Throwable;

final class AdminTenantContext
{
    public const SELECT_ACTION = 'safecontracts_esc_select_tenant';
    public const USER_META_KEY = 'safecontracts_esc_selected_tenant_id';

    public static function register(): void
    {
        add_action('admin_init', [self::class, 'resolveRequest'], 1);
        add_action('admin_notices', [self::class, 'renderSwitcher'], 5);
        add_action('admin_post_' . self::SELECT_ACTION, [self::class, 'handleSelect']);
    }

    public static function resolveRequest(): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            return;
        }
        if (! self::isSafeContractsAdminRequest() || ! current_user_can(Capabilities::ACCESS)) {
            return;
        }

        self::resolveUser(get_current_user_id());
    }

    public static function resolveUser(int $userId): ?int
    {
        TenantContextStore::reset();
        if ($userId <= 0) {
            return null;
        }

        $memberships = new TenantMembershipRepository();
        $resolver = new TenantResolver($memberships, TenantContextStore::context());
        $stored = self::storedTenantId($userId);

        if ($stored !== null) {
            try {
                return $resolver->resolveForUser($userId, $stored);
            } catch (Throwable) {
                delete_user_meta($userId, self::USER_META_KEY);
                TenantContextStore::reset();
                $resolver = new TenantResolver($memberships, TenantContextStore::context());
            }
        }

        try {
            $tenantId = $resolver->resolveForUser($userId);
            update_user_meta($userId, self::USER_META_KEY, $tenantId);
            return $tenantId;
        } catch (Throwable) {
            // Zero or multiple active memberships intentionally leave the context empty.
            TenantContextStore::reset();
            return null;
        }
    }

    public static function selectForUser(int $userId, int $tenantId): int
    {
        if ($userId <= 0 || $tenantId <= 0) {
            throw new RuntimeException('A valid authenticated user and Enterprise tenant are required.');
        }

        $memberships = new TenantMembershipRepository();
        if (! $memberships->isActiveMember($tenantId, $userId)) {
            throw new RuntimeException('The selected Enterprise tenant is not available to the current user.');
        }

        update_user_meta($userId, self::USER_META_KEY, $tenantId);
        TenantContextStore::reset();
        return (new TenantResolver($memberships, TenantContextStore::context()))
            ->resolveForUser($userId, $tenantId);
    }

    public static function handleSelect(): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            wp_die(__('You do not have permission to select an Enterprise tenant.', 'safecontracts'));
        }
        check_admin_referer(self::SELECT_ACTION);

        $userId = get_current_user_id();
        $tenantId = isset($_POST['tenant_id']) ? (int) $_POST['tenant_id'] : 0;
        try {
            self::selectForUser($userId, $tenantId);
        } catch (Throwable) {
            wp_die(__('The selected Enterprise tenant is not available to your account.', 'safecontracts'));
        }

        $fallback = admin_url('admin.php?page=' . AdminShell::SLUG);
        $requestedRedirect = isset($_POST['redirect_to']) ? (string) $_POST['redirect_to'] : '';
        $redirect = $requestedRedirect === ''
            ? $fallback
            : wp_validate_redirect($requestedRedirect, $fallback);
        wp_safe_redirect($redirect);
        exit;
    }

    public static function renderSwitcher(): void
    {
        if (
            ! CoreTenantEnforcement::isEnabled()
            || ! AdminShell::isSafeContractsPage()
            || ! current_user_can(Capabilities::ACCESS)
        ) {
            return;
        }

        $userId = get_current_user_id();
        if ($userId <= 0) {
            return;
        }
        $tenants = (new TenantDirectoryRepository())->forUser($userId);
        $currentTenantId = TenantContextStore::context()->tenantId();

        if ($tenants === []) {
            echo '<div class="notice notice-error"><p>'
                . esc_html__('No active Enterprise tenant membership is available for this account.', 'safecontracts')
                . '</p></div>';
            return;
        }

        $page = sanitize_key((string) ($_GET['page'] ?? AdminShell::SLUG));
        if ($page !== AdminShell::SLUG && ! str_starts_with($page, 'safecontracts-')) {
            $page = AdminShell::SLUG;
        }
        $redirectTo = add_query_arg(['page' => $page], admin_url('admin.php'));
        ?>
        <div class="notice notice-info safecontracts-tenant-switcher">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:8px 0;">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::SELECT_ACTION); ?>">
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirectTo); ?>">
                <?php wp_nonce_field(self::SELECT_ACTION); ?>
                <strong><?php echo esc_html__('Enterprise tenant', 'safecontracts'); ?></strong>
                <label class="screen-reader-text" for="safecontracts-esc-tenant-select"><?php echo esc_html__('Select Enterprise tenant', 'safecontracts'); ?></label>
                <select id="safecontracts-esc-tenant-select" name="tenant_id" required>
                    <?php if ($currentTenantId === null && count($tenants) > 1) : ?>
                        <option value=""><?php echo esc_html__('Select a tenant', 'safecontracts'); ?></option>
                    <?php endif; ?>
                    <?php foreach ($tenants as $tenant) : ?>
                        <?php $tenantId = (int) ($tenant['id'] ?? 0); ?>
                        <option value="<?php echo esc_attr((string) $tenantId); ?>" <?php selected($currentTenantId, $tenantId); ?>>
                            <?php echo esc_html((string) ($tenant['name'] ?? $tenant['slug'] ?? ('Tenant ' . $tenantId))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button(__('Switch tenant', 'safecontracts'), 'secondary', 'submit', false); ?>
                <?php if ($currentTenantId === null && count($tenants) > 1) : ?>
                    <span><?php echo esc_html__('Choose a tenant before opening tenant-owned business data.', 'safecontracts'); ?></span>
                <?php endif; ?>
            </form>
        </div>
        <?php
    }

    public static function storedTenantId(int $userId): ?int
    {
        if ($userId <= 0) {
            return null;
        }
        $value = get_user_meta($userId, self::USER_META_KEY, true);
        $tenantId = is_numeric($value) ? (int) $value : 0;
        return $tenantId > 0 ? $tenantId : null;
    }

    private static function isSafeContractsAdminRequest(): bool
    {
        if (AdminShell::isSafeContractsPage()) {
            return true;
        }
        $action = sanitize_key((string) ($_REQUEST['action'] ?? ''));
        return str_starts_with($action, 'safecontracts_');
    }
}
