<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;
use SafeContracts\Suppliers\SupplierService;
use SafeContracts\Suppliers\SupplierStatus;
use SafeContracts\Translations\TranslationCatalog;
use Throwable;

final class SuppliersPage
{
    public const SLUG = 'safecontracts-suppliers';
    public const SAVE_ACTION = 'safecontracts_save_supplier_admin';
    public const ARCHIVE_ACTION = 'safecontracts_archive_supplier_admin';

    public static function register(): void
    {
        add_submenu_page(
            AdminShell::SLUG,
            __('Suppliers', 'safecontracts'),
            __('Suppliers', 'safecontracts'),
            Capabilities::VIEW_SUPPLIERS,
            self::SLUG,
            [self::class, 'render']
        );
    }

    public static function handleSave(): void
    {
        $supplierId = max(0, (int) ($_POST['supplier_id'] ?? 0));
        $required = $supplierId > 0 ? Capabilities::EDIT_SUPPLIERS : Capabilities::CREATE_SUPPLIERS;
        if (! current_user_can($required) && ! current_user_can(Capabilities::MANAGE_REFERENCE_DATA)) {
            wp_die(__('You do not have permission to save suppliers.', 'safecontracts'));
        }
        check_admin_referer(self::SAVE_ACTION);

        $status = 'saved';
        try {
            $supplierId = (new SupplierService())->save([
                'id' => $supplierId,
                'internal_code' => sanitize_text_field((string) ($_POST['internal_code'] ?? '')),
                'legal_name' => sanitize_text_field((string) ($_POST['legal_name'] ?? '')),
                'trading_name' => sanitize_text_field((string) ($_POST['trading_name'] ?? '')),
                'contact_name' => sanitize_text_field((string) ($_POST['contact_name'] ?? '')),
                'phone' => sanitize_text_field((string) ($_POST['phone'] ?? '')),
                'email' => sanitize_email((string) ($_POST['email'] ?? '')),
                'address' => sanitize_textarea_field((string) ($_POST['address'] ?? '')),
                'country_code' => sanitize_text_field((string) ($_POST['country_code'] ?? '')),
                'registration_number' => sanitize_text_field((string) ($_POST['registration_number'] ?? '')),
                'tax_number' => sanitize_text_field((string) ($_POST['tax_number'] ?? '')),
                'default_currency' => sanitize_text_field((string) ($_POST['default_currency'] ?? '')),
                'payment_terms' => sanitize_text_field((string) ($_POST['payment_terms'] ?? '')),
                'status' => sanitize_key((string) ($_POST['status'] ?? SupplierStatus::ACTIVE)),
                'notes' => sanitize_textarea_field((string) ($_POST['notes'] ?? '')),
            ]);
        } catch (Throwable $error) {
            unset($error);
            $status = 'invalid';
        }

        wp_safe_redirect(add_query_arg([
            'page' => self::SLUG,
            'supplier_id' => $supplierId,
            'safecontracts_status' => $status,
        ], admin_url('admin.php')));
        exit;
    }

    public static function handleArchive(): void
    {
        if (! current_user_can(Capabilities::ARCHIVE_SUPPLIERS) && ! current_user_can(Capabilities::MANAGE_REFERENCE_DATA)) {
            wp_die(__('You do not have permission to archive suppliers.', 'safecontracts'));
        }
        $supplierId = max(0, (int) ($_POST['supplier_id'] ?? 0));
        check_admin_referer(self::ARCHIVE_ACTION . '_' . $supplierId);
        $status = 'archived';
        try {
            (new SupplierService())->archive($supplierId);
        } catch (Throwable $error) {
            unset($error);
            $status = 'archive_failed';
        }
        wp_safe_redirect(add_query_arg([
            'page' => self::SLUG,
            'safecontracts_status' => $status,
        ], admin_url('admin.php')));
        exit;
    }

