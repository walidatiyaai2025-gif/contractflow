from __future__ import annotations

import re
from pathlib import Path


def read(path: str) -> str:
    return Path(path).read_text()


def write(path: str, text: str) -> None:
    Path(path).write_text(text)


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected exactly one match, found {count}")
    return text.replace(old, new, 1)


def replace_region(text: str, start: str, end: str, new: str, label: str) -> str:
    a = text.find(start)
    if a < 0:
        raise SystemExit(f"{label}: start marker missing")
    b = text.find(end, a + len(start))
    if b < 0:
        raise SystemExit(f"{label}: end marker missing")
    return text[:a] + new + text[b:]


# ---------------------------------------------------------------------------
# Shared B085/B100 primitives.
# ---------------------------------------------------------------------------
Path("mobile/lib/core/widgets/compact_list_toolbar.dart").write_text(
    r'''import 'package:flutter/material.dart';

/// Shared compact Search | Filter | Sort toolbar geometry for list surfaces.
///
/// The caller owns data/query semantics. This widget owns only the responsive
/// one-row layout so list screens do not independently reinvent spacing and
/// crowding behavior.
final class CompactListToolbar extends StatelessWidget {
  const CompactListToolbar({
    required this.search,
    this.filter,
    this.sort,
    this.actions = const <Widget>[],
    super.key,
  });

  final Widget search;
  final Widget? filter;
  final Widget? sort;
  final List<Widget> actions;

  @override
  Widget build(BuildContext context) {
    final items = <Widget>[
      if (filter != null) filter!,
      if (sort != null) sort!,
      ...actions,
    ];
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Expanded(child: search),
        for (final item in items) ...[
          const SizedBox(width: 5),
          item,
        ],
      ],
    );
  }
}
'''
)

# ---------------------------------------------------------------------------
# Customers: consume authoritative total/total_pages and shared paginator.
# ---------------------------------------------------------------------------
path = "mobile/lib/features/customers/customers.dart"
text = read(path)
customer_page = r'''final class CustomerPage {
  const CustomerPage({
    required this.customers,
    required this.page,
    required this.perPage,
    required this.total,
    required this.totalPages,
    required this.sort,
    required this.order,
    required this.hasMore,
    required this.boundedWindow,
    required this.scope,
  });

  final List<SafeContractsCustomer> customers;
  final int page;
  final int perPage;
  final int total;
  final int totalPages;
  final String sort;
  final String order;
  final bool hasMore;
  final int boundedWindow;
  final String? scope;

  factory CustomerPage.fromEnvelope(ApiEnvelope envelope) {
    final values = apiObjectList(envelope.data, 'customers.data');
    final customers =
        values.map(SafeContractsCustomer.fromData).toList(growable: false);
    final meta = envelope.meta;
    final page = _boundedInt(meta['page'], 'meta.page', minimum: 1, maximum: 500);
    final perPage = _boundedInt(
      meta['per_page'],
      'meta.per_page',
      minimum: 1,
      maximum: 100,
    );
    final total = _boundedInt(
      meta['total'],
      'meta.total',
      minimum: 0,
      maximum: 500,
    );
    final totalPages = _boundedInt(
      meta['total_pages'],
      'meta.total_pages',
      minimum: 1,
      maximum: 500,
    );
    final boundedWindow = _boundedInt(
      meta['bounded_window'],
      'meta.bounded_window',
      minimum: 1,
      maximum: 500,
    );
    if (page > totalPages || customers.length > perPage || customers.length > boundedWindow) {
      throw const FormatException(
        'Customer page metadata does not match its bounded rows.',
      );
    }
    final seen = <int>{};
    for (final customer in customers) {
      if (!seen.add(customer.id)) {
        throw const FormatException('Customer page contains a duplicate ID.');
      }
    }
    final sort = _requiredText(meta['sort'], 'meta.sort');
    if (sort != 'name' && sort != 'id') {
      throw const FormatException('Customer sort metadata is invalid.');
    }
    final order = _requiredText(meta['order'], 'meta.order').toLowerCase();
    if (order != 'asc' && order != 'desc') {
      throw const FormatException('Customer order metadata is invalid.');
    }
    final hasMore = _boolish(meta['has_more'], 'meta.has_more');
    if (hasMore != (page < totalPages)) {
      throw const FormatException('Customer has_more metadata is inconsistent.');
    }
    return CustomerPage(
      customers: List<SafeContractsCustomer>.unmodifiable(customers),
      page: page,
      perPage: perPage,
      total: total,
      totalPages: totalPages,
      sort: sort,
      order: order,
      hasMore: hasMore,
      boundedWindow: boundedWindow,
      scope: _scope(meta['scope']),
    );
  }
}

'''
text = replace_region(
    text,
    "final class CustomerPage {",
    "final class CustomersRepository {",
    customer_page,
    "customer page metadata",
)
text = text.replace(
    "if (page < 1 || page > 5) {\n      throw ArgumentError('Customer page must be between 1 and 5.');\n    }",
    "if (page < 1 || page > 500) {\n      throw ArgumentError('Customer page is outside the supported bounded range.');\n    }",
)
text = text.replace("if (page < 1 || page > 5) return;", "if (page < 1 || page > 500) return;")
text = text.replace(
    "if (page != null && page.hasMore && page.page < 5) {\n      await loadPage(page.page + 1);\n    }",
    "if (page != null && page.hasMore && page.page < page.totalPages) {\n      await loadPage(page.page + 1);\n    }",
)
write(path, text)

path = "mobile/lib/features/customers/customers_screen.dart"
text = read(path)
if "../../core/widgets/compact_pagination.dart" not in text:
    text = text.replace(
        "import '../../core/localization/safecontracts_localizations.dart';\n",
        "import '../../core/localization/safecontracts_localizations.dart';\nimport '../../core/widgets/compact_list_toolbar.dart';\nimport '../../core/widgets/compact_pagination.dart';\n",
        1,
    )
