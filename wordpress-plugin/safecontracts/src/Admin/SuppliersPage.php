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
    private const PAGE_SIZE = 20;

    public static function register(): void
    {
        Worker1Assets::register();
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
            $countryCode = strtoupper(sanitize_text_field((string) ($_POST['country_code'] ?? '')));
            $defaultCurrency = strtoupper(sanitize_text_field((string) ($_POST['default_currency'] ?? '')));
            $supplierId = (new SupplierService())->save([
                'id' => $supplierId,
                'internal_code' => sanitize_text_field((string) ($_POST['internal_code'] ?? '')),
                'legal_name' => sanitize_text_field((string) ($_POST['legal_name'] ?? '')),
                'trading_name' => sanitize_text_field((string) ($_POST['trading_name'] ?? '')),
                'contact_name' => sanitize_text_field((string) ($_POST['contact_name'] ?? '')),
                'phone' => sanitize_text_field((string) ($_POST['phone'] ?? '')),
                'email' => sanitize_email((string) ($_POST['email'] ?? '')),
                'address' => sanitize_textarea_field((string) ($_POST['address'] ?? '')),
                'country_code' => $countryCode,
                'registration_number' => sanitize_text_field((string) ($_POST['registration_number'] ?? '')),
                'tax_number' => sanitize_text_field((string) ($_POST['tax_number'] ?? '')),
                'default_currency' => $defaultCurrency,
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
        $query = self::queryText('supplier_search');
        $statusFilter = self::queryKey('supplier_status');
        if (! in_array($statusFilter, ['', SupplierStatus::ACTIVE, SupplierStatus::INACTIVE, SupplierStatus::SUSPENDED], true)) {
            $statusFilter = '';
        }
        $includeArchived = self::canViewArchived()
            && isset($_GET['include_archived'])
            && (string) $_GET['include_archived'] === '1';
        $suppliers = [];
        $selected = null;
        $selectedId = max(0, (int) ($_GET['supplier_id'] ?? 0));
        try {
            $suppliers = $service->search($query, 200, $includeArchived);
            if ($statusFilter !== '') {
                $suppliers = array_values(array_filter($suppliers, static fn (array $supplier): bool => (string) ($supplier['status'] ?? '') === $statusFilter));
            }
            if ($selectedId > 0) {
                $selected = $service->find($selectedId);
            }
        } catch (Throwable $error) {
            unset($error);
        }

        $status = self::queryKey('safecontracts_status');
        $canCreate = current_user_can(Capabilities::CREATE_SUPPLIERS) || current_user_can(Capabilities::MANAGE_REFERENCE_DATA);
        $canEdit = current_user_can(Capabilities::EDIT_SUPPLIERS) || current_user_can(Capabilities::MANAGE_REFERENCE_DATA);
        $canArchive = current_user_can(Capabilities::ARCHIVE_SUPPLIERS) || current_user_can(Capabilities::MANAGE_REFERENCE_DATA);
        $selectedCountry = strtoupper(trim((string) ($selected['country_code'] ?? '')));
        $selectedCurrency = strtoupper(trim((string) ($selected['default_currency'] ?? '')));
        $read = new AdminReadRepository();
        $countryChoices = AdminLookupOptions::countryChoices($selectedCountry);
        $currencyChoices = AdminLookupOptions::currencyChoices($read, $selectedCurrency);

        $contractCounts = [];
        $selectedContracts = [];
        try {
            foreach ($read->contracts([]) as $contract) {
                if ((string) ($contract['counterparty_type'] ?? '') !== 'supplier') {
                    continue;
                }
                $supplierId = (int) ($contract['supplier_id'] ?? $contract['counterparty_id'] ?? 0);
                if ($supplierId <= 0) {
                    continue;
                }
                $contractCounts[$supplierId] = ($contractCounts[$supplierId] ?? 0) + 1;
                if ($selectedId > 0 && $supplierId === $selectedId) {
                    $selectedContracts[] = $contract;
                }
            }
        } catch (Throwable $error) {
            unset($error);
        }

        $activeCount = count(array_filter($suppliers, static fn (array $supplier): bool => empty($supplier['is_archived']) && (string) ($supplier['status'] ?? '') === SupplierStatus::ACTIVE));
        $archivedCount = count(array_filter($suppliers, static fn (array $supplier): bool => ! empty($supplier['is_archived'])));
        $payableContracts = array_sum(array_map(static fn (array $supplier): int => $contractCounts[(int) ($supplier['id'] ?? 0)] ?? 0, $suppliers));
        $totalRows = count($suppliers);
        $totalPages = max(1, (int) ceil($totalRows / self::PAGE_SIZE));
        $currentPage = min($totalPages, max(1, (int) ($_GET['supplier_page'] ?? 1)));
        $pageRows = array_slice($suppliers, ($currentPage - 1) * self::PAGE_SIZE, self::PAGE_SIZE);
        $showEditor = ($selected === null && $canCreate) || ($selected !== null && ($canEdit || ! empty($selected['is_archived'])));
        ?>
        <div class="wrap safecontracts-settings safecontracts-suppliers safecontracts-worker1" dir="auto">
            <header class="safecontracts-worker1__header">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html(self::text('Supplier master data · Accounts Payable')); ?></p>
                    <h1><?php echo esc_html__('Suppliers', 'safecontracts'); ?></h1>
                    <p class="description"><?php echo esc_html__('Supplier master data is authoritative for Accounts Payable contracts. Archiving removes a supplier from new operations while preserving contract and financial history.', 'safecontracts'); ?></p>
                </div>
                <div class="safecontracts-worker1__header-actions">
                    <?php if (current_user_can(Capabilities::VIEW_PAYABLES)) : ?>
                        <a class="button" href="<?php echo esc_url(add_query_arg(['page' => FinancePage::SLUG, 'direction' => 'payable'], admin_url('admin.php'))); ?>"><?php echo esc_html__('Accounts Payable', 'safecontracts'); ?></a>
                    <?php endif; ?>
                    <a class="button" href="<?php echo esc_url(add_query_arg(['page' => ContractsPage::SLUG, 'financial_direction' => 'payable'], admin_url('admin.php'))); ?>"><?php echo esc_html(self::text('Supplier contracts')); ?></a>
                    <?php if ($canCreate && $selected) : ?><a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html(self::text('Add supplier')); ?></a><?php endif; ?>
                </div>
            </header>

            <div class="safecontracts-worker1__notice-stack">
                <?php if ($status === 'saved') : ?><div class="notice notice-success inline"><p><?php echo esc_html__('Supplier saved.', 'safecontracts'); ?></p></div><?php endif; ?>
                <?php if ($status === 'invalid') : ?><div class="notice notice-error inline"><p><?php echo esc_html__('Supplier was not saved. Check required fields, duplicate identifiers and validation rules.', 'safecontracts'); ?></p></div><?php endif; ?>
                <?php if ($status === 'archived') : ?><div class="notice notice-success inline"><p><?php echo esc_html__('Supplier archived. Existing contract and finance history is preserved.', 'safecontracts'); ?></p></div><?php endif; ?>
                <?php if ($status === 'archive_failed') : ?><div class="notice notice-error inline"><p><?php echo esc_html__('Supplier could not be archived.', 'safecontracts'); ?></p></div><?php endif; ?>
            </div>

            <section class="safecontracts-worker1__metrics" aria-label="<?php echo esc_attr(self::text('Supplier summary')); ?>">
                <article class="safecontracts-worker1__metric"><span><?php echo esc_html(self::text('Visible suppliers')); ?></span><strong><?php echo esc_html((string) $totalRows); ?></strong><small><?php echo esc_html(self::text('Current authorized result set')); ?></small></article>
                <article class="safecontracts-worker1__metric safecontracts-worker1__metric--payable"><span><?php echo esc_html(self::text('Payable contracts')); ?></span><strong><?php echo esc_html((string) $payableContracts); ?></strong><small><?php echo esc_html(self::text('Linked supplier contracts')); ?></small></article>
                <article class="safecontracts-worker1__metric"><span><?php echo esc_html(self::text('Active suppliers')); ?></span><strong><?php echo esc_html((string) $activeCount); ?></strong><small><?php echo esc_html(self::text('Available for active operations')); ?></small></article>
                <article class="safecontracts-worker1__metric<?php echo $archivedCount > 0 ? ' safecontracts-worker1__metric--warning' : ''; ?>"><span><?php echo esc_html(self::text('Archived in result')); ?></span><strong><?php echo esc_html((string) $archivedCount); ?></strong><small><?php echo esc_html($includeArchived ? self::text('Archive visibility is enabled') : self::text('Archived suppliers are hidden')); ?></small></article>
            </section>

            <section class="safecontracts-worker1__toolbar">
                <form method="get" class="safecontracts-worker1__filter-grid">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                    <?php if ($selectedId > 0) : ?><input type="hidden" name="supplier_id" value="<?php echo esc_attr((string) $selectedId); ?>"><?php endif; ?>
                    <label><?php echo esc_html__('Search suppliers', 'safecontracts'); ?><input type="search" name="supplier_search" value="<?php echo esc_attr($query); ?>" placeholder="<?php echo esc_attr__('Name, code, registration or tax number', 'safecontracts'); ?>"></label>
                    <label><?php echo esc_html__('Status', 'safecontracts'); ?><select name="supplier_status"><option value=""><?php echo esc_html__('All statuses', 'safecontracts'); ?></option><?php foreach ([SupplierStatus::ACTIVE, SupplierStatus::INACTIVE, SupplierStatus::SUSPENDED] as $supplierStatus) : ?><option value="<?php echo esc_attr($supplierStatus); ?>" <?php selected($statusFilter, $supplierStatus); ?>><?php echo esc_html(self::statusLabel($supplierStatus)); ?></option><?php endforeach; ?></select></label>
                    <?php if (self::canViewArchived()) : ?>
                        <label class="safecontracts-worker1__checkbox"><input type="checkbox" name="include_archived" value="1" <?php checked($includeArchived); ?>> <?php echo esc_html__('Include archived', 'safecontracts'); ?></label>
                    <?php endif; ?>
                    <div class="safecontracts-worker1__filter-actions"><button class="button button-primary" type="submit"><?php echo esc_html__('Apply filters', 'safecontracts'); ?></button><a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html(self::text('Clear')); ?></a></div>
                </form>
            </section>

            <div class="safecontracts-worker1__layout<?php echo $showEditor ? '' : ' safecontracts-worker1__layout--single'; ?>">
                <section class="safecontracts-worker1__panel">
                    <div class="safecontracts-worker1__panel-head"><div><h2><?php echo esc_html__('Supplier directory', 'safecontracts'); ?></h2><p><?php echo esc_html(self::text('Counterparties for payable obligations')); ?></p></div><span class="safecontracts-worker1__count"><?php echo esc_html((string) $totalRows); ?></span></div>
                    <div class="safecontracts-worker1__panel-body--flush">
                        <?php if ($pageRows === []) : ?>
                            <div class="safecontracts-worker1__empty"><span class="safecontracts-worker1__empty-mark" aria-hidden="true">+</span><h3><?php echo esc_html(self::text('No suppliers match the current filters')); ?></h3><p><?php echo esc_html(self::text('Change the search or status filter, enable archived visibility if authorized, or create a supplier.')); ?></p></div>
                        <?php else : ?>
                            <div class="safecontracts-worker1__table-scroll">
                                <table class="widefat striped">
                                    <thead><tr><th><?php echo esc_html__('Supplier', 'safecontracts'); ?></th><th><?php echo esc_html__('Code', 'safecontracts'); ?></th><th><?php echo esc_html__('Currency', 'safecontracts'); ?></th><th><?php echo esc_html__('Terms', 'safecontracts'); ?></th><th><?php echo esc_html(self::text('AP contracts')); ?></th><th><?php echo esc_html__('Status', 'safecontracts'); ?></th><th><?php echo esc_html__('Actions', 'safecontracts'); ?></th></tr></thead>
                                    <tbody>
                                    <?php foreach ($pageRows as $supplier) : $supplierId = (int) ($supplier['id'] ?? 0); $isArchived = ! empty($supplier['is_archived']); ?>
                                        <tr>
                                            <td><div class="safecontracts-worker1__primary-cell"><strong><?php echo esc_html((string) $supplier['legal_name']); ?></strong><span class="safecontracts-worker1__secondary"><?php echo esc_html((string) ($supplier['trading_name'] ?: $supplier['email'] ?: $supplier['phone'] ?: '')); ?></span></div></td>
                                            <td><?php echo esc_html((string) ($supplier['internal_code'] ?: '—')); ?></td>
                                            <td><?php echo esc_html((string) ($supplier['default_currency'] ?: '—')); ?></td>
                                            <td><?php echo esc_html((string) ($supplier['payment_terms'] ?: '—')); ?></td>
                                            <td><strong><?php echo esc_html((string) ($contractCounts[$supplierId] ?? 0)); ?></strong></td>
                                            <td><span class="safecontracts-worker1__status safecontracts-worker1__status--<?php echo esc_attr($isArchived ? 'archived' : (string) $supplier['status']); ?>"><?php echo esc_html($isArchived ? __('Archived', 'safecontracts') : self::statusLabel((string) $supplier['status'])); ?></span></td>
                                            <td><div class="safecontracts-dashboard-table-actions">
                                                <a class="button button-small" href="<?php echo esc_url(self::supplierUrl($supplierId, $query, $statusFilter, $includeArchived, $currentPage)); ?>"><?php echo esc_html__('Open', 'safecontracts'); ?></a>
                                                <?php if ($canArchive && ! $isArchived) : ?>
                                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-safecontracts-delete-form data-delete-message="<?php echo esc_attr__('Archive this supplier? Existing contracts and financial history will remain available, but the supplier cannot be used for new contracts.', 'safecontracts'); ?>">
                                                        <input type="hidden" name="action" value="<?php echo esc_attr(self::ARCHIVE_ACTION); ?>"><input type="hidden" name="supplier_id" value="<?php echo esc_attr((string) $supplierId); ?>"><?php wp_nonce_field(self::ARCHIVE_ACTION . '_' . $supplierId); ?>
                                                        <button type="submit" class="button button-small safecontracts-delete-button"><?php echo esc_html__('Archive', 'safecontracts'); ?></button>
                                                    </form>
                                                <?php endif; ?>
                                            </div></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php self::renderPagination($currentPage, $totalPages, $totalRows, $query, $statusFilter, $includeArchived, $selectedId); ?>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if ($showEditor) : ?>
                    <aside class="safecontracts-worker1__panel safecontracts-worker1__editor">
                        <div class="safecontracts-worker1__panel-head"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html($selected ? __('Supplier profile', 'safecontracts') : self::text('New payable counterparty')); ?></p><h2><?php echo $selected ? esc_html((string) $selected['legal_name']) : esc_html__('Create supplier', 'safecontracts'); ?></h2></div></div>
                        <div class="safecontracts-worker1__panel-body">
                            <?php if ($selected) : ?>
                                <div class="safecontracts-worker1__context">
                                    <div class="safecontracts-worker1__context-row"><span><?php echo esc_html(self::text('Financial direction')); ?></span><strong><?php echo esc_html__('Accounts Payable', 'safecontracts'); ?></strong></div>
                                    <div class="safecontracts-worker1__context-row"><span><?php echo esc_html(self::text('Supplier contracts')); ?></span><strong><?php echo esc_html((string) count($selectedContracts)); ?></strong></div>
                                    <div class="safecontracts-worker1__context-row"><span><?php echo esc_html__('Default currency', 'safecontracts'); ?></span><strong><?php echo esc_html((string) ($selected['default_currency'] ?: '—')); ?></strong></div>
                                </div>
                            <?php endif; ?>
                            <?php if ($selected && ! empty($selected['is_archived'])) : ?><div class="notice notice-warning inline"><p><?php echo esc_html__('Archived suppliers are read-only. Their historical contracts and financial records remain available.', 'safecontracts'); ?></p></div><?php endif; ?>
                            <?php if ($selected && $selectedContracts !== []) : ?>
                                <div class="safecontracts-worker1__form-section">
                                    <h3><?php echo esc_html(self::text('Recent supplier contracts')); ?></h3>
                                    <ul class="safecontracts-worker1__attachment-list">
                                        <?php foreach (array_slice($selectedContracts, 0, 5) as $contract) : ?><li><span><?php echo esc_html((string) ($contract['contract_number'] ?? '')); ?></span><a href="<?php echo esc_url(add_query_arg(['page' => ContractsPage::SLUG, 'contract_id' => (int) ($contract['id'] ?? 0)], admin_url('admin.php'))); ?>"><?php echo esc_html__('Open', 'safecontracts'); ?></a></li><?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <?php if (! $selected || empty($selected['is_archived'])) : ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><input type="hidden" name="supplier_id" value="<?php echo esc_attr((string) ($selected['id'] ?? 0)); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>
                                    <div class="safecontracts-worker1__form-section"><h3><?php echo esc_html(self::text('Identity & status')); ?></h3><div class="safecontracts-worker1__field-grid"><label class="safecontracts-worker1__field-full"><?php echo esc_html__('Legal name', 'safecontracts'); ?><input name="legal_name" maxlength="191" required value="<?php echo esc_attr((string) ($selected['legal_name'] ?? '')); ?>"></label><label><?php echo esc_html__('Trading name', 'safecontracts'); ?><input name="trading_name" maxlength="191" value="<?php echo esc_attr((string) ($selected['trading_name'] ?? '')); ?>"></label><label><?php echo esc_html__('Internal code', 'safecontracts'); ?><input name="internal_code" maxlength="100" value="<?php echo esc_attr((string) ($selected['internal_code'] ?? '')); ?>"></label><label><?php echo esc_html__('Status', 'safecontracts'); ?><select name="status"><?php foreach ([SupplierStatus::ACTIVE, SupplierStatus::INACTIVE, SupplierStatus::SUSPENDED] as $supplierStatus) : ?><option value="<?php echo esc_attr($supplierStatus); ?>" <?php selected((string) ($selected['status'] ?? SupplierStatus::ACTIVE), $supplierStatus); ?>><?php echo esc_html(self::statusLabel($supplierStatus)); ?></option><?php endforeach; ?></select></label></div></div>
                                    <div class="safecontracts-worker1__form-section"><h3><?php echo esc_html__('Contact', 'safecontracts'); ?></h3><div class="safecontracts-worker1__field-grid"><label><?php echo esc_html__('Contact name', 'safecontracts'); ?><input name="contact_name" maxlength="191" value="<?php echo esc_attr((string) ($selected['contact_name'] ?? '')); ?>"></label><label><?php echo esc_html__('Phone', 'safecontracts'); ?><input name="phone" maxlength="64" value="<?php echo esc_attr((string) ($selected['phone'] ?? '')); ?>"></label><label><?php echo esc_html__('Email', 'safecontracts'); ?><input type="email" name="email" maxlength="191" value="<?php echo esc_attr((string) ($selected['email'] ?? '')); ?>"></label><label><?php echo esc_html__('Country', 'safecontracts'); ?><select name="country_code"><option value=""><?php echo esc_html__('Select country', 'safecontracts'); ?></option><?php foreach ($countryChoices as $countryCode => $countryLabel) : ?><option value="<?php echo esc_attr($countryCode); ?>" <?php selected($selectedCountry, $countryCode); ?>><?php echo esc_html($countryLabel); ?></option><?php endforeach; ?></select></label><label class="safecontracts-worker1__field-full"><?php echo esc_html__('Address', 'safecontracts'); ?><textarea rows="3" name="address"><?php echo esc_textarea((string) ($selected['address'] ?? '')); ?></textarea></label></div></div>
                                    <div class="safecontracts-worker1__form-section"><h3><?php echo esc_html(self::text('Commercial profile')); ?></h3><div class="safecontracts-worker1__field-grid"><label><?php echo esc_html__('Registration number', 'safecontracts'); ?><input name="registration_number" maxlength="100" value="<?php echo esc_attr((string) ($selected['registration_number'] ?? '')); ?>"></label><label><?php echo esc_html__('Tax / VAT number', 'safecontracts'); ?><input name="tax_number" maxlength="100" value="<?php echo esc_attr((string) ($selected['tax_number'] ?? '')); ?>"></label><label><?php echo esc_html__('Default currency', 'safecontracts'); ?><select name="default_currency"><option value=""><?php echo esc_html__('Select currency', 'safecontracts'); ?></option><?php foreach ($currencyChoices as $currencyChoice) : ?><option value="<?php echo esc_attr($currencyChoice); ?>" <?php selected($selectedCurrency, $currencyChoice); ?>><?php echo esc_html($currencyChoice); ?></option><?php endforeach; ?></select></label><label><?php echo esc_html__('Payment terms', 'safecontracts'); ?><input name="payment_terms" maxlength="191" placeholder="<?php echo esc_attr__('e.g. Net 30', 'safecontracts'); ?>" value="<?php echo esc_attr((string) ($selected['payment_terms'] ?? '')); ?>"></label><label class="safecontracts-worker1__field-full"><?php echo esc_html__('Notes', 'safecontracts'); ?><textarea rows="4" name="notes"><?php echo esc_textarea((string) ($selected['notes'] ?? '')); ?></textarea></label></div></div>
                                    <?php submit_button($selected ? __('Save supplier', 'safecontracts') : __('Create supplier', 'safecontracts'), 'primary', 'submit', false); ?>
                                    <?php if ($selected) : ?> <a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Cancel', 'safecontracts'); ?></a><?php endif; ?>
                                </form>
                            <?php else : ?>
                                <div class="safecontracts-worker1__summary-list"><div class="safecontracts-worker1__summary-item"><span><?php echo esc_html__('Registration', 'safecontracts'); ?></span><strong><?php echo esc_html((string) ($selected['registration_number'] ?: '—')); ?></strong></div><div class="safecontracts-worker1__summary-item"><span><?php echo esc_html__('Tax / VAT', 'safecontracts'); ?></span><strong><?php echo esc_html((string) ($selected['tax_number'] ?: '—')); ?></strong></div><div class="safecontracts-worker1__summary-item"><span><?php echo esc_html__('Currency', 'safecontracts'); ?></span><strong><?php echo esc_html((string) ($selected['default_currency'] ?: '—')); ?></strong></div><div class="safecontracts-worker1__summary-item"><span><?php echo esc_html__('Terms', 'safecontracts'); ?></span><strong><?php echo esc_html((string) ($selected['payment_terms'] ?: '—')); ?></strong></div></div>
                            <?php endif; ?>
                        </div>
                    </aside>
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

    private static function text(string $english): string
    {
        if (TranslationCatalog::currentLanguage() !== 'ar') {
            return __($english, 'safecontracts');
        }

        return match ($english) {
            'Supplier master data · Accounts Payable' => 'بيانات الموردين الأساسية · حسابات الدفع',
            'Supplier contracts' => 'عقود الموردين',
            'Add supplier' => 'إضافة مورد',
            'Supplier summary' => 'ملخص الموردين',
            'Visible suppliers' => 'الموردون الظاهرون',
            'Current authorized result set' => 'النتائج الحالية ضمن الصلاحيات',
            'Payable contracts' => 'عقود مستحقة الدفع',
            'Linked supplier contracts' => 'عقود المورد المرتبطة',
            'Active suppliers' => 'الموردون النشطون',
            'Available for active operations' => 'متاح للعمليات النشطة',
            'Archived in result' => 'المؤرشف ضمن النتائج',
            'Archive visibility is enabled' => 'عرض السجلات المؤرشفة مفعّل',
            'Archived suppliers are hidden' => 'الموردون المؤرشفون مخفيون',
            'Clear' => 'مسح',
            'Counterparties for payable obligations' => 'الأطراف المقابلة للالتزامات واجبة الدفع',
            'No suppliers match the current filters' => 'لا يوجد موردون يطابقون الفلاتر الحالية',
            'Change the search or status filter, enable archived visibility if authorized, or create a supplier.' => 'غيّر البحث أو فلتر الحالة، أو فعّل عرض المؤرشف إذا كانت لديك الصلاحية، أو أنشئ مورداً جديداً.',
            'AP contracts' => 'عقود الدفع',
            'New payable counterparty' => 'مورد جديد مستحق الدفع له',
            'Financial direction' => 'الاتجاه المالي',
            'Recent supplier contracts' => 'أحدث عقود المورد',
            'Identity & status' => 'البيانات التعريفية والحالة',
            'Commercial profile' => 'الملف التجاري',
            'Supplier pagination' => 'ترقيم صفحات الموردين',
            '%1$d suppliers · page %2$d of %3$d' => '%1$d مورد · الصفحة %2$d من %3$d',
            default => $english,
        };
    }

    private static function statusLabel(string $status): string
    {
        return TranslationCatalog::text(ucwords(str_replace('_', ' ', $status)));
    }

    private static function queryText(string $key): string
    {
        return isset($_GET[$key]) && is_scalar($_GET[$key]) ? sanitize_text_field((string) $_GET[$key]) : '';
    }

    private static function queryKey(string $key): string
    {
        return isset($_GET[$key]) && is_scalar($_GET[$key]) ? sanitize_key((string) $_GET[$key]) : '';
    }

    private static function supplierUrl(int $supplierId, string $query, string $status, bool $includeArchived, int $page): string
    {
        $args = ['page' => self::SLUG, 'supplier_id' => $supplierId];
        if ($query !== '') { $args['supplier_search'] = $query; }
        if ($status !== '') { $args['supplier_status'] = $status; }
        if ($includeArchived) { $args['include_archived'] = '1'; }
        if ($page > 1) { $args['supplier_page'] = $page; }
        return add_query_arg($args, admin_url('admin.php'));
    }

    private static function renderPagination(int $currentPage, int $totalPages, int $totalRows, string $query, string $status, bool $includeArchived, int $selectedId): void
    {
        if ($totalPages <= 1) {
            return;
        }
        $base = ['page' => self::SLUG];
        if ($query !== '') { $base['supplier_search'] = $query; }
        if ($status !== '') { $base['supplier_status'] = $status; }
        if ($includeArchived) { $base['include_archived'] = '1'; }
        if ($selectedId > 0) { $base['supplier_id'] = $selectedId; }
        ?>
        <nav class="safecontracts-worker1__pagination" aria-label="<?php echo esc_attr(self::text('Supplier pagination')); ?>">
            <span><?php echo esc_html(sprintf(self::text('%1$d suppliers · page %2$d of %3$d'), $totalRows, $currentPage, $totalPages)); ?></span>
            <span class="safecontracts-worker1__pagination-links">
                <?php for ($page = 1; $page <= $totalPages; $page++) : ?><a class="button button-small" <?php if ($page === $currentPage) : ?>aria-current="page"<?php endif; ?> href="<?php echo esc_url(add_query_arg(array_merge($base, ['supplier_page' => $page]), admin_url('admin.php'))); ?>"><?php echo esc_html((string) $page); ?></a><?php endfor; ?>
            </span>
        </nav>
        <?php
    }
}