    public static function render(): void
    {
        if (! self::canView()) {
            wp_die(__('You do not have permission to view suppliers.', 'safecontracts'));
        }

        $service = new SupplierService();
        $query = isset($_GET['supplier_search']) && is_scalar($_GET['supplier_search'])
            ? sanitize_text_field((string) $_GET['supplier_search'])
            : '';
        $includeArchived = self::canViewArchived()
            && isset($_GET['include_archived'])
            && (string) $_GET['include_archived'] === '1';
        $suppliers = [];
        $selected = null;
        $selectedId = max(0, (int) ($_GET['supplier_id'] ?? 0));
        try {
            $suppliers = $service->search($query, 200, $includeArchived);
            if ($selectedId > 0) {
                $selected = $service->find($selectedId);
            }
        } catch (Throwable $error) {
            unset($error);
        }

        $status = isset($_GET['safecontracts_status']) && is_scalar($_GET['safecontracts_status'])
            ? sanitize_key((string) $_GET['safecontracts_status'])
            : '';
        $canCreate = current_user_can(Capabilities::CREATE_SUPPLIERS) || current_user_can(Capabilities::MANAGE_REFERENCE_DATA);
        $canEdit = current_user_can(Capabilities::EDIT_SUPPLIERS) || current_user_can(Capabilities::MANAGE_REFERENCE_DATA);
        $canArchive = current_user_can(Capabilities::ARCHIVE_SUPPLIERS) || current_user_can(Capabilities::MANAGE_REFERENCE_DATA);
        ?>
        <div class="wrap safecontracts-settings safecontracts-suppliers" dir="auto">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Counterparty master data', 'safecontracts'); ?></p>
                    <h1><?php echo esc_html__('Suppliers', 'safecontracts'); ?></h1>
                    <p class="description"><?php echo esc_html__('Supplier master data is authoritative for Accounts Payable contracts. Archiving removes a supplier from new operations while preserving contract and financial history.', 'safecontracts'); ?></p>
                </div>
                <?php if (current_user_can(Capabilities::VIEW_PAYABLES)) : ?>
                    <a class="button" href="<?php echo esc_url(add_query_arg(['page' => FinancePage::SLUG, 'direction' => 'payable'], admin_url('admin.php'))); ?>"><?php echo esc_html__('Open Accounts Payable', 'safecontracts'); ?></a>
                <?php endif; ?>
            </div>

            <?php if ($status === 'saved') : ?><div class="notice notice-success inline"><p><?php echo esc_html__('Supplier saved.', 'safecontracts'); ?></p></div><?php endif; ?>
            <?php if ($status === 'invalid') : ?><div class="notice notice-error inline"><p><?php echo esc_html__('Supplier was not saved. Check required fields, duplicate identifiers and validation rules.', 'safecontracts'); ?></p></div><?php endif; ?>
            <?php if ($status === 'archived') : ?><div class="notice notice-success inline"><p><?php echo esc_html__('Supplier archived. Existing contract and finance history is preserved.', 'safecontracts'); ?></p></div><?php endif; ?>
            <?php if ($status === 'archive_failed') : ?><div class="notice notice-error inline"><p><?php echo esc_html__('Supplier could not be archived.', 'safecontracts'); ?></p></div><?php endif; ?>

            <section class="safecontracts-admin-card">
                <form class="safecontracts-filter-bar" method="get">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                    <label><?php echo esc_html__('Search suppliers', 'safecontracts'); ?><input type="search" name="supplier_search" value="<?php echo esc_attr($query); ?>" placeholder="<?php echo esc_attr__('Name, code, registration or tax number', 'safecontracts'); ?>"></label>
                    <?php if (self::canViewArchived()) : ?>
                        <label class="safecontracts-inline-check"><input type="checkbox" name="include_archived" value="1" <?php checked($includeArchived); ?>> <?php echo esc_html__('Include archived', 'safecontracts'); ?></label>
                    <?php endif; ?>
                    <button class="button button-primary" type="submit"><?php echo esc_html__('Apply', 'safecontracts'); ?></button>
                </form>
            </section>

            <div class="safecontracts-split-layout">
                <section class="safecontracts-admin-card safecontracts-table-card">
                    <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Supplier directory', 'safecontracts'); ?></p><h2><?php echo esc_html__('Supplier master', 'safecontracts'); ?></h2></div><span class="safecontracts-count-badge"><?php echo esc_html((string) count($suppliers)); ?></span></div>
                    <?php if ($suppliers === []) : ?>
                        <p><?php echo esc_html__('No suppliers match the current search.', 'safecontracts'); ?></p>
                    <?php else : ?>
                        <table class="widefat striped">
                            <thead><tr><th><?php echo esc_html__('Supplier', 'safecontracts'); ?></th><th><?php echo esc_html__('Code', 'safecontracts'); ?></th><th><?php echo esc_html__('Currency', 'safecontracts'); ?></th><th><?php echo esc_html__('Terms', 'safecontracts'); ?></th><th><?php echo esc_html__('Status', 'safecontracts'); ?></th><th><?php echo esc_html__('Actions', 'safecontracts'); ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($suppliers as $supplier) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html((string) $supplier['legal_name']); ?></strong><?php if ((string) $supplier['trading_name'] !== '') : ?><br><small><?php echo esc_html((string) $supplier['trading_name']); ?></small><?php endif; ?></td>
                                    <td><?php echo esc_html((string) ($supplier['internal_code'] ?: '—')); ?></td>
                                    <td><?php echo esc_html((string) ($supplier['default_currency'] ?: '—')); ?></td>
                                    <td><?php echo esc_html((string) ($supplier['payment_terms'] ?: '—')); ?></td>
                                    <td><span class="safecontracts-status-pill safecontracts-status-pill--<?php echo esc_attr((string) $supplier['status']); ?>"><?php echo esc_html(self::statusLabel((string) $supplier['status'])); ?></span></td>
                                    <td><div class="safecontracts-dashboard-table-actions">
                                        <a class="button button-small" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'supplier_id' => (int) $supplier['id'], 'include_archived' => $includeArchived ? '1' : '0'], admin_url('admin.php'))); ?>"><?php echo esc_html__('Open', 'safecontracts'); ?></a>
                                        <?php if ($canArchive && empty($supplier['is_archived'])) : ?>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-safecontracts-delete-form data-delete-message="<?php echo esc_attr__('Archive this supplier? Existing contracts and financial history will remain available, but the supplier cannot be used for new contracts.', 'safecontracts'); ?>">
                                                <input type="hidden" name="action" value="<?php echo esc_attr(self::ARCHIVE_ACTION); ?>"><input type="hidden" name="supplier_id" value="<?php echo esc_attr((string) $supplier['id']); ?>"><?php wp_nonce_field(self::ARCHIVE_ACTION . '_' . (int) $supplier['id']); ?>
                                                <button type="submit" class="button button-small safecontracts-delete-button"><?php echo esc_html__('Archive', 'safecontracts'); ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </div></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>

                <?php if (($selected === null && $canCreate) || ($selected !== null && ($canEdit || ! empty($selected['is_archived'])))) : ?>
                <section class="safecontracts-admin-card safecontracts-supplier-editor">
                    <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html($selected ? __('Supplier profile', 'safecontracts') : __('New counterparty', 'safecontracts')); ?></p><h2><?php echo $selected ? esc_html((string) $selected['legal_name']) : esc_html__('Create supplier', 'safecontracts'); ?></h2></div></div>
                    <?php if ($selected && ! empty($selected['is_archived'])) : ?><div class="notice notice-warning inline"><p><?php echo esc_html__('Archived suppliers are read-only. Their historical contracts and financial records remain available.', 'safecontracts'); ?></p></div><?php endif; ?>
                    <?php if (! $selected || empty($selected['is_archived'])) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><input type="hidden" name="supplier_id" value="<?php echo esc_attr((string) ($selected['id'] ?? 0)); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>
                        <div class="safecontracts-field-row"><label><?php echo esc_html__('Legal name', 'safecontracts'); ?><input name="legal_name" maxlength="191" required value="<?php echo esc_attr((string) ($selected['legal_name'] ?? '')); ?>"></label><label><?php echo esc_html__('Trading name', 'safecontracts'); ?><input name="trading_name" maxlength="191" value="<?php echo esc_attr((string) ($selected['trading_name'] ?? '')); ?>"></label></div>
                        <div class="safecontracts-field-row"><label><?php echo esc_html__('Internal code', 'safecontracts'); ?><input name="internal_code" maxlength="100" value="<?php echo esc_attr((string) ($selected['internal_code'] ?? '')); ?>"></label><label><?php echo esc_html__('Status', 'safecontracts'); ?><select name="status"><?php foreach ([SupplierStatus::ACTIVE, SupplierStatus::INACTIVE, SupplierStatus::SUSPENDED] as $supplierStatus) : ?><option value="<?php echo esc_attr($supplierStatus); ?>" <?php selected((string) ($selected['status'] ?? SupplierStatus::ACTIVE), $supplierStatus); ?>><?php echo esc_html(self::statusLabel($supplierStatus)); ?></option><?php endforeach; ?></select></label></div>
                        <div class="safecontracts-field-row"><label><?php echo esc_html__('Contact name', 'safecontracts'); ?><input name="contact_name" maxlength="191" value="<?php echo esc_attr((string) ($selected['contact_name'] ?? '')); ?>"></label><label><?php echo esc_html__('Phone', 'safecontracts'); ?><input name="phone" maxlength="64" value="<?php echo esc_attr((string) ($selected['phone'] ?? '')); ?>"></label></div>
                        <div class="safecontracts-field-row"><label><?php echo esc_html__('Email', 'safecontracts'); ?><input type="email" name="email" maxlength="191" value="<?php echo esc_attr((string) ($selected['email'] ?? '')); ?>"></label><label><?php echo esc_html__('Country code', 'safecontracts'); ?><input name="country_code" maxlength="2" pattern="[A-Za-z]{2}" placeholder="KW" value="<?php echo esc_attr((string) ($selected['country_code'] ?? '')); ?>"></label></div>
                        <div class="safecontracts-field-row"><label><?php echo esc_html__('Registration number', 'safecontracts'); ?><input name="registration_number" maxlength="100" value="<?php echo esc_attr((string) ($selected['registration_number'] ?? '')); ?>"></label><label><?php echo esc_html__('Tax / VAT number', 'safecontracts'); ?><input name="tax_number" maxlength="100" value="<?php echo esc_attr((string) ($selected['tax_number'] ?? '')); ?>"></label></div>
                        <div class="safecontracts-field-row"><label><?php echo esc_html__('Default currency', 'safecontracts'); ?><input name="default_currency" maxlength="3" pattern="[A-Za-z]{3}" placeholder="KWD" value="<?php echo esc_attr((string) ($selected['default_currency'] ?? '')); ?>"></label><label><?php echo esc_html__('Payment terms', 'safecontracts'); ?><input name="payment_terms" maxlength="191" placeholder="<?php echo esc_attr__('e.g. Net 30', 'safecontracts'); ?>" value="<?php echo esc_attr((string) ($selected['payment_terms'] ?? '')); ?>"></label></div>
                        <p><label><?php echo esc_html__('Address', 'safecontracts'); ?><textarea class="widefat" rows="3" name="address"><?php echo esc_textarea((string) ($selected['address'] ?? '')); ?></textarea></label></p>
                        <p><label><?php echo esc_html__('Notes', 'safecontracts'); ?><textarea class="widefat" rows="4" name="notes"><?php echo esc_textarea((string) ($selected['notes'] ?? '')); ?></textarea></label></p>
                        <?php submit_button($selected ? __('Save supplier', 'safecontracts') : __('Create supplier', 'safecontracts')); ?>
                    </form>
                    <?php else : ?>
                        <dl class="safecontracts-detail-list">
                            <div><dt><?php echo esc_html__('Registration', 'safecontracts'); ?></dt><dd><?php echo esc_html((string) ($selected['registration_number'] ?: '—')); ?></dd></div>
                            <div><dt><?php echo esc_html__('Tax / VAT', 'safecontracts'); ?></dt><dd><?php echo esc_html((string) ($selected['tax_number'] ?: '—')); ?></dd></div>
                            <div><dt><?php echo esc_html__('Currency', 'safecontracts'); ?></dt><dd><?php echo esc_html((string) ($selected['default_currency'] ?: '—')); ?></dd></div>
                        </dl>
                    <?php endif; ?>
                </section>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private static function canView(): bool
    {
        return current_user_can(Capabilities::VIEW_SUPPLIERS)
            || current_user_can(Capabilities::VIEW_ALL)
            || current_user_can(Capabilities::MANAGE_REFERENCE_DATA);
    }

    private static function canViewArchived(): bool
    {
        return current_user_can(Capabilities::ARCHIVE_SUPPLIERS)
            || current_user_can(Capabilities::VIEW_ALL)
            || current_user_can(Capabilities::MANAGE_REFERENCE_DATA);
    }

    private static function statusLabel(string $status): string
    {
        return TranslationCatalog::text(ucwords(str_replace('_', ' ', $status)));
    }
}