text = replace_once(
    text,
    """        _Pagination(
          page: page,
          busy: controller.state == CustomersLoadState.loading,
          onPrevious: controller.previousPage,
          onNext: controller.nextPage,
        ),""",
    """        CompactPagination(
          page: page.page,
          totalPages: page.totalPages,
          total: page.total,
          isLoading: controller.state == CustomersLoadState.loading,
          previousLabel: context.scL10n.t('Previous'),
          nextLabel: context.scL10n.t('Next'),
          onPrevious: page.page > 1
              ? () => unawaited(controller.previousPage())
              : null,
          onNext: page.hasMore
              ? () => unawaited(controller.nextPage())
              : null,
          resultLabelBuilder: (total) => context.scL10n.isArabic
              ? '$total نتيجة'
              : '$total results',
        ),""",
    "customers shared paginator",
)
text = replace_region(
    text,
    "final class _Pagination extends StatelessWidget {",
    "final class _CountBadge extends StatelessWidget {",
    "",
    "remove customer local paginator",
)
# Convert customer Search + sort/refresh/create block to the shared row.
header_start = text.index("final class _CustomerHeader extends StatelessWidget {")
header_end = text.index("final class _CustomerBody extends StatelessWidget {", header_start)
header = text[header_start:header_end]
old_start = "                SearchBar(\n"
old_end = "              ],\n            ),\n"
# Target only the first SearchBar through the wrapping toolbar children.
a = header.find(old_start)
if a < 0:
    raise SystemExit("customer toolbar search marker missing")
wrap = header.find("                Wrap(\n", a)
if wrap < 0:
    raise SystemExit("customer toolbar wrap marker missing")
# Locate end of the Wrap by the SafeContractsSurface children closing sequence.
end = header.find("              ],\n            ),", wrap)
if end < 0:
    raise SystemExit("customer toolbar end marker missing")
end += len("              ],\n            ),")
shared_customer_toolbar = r'''                CompactListToolbar(
                  search: SearchBar(
                    controller: searchController,
                    leading: const Icon(Icons.search_rounded),
                    hintText: ar
                        ? 'بحث بالاسم أو الكود أو جهة الاتصال أو الهاتف'
                        : 'Search name, code, contact, email or phone',
                    onChanged: onQueryChanged,
                    trailing: [
                      if (query.isNotEmpty)
                        IconButton(
                          onPressed: onClear,
                          icon: const Icon(Icons.close_rounded),
                        ),
                    ],
                  ),
                  sort: PopupMenuButton<String>(
                    enabled: !busy,
                    initialValue: controller.order,
                    tooltip: ar ? 'الترتيب' : 'Sort',
                    onSelected: (value) => unawaited(controller.setOrder(value)),
                    itemBuilder: (context) => [
                      PopupMenuItem(value: 'asc', child: Text(ar ? 'أ–ي' : 'A–Z')),
                      PopupMenuItem(value: 'desc', child: Text(ar ? 'ي–أ' : 'Z–A')),
                    ],
                    icon: const Icon(Icons.swap_vert_rounded),
                  ),
                  actions: [
                    IconButton.filledTonal(
                      tooltip: context.scL10n.t('Refresh customers'),
                      onPressed: busy ? null : () => unawaited(controller.refresh()),
                      icon: const Icon(Icons.refresh_rounded),
                    ),
                    if (onCreate != null)
                      IconButton.filled(
                        tooltip: ar ? 'عميل جديد' : 'New customer',
                        onPressed: busy ? null : onCreate,
                        icon: const Icon(Icons.person_add_alt_1_rounded),
                      ),
                  ],
                ),'''
header = header[:a] + shared_customer_toolbar + header[end:]
text = text[:header_start] + header + text[header_end:]
write(path, text)

# ---------------------------------------------------------------------------
# Payments: remove page-5 UI/model cap and use shared paginator metadata.
# ---------------------------------------------------------------------------
path = "mobile/lib/features/payments/payments.dart"
text = read(path)
payment_page = r'''final class PaymentPage {
  const PaymentPage({
    required this.payments,
    required this.page,
    required this.perPage,
    required this.total,
    required this.totalPages,
    required this.hasMore,
    required this.sort,
    required this.order,
  });

  final List<SafeContractsPayment> payments;
  final int page;
  final int perPage;
  final int total;
  final int totalPages;
  final bool hasMore;
  final String sort;
  final String order;

  factory PaymentPage.fromEnvelope(ApiEnvelope envelope) {
    final rows = apiObjectList(envelope.data, 'payments.data');
    final meta = envelope.meta;
    final payments = rows.map(SafeContractsPayment.fromData).toList();
    final ids = <int>{};
    for (final payment in payments) {
      if (!ids.add(payment.id)) {
        throw const FormatException('payments contain duplicate IDs.');
      }
    }
    final page = _boundedInt(meta['page'], 'meta.page', 1, 500);
    final totalPages = _boundedInt(meta['total_pages'], 'meta.total_pages', 1, 500);
    final hasMore = _boolish(meta['has_more'], 'meta.has_more');
    if (page > totalPages || hasMore != (page < totalPages)) {
      throw const FormatException('Payment paging metadata is inconsistent.');
    }
    return PaymentPage(
      payments: List<SafeContractsPayment>.unmodifiable(payments),
      page: page,
      perPage: _boundedInt(meta['per_page'], 'meta.per_page', 1, 100),
      total: _boundedInt(meta['total'], 'meta.total', 0, 500),
      totalPages: totalPages,
      hasMore: hasMore,
      sort: _requiredText(meta['sort'], 'meta.sort'),
      order: _order(meta['order']),
    );
  }
}

'''
text = replace_region(text, "final class PaymentPage {", "final class PaymentMethodOption {", payment_page, "payment page metadata")
text = text.replace(
    "if (page < 1 || page > 5) {\n      throw ArgumentError('Payment page must be between 1 and 5.');\n    }",
    "if (page < 1 || page > 500) {\n      throw ArgumentError('Payment page is outside the supported bounded range.');\n    }",
)
write(path, text)

