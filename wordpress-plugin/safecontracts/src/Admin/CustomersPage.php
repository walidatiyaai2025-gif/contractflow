<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Customers\CustomerService;
use SafeContracts\Deletion\SafeDeletionService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Translations\TranslationCatalog;
use Throwable;

final class CustomersPage
{
    public const SLUG = 'safecontracts-customers';
    public const SAVE_ACTION = 'safecontracts_save_customer';
    public const DELETE_ACTION = 'safecontracts_delete_customer';
    private const PAGE_SIZE = 20;

    public static function register(): void
    {
        Worker1Assets::register();
        add_submenu_page(
            AdminShell::SLUG,
            __('Customers', 'safecontracts'),
            __('Customers', 'safecontracts'),
            Capabilities::ACCESS,
            self::SLUG,
            [self::class, 'render']
        );
    }

    public static function handleSave(): void
    {
        if (! current_user_can(Capabilities::MANAGE_REFERENCE_DATA)) {
            wp_die(__('You do not have permission to manage customers.', 'safecontracts'));
        }
        check_admin_referer(self::SAVE_ACTION);
        $status = 'saved';
        try {
            (new CustomerService())->save([
                'id' => (int) ($_POST['customer_id'] ?? 0),
                'internal_code' => sanitize_text_field((string) ($_POST['internal_code'] ?? '')),
                'name' => sanitize_text_field((string) ($_POST['name'] ?? '')),
                'contact_name' => sanitize_text_field((string) ($_POST['contact_name'] ?? '')),
                'email' => sanitize_text_field((string) ($_POST['email'] ?? '')),
                'phone' => sanitize_text_field((string) ($_POST['phone'] ?? '')),
                'notes' => sanitize_textarea_field((string) ($_POST['notes'] ?? '')),
                'is_active' => isset($_POST['is_active']),
            ]);
        } catch (Throwable $error) {
            unset($error);
            $status = 'invalid';
        }
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'safecontracts_status' => $status], admin_url('admin.php')));
        exit;
    }

    public static function handleDelete(): void
    {
        if (! current_user_can(Capabilities::MANAGE_REFERENCE_DATA)) {
            wp_die(__('You do not have permission to delete customers.', 'safecontracts'));
        }
        $customerId = max(0, (int) ($_POST['customer_id'] ?? 0));
        check_admin_referer(self::DELETE_ACTION . '_' . $customerId);
        $status = 'deleted';
        try {
            (new SafeDeletionService())->archiveCustomer($customerId);
        } catch (Throwable $error) {
            unset($error);
            $status = 'delete_failed';
        }
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'safecontracts_status' => $status], admin_url('admin.php')));
        exit;
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            wp_die(__('You do not have permission to access customers.', 'safecontracts'));
        }

        $read = new AdminReadRepository();
        $filters = DashboardFilters::normalize($_GET);
        $customers = $read->customers($filters);
        $search = self::queryText('customer_search');
        if ($search !== '') {
            $customers = array_values(array_filter($customers, static function (array $customer) use ($search): bool {
                $haystack = implode(' ', [
                    (string) ($customer['internal_code'] ?? ''),
                    (string) ($customer['name'] ?? ''),
                    (string) ($customer['contact_name'] ?? ''),
                    (string) ($customer['email'] ?? ''),
                    (string) ($customer['phone'] ?? ''),
                ]);
                return stripos($haystack, $search) !== false;
            }));
        }

        $editing = null;
        $editId = max(0, (int) ($_GET['customer_id'] ?? 0));
        if ($editId > 0 && current_user_can(Capabilities::MANAGE_REFERENCE_DATA)) {
            $editableRows = $read->customers(['customer_id' => $editId]);
            $editing = $editableRows[0] ?? null;
        }

        $contractCounts = [];
        $customerContracts = [];
        try {
            foreach ($read->contracts([]) as $contract) {
                if ((string) ($contract['counterparty_type'] ?? '') !== 'customer') {
                    continue;
                }
                $customerId = (int) ($contract['customer_id'] ?? $contract['counterparty_id'] ?? 0);
                if ($customerId <= 0) {
                    continue;
                }
                $contractCounts[$customerId] = ($contractCounts[$customerId] ?? 0) + 1;
                if ($editId > 0 && $customerId === $editId) {
                    $customerContracts[] = $contract;
                }
            }
        } catch (Throwable $error) {
            unset($error);
        }

        $contactReady = count(array_filter($customers, static fn (array $customer): bool => trim((string) ($customer['email'] ?? '')) !== '' || trim((string) ($customer['phone'] ?? '')) !== ''));
        $visibleContractCount = array_sum(array_map(static fn (array $customer): int => $contractCounts[(int) ($customer['id'] ?? 0)] ?? 0, $customers));
        $totalRows = count($customers);
        $totalPages = max(1, (int) ceil($totalRows / self::PAGE_SIZE));
        $currentPage = min($totalPages, max(1, (int) ($_GET['customer_page'] ?? 1)));
        $pageRows = array_slice($customers, ($currentPage - 1) * self::PAGE_SIZE, self::PAGE_SIZE);
        $status = self::queryKey('safecontracts_status');
        $canManage = current_user_can(Capabilities::MANAGE_REFERENCE_DATA);
        ?>
        <div class="wrap safecontracts-settings safecontracts-customers safecontracts-worker1" dir="auto">
            <header class="safecontracts-worker1__header">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html(self::text('Customer master data · Accounts Receivable')); ?></p>
                    <h1><?php echo esc_html__('Customers', 'safecontracts'); ?></h1>
                    <p class="description"><?php echo esc_html(self::text('Maintain the customer master used by receivable contracts. Customer identity and contact data stay separate from contract financial authority.')); ?></p>
                </div>
                <div class="safecontracts-worker1__header-actions">
                    <a class="button" href="<?php echo esc_url(add_query_arg(['page' => ContractsPage::SLUG, 'financial_direction' => 'receivable'], admin_url('admin.php'))); ?>"><?php echo esc_html__('Receivable contracts', 'safecontracts'); ?></a>
                    <?php if ($canManage && $editing) : ?>
                        <a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Add customer', 'safecontracts'); ?></a>
                    <?php endif; ?>
                </div>
            </header>

            <div class="safecontracts-worker1__notice-stack">
                <?php if ($status === 'saved') : ?><div class="notice notice-success inline"><p><?php echo esc_html(self::text('Customer saved successfully.')); ?></p></div><?php endif; ?>
                <?php if ($status === 'invalid') : ?><div class="notice notice-error inline"><p><?php echo esc_html(self::text('Customer was not saved. Check required fields and validation rules.')); ?></p></div><?php endif; ?>
                <?php if ($status === 'deleted') : ?><div class="notice notice-success inline"><p><?php echo esc_html(self::text('Customer archived from active records. Linked contracts and history were preserved.')); ?></p></div><?php endif; ?>
                <?php if ($status === 'delete_failed') : ?><div class="notice notice-error inline"><p><?php echo esc_html(self::text('Customer could not be archived.')); ?></p></div><?php endif; ?>
                <?php if (! empty($filters['date_range_error'])) : ?><div class="notice notice-error inline"><p><?php echo esc_html__('Invalid period. Use valid dates and make sure the end date is not earlier than the start date.', 'safecontracts'); ?></p></div><?php endif; ?>
            </div>

            <section class="safecontracts-worker1__metrics" aria-label="<?php echo esc_attr(self::text('Customer summary')); ?>">
                <article class="safecontracts-worker1__metric"><span><?php echo esc_html(self::text('Visible customers')); ?></span><strong><?php echo esc_html((string) $totalRows); ?></strong><small><?php echo esc_html(self::text('Within the current data scope')); ?></small></article>
                <article class="safecontracts-worker1__metric safecontracts-worker1__metric--receivable"><span><?php echo esc_html__('Receivable contracts', 'safecontracts'); ?></span><strong><?php echo esc_html((string) $visibleContractCount); ?></strong><small><?php echo esc_html(self::text('Linked to the visible customers')); ?></small></article>
                <article class="safecontracts-worker1__metric"><span><?php echo esc_html(self::text('Contact-ready')); ?></span><strong><?php echo esc_html((string) $contactReady); ?></strong><small><?php echo esc_html(self::text('Has email or phone')); ?></small></article>
                <article class="safecontracts-worker1__metric"><span><?php echo esc_html(self::text('Current page')); ?></span><strong><?php echo esc_html($currentPage . ' / ' . $totalPages); ?></strong><small><?php echo esc_html(sprintf(self::text('Up to %d customers per page'), self::PAGE_SIZE)); ?></small></article>
            </section>

            <section class="safecontracts-worker1__toolbar">
                <form method="get" class="safecontracts-worker1__filter-grid">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                    <?php if ($editId > 0) : ?><input type="hidden" name="customer_id" value="<?php echo esc_attr((string) $editId); ?>"><?php endif; ?>
                    <label><?php echo esc_html(self::text('Search')); ?><input type="search" name="customer_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr(self::text('Name, code, contact, email or phone')); ?>"></label>
                    <?php AdminPeriodFilter::renderFields($filters); ?>
                    <div class="safecontracts-worker1__filter-actions">
                        <button class="button button-primary" type="submit"><?php echo esc_html__('Apply filters', 'safecontracts'); ?></button>
                        <a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html(self::text('Clear')); ?></a>
                    </div>
                </form>
                <p class="description"><?php echo esc_html(self::text('The period filter uses the customer record creation date. Search is applied only to records already visible in your authorized data scope.')); ?></p>
            </section>

            <div class="safecontracts-worker1__layout<?php echo $canManage ? '' : ' safecontracts-worker1__layout--single'; ?>">
                <section class="safecontracts-worker1__panel">
                    <div class="safecontracts-worker1__panel-head">
                        <div><h2><?php echo esc_html(self::text('Customer directory')); ?></h2><p><?php echo esc_html(self::text('Active customer master records')); ?></p></div>
                        <span class="safecontracts-worker1__count"><?php echo esc_html((string) $totalRows); ?></span>
                    </div>
                    <div class="safecontracts-worker1__panel-body--flush">
                        <?php if ($pageRows === []) : ?>
                            <div class="safecontracts-worker1__empty">
                                <span class="safecontracts-worker1__empty-mark" aria-hidden="true">+</span>
                                <h3><?php echo esc_html(self::text('No customers match the current filters')); ?></h3>
                                <p><?php echo esc_html(self::text('Clear the search or period filter, or add a customer if you have permission.')); ?></p>
                            </div>
                        <?php else : ?>
                            <div class="safecontracts-worker1__table-scroll">
                                <table class="widefat striped">
                                    <thead><tr><th><?php echo esc_html__('Customer', 'safecontracts'); ?></th><th><?php echo esc_html__('Code', 'safecontracts'); ?></th><th><?php echo esc_html__('Contact', 'safecontracts'); ?></th><th><?php echo esc_html(self::text('AR contracts')); ?></th><th><?php echo esc_html__('Status', 'safecontracts'); ?></th><th><?php echo esc_html__('Actions', 'safecontracts'); ?></th></tr></thead>
                                    <tbody>
                                    <?php foreach ($pageRows as $customer) : $customerId = (int) ($customer['id'] ?? 0); ?>
                                        <tr>
                                            <td><div class="safecontracts-worker1__primary-cell"><a href="<?php echo esc_url(self::customerUrl($customerId, $filters, $search, $currentPage)); ?>"><?php echo esc_html((string) $customer['name']); ?></a><span class="safecontracts-worker1__secondary"><?php echo esc_html((string) ($customer['email'] ?: $customer['phone'] ?: self::text('No contact channel'))); ?></span></div></td>
                                            <td><?php echo esc_html((string) ($customer['internal_code'] ?: '—')); ?></td>
                                            <td><div class="safecontracts-worker1__primary-cell"><span><?php echo esc_html((string) ($customer['contact_name'] ?: '—')); ?></span><span class="safecontracts-worker1__secondary"><?php echo esc_html((string) ($customer['phone'] ?: '')); ?></span></div></td>
                                            <td><strong><?php echo esc_html((string) ($contractCounts[$customerId] ?? 0)); ?></strong></td>
                                            <td><span class="safecontracts-worker1__status safecontracts-worker1__status--active"><?php echo esc_html__('Active', 'safecontracts'); ?></span></td>
                                            <td>
                                                <?php if ($canManage) : ?>
                                                    <div class="safecontracts-dashboard-table-actions">
                                                        <a class="button button-small" href="<?php echo esc_url(self::customerUrl($customerId, $filters, $search, $currentPage)); ?>"><?php echo esc_html__('Open', 'safecontracts'); ?></a>
                                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-safecontracts-delete-form data-delete-message="<?php echo esc_attr__('Delete this customer from active SafeContracts records? Linked contracts and history will be preserved.', 'safecontracts'); ?>">
                                                            <input type="hidden" name="action" value="<?php echo esc_attr(self::DELETE_ACTION); ?>">
                                                            <input type="hidden" name="customer_id" value="<?php echo esc_attr((string) $customerId); ?>">
                                                            <?php wp_nonce_field(self::DELETE_ACTION . '_' . $customerId); ?>
                                                            <button type="submit" class="button button-small safecontracts-delete-button"><?php echo esc_html__('Delete', 'safecontracts'); ?></button>
                                                        </form>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php self::renderPagination($currentPage, $totalPages, $totalRows, $filters, $search, $editId); ?>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if ($canManage) : ?>
                    <aside class="safecontracts-worker1__panel safecontracts-worker1__editor">
                        <div class="safecontracts-worker1__panel-head">
                            <div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html($editing ? self::text('Customer profile') : self::text('New receivable party')); ?></p><h2><?php echo $editing ? esc_html((string) $editing['name']) : esc_html__('Add customer', 'safecontracts'); ?></h2></div>
                        </div>
                        <div class="safecontracts-worker1__panel-body">
                            <?php if ($editing) : ?>
                                <div class="safecontracts-worker1__context">
                                    <div class="safecontracts-worker1__context-row"><span><?php echo esc_html__('Status', 'safecontracts'); ?></span><strong><?php echo esc_html(self::text('Active customer')); ?></strong></div>
                                    <div class="safecontracts-worker1__context-row"><span><?php echo esc_html__('Receivable contracts', 'safecontracts'); ?></span><strong><?php echo esc_html((string) count($customerContracts)); ?></strong></div>
                                    <div class="safecontracts-worker1__context-row"><span><?php echo esc_html(self::text('Updated')); ?></span><strong><?php echo esc_html((string) ($editing['updated_at'] ?? '—')); ?></strong></div>
                                </div>
                                <?php if ($customerContracts !== []) : ?>
                                    <div class="safecontracts-worker1__form-section">
                                        <h3><?php echo esc_html(self::text('Recent linked contracts')); ?></h3>
                                        <ul class="safecontracts-worker1__attachment-list">
                                            <?php foreach (array_slice($customerContracts, 0, 5) as $contract) : ?>
                                                <li><span><?php echo esc_html((string) ($contract['contract_number'] ?? '')); ?></span><a href="<?php echo esc_url(add_query_arg(['page' => ContractsPage::SLUG, 'contract_id' => (int) ($contract['id'] ?? 0)], admin_url('admin.php'))); ?>"><?php echo esc_html__('Open', 'safecontracts'); ?></a></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">
                                <input type="hidden" name="customer_id" value="<?php echo esc_attr((string) ($editing['id'] ?? 0)); ?>">
                                <?php wp_nonce_field(self::SAVE_ACTION); ?>
                                <div class="safecontracts-worker1__form-section">
                                    <h3><?php echo esc_html(self::text('Identity')); ?></h3>
                                    <div class="safecontracts-worker1__field-grid">
                                        <label class="safecontracts-worker1__field-full"><?php echo esc_html__('Name', 'safecontracts'); ?><input name="name" required maxlength="191" value="<?php echo esc_attr((string) ($editing['name'] ?? '')); ?>"></label>
                                        <label><?php echo esc_html__('Internal code', 'safecontracts'); ?><input name="internal_code" maxlength="100" value="<?php echo esc_attr((string) ($editing['internal_code'] ?? '')); ?>"></label>
                                        <label class="safecontracts-worker1__checkbox"><input type="checkbox" name="is_active" value="1" <?php checked((bool) ($editing['is_active'] ?? true)); ?>> <?php echo esc_html__('Active', 'safecontracts'); ?></label>
                                    </div>
                                </div>
                                <div class="safecontracts-worker1__form-section">
                                    <h3><?php echo esc_html__('Contact', 'safecontracts'); ?></h3>
                                    <div class="safecontracts-worker1__field-grid">
                                        <label class="safecontracts-worker1__field-full"><?php echo esc_html__('Contact name', 'safecontracts'); ?><input name="contact_name" maxlength="191" value="<?php echo esc_attr((string) ($editing['contact_name'] ?? '')); ?>"></label>
                                        <label><?php echo esc_html__('Email', 'safecontracts'); ?><input type="email" name="email" maxlength="191" value="<?php echo esc_attr((string) ($editing['email'] ?? '')); ?>"></label>
                                        <label><?php echo esc_html__('Phone', 'safecontracts'); ?><input name="phone" maxlength="64" value="<?php echo esc_attr((string) ($editing['phone'] ?? '')); ?>"></label>
                                    </div>
                                </div>
                                <div class="safecontracts-worker1__form-section">
                                    <h3><?php echo esc_html__('Notes', 'safecontracts'); ?></h3>
                                    <label class="safecontracts-worker1__field-full"><textarea name="notes" rows="4"><?php echo esc_textarea((string) ($editing['notes'] ?? '')); ?></textarea></label>
                                </div>
                                <?php submit_button($editing ? __('Update customer', 'safecontracts') : __('Add customer', 'safecontracts'), 'primary', 'submit', false); ?>
                                <?php if ($editing) : ?> <a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Cancel', 'safecontracts'); ?></a><?php endif; ?>
                            </form>
                        </div>
                    </aside>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private static function queryText(string $key): string
    {
        return isset($_GET[$key]) && is_scalar($_GET[$key]) ? sanitize_text_field((string) $_GET[$key]) : '';
    }

    private static function queryKey(string $key): string
    {
        return isset($_GET[$key]) && is_scalar($_GET[$key]) ? sanitize_key((string) $_GET[$key]) : '';
    }

    /** @param array<string,mixed> $filters */
    private static function customerUrl(int $customerId, array $filters, string $search, int $page): string
    {
        $args = ['page' => self::SLUG, 'customer_id' => $customerId];
        if ($search !== '') { $args['customer_search'] = $search; }
        if (! empty($filters['date_from'])) { $args['date_from'] = (string) $filters['date_from']; }
        if (! empty($filters['date_to'])) { $args['date_to'] = (string) $filters['date_to']; }
        if ($page > 1) { $args['customer_page'] = $page; }
        return add_query_arg($args, admin_url('admin.php'));
    }

    private static function text(string $english): string
    {
        if (TranslationCatalog::currentLanguage() !== 'ar') {
            return __($english, 'safecontracts');
        }

        return match ($english) {
            'Customer master data · Accounts Receivable' => 'بيانات العملاء الأساسية · حسابات القبض',
            'Maintain the customer master used by receivable contracts. Customer identity and contact data stay separate from contract financial authority.' => 'إدارة بيانات العملاء المستخدمة في عقود المبالغ المستحقة لنا، مع إبقاء بيانات الهوية والتواصل منفصلة عن الصلاحيات المالية للعقد.',
            'Customer saved successfully.' => 'تم حفظ العميل بنجاح.',
            'Customer was not saved. Check required fields and validation rules.' => 'لم يتم حفظ العميل. راجع الحقول المطلوبة وقواعد التحقق.',
            'Customer archived from active records. Linked contracts and history were preserved.' => 'تمت أرشفة العميل من السجلات النشطة مع الاحتفاظ بالعقود المرتبطة والسجل التاريخي.',
            'Customer could not be archived.' => 'تعذر أرشفة العميل.',
            'Customer summary' => 'ملخص العملاء',
            'Visible customers' => 'العملاء الظاهرون',
            'Within the current data scope' => 'ضمن نطاق البيانات الحالي',
            'Linked to the visible customers' => 'مرتبطة بالعملاء الظاهرين',
            'Contact-ready' => 'بيانات التواصل متاحة',
            'Has email or phone' => 'يتوفر بريد إلكتروني أو هاتف',
            'Current page' => 'الصفحة الحالية',
            'Up to %d customers per page' => 'حتى %d عميلاً في الصفحة',
            'Search' => 'بحث',
            'Name, code, contact, email or phone' => 'الاسم أو الكود أو جهة الاتصال أو البريد أو الهاتف',
            'Clear' => 'مسح',
            'The period filter uses the customer record creation date. Search is applied only to records already visible in your authorized data scope.' => 'يستخدم فلتر الفترة تاريخ إنشاء سجل العميل، ويُطبّق البحث فقط على السجلات الظاهرة ضمن نطاق صلاحياتك.',
            'Customer directory' => 'دليل العملاء',
            'Active customer master records' => 'سجلات العملاء النشطة',
            'No customers match the current filters' => 'لا يوجد عملاء يطابقون الفلاتر الحالية',
            'Clear the search or period filter, or add a customer if you have permission.' => 'امسح البحث أو فلتر الفترة، أو أضف عميلاً إذا كانت لديك الصلاحية.',
            'AR contracts' => 'عقود القبض',
            'No contact channel' => 'لا توجد وسيلة تواصل',
            'Customer profile' => 'ملف العميل',
            'New receivable party' => 'عميل جديد مستحق لنا',
            'Active customer' => 'عميل نشط',
            'Updated' => 'آخر تحديث',
            'Recent linked contracts' => 'أحدث العقود المرتبطة',
            'Identity' => 'البيانات التعريفية',
            'Customer pagination' => 'ترقيم صفحات العملاء',
            '%1$d customers · page %2$d of %3$d' => '%1$d عميل · الصفحة %2$d من %3$d',
            default => $english,
        };
    }

    /** @param array<string,mixed> $filters */
    private static function renderPagination(int $currentPage, int $totalPages, int $totalRows, array $filters, string $search, int $editId): void
    {
        if ($totalPages <= 1) {
            return;
        }
        $base = ['page' => self::SLUG];
        if ($search !== '') { $base['customer_search'] = $search; }
        if (! empty($filters['date_from'])) { $base['date_from'] = (string) $filters['date_from']; }
        if (! empty($filters['date_to'])) { $base['date_to'] = (string) $filters['date_to']; }
        if ($editId > 0) { $base['customer_id'] = $editId; }
        ?>
        <nav class="safecontracts-worker1__pagination" aria-label="<?php echo esc_attr(self::text('Customer pagination')); ?>">
            <span><?php echo esc_html(sprintf(self::text('%1$d customers · page %2$d of %3$d'), $totalRows, $currentPage, $totalPages)); ?></span>
            <span class="safecontracts-worker1__pagination-links">
                <?php for ($page = 1; $page <= $totalPages; $page++) : ?>
                    <?php $url = add_query_arg(array_merge($base, ['customer_page' => $page]), admin_url('admin.php')); ?>
                    <a class="button button-small" <?php if ($page === $currentPage) : ?>aria-current="page"<?php endif; ?> href="<?php echo esc_url($url); ?>"><?php echo esc_html((string) $page); ?></a>
                <?php endfor; ?>
            </span>
        </nav>
        <?php
    }
}
