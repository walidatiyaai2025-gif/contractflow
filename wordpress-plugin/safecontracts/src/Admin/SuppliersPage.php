<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;
use SafeContracts\Suppliers\SupplierService;
use SafeContracts\Suppliers\SupplierStatus;
use Throwable;

final class SuppliersPage
{
    public const SLUG = 'safecontracts-suppliers';
    public const SAVE_ACTION = 'safecontracts_save_supplier';
    public const ARCHIVE_ACTION = 'safecontracts_archive_supplier';

    public static function register(): void
    {
        if (! self::canView()) {
            return;
        }
        add_submenu_page(
            AdminShell::SLUG,
            __('Suppliers', 'safecontracts'),
            __('Suppliers', 'safecontracts'),
            Capabilities::ACCESS,
            self::SLUG,
            [self::class, 'render'],
            11
        );
    }

    public static function handleSave(): void
    {
        self::requireAdminAccess();
        check_admin_referer(self::SAVE_ACTION);
        $supplierId = max(0, (int) ($_POST['id'] ?? 0));
        try {
            $saved = (new SupplierService())->save([
                'id' => $supplierId,
                'internal_code' => $_POST['internal_code'] ?? '',
                'legal_name' => $_POST['legal_name'] ?? '',
                'trading_name' => $_POST['trading_name'] ?? '',
                'contact_name' => $_POST['contact_name'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'email' => $_POST['email'] ?? '',
                'address' => $_POST['address'] ?? '',
                'country_code' => $_POST['country_code'] ?? '',
                'registration_number' => $_POST['registration_number'] ?? '',
                'tax_number' => $_POST['tax_number'] ?? '',
                'default_currency' => $_POST['default_currency'] ?? '',
                'payment_terms' => $_POST['payment_terms'] ?? '',
                'status' => $_POST['status'] ?? SupplierStatus::ACTIVE,
                'notes' => $_POST['notes'] ?? '',
            ]);
            AdminFeedback::success(__('Supplier saved.', 'safecontracts'));
            self::redirect(['edit' => $saved]);
        } catch (Throwable $error) {
            AdminFeedback::error($error->getMessage());
            self::redirect($supplierId > 0 ? ['edit' => $supplierId] : []);
        }
    }

    public static function handleArchive(): void
    {
        self::requireAdminAccess();
        check_admin_referer(self::ARCHIVE_ACTION);
        $supplierId = max(0, (int) ($_POST['id'] ?? 0));
        try {
            (new SupplierService())->archive($supplierId);
            AdminFeedback::success(__('Supplier archived. Historical contracts and financial obligations were preserved.', 'safecontracts'));
            self::redirect();
        } catch (Throwable $error) {
            AdminFeedback::error($error->getMessage());
            self::redirect($supplierId > 0 ? ['edit' => $supplierId] : []);
        }
    }

    public static function render(): void
    {
        self::requireAdminAccess();
        $service = new SupplierService();
        $query = trim((string) ($_GET['q'] ?? ''));
        $includeArchived = ! empty($_GET['archived']);
        $editing = null;
        $editId = max(0, (int) ($_GET['edit'] ?? 0));
        try {
            if ($editId > 0) {
                $editing = $service->find($editId, true);
            }
            $suppliers = $service->search($query, 200, $includeArchived);
        } catch (Throwable $error) {
            AdminFeedback::error($error->getMessage());
            $suppliers = [];
        }

        $canCreate = self::canCreate();
        $canEdit = self::canEdit();
        ?>
        <div class="wrap safecontracts-settings safecontracts-suppliers" dir="auto">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Counterparty master data', 'safecontracts'); ?></p>
                    <h1><?php echo esc_html__('Suppliers', 'safecontracts'); ?></h1>
                    <p><?php echo esc_html__('Manage Supplier identity and payment defaults without creating synthetic Customer records. Archive is the only deletion path.', 'safecontracts'); ?></p>
                </div>
                <a class="button" href="<?php echo esc_url(add_query_arg(['page' => FinancePage::SLUG, 'direction' => 'payable'], admin_url('admin.php'))); ?>"><?php echo esc_html__('Open Accounts Payable', 'safecontracts'); ?></a>
            </div>

            <section class="safecontracts-admin-card">
                <form class="safecontracts-filter-bar" method="get">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                    <label><?php echo esc_html__('Search suppliers', 'safecontracts'); ?><input type="search" name="q" value="<?php echo esc_attr($query); ?>" placeholder="<?php echo esc_attr__('Name, code, registration or tax number', 'safecontracts'); ?>"></label>
                    <label class="safecontracts-inline-check"><input type="checkbox" name="archived" value="1" <?php checked($includeArchived); ?>> <?php echo esc_html__('Include archived', 'safecontracts'); ?></label>
                    <button class="button" type="submit"><?php echo esc_html__('Search', 'safecontracts'); ?></button>
                    <a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Clear', 'safecontracts'); ?></a>
                </form>
            </section>

            <?php if (($editing !== null && $canEdit && ! $editing['is_archived']) || ($editing === null && $canCreate)) : ?>
                <?php self::renderForm($editing); ?>
            <?php elseif ($editing !== null && $editing['is_archived']) : ?>
                <section class="safecontracts-admin-card safecontracts-admin-card--security"><h2><?php echo esc_html__('Archived Supplier', 'safecontracts'); ?></h2><p><?php echo esc_html__('Archived Suppliers are read-only. Historical contracts and AP ledger records remain linked to this Supplier ID.', 'safecontracts'); ?></p></section>
            <?php endif; ?>

            <section class="safecontracts-admin-card safecontracts-table-card">
                <div class="safecontracts-section-heading"><div><h2><?php echo esc_html__('Supplier directory', 'safecontracts'); ?></h2><p><?php echo esc_html(sprintf(__('%d suppliers in this view.', 'safecontracts'), count($suppliers))); ?></p></div></div>
                <div class="safecontracts-table-scroll"><table class="widefat striped"><thead><tr>
                    <th><?php echo esc_html__('Supplier', 'safecontracts'); ?></th><th><?php echo esc_html__('Code', 'safecontracts'); ?></th><th><?php echo esc_html__('Contact', 'safecontracts'); ?></th><th><?php echo esc_html__('Registration / Tax', 'safecontracts'); ?></th><th><?php echo esc_html__('Currency / Terms', 'safecontracts'); ?></th><th><?php echo esc_html__('Status', 'safecontracts'); ?></th><th><?php echo esc_html__('Actions', 'safecontracts'); ?></th>
                </tr></thead><tbody>
                <?php if ($suppliers === []) : ?><tr><td colspan="7"><?php echo esc_html__('No suppliers match this view.', 'safecontracts'); ?></td></tr><?php endif; ?>
                <?php foreach ($suppliers as $supplier) : ?>
                    <tr>
                        <td><strong><?php echo esc_html((string) $supplier['legal_name']); ?></strong><?php if ($supplier['trading_name'] !== '') : ?><br><small><?php echo esc_html((string) $supplier['trading_name']); ?></small><?php endif; ?></td>
                        <td><?php echo esc_html((string) ($supplier['internal_code'] ?: '—')); ?></td>
                        <td><?php echo esc_html((string) ($supplier['contact_name'] ?: '—')); ?><br><small><?php echo esc_html(trim((string) $supplier['phone'] . ' ' . (string) $supplier['email']) ?: '—'); ?></small></td>
                        <td><?php echo esc_html((string) ($supplier['registration_number'] ?: '—')); ?><br><small><?php echo esc_html((string) ($supplier['tax_number'] ?: '—')); ?></small></td>
                        <td><?php echo esc_html((string) ($supplier['default_currency'] ?: '—')); ?><br><small><?php echo esc_html((string) ($supplier['payment_terms'] ?: '—')); ?></small></td>
                        <td><span class="safecontracts-status-badge safecontracts-status-badge--<?php echo esc_attr((string) ($supplier['is_archived'] ? 'archived' : $supplier['status'])); ?>"><?php echo esc_html($supplier['is_archived'] ? __('Archived', 'safecontracts') : self::statusLabel((string) $supplier['status'])); ?></span></td>
                        <td><a class="button button-small" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'edit' => (int) $supplier['id'], 'archived' => $includeArchived ? 1 : 0], admin_url('admin.php'))); ?>"><?php echo esc_html($supplier['is_archived'] ? __('View', 'safecontracts') : __('Edit', 'safecontracts')); ?></a>
                        <?php if (! $supplier['is_archived'] && $canEdit) : ?><form class="safecontracts-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Archive this Supplier? Historical contracts and financial records will remain.', 'safecontracts')); ?>');"><input type="hidden" name="action" value="<?php echo esc_attr(self::ARCHIVE_ACTION); ?>"><input type="hidden" name="id" value="<?php echo esc_attr((string) $supplier['id']); ?>"><?php wp_nonce_field(self::ARCHIVE_ACTION); ?><button class="button button-small" type="submit"><?php echo esc_html__('Archive', 'safecontracts'); ?></button></form><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
            </section>
        </div>
        <?php
    }

    /** @param array<string,mixed>|null $supplier */
    private static function renderForm(?array $supplier): void
    {
        $supplier ??= [
            'id' => 0, 'internal_code' => '', 'legal_name' => '', 'trading_name' => '', 'contact_name' => '', 'phone' => '', 'email' => '',
            'address' => '', 'country_code' => '', 'registration_number' => '', 'tax_number' => '', 'default_currency' => '',
            'payment_terms' => '', 'status' => SupplierStatus::ACTIVE, 'notes' => '',
        ];
        ?>
        <section class="safecontracts-admin-card safecontracts-form-card">
            <div class="safecontracts-section-heading"><div><h2><?php echo esc_html((int) $supplier['id'] > 0 ? __('Edit Supplier', 'safecontracts') : __('New Supplier', 'safecontracts')); ?></h2><p><?php echo esc_html__('Legal identity drives contracts and payables; trading/contact defaults support operations.', 'safecontracts'); ?></p></div></div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><input type="hidden" name="id" value="<?php echo esc_attr((string) $supplier['id']); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>
                <div class="safecontracts-form-grid">
                    <?php self::input('internal_code', __('Internal code', 'safecontracts'), $supplier['internal_code']); ?>
                    <?php self::input('legal_name', __('Legal name', 'safecontracts'), $supplier['legal_name'], true); ?>
                    <?php self::input('trading_name', __('Trading name', 'safecontracts'), $supplier['trading_name']); ?>
                    <?php self::input('contact_name', __('Contact person', 'safecontracts'), $supplier['contact_name']); ?>
                    <?php self::input('phone', __('Phone', 'safecontracts'), $supplier['phone']); ?>
                    <?php self::input('email', __('Email', 'safecontracts'), $supplier['email'], false, 'email'); ?>
                    <?php self::input('country_code', __('Country code', 'safecontracts'), $supplier['country_code'], false, 'text', 2); ?>
                    <?php self::input('registration_number', __('Registration number', 'safecontracts'), $supplier['registration_number']); ?>
                    <?php self::input('tax_number', __('Tax number', 'safecontracts'), $supplier['tax_number']); ?>
                    <?php self::input('default_currency', __('Default currency', 'safecontracts'), $supplier['default_currency'], false, 'text', 3); ?>
                    <?php self::input('payment_terms', __('Payment terms', 'safecontracts'), $supplier['payment_terms']); ?>
                    <label><?php echo esc_html__('Status', 'safecontracts'); ?><select name="status"><?php foreach (SupplierStatus::all() as $status) : ?><option value="<?php echo esc_attr($status); ?>" <?php selected($supplier['status'], $status); ?>><?php echo esc_html(self::statusLabel($status)); ?></option><?php endforeach; ?></select></label>
                    <label class="safecontracts-form-span-2"><?php echo esc_html__('Address', 'safecontracts'); ?><textarea name="address" rows="3"><?php echo esc_textarea((string) $supplier['address']); ?></textarea></label>
                    <label class="safecontracts-form-span-2"><?php echo esc_html__('Notes', 'safecontracts'); ?><textarea name="notes" rows="4"><?php echo esc_textarea((string) $supplier['notes']); ?></textarea></label>
                </div>
                <div class="safecontracts-form-actions"><button class="button button-primary" type="submit"><?php echo esc_html__('Save Supplier', 'safecontracts'); ?></button><a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Cancel', 'safecontracts'); ?></a></div>
            </form>
        </section>
        <?php
    }

    private static function input(string $name, string $label, mixed $value, bool $required = false, string $type = 'text', int $maxLength = 191): void
    {
        ?><label><?php echo esc_html($label); ?><input type="<?php echo esc_attr($type); ?>" name="<?php echo esc_attr($name); ?>" maxlength="<?php echo esc_attr((string) $maxLength); ?>" value="<?php echo esc_attr((string) $value); ?>" <?php echo $required ? 'required' : ''; ?>></label><?php
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            SupplierStatus::ACTIVE => __('Active', 'safecontracts'),
            SupplierStatus::INACTIVE => __('Inactive', 'safecontracts'),
            SupplierStatus::SUSPENDED => __('Suspended', 'safecontracts'),
            default => $status,
        };
    }

    private static function canView(): bool
    {
        return current_user_can(Capabilities::VIEW_SUPPLIERS) || current_user_can(Capabilities::MANAGE_SUPPLIERS) || current_user_can(Capabilities::MANAGE_REFERENCE_DATA);
    }

    private static function canCreate(): bool
    {
        return current_user_can(Capabilities::CREATE_SUPPLIERS) || current_user_can(Capabilities::MANAGE_SUPPLIERS) || current_user_can(Capabilities::MANAGE_REFERENCE_DATA);
    }

    private static function canEdit(): bool
    {
        return current_user_can(Capabilities::EDIT_SUPPLIERS) || current_user_can(Capabilities::MANAGE_SUPPLIERS) || current_user_can(Capabilities::MANAGE_REFERENCE_DATA);
    }

    private static function requireAdminAccess(): void
    {
        if (! current_user_can(Capabilities::ACCESS) || ! self::canView()) {
            wp_die(__('You do not have permission to view SafeContracts suppliers.', 'safecontracts'));
        }
    }

    /** @param array<string,mixed> $args */
    private static function redirect(array $args = []): void
    {
        $url = add_query_arg(['page' => self::SLUG, ...$args], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }
}