path = "mobile/lib/features/payments/payments_screen.dart"
text = read(path)
if "../../core/widgets/compact_pagination.dart" not in text:
    text = text.replace(
        "import '../../core/localization/safecontracts_localizations.dart';\n",
        "import '../../core/localization/safecontracts_localizations.dart';\nimport '../../core/widgets/compact_pagination.dart';\n",
        1,
    )
text = replace_once(
    text,
    """          _PaymentPaging(
            page: page,
            loading: _loading,
            onPrevious:
                page.page > 1 ? () => unawaited(_load(page.page - 1)) : null,
            onNext: page.hasMore && page.page < 5
                ? () => unawaited(_load(page.page + 1))
                : null,
          ),""",
    """          CompactPagination(
            page: page.page,
            totalPages: page.totalPages,
            total: page.total,
            isLoading: _loading,
            previousLabel: l10n.t('Previous'),
            nextLabel: l10n.t('Next'),
            onPrevious: page.page > 1
                ? () => unawaited(_load(page.page - 1))
                : null,
            onNext: page.hasMore
                ? () => unawaited(_load(page.page + 1))
                : null,
            resultLabelBuilder: (total) => l10n.isArabic
                ? '$total نتيجة'
                : '$total results',
          ),""",
    "payments shared paginator",
)
text = replace_region(
    text,
    "final class _PaymentPaging extends StatelessWidget {",
    "final class PaymentDetailScreen extends StatefulWidget {",
    "",
    "remove payment local paginator",
)
write(path, text)

# ---------------------------------------------------------------------------
# Notifications: server total/count + no page-5 cap + shared paginator.
# ---------------------------------------------------------------------------
path = "wordpress-plugin/safecontracts/src/Notifications/DeliveryLogRepository.php"
text = read(path)
text = text.replace("$offset = max(0, min(500, $offset));", "$offset = max(0, min(1000000, $offset));", 1)
count_method = r'''
    public function countSentForUser(int $userId): int
    {
        global $wpdb;
        if ($userId <= 0) {
            throw new InvalidArgumentException('Notification inbox requires a valid user.');
        }
        $table = $wpdb->prefix . 'safecontracts_notification_deliveries';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT COUNT(id) AS total FROM {$table} WHERE user_id = %d AND status = 'sent'",
                $userId
            ),
            ARRAY_A
        );
        if (! is_array($rows) || $rows === []) {
            return 0;
        }
        return max(0, (int) ($rows[0]['total'] ?? 0));
    }

'''
if "public function countSentForUser(" not in text:
    text = text.replace("    public function hasSentForUser(", count_method + "    public function hasSentForUser(", 1)
write(path, text)

path = "wordpress-plugin/safecontracts/src/Rest/NotificationsController.php"
text = read(path)
old = r'''            $params = ApiAbuseGuard::safeParams($request, ['page', 'per_page']);
            $page = self::boundedInt($params['page'] ?? 1, 1, 5, 'page');
            $perPage = self::boundedInt($params['per_page'] ?? 25, 1, 50, 'per_page');
            $userId = self::currentUserId();
            $repository = new DeliveryLogRepository();
            $readRepository = new NotificationReadStateRepository();
            $readSet = array_fill_keys($readRepository->idsForUser($userId), true);

            $rows = $repository->recentForUser(
                $userId,
                $perPage + 1,
                ($page - 1) * $perPage
            );
            $hasMore = count($rows) > $perPage;
            $rows = array_slice($rows, 0, $perPage);
'''
new = r'''            $params = ApiAbuseGuard::safeParams($request, ['page', 'per_page']);
            $perPage = self::boundedInt($params['per_page'] ?? 25, 1, 50, 'per_page');
            $maximumPage = intdiv(1000000, $perPage) + 1;
            $page = self::boundedInt($params['page'] ?? 1, 1, $maximumPage, 'page');
            $userId = self::currentUserId();
            $repository = new DeliveryLogRepository();
            $readRepository = new NotificationReadStateRepository();
            $readSet = array_fill_keys($readRepository->idsForUser($userId), true);
            $total = $repository->countSentForUser($userId);
            $totalPages = max(1, (int) ceil($total / $perPage));
            if ($page > $totalPages) {
                throw new InvalidArgumentException('Notification page exceeds the available result set.');
            }
            $rows = $repository->recentForUser(
                $userId,
                $perPage,
                ($page - 1) * $perPage
            );
            $hasMore = $page < $totalPages;
'''
text = replace_once(text, old, new, "notification authoritative paging")
text = replace_once(
    text,
    """                'returned' => count($items),
                'has_more' => $hasMore,
                'scope' => 'current_user',""",
    """                'returned' => count($items),
                'total' => $total,
                'total_pages' => $totalPages,
                'has_more' => $hasMore,
                'scope' => 'current_user',""",
    "notification total metadata",
)
write(path, text)

