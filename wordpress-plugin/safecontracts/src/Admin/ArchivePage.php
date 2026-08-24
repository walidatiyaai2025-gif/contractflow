<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;
use SafeContracts\Translations\TranslationCatalog;

final class ArchivePage
{
    public const SLUG = 'safecontracts-archive';
    private const PAGE_SIZE = 25;

    public static function register(): void
    {
        Worker1Assets::register();
        add_submenu_page(
            AdminShell::SLUG,
            __('Archive', 'safecontracts'),
            __('Archive', 'safecontracts'),
            Capabilities::VIEW_ALL,
            self::SLUG,
            [self::class, 'render']
        );
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::VIEW_ALL)) {
            wp_die(__('You do not have permission to view the archive.', 'safecontracts'));
        }

        $canViewSuppliers = current_user_can(Capabilities::VIEW_SUPPLIERS);
        $allRows = self::rows();
        $counts = [];
        foreach ($allRows as $row) {
            $type = (string) $row['type'];
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        $typeFilter = self::queryText('archive_type');
        $allowedTypes = array_keys($counts);
        if ($typeFilter !== '' && ! in_array($typeFilter, $allowedTypes, true)) {
            $typeFilter = '';
        }
        $search = self::queryText('archive_search');
        $rows = array_values(array_filter($allRows, static function (array $row) use ($typeFilter, $search): bool {
            if ($typeFilter !== '' && (string) $row['type'] !== $typeFilter) {
                return false;
            }
            if ($search === '') {
                return true;
            }
            $haystack = implode(' ', [(string) $row['type'], (string) $row['id'], (string) $row['label'], (string) $row['archived_at']]);
            return stripos($haystack, $search) !== false;
        }));

        $totalRows = count($rows);
        $totalPages = max(1, (int) ceil($totalRows / self::PAGE_SIZE));
        $currentPage = min($totalPages, max(1, (int) ($_GET['archive_page'] ?? 1)));
        $pageRows = array_slice($rows, ($currentPage - 1) * self::PAGE_SIZE, self::PAGE_SIZE);
        $financialCount = ($counts['Contract'] ?? 0) + ($counts['Payment'] ?? 0) + ($counts['Collection'] ?? 0);
        $referenceCount = ($counts['Customer'] ?? 0) + ($counts['Supplier'] ?? 0) + ($counts['Payment method'] ?? 0);
        ?>
        <div class="wrap safecontracts-settings safecontracts-archive safecontracts-worker1" dir="auto">
            <header class="safecontracts-worker1__header">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html(self::text('Safe deletion · preserved history')); ?></p>
                    <h1><?php echo esc_html__('Archive', 'safecontracts'); ?></h1>
                    <p class="description"><?php echo esc_html(self::text('Archived and inactive records remain available as operational history and audit evidence. This page does not invent a restore operation where the underlying service does not support one.')); ?></p>
                </div>
                <div class="safecontracts-worker1__header-actions">
                    <a class="button" href="<?php echo esc_url(add_query_arg(['page' => ContractsPage::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Active contracts', 'safecontracts'); ?></a>
                    <a class="button" href="<?php echo esc_url(add_query_arg(['page' => CustomersPage::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Customers', 'safecontracts'); ?></a>
                    <?php if ($canViewSuppliers) : ?><a class="button" href="<?php echo esc_url(add_query_arg(['page' => SuppliersPage::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Suppliers', 'safecontracts'); ?></a><?php endif; ?>
                </div>
            </header>

            <section class="safecontracts-worker1__metrics" aria-label="<?php echo esc_attr(self::text('Archive summary')); ?>">
                <article class="safecontracts-worker1__metric"><span><?php echo esc_html(self::text('Archived records')); ?></span><strong><?php echo esc_html((string) count($allRows)); ?></strong><small><?php echo esc_html(self::text('Across supported archive sources')); ?></small></article>
                <article class="safecontracts-worker1__metric"><span><?php echo esc_html(self::text('Financial records')); ?></span><strong><?php echo esc_html((string) $financialCount); ?></strong><small><?php echo esc_html(self::text('Contracts, payments and collections')); ?></small></article>
                <article class="safecontracts-worker1__metric"><span><?php echo esc_html(self::text('Reference records')); ?></span><strong><?php echo esc_html((string) $referenceCount); ?></strong><small><?php echo esc_html(self::text('Customers, suppliers and payment methods')); ?></small></article>
                <article class="safecontracts-worker1__metric"><span><?php echo esc_html(self::text('Filtered result')); ?></span><strong><?php echo esc_html((string) $totalRows); ?></strong><small><?php echo esc_html(self::text('Matches the current archive filters')); ?></small></article>
            </section>

            <section class="safecontracts-worker1__toolbar">
                <form method="get" class="safecontracts-worker1__filter-grid">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                    <label><?php echo esc_html(self::text('Search archive')); ?><input type="search" name="archive_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr(self::text('Record, type, ID or archived date')); ?>"></label>
                    <label><?php echo esc_html(self::text('Record type')); ?><select name="archive_type"><option value=""><?php echo esc_html(self::text('All types')); ?></option><?php foreach ($allowedTypes as $type) : ?><option value="<?php echo esc_attr($type); ?>" <?php selected($typeFilter, $type); ?>><?php echo esc_html(self::typeLabel($type)); ?></option><?php endforeach; ?></select></label>
                    <div class="safecontracts-worker1__filter-actions"><button class="button button-primary" type="submit"><?php echo esc_html__('Apply filters', 'safecontracts'); ?></button><a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html(self::text('Clear')); ?></a></div>
                </form>
                <?php if ($counts !== []) : ?><div class="safecontracts-worker1__archive-types" style="margin-top:12px;"><?php foreach ($counts as $type => $count) : ?><a class="safecontracts-worker1__status<?php echo $typeFilter === $type ? ' safecontracts-worker1__status--active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'archive_type' => $type], admin_url('admin.php'))); ?>"><?php echo esc_html(self::typeLabel($type) . ' · ' . $count); ?></a><?php endforeach; ?></div><?php endif; ?>
            </section>

            <section class="safecontracts-worker1__panel">
                <div class="safecontracts-worker1__panel-head"><div><h2><?php echo esc_html(self::text('Archived / inactive records')); ?></h2><p><?php echo esc_html(self::text('Soft-deleted records are excluded from normal operations while their history remains preserved.')); ?></p></div><span class="safecontracts-worker1__count"><?php echo esc_html((string) $totalRows); ?></span></div>
                <div class="safecontracts-worker1__panel-body--flush">
                    <?php if ($pageRows === []) : ?>
                        <div class="safecontracts-worker1__empty"><span class="safecontracts-worker1__empty-mark" aria-hidden="true">✓</span><h3><?php echo esc_html($allRows === [] ? self::text('Archive is empty') : self::text('No archived records match the current filters')); ?></h3><p><?php echo esc_html($allRows === [] ? self::text('There are no archived records in the supported archive sources.') : self::text('Clear the type or search filter to see more archived records.')); ?></p></div>
                    <?php else : ?>
                        <div class="safecontracts-worker1__table-scroll">
                            <table class="widefat striped">
                                <thead><tr><th><?php echo esc_html__('Type', 'safecontracts'); ?></th><th><?php echo esc_html__('ID', 'safecontracts'); ?></th><th><?php echo esc_html__('Record', 'safecontracts'); ?></th><th><?php echo esc_html__('Archived / updated', 'safecontracts'); ?></th><th><?php echo esc_html__('By user', 'safecontracts'); ?></th><th><?php echo esc_html(self::text('Available action')); ?></th></tr></thead>
                                <tbody>
                                <?php foreach ($pageRows as $row) : ?>
                                    <tr>
                                        <td><span class="safecontracts-worker1__type-pill"><?php echo esc_html(self::typeLabel((string) $row['type'])); ?></span></td>
                                        <td>#<?php echo esc_html((string) $row['id']); ?></td>
                                        <td><div class="safecontracts-worker1__primary-cell"><strong><?php echo esc_html((string) ($row['label'] !== '' ? $row['label'] : '—')); ?></strong><span class="safecontracts-worker1__secondary"><?php echo esc_html(self::typeLabel((string) $row['type'])); ?></span></div></td>
                                        <td><?php echo esc_html((string) ($row['archived_at'] !== '' ? $row['archived_at'] : '—')); ?></td>
                                        <td><?php echo (int) $row['archived_by'] > 0 ? '#' . esc_html((string) $row['archived_by']) : '—'; ?></td>
                                        <td><?php if ((string) $row['type'] === 'Supplier' && $canViewSuppliers) : ?><a class="button button-small" href="<?php echo esc_url(add_query_arg(['page' => SuppliersPage::SLUG, 'supplier_id' => (int) $row['id'], 'include_archived' => '1'], admin_url('admin.php'))); ?>"><?php echo esc_html(self::text('View read-only supplier')); ?></a><?php else : ?><span class="description"><?php echo esc_html(self::text('History only')); ?></span><?php endif; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php self::renderPagination($currentPage, $totalPages, $totalRows, $search, $typeFilter); ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
        <?php
    }

    /** @return list<array{type:string,id:int,label:string,archived_at:string,archived_by:int}> */
    private static function rows(): array
    {
        global $wpdb;
        $result = [];
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $suppliers = $wpdb->prefix . 'safecontracts_suppliers';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $collections = $wpdb->prefix . 'safecontracts_payment_collections';
        $methods = $wpdb->prefix . 'safecontracts_payment_methods';

        self::append($result, $wpdb->get_results("SELECT id, name AS label, updated_at AS archived_at, 0 AS archived_by FROM {$customers} WHERE is_active = 0 ORDER BY updated_at DESC LIMIT 500", ARRAY_A), 'Customer');
        self::append($result, $wpdb->get_results("SELECT id, COALESCE(NULLIF(legal_name, ''), name) AS label, COALESCE(archived_at, updated_at) AS archived_at, COALESCE(archived_by, 0) AS archived_by FROM {$suppliers} WHERE is_archived = 1 ORDER BY archived_at DESC, id DESC LIMIT 500", ARRAY_A), 'Supplier');
        self::append($result, $wpdb->get_results("SELECT id, contract_number AS label, updated_at AS archived_at, COALESCE(updated_by, 0) AS archived_by FROM {$contracts} WHERE is_archived = 1 ORDER BY updated_at DESC LIMIT 500", ARRAY_A), 'Contract');
        self::append($result, $wpdb->get_results("SELECT id, COALESCE(reference, CONCAT('Payment #', id)) AS label, COALESCE(archived_at, updated_at) AS archived_at, COALESCE(archived_by, 0) AS archived_by FROM {$payments} WHERE is_archived = 1 ORDER BY archived_at DESC, id DESC LIMIT 500", ARRAY_A), 'Payment');
        self::append($result, $wpdb->get_results("SELECT id, COALESCE(reference, CONCAT('Collection #', id)) AS label, COALESCE(archived_at, updated_at) AS archived_at, COALESCE(archived_by, 0) AS archived_by FROM {$collections} WHERE is_archived = 1 ORDER BY archived_at DESC, id DESC LIMIT 500", ARRAY_A), 'Collection');
        self::append($result, $wpdb->get_results("SELECT id, name AS label, updated_at AS archived_at, 0 AS archived_by FROM {$methods} WHERE is_active = 0 ORDER BY updated_at DESC LIMIT 500", ARRAY_A), 'Payment method');

        usort($result, static fn (array $a, array $b): int => strcmp((string) $b['archived_at'], (string) $a['archived_at']));
        return array_slice($result, 0, 1500);
    }

    /** @param list<array{type:string,id:int,label:string,archived_at:string,archived_by:int}> $result */
    private static function append(array &$result, mixed $rows, string $type): void
    {
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $result[] = [
                'type' => $type,
                'id' => $id,
                'label' => trim((string) ($row['label'] ?? '')),
                'archived_at' => (string) ($row['archived_at'] ?? ''),
                'archived_by' => (int) ($row['archived_by'] ?? 0),
            ];
        }
    }

    private static function queryText(string $key): string
    {
        return isset($_GET[$key]) && is_scalar($_GET[$key]) ? sanitize_text_field((string) $_GET[$key]) : '';
    }

    private static function text(string $english): string
    {
        if (TranslationCatalog::currentLanguage() !== 'ar') {
            return __($english, 'safecontracts');
        }

        return match ($english) {
            'Safe deletion · preserved history' => 'حذف آمن · سجل محفوظ',
            'Archived and inactive records remain available as operational history and audit evidence. This page does not invent a restore operation where the underlying service does not support one.' => 'تظل السجلات المؤرشفة وغير النشطة متاحة كسجل تشغيلي ودليل تدقيق. ولا تضيف هذه الصفحة عملية استعادة ما لم تكن الخدمة الأساسية تدعمها.',
            'Archive summary' => 'ملخص الأرشيف',
            'Archived records' => 'السجلات المؤرشفة',
            'Across supported archive sources' => 'عبر مصادر الأرشيف المدعومة',
            'Financial records' => 'السجلات المالية',
            'Contracts, payments and collections' => 'العقود والدفعات والتحصيلات',
            'Reference records' => 'السجلات المرجعية',
            'Customers, suppliers and payment methods' => 'العملاء والموردون وطرق السداد',
            'Filtered result' => 'النتيجة بعد التصفية',
            'Matches the current archive filters' => 'يطابق فلاتر الأرشيف الحالية',
            'Search archive' => 'بحث في الأرشيف',
            'Record, type, ID or archived date' => 'السجل أو النوع أو المعرّف أو تاريخ الأرشفة',
            'Record type' => 'نوع السجل',
            'All types' => 'كل الأنواع',
            'Clear' => 'مسح',
            'Archived / inactive records' => 'السجلات المؤرشفة / غير النشطة',
            'Soft-deleted records are excluded from normal operations while their history remains preserved.' => 'تُستبعد السجلات المؤرشفة بأمان من العمليات العادية مع الاحتفاظ بسجلها التاريخي.',
            'Archive is empty' => 'الأرشيف فارغ',
            'No archived records match the current filters' => 'لا توجد سجلات مؤرشفة تطابق الفلاتر الحالية',
            'There are no archived records in the supported archive sources.' => 'لا توجد سجلات مؤرشفة في مصادر الأرشيف المدعومة.',
            'Clear the type or search filter to see more archived records.' => 'امسح فلتر النوع أو البحث لعرض المزيد من السجلات المؤرشفة.',
            'Available action' => 'الإجراء المتاح',
            'View read-only supplier' => 'عرض المورد للقراءة فقط',
            'History only' => 'سجل تاريخي فقط',
            'Collection' => 'تحصيل',
            'Archive pagination' => 'ترقيم صفحات الأرشيف',
            '%1$d records · page %2$d of %3$d' => '%1$d سجل · الصفحة %2$d من %3$d',
            default => $english,
        };
    }

    private static function typeLabel(string $type): string
    {
        return match ($type) {
            'Customer' => __('Customer', 'safecontracts'),
            'Supplier' => __('Supplier', 'safecontracts'),
            'Contract' => __('Contract', 'safecontracts'),
            'Payment' => __('Payment', 'safecontracts'),
            'Collection' => self::text('Collection'),
            'Payment method' => __('Payment method', 'safecontracts'),
            default => $type,
        };
    }

    private static function renderPagination(int $currentPage, int $totalPages, int $totalRows, string $search, string $type): void
    {
        if ($totalPages <= 1) {
            return;
        }
        $base = ['page' => self::SLUG];
        if ($search !== '') { $base['archive_search'] = $search; }
        if ($type !== '') { $base['archive_type'] = $type; }
        ?>
        <nav class="safecontracts-worker1__pagination" aria-label="<?php echo esc_attr(self::text('Archive pagination')); ?>">
            <span><?php echo esc_html(sprintf(self::text('%1$d records · page %2$d of %3$d'), $totalRows, $currentPage, $totalPages)); ?></span>
            <span class="safecontracts-worker1__pagination-links"><?php for ($page = 1; $page <= $totalPages; $page++) : ?><a class="button button-small" <?php if ($page === $currentPage) : ?>aria-current="page"<?php endif; ?> href="<?php echo esc_url(add_query_arg(array_merge($base, ['archive_page' => $page]), admin_url('admin.php'))); ?>"><?php echo esc_html((string) $page); ?></a><?php endfor; ?></span>
        </nav>
        <?php
    }
}