path = "mobile/lib/features/notifications/notifications.dart"
text = read(path)
notification_page = r'''final class NotificationPage {
  const NotificationPage({
    required this.notifications,
    required this.page,
    required this.perPage,
    required this.total,
    required this.totalPages,
    required this.hasMore,
  });

  final List<SafeContractsNotification> notifications;
  final int page;
  final int perPage;
  final int total;
  final int totalPages;
  final bool hasMore;

  factory NotificationPage.fromEnvelope(ApiEnvelope envelope) {
    final rows = apiObjectList(envelope.data, 'notifications.data');
    final notifications =
        rows.map(SafeContractsNotification.fromData).toList(growable: false);
    final ids = <int>{};
    for (final notification in notifications) {
      if (!ids.add(notification.id)) {
        throw const FormatException('notifications contain duplicate IDs.');
      }
    }

    final meta = envelope.meta;
    final page = _boundedInt(meta['page'], 'meta.page', 1, 1000001);
    final perPage = _boundedInt(meta['per_page'], 'meta.per_page', 1, 50);
    final total = _boundedInt(meta['total'], 'meta.total', 0, 100000000);
    final totalPages = _boundedInt(
      meta['total_pages'],
      'meta.total_pages',
      1,
      1000001,
    );
    final hasMore = _boolish(meta['has_more'], 'meta.has_more');
    if (page > totalPages || hasMore != (page < totalPages)) {
      throw const FormatException('notification pagination metadata is inconsistent.');
    }
    if (meta['scope'] != 'current_user') {
      throw const FormatException('notification scope metadata is invalid.');
    }

    return NotificationPage(
      notifications:
          List<SafeContractsNotification>.unmodifiable(notifications),
      page: page,
      perPage: perPage,
      total: total,
      totalPages: totalPages,
      hasMore: hasMore,
    );
  }
}

'''
text = replace_region(text, "final class NotificationPage {", "final class NotificationsRepository {", notification_page, "notification page model")
text = text.replace(
    "if (page < 1 || page > 5) {\n      throw ArgumentError('Notification page must be between 1 and 5.');\n    }",
    "if (page < 1 || page > 1000001) {\n      throw ArgumentError('Notification page exceeds the bounded server offset.');\n    }",
)
text = text.replace("  String? errorMessage;\n", "  String? errorMessage;\n  bool pageRequestInFlight = false;\n", 1)
old_load = r'''    if (page < 1 || page > 5) {
      return;
    }

    currentPage = null;
    state = NotificationsLoadState.loading;
    errorMessage = null;
    notifyListeners();
    try {
      final nextPage = await repository.loadPage(page: page, perPage: pageSize);
      currentPage = nextPage;
      _readIds.addAll(
        nextPage.notifications
            .where((item) => item.isRead)
            .map((item) => item.id),
      );
      state = NotificationsLoadState.ready;
    } on SafeContractsApiException catch (error) {
      currentPage = null;
      errorMessage = error.message;
      state = NotificationsLoadState.error;
    } on Object catch (error) {
      currentPage = null;
      errorMessage = error.toString();
      state = NotificationsLoadState.error;
    }
    notifyListeners();
'''
new_load = r'''    if (page < 1 || page > 1000001 || pageRequestInFlight) {
      return;
    }

    final hadPage = currentPage != null;
    pageRequestInFlight = true;
    state = NotificationsLoadState.loading;
    errorMessage = null;
    notifyListeners();
    try {
      final nextPage = await repository.loadPage(page: page, perPage: pageSize);
      currentPage = nextPage;
      _readIds.addAll(
        nextPage.notifications
            .where((item) => item.isRead)
            .map((item) => item.id),
      );
      state = NotificationsLoadState.ready;
    } on SafeContractsApiException catch (error) {
      if (!hadPage) currentPage = null;
      errorMessage = error.message;
      state = NotificationsLoadState.error;
    } on Object catch (error) {
      if (!hadPage) currentPage = null;
      errorMessage = error.toString();
      state = NotificationsLoadState.error;
    } finally {
      pageRequestInFlight = false;
      notifyListeners();
    }
'''
text = replace_once(text, old_load, new_load, "notification request guard")
text = text.replace(
    "if (page != null && page.hasMore && page.page < 5) {\n      await loadPage(page.page + 1);\n    }",
    "if (page != null && page.hasMore && page.page < page.totalPages) {\n      await loadPage(page.page + 1);\n    }",
)
write(path, text)

path = "mobile/lib/features/notifications/notifications_screen.dart"
text = read(path)
if "../../core/widgets/compact_pagination.dart" not in text:
    text = text.replace(
        "import '../../core/localization/safecontracts_localizations.dart';\n",
        "import '../../core/localization/safecontracts_localizations.dart';\nimport '../../core/widgets/compact_pagination.dart';\n",
        1,
    )
text = replace_once(
    text,
    "                _PagingControls(controller: controller),",
    """                CompactPagination(
                  page: page.page,
                  totalPages: page.totalPages,
                  total: page.total,
                  isLoading: controller.pageRequestInFlight,
                  previousLabel: l10n.t('Previous'),
                  nextLabel: l10n.t('Next'),
                  onPrevious: page.page > 1
                      ? () => unawaited(controller.previousPage())
                      : null,
                  onNext: page.hasMore
                      ? () => unawaited(controller.nextPage())
                      : null,
                  resultLabelBuilder: (total) => l10n.isArabic
                      ? '$total إشعار'
                      : '$total notifications',
                ),""",
    "notification shared paginator",
)
text = replace_region(
    text,
    "final class _PagingControls extends StatelessWidget {",
    "bool _isPaymentDue(SafeContractsNotification notification) {",
    "",
    "remove notification local paginator",
)
write(path, text)

# ---------------------------------------------------------------------------
# Suppliers: add SQL COUNT/LIMIT/OFFSET API pagination and shared UI paginator.
# ---------------------------------------------------------------------------
path = "wordpress-plugin/safecontracts/src/Suppliers/SupplierRepository.php"
text = read(path)
page_method = r'''
    /** @return array{rows:list<array<string,mixed>>,total:int} */
    public function page(string $query, int $page, int $perPage, bool $includeArchived = false): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_suppliers';
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        if ($offset > 1000000) {
            throw new RuntimeException('Supplier page offset exceeds the supported bounded range.');
        }
        $where = [$includeArchived ? '1 = 1' : 'is_archived = 0'];
        $args = [];
        if ($query !== '') {
            $like = '%' . $wpdb->esc_like($query) . '%';
            $where[] = '(legal_name LIKE %s OR trading_name LIKE %s OR name LIKE %s OR internal_code LIKE %s OR registration_number LIKE %s OR tax_number LIKE %s)';
            $args = [$like, $like, $like, $like, $like, $like];
        }
        $whereSql = implode(' AND ', $where);
        $countRows = $wpdb->get_results(
            $args === []
                ? "SELECT COUNT(id) AS total FROM {$table} WHERE {$whereSql}"
                : $wpdb->prepare("SELECT COUNT(id) AS total FROM {$table} WHERE {$whereSql}", ...$args),
            ARRAY_A
        );
        $total = is_array($countRows) && $countRows !== [] ? max(0, (int) ($countRows[0]['total'] ?? 0)) : 0;
        $pageArgs = [...$args, $perPage, $offset];
        $sql = 'SELECT ' . self::SELECT_FIELDS . " FROM {$table} WHERE {$whereSql}"
            . ' ORDER BY COALESCE(NULLIF(legal_name, \'\'), name) ASC, id ASC LIMIT %d OFFSET %d';
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$pageArgs), ARRAY_A);
        $mapped = is_array($rows)
            ? array_values(array_map(fn (array $row): array => $this->map($row), $rows))
            : [];
        return ['rows' => $mapped, 'total' => $total];
    }

'''
if "public function page(string $query" not in text:
    text = text.replace("    public function create(array $data, int $actorId): int\n", page_method + "    public function create(array $data, int $actorId): int\n", 1)
write(path, text)

path = "wordpress-plugin/safecontracts/src/Suppliers/SupplierService.php"
text = read(path)
service_method = r'''
    /** @return array{rows:list<array<string,mixed>>,total:int} */
    public function searchPage(mixed $query, int $page, int $perPage, bool $includeArchived = false): array
    {
        $this->requireView();
        $query = trim(strip_tags((string) $query));
        if (strlen($query) > 191) {
            throw new InvalidArgumentException('Supplier search is too long.');
        }
        if ($includeArchived
            && ! current_user_can(Capabilities::ARCHIVE_SUPPLIERS)
            && ! current_user_can(Capabilities::EDIT_SUPPLIERS)
            && ! current_user_can(Capabilities::MANAGE_SUPPLIERS)
            && ! current_user_can(Capabilities::VIEW_ALL)) {
            throw new DomainException('You do not have permission to view archived suppliers.');
        }
        if ($page < 1 || $perPage < 1 || $perPage > 100 || (($page - 1) * $perPage) > 1000000) {
            throw new InvalidArgumentException('Supplier pagination is outside the supported range.');
        }
        return $this->repository->page($query, $page, $perPage, $includeArchived);
    }

'''
if "public function searchPage(" not in text:
    text = text.replace("    public function save(array $input): int\n", service_method + "    public function save(array $input): int\n", 1)
write(path, text)

path = "wordpress-plugin/safecontracts/src/Rest/SuppliersController.php"
text = read(path)
old_index = r'''            $params = ApiAbuseGuard::safeParams($request, ['search', 'limit', 'include_archived']);
            $rows = (new SupplierService())->search(
                $params['search'] ?? '',
                isset($params['limit']) ? (int) $params['limit'] : 100,
                self::boolParam($params['include_archived'] ?? false)
            );
            return ApiResponse::ok($rows, [
                'returned' => count($rows),
                'bounded_window' => min(500, max(1, (int) ($params['limit'] ?? 100))),
            ]);
'''
new_index = r'''            $params = ApiAbuseGuard::safeParams($request, ['search', 'limit', 'include_archived', 'page', 'per_page']);
            $service = new SupplierService();
            $includeArchived = self::boolParam($params['include_archived'] ?? false);
            if (array_key_exists('page', $params) || array_key_exists('per_page', $params)) {
                $perPage = self::boundedInt($params['per_page'] ?? 25, 1, 100, 'per_page');
                $maximumPage = intdiv(1000000, $perPage) + 1;
                $page = self::boundedInt($params['page'] ?? 1, 1, $maximumPage, 'page');
                $result = $service->searchPage($params['search'] ?? '', $page, $perPage, $includeArchived);
                $total = $result['total'];
                $totalPages = max(1, (int) ceil($total / $perPage));
                if ($page > $totalPages) {
                    throw new InvalidArgumentException('Supplier page exceeds the available result set.');
                }
                return ApiResponse::ok($result['rows'], [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => $totalPages,
                    'returned' => count($result['rows']),
                    'has_more' => $page < $totalPages,
                ]);
            }
            $rows = $service->search(
                $params['search'] ?? '',
                isset($params['limit']) ? (int) $params['limit'] : 100,
                $includeArchived
            );
            return ApiResponse::ok($rows, [
                'returned' => count($rows),
                'bounded_window' => min(500, max(1, (int) ($params['limit'] ?? 100))),
            ]);
'''
text = replace_once(text, old_index, new_index, "supplier paged endpoint")
bounded_method = r'''
    private static function boundedInt(mixed $value, int $minimum, int $maximum, string $field): int
    {
        $parsed = is_int($value) ? $value : (is_string($value) ? filter_var($value, FILTER_VALIDATE_INT) : false);
        if ($parsed === false || $parsed < $minimum || $parsed > $maximum) {
            throw new InvalidArgumentException("{$field} is outside the supported range.");
        }
        return (int) $parsed;
    }

'''
if "private static function boundedInt(" not in text:
    text = text.replace("    private static function boolParam(mixed $value): bool\n", bounded_method + "    private static function boolParam(mixed $value): bool\n", 1)
write(path, text)

path = "mobile/lib/features/suppliers/suppliers.dart"
text = read(path)
supplier_page = r'''
final class SupplierPage {
  const SupplierPage({
    required this.suppliers,
    required this.page,
    required this.perPage,
    required this.total,
    required this.totalPages,
    required this.hasMore,
  });

  final List<SafeContractsSupplier> suppliers;
  final int page;
  final int perPage;
  final int total;
  final int totalPages;
  final bool hasMore;

  factory SupplierPage.fromEnvelope(ApiEnvelope envelope) {
    final rows = apiObjectList(envelope.data, 'suppliers.data');
    final suppliers = rows.map(SafeContractsSupplier.fromData).toList(growable: false);
    final ids = <int>{};
    for (final supplier in suppliers) {
      if (!ids.add(supplier.id)) {
        throw const FormatException('Supplier page contains a duplicate ID.');
      }
    }
    final meta = envelope.meta;
    final page = _boundedInt(meta['page'], 'meta.page', 1, 1000001);
    final perPage = _boundedInt(meta['per_page'], 'meta.per_page', 1, 100);
    final total = _boundedInt(meta['total'], 'meta.total', 0, 100000000);
    final totalPages = _boundedInt(meta['total_pages'], 'meta.total_pages', 1, 1000001);
    final hasMore = _boolish(meta['has_more'], 'meta.has_more');
    if (page > totalPages || hasMore != (page < totalPages)) {
      throw const FormatException('Supplier pagination metadata is inconsistent.');
    }
    return SupplierPage(
      suppliers: List<SafeContractsSupplier>.unmodifiable(suppliers),
      page: page,
      perPage: perPage,
      total: total,
      totalPages: totalPages,
      hasMore: hasMore,
    );
  }
}

'''
text = text.replace("final class SuppliersRepository {\n", supplier_page + "final class SuppliersRepository {\n", 1)
repo_start = text.index("final class SuppliersRepository {")
repo_end = text.index("final class SuppliersController extends ChangeNotifier {", repo_start)
repo = text[repo_start:repo_end]
load_method = r'''
  Future<SupplierPage> loadPage({
    required int page,
    required int perPage,
    String query = '',
    bool includeArchived = false,
  }) async {
    if (page < 1 || page > 1000001) {
      throw ArgumentError('Supplier page exceeds the bounded server offset.');
    }
    final envelope = await client.get(
      'suppliers',
      query: <String, String>{
        if (query.trim().isNotEmpty) 'search': query.trim(),
        if (includeArchived) 'include_archived': '1',
        'page': '$page',
        'per_page': '${perPage.clamp(1, 100)}',
      },
    );
    return SupplierPage.fromEnvelope(envelope);
  }

'''
if "Future<SupplierPage> loadPage" not in repo:
    repo = repo.replace("  Future<List<SafeContractsSupplier>> search({\n", load_method + "  Future<List<SafeContractsSupplier>> search({\n", 1)
text = text[:repo_start] + repo + text[repo_end:]
controller_start = text.index("final class SuppliersController extends ChangeNotifier {")
helper_start = text.index("int _positiveInt(", controller_start)
controller = r'''final class SuppliersController extends ChangeNotifier {
  SuppliersController({
    required this.repository,
    required this.canAccess,
    required this.canCreate,
    required this.canEdit,
    required this.canArchive,
    this.pageSize = 25,
  });

  final SuppliersRepository repository;
  final bool canAccess;
  final bool canCreate;
  final bool canEdit;
  final bool canArchive;
  final int pageSize;

  SuppliersLoadState state = SuppliersLoadState.idle;
  SupplierDetailLoadState detailState = SupplierDetailLoadState.idle;
  SupplierPage? currentPage;
  SafeContractsSupplier? selectedSupplier;
  int? selectedSupplierId;
  String searchQuery = '';
  bool includeArchived = false;
  String? errorMessage;
  String? detailErrorMessage;
  bool mutationInFlight = false;
  bool pageRequestInFlight = false;

  List<SafeContractsSupplier> get suppliers =>
      currentPage?.suppliers ?? const <SafeContractsSupplier>[];

  Future<void> ensureLoaded() async {
    if (state == SuppliersLoadState.idle) await loadPage(1);
  }

  Future<void> loadPage(int page, {bool silent = false}) async {
    if (!canAccess) {
      currentPage = null;
      errorMessage = 'Supplier access is not authorized for this session.';
      state = SuppliersLoadState.error;
      notifyListeners();
      return;
    }
    if (page < 1 || page > 1000001 || pageRequestInFlight) return;
    pageRequestInFlight = true;
    if (!silent) {
      state = SuppliersLoadState.loading;
      errorMessage = null;
      notifyListeners();
    }
    try {
      final next = await repository.loadPage(
        page: page,
        perPage: pageSize.clamp(1, 100),
        query: searchQuery,
        includeArchived: includeArchived && canArchive,
      );
      currentPage = next;
      if (selectedSupplierId != null) {
        final match = next.suppliers.where((item) => item.id == selectedSupplierId);
        if (match.isNotEmpty) selectedSupplier = match.first;
      }
      state = SuppliersLoadState.ready;
      errorMessage = null;
    } on SafeContractsApiException catch (error) {
      if (!silent || currentPage == null) {
        errorMessage = error.message;
        state = SuppliersLoadState.error;
      }
    } on Object catch (error) {
      if (!silent || currentPage == null) {
        errorMessage = error.toString();
        state = SuppliersLoadState.error;
      }
    } finally {
      pageRequestInFlight = false;
      notifyListeners();
    }
  }

  Future<void> refresh() => loadPage(currentPage?.page ?? 1);

  Future<void> refreshSilently() => loadPage(currentPage?.page ?? 1, silent: true);

  Future<void> previousPage() async {
    final page = currentPage?.page ?? 1;
    if (page > 1) await loadPage(page - 1);
  }

  Future<void> nextPage() async {
    final page = currentPage;
    if (page != null && page.hasMore && page.page < page.totalPages) {
      await loadPage(page.page + 1);
    }
  }

  Future<void> setSearch(String value) async {
    searchQuery = value.trim();
    await loadPage(1);
  }

  Future<void> setIncludeArchived(bool value) async {
    if (!canArchive) return;
    includeArchived = value;
    await loadPage(1);
  }

  Future<void> openSupplier(int id) async {
    if (!canAccess || id <= 0) return;
    selectedSupplierId = id;
    selectedSupplier = null;
    detailErrorMessage = null;
    detailState = SupplierDetailLoadState.loading;
    notifyListeners();
    try {
      final value = await repository.loadSupplier(id);
      if (selectedSupplierId != id) return;
      selectedSupplier = value;
      detailState = SupplierDetailLoadState.ready;
    } on SafeContractsApiException catch (error) {
      if (selectedSupplierId != id) return;
      detailErrorMessage = error.message;
      detailState = SupplierDetailLoadState.error;
    } on Object catch (error) {
      if (selectedSupplierId != id) return;
      detailErrorMessage = error.toString();
      detailState = SupplierDetailLoadState.error;
    }
    notifyListeners();
  }

  void closeSupplier() {
    selectedSupplierId = null;
    selectedSupplier = null;
    detailErrorMessage = null;
    detailState = SupplierDetailLoadState.idle;
    notifyListeners();
  }

  Future<SafeContractsSupplier> save({
    int? id,
    required SupplierDraft draft,
  }) async {
    if (id == null && !canCreate) {
      throw StateError('Supplier creation is not authorized.');
    }
    if (id != null && !canEdit) {
      throw StateError('Supplier editing is not authorized.');
    }
    mutationInFlight = true;
    notifyListeners();
    try {
      final saved = id == null
          ? await repository.create(draft)
          : await repository.update(id, draft);
      searchQuery = '';
      await loadPage(1);
      await openSupplier(saved.id);
      return saved;
    } finally {
      mutationInFlight = false;
      notifyListeners();
    }
  }

  Future<void> archiveSelected() async {
    final id = selectedSupplierId;
    if (!canArchive || id == null) {
      throw StateError('Supplier archiving is not authorized.');
    }
    mutationInFlight = true;
    notifyListeners();
    try {
      await repository.archive(id);
      closeSupplier();
      await loadPage(1);
    } finally {
      mutationInFlight = false;
      notifyListeners();
    }
  }
}

'''
text = text[:controller_start] + controller + text[helper_start:]
# Add shared integer parser used by SupplierPage.
if "int _boundedInt(" not in text:
    marker = "String _requiredText(Object? value, String field) {"
    helper = r'''int _boundedInt(Object? value, String field, int minimum, int maximum) {
  final parsed = switch (value) {
    final int value => value,
    final String value => int.tryParse(value),
    _ => null,
  };
  if (parsed == null || parsed < minimum || parsed > maximum) {
    throw FormatException('$field is outside the supported range.');
  }
  return parsed;
}

'''
    text = text.replace(marker, helper + marker, 1)
write(path, text)

path = "mobile/lib/features/suppliers/suppliers_screen.dart"
text = read(path)
if "../../core/widgets/compact_pagination.dart" not in text:
    text = text.replace(
        "import '../../core/localization/safecontracts_localizations.dart';\n",
        "import '../../core/localization/safecontracts_localizations.dart';\nimport '../../core/widgets/compact_list_toolbar.dart';\nimport '../../core/widgets/compact_pagination.dart';\n",
        1,
    )
# Add shared paginator to supplier list after Expanded list.
needle = r'''        Expanded(
          child: RefreshIndicator(
            onRefresh: controller.refresh,
            child: ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.only(top: 2, bottom: 8),
              itemCount: suppliers.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final supplier = suppliers[index];
                return _SupplierCard(
                  supplier: supplier,
                  selected: controller.selectedSupplierId == supplier.id,
                  onTap: () => unawaited(controller.openSupplier(supplier.id)),
                );
              },
            ),
          ),
        ),
'''
replacement = needle + r'''        if (controller.currentPage != null)
          CompactPagination(
            page: controller.currentPage!.page,
            totalPages: controller.currentPage!.totalPages,
            total: controller.currentPage!.total,
            isLoading: controller.pageRequestInFlight,
            previousLabel: context.scL10n.t('Previous'),
            nextLabel: context.scL10n.t('Next'),
            onPrevious: controller.currentPage!.page > 1
                ? () => unawaited(controller.previousPage())
                : null,
            onNext: controller.currentPage!.hasMore
                ? () => unawaited(controller.nextPage())
                : null,
            resultLabelBuilder: (total) => context.scL10n.isArabic
                ? '$total نتيجة'
                : '$total results',
          ),
'''
text = replace_once(text, needle, replacement, "supplier shared paginator")
# Replace supplier header SearchBar + filter Wrap with shared compact toolbar.
header_start = text.index("final class _SupplierHeader extends StatelessWidget {")
header_end = text.index("final class _SupplierBody extends StatelessWidget {", header_start)
header = text[header_start:header_end]
a = header.find("                SearchBar(\n")
wrap = header.find("                Wrap(\n", a)
if a < 0 or wrap < 0:
    raise SystemExit("supplier toolbar markers missing")
end = header.find("              ],\n            ),", wrap)
if end < 0:
    raise SystemExit("supplier toolbar end missing")
end += len("              ],\n            ),")
shared_supplier_toolbar = r'''                CompactListToolbar(
                  search: SearchBar(
                    controller: searchController,
                    enabled: !controller.mutationInFlight,
                    leading: const Icon(Icons.search_rounded),
                    hintText: ar
                        ? 'بحث بالاسم أو الكود أو التسجيل أو الرقم الضريبي'
                        : 'Search name, code, registration or tax number',
                    onSubmitted: busy
                        ? null
                        : (value) => unawaited(controller.setSearch(value)),
                    trailing: [
                      if (controller.searchQuery.isNotEmpty)
                        IconButton(
                          onPressed: busy
                              ? null
                              : () {
                                  searchController.clear();
                                  unawaited(controller.setSearch(''));
                                },
                          icon: const Icon(Icons.close_rounded),
                        ),
                    ],
                  ),
                  filter: PopupMenuButton<String>(
                    enabled: !busy,
                    initialValue: status,
                    tooltip: ar ? 'الحالة' : 'Status',
                    onSelected: onStatusChanged,
                    itemBuilder: (context) => [
                      PopupMenuItem(value: '', child: Text(ar ? 'الكل' : 'All')),
                      for (final item in const ['active', 'inactive', 'suspended'])
                        PopupMenuItem(
                          value: item,
                          child: Text(context.scL10n.status(item)),
                        ),
                    ],
                    icon: const Icon(Icons.filter_alt_outlined),
                  ),
                  actions: [
                    if (controller.canArchive)
                      IconButton(
                        tooltip: ar ? 'إظهار المؤرشف' : 'Show archived',
                        onPressed: busy
                            ? null
                            : () => unawaited(
                                  controller.setIncludeArchived(!controller.includeArchived),
                                ),
                        icon: Icon(
                          controller.includeArchived
                              ? Icons.inventory_2_rounded
                              : Icons.inventory_2_outlined,
                        ),
                      ),
                    IconButton.filledTonal(
                      tooltip: context.scL10n.t('Refresh'),
                      onPressed: busy ? null : () => unawaited(controller.refresh()),
                      icon: const Icon(Icons.refresh_rounded),
                    ),
                    if (onCreate != null)
                      IconButton.filled(
                        tooltip: ar ? 'مورد جديد' : 'New supplier',
                        onPressed: busy ? null : onCreate,
                        icon: const Icon(Icons.add_business_rounded),
                      ),
                  ],
                ),'''
header = header[:a] + shared_supplier_toolbar + header[end:]
text = text[:header_start] + header + text[header_end:]
write(path, text)

# ---------------------------------------------------------------------------
# Contracts: make the existing Search | Filter | Sort top row use shared layout.
# ---------------------------------------------------------------------------
path = "mobile/lib/features/contracts/contracts_screen.dart"
text = read(path)
if "../../core/widgets/compact_list_toolbar.dart" not in text:
    text = text.replace(
        "import '../../core/localization/safecontracts_localizations.dart';\n",
        "import '../../core/localization/safecontracts_localizations.dart';\nimport '../../core/widgets/compact_list_toolbar.dart';\n",
        1,
    )
text = text.replace(
    """                    return Row(
                      children: [
                        Expanded(
                          child: SearchBar(""",
    """                    return CompactListToolbar(
                      search: SearchBar(""",
    1,
)
text = text.replace(
    """                            ],
                          ),
                        ),
                        const SizedBox(width: 5),
                        _CustomerFilterMenu(""",
    """                            ],
                          ),
                      filter: _CustomerFilterMenu(""",
    1,
)
text = text.replace(
    """                        ),
                        const SizedBox(width: 5),
                        _SortMenu(""",
    """                        ),
                      sort: _SortMenu(""",
    1,
)
# CompactListToolbar closes with ); rather than Row children list.
text = text.replace(
    """                          onSelected: (value) =>
                              unawaited(controller.selectSort(value)),
                        ),
                      ],
                    );""",
    """                          onSelected: (value) =>
                              unawaited(controller.selectSort(value)),
                        ),
                    );""",
    1,
)
write(path, text)

# ---------------------------------------------------------------------------
# Regression contract for B085/B100 and removal of page-5 client caps.
# ---------------------------------------------------------------------------
Path("mobile/test/alkenzy_global_list_closure_test.dart").write_text(
    r'''import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('B085 all named paged list surfaces consume shared paginator', () {
    for (final path in const [
      'lib/features/contracts/contracts_screen.dart',
      'lib/features/customers/customers_screen.dart',
      'lib/features/suppliers/suppliers_screen.dart',
      'lib/features/payments/payments_screen.dart',
      'lib/features/notifications/notifications_screen.dart',
    ]) {
      final source = File(path).readAsStringSync();
      expect(source, contains('CompactPagination('), reason: path);
    }
  });

  test('B085 client paging no longer hard-stops at page five', () {
    for (final path in const [
      'lib/features/customers/customers.dart',
      'lib/features/payments/payments.dart',
      'lib/features/notifications/notifications.dart',
    ]) {
      final source = File(path).readAsStringSync();
      expect(source, isNot(contains('page < 5')), reason: path);
      expect(source, isNot(contains('page > 5')), reason: path);
      expect(source, isNot(contains('page >= 5')), reason: path);
    }
  });

  test('B100 applicable list screens share compact toolbar geometry', () {
    for (final path in const [
      'lib/features/contracts/contracts_screen.dart',
      'lib/features/customers/customers_screen.dart',
      'lib/features/suppliers/suppliers_screen.dart',
    ]) {
      final source = File(path).readAsStringSync();
      expect(source, contains('CompactListToolbar('), reason: path);
    }
  });

  test('B085 notification and supplier APIs expose authoritative page totals', () {
    final notifications = File(
      '../wordpress-plugin/safecontracts/src/Rest/NotificationsController.php',
    ).readAsStringSync();
    final suppliers = File(
      '../wordpress-plugin/safecontracts/src/Rest/SuppliersController.php',
    ).readAsStringSync();
    final supplierRepository = File(
      '../wordpress-plugin/safecontracts/src/Suppliers/SupplierRepository.php',
    ).readAsStringSync();

    expect(notifications, contains("'total_pages' => $totalPages"));
    expect(notifications, contains('countSentForUser'));
    expect(suppliers, contains("'total_pages' => $totalPages"));
    expect(suppliers, contains("'has_more' => $page < $totalPages"));
    expect(supplierRepository, contains('COUNT(id) AS total'));
    expect(supplierRepository, contains('LIMIT %d OFFSET %d'));
  });
}
'''
)
