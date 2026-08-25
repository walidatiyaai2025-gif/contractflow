#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def edit(path: str, transform):
    target = ROOT / path
    text = target.read_text(encoding='utf-8')
    updated = transform(text)
    if updated == text:
        raise SystemExit(f'No changes applied to {path}')
    target.write_text(updated, encoding='utf-8')


def replace_once(text: str, old: str, new: str, label: str) -> str:
    if old not in text:
        raise SystemExit(f'Marker not found: {label}')
    return text.replace(old, new, 1)


def patch_contracts_model(text: str) -> str:
    pattern = re.compile(r"  Future<void> loadPage\(int page\) async \{.*?\n  \}\n\n  Future<void> refresh\(\) => loadPage\(currentPage\?\.page \?\? 1\);", re.S)
    replacement = '''  Future<void> loadPage(int page) async {
    if (_pageRequestInFlight) return;
    if (!canAccess) {
      currentPage = null;
      errorMessage = 'Contract access is not authorized for this session.';
      state = ContractsLoadState.error;
      notifyListeners();
      return;
    }
    if (page < 1 || (page - 1) * pageSize > 1000000) return;
    _pageRequestInFlight = true;
    final previousPage = currentPage;
    state = ContractsLoadState.loading;
    errorMessage = null;
    notifyListeners();
    try {
      final nextPage = await repository.loadPage(
        page: page,
        perPage: pageSize,
        filters: filters,
        sort: sort,
        search: searchQuery,
      );
      if (page > 1 && previousPage != null) {
        final merged = <int, SafeContractsContract>{
          for (final item in previousPage.contracts) item.id: item,
          for (final item in nextPage.contracts) item.id: item,
        };
        currentPage = ContractPage(
          contracts: List<SafeContractsContract>.unmodifiable(merged.values),
          page: nextPage.page,
          perPage: nextPage.perPage,
          total: nextPage.total,
          totalPages: nextPage.totalPages,
          sort: nextPage.sort,
          order: nextPage.order,
          hasMore: nextPage.hasMore,
          boundedWindow: nextPage.boundedWindow,
          scope: nextPage.scope,
        );
      } else {
        currentPage = nextPage;
      }
      state = ContractsLoadState.ready;
    } on SafeContractsApiException catch (error) {
      currentPage = previousPage;
      errorMessage = error.message;
      state = ContractsLoadState.error;
    } on Object catch (error) {
      currentPage = previousPage;
      errorMessage = error.toString();
      state = ContractsLoadState.error;
    } finally {
      _pageRequestInFlight = false;
      notifyListeners();
    }
  }

  Future<void> refresh() => loadPage(1);'''
    text, count = pattern.subn(replacement, text, count=1)
    if count != 1:
        raise SystemExit('Contracts loadPage marker not found')

    pattern = re.compile(r"  Future<void> refreshSilently\(\) async \{.*?\n  \}\n\n  Future<void> previousPage", re.S)
    replacement = '''  Future<void> refreshSilently() async {
    if (!canAccess || _pageRequestInFlight) return;
    final previous = currentPage;
    try {
      final first = await repository.loadPage(
        page: 1,
        perPage: pageSize,
        filters: filters,
        sort: sort,
        search: searchQuery,
      );
      if (previous != null && previous.page > 1) {
        final merged = <int, SafeContractsContract>{
          for (final item in first.contracts) item.id: item,
          for (final item in previous.contracts) item.id: item,
        };
        currentPage = ContractPage(
          contracts: List<SafeContractsContract>.unmodifiable(merged.values),
          page: previous.page.clamp(1, first.totalPages).toInt(),
          perPage: first.perPage,
          total: first.total,
          totalPages: first.totalPages,
          sort: first.sort,
          order: first.order,
          hasMore: previous.page < first.totalPages,
          boundedWindow: first.boundedWindow,
          scope: first.scope,
        );
      } else {
        currentPage = first;
      }
      state = ContractsLoadState.ready;
      errorMessage = null;
      notifyListeners();
    } on Object {
      // Preserve the last authorized snapshot on silent refresh failure.
    }
  }

  Future<void> previousPage'''
    text, count = pattern.subn(replacement, text, count=1)
    if count != 1:
        raise SystemExit('Contracts refreshSilently marker not found')
    return text


def patch_contracts_screen(text: str) -> str:
    text = text.replace("import '../../core/widgets/compact_pagination.dart';\n", '')
    text = replace_once(
        text,
        "  late final TextEditingController _search;\n  final Map<int, Future<ContractMedia?>> _media = {};",
        "  late final TextEditingController _search;\n  final ScrollController _scrollController = ScrollController();\n  final Map<int, Future<ContractMedia?>> _media = {};",
        'contracts scroll field',
    )
    text = replace_once(
        text,
        "    _search = TextEditingController(text: _searchText);\n    unawaited(widget.controller.ensureLoaded());",
        "    _search = TextEditingController(text: _searchText);\n    _scrollController.addListener(_loadNextOnScroll);\n    unawaited(widget.controller.ensureLoaded());",
        'contracts init scroll',
    )
    text = replace_once(
        text,
        "  @override\n  void dispose() {\n    _searchDebounce?.cancel();\n    _search.dispose();\n    super.dispose();\n  }",
        "  @override\n  void dispose() {\n    _searchDebounce?.cancel();\n    _scrollController.dispose();\n    _search.dispose();\n    super.dispose();\n  }\n\n  void _loadNextOnScroll() {\n    final page = widget.controller.currentPage;\n    if (page == null || !page.hasMore || widget.controller.pageRequestInFlight) {\n      return;\n    }\n    if (!_scrollController.hasClients ||\n        _scrollController.position.extentAfter > 360) {\n      return;\n    }\n    unawaited(widget.controller.nextPage());\n  }",
        'contracts scroll handler',
    )
    text = replace_once(
        text,
        "                  controller: widget.controller,\n                  contracts: contracts,",
        "                  controller: widget.controller,\n                  scrollController: _scrollController,\n                  contracts: contracts,",
        'contracts content call',
    )
    text = replace_once(
        text,
        "    required this.controller,\n    required this.contracts,",
        "    required this.controller,\n    required this.scrollController,\n    required this.contracts,",
        'contracts content constructor',
    )
    text = replace_once(
        text,
        "  final ContractsController controller;\n  final List<SafeContractsContract> contracts;",
        "  final ContractsController controller;\n  final ScrollController scrollController;\n  final List<SafeContractsContract> contracts;",
        'contracts content field',
    )
    text = text.replace("          _Pagination(controller: controller, page: page),\n", '')
    text = text.replace("        _Pagination(controller: controller, page: page),\n", '')
    text = text.replace(
        "                  return ListView.separated(\n                    physics:",
        "                  return ListView.separated(\n                    controller: scrollController,\n                    physics:",
        1,
    )
    text = text.replace(
        "                return GridView.builder(\n                  physics:",
        "                return GridView.builder(\n                  controller: scrollController,\n                  physics:",
        1,
    )
    text = re.sub(
        r"\nfinal class _Pagination extends StatelessWidget \{.*?\n\}\n\n(?=final class _InlineLoadError)",
        "\n",
        text,
        count=1,
        flags=re.S,
    )
    return text


def patch_customers_model(text: str) -> str:
    pattern = re.compile(r"  Future<void> loadPage\(int page\) async \{.*?\n  \}\n\n  Future<void> refresh\(\) => loadPage\(currentPage\?\.page \?\? 1\);", re.S)
    replacement = '''  Future<void> loadPage(int page) async {
    if (!canAccess) {
      currentPage = null;
      errorMessage = 'Customer access is not authorized for this session.';
      state = CustomersLoadState.error;
      notifyListeners();
      return;
    }
    if (page < 1 || (page - 1) * pageSize > 1000000) return;
    final previous = currentPage;
    state = CustomersLoadState.loading;
    errorMessage = null;
    notifyListeners();
    try {
      final next = await repository.loadPage(
        page: page,
        perPage: pageSize,
        order: order,
      );
      if (page > 1 && previous != null) {
        final merged = <int, SafeContractsCustomer>{
          for (final item in previous.customers) item.id: item,
          for (final item in next.customers) item.id: item,
        };
        currentPage = CustomerPage(
          customers: List<SafeContractsCustomer>.unmodifiable(merged.values),
          page: next.page,
          perPage: next.perPage,
          total: next.total,
          totalPages: next.totalPages,
          hasMore: next.hasMore,
          scope: next.scope,
        );
      } else {
        currentPage = next;
      }
      state = CustomersLoadState.ready;
    } on SafeContractsApiException catch (error) {
      currentPage = previous;
      errorMessage = error.message;
      state = CustomersLoadState.error;
    } on Object catch (error) {
      currentPage = previous;
      errorMessage = error.toString();
      state = CustomersLoadState.error;
    }
    notifyListeners();
  }

  Future<void> refresh() => loadPage(1);'''
    text, count = pattern.subn(replacement, text, count=1)
    if count != 1:
        raise SystemExit('Customers loadPage marker not found')
    text = text.replace(
        "    if (page != null && page.hasMore && page.page < 5) {\n      await loadPage(page.page + 1);\n    }",
        "    if (page != null && page.hasMore) {\n      await loadPage(page.page + 1);\n    }",
        1,
    )
    return text


def patch_customers_screen(text: str) -> str:
    old = '''    final list = _CustomerList(
      controller: controller,
      customers: customers,
      hasQuery: hasQuery,
    );'''
    new = '''    final list = NotificationListener<ScrollNotification>(
      onNotification: (notification) {
        final page = controller.currentPage;
        if (notification.metrics.extentAfter <= 360 &&
            page != null &&
            page.hasMore &&
            controller.state != CustomersLoadState.loading) {
          unawaited(controller.nextPage());
        }
        return false;
      },
      child: _CustomerList(
        controller: controller,
        customers: customers,
        hasQuery: hasQuery,
      ),
    );'''
    text = replace_once(text, old, new, 'customer scroll wrapper')
    text = re.sub(
        r"\n        _Pagination\(\n          page: page,.*?\n        \),",
        "",
        text,
        count=1,
        flags=re.S,
    )
    text = text.replace('No matching customers on this page.', 'No matching customers in the loaded data.')
    text = re.sub(
        r"\nfinal class _Pagination extends StatelessWidget \{.*?\n\}\n\n(?=final class _CustomerCard)",
        "\n",
        text,
        count=1,
        flags=re.S,
    )
    return text


def patch_notifications_model(text: str) -> str:
    text = text.replace("final page = _boundedInt(meta['page'], 'meta.page', 1, 5);", "final page = _boundedInt(meta['page'], 'meta.page', 1, 1000000);")
    text = text.replace(
        "    if (page < 1 || page > 5) {\n      throw ArgumentError('Notification page must be between 1 and 5.');\n    }",
        "    if (page < 1 || page > 1000000) {\n      throw ArgumentError('Notification page is outside the supported range.');\n    }",
        1,
    )
    pattern = re.compile(r"  Future<void> loadPage\(int page\) async \{.*?\n  \}\n\n  Future<void> refresh\(\) => loadPage\(currentPage\?\.page \?\? 1\);", re.S)
    replacement = '''  Future<void> loadPage(int page) async {
    if (!canAccess) {
      currentPage = null;
      errorMessage = 'Notification access is not authorized for this session.';
      state = NotificationsLoadState.error;
      notifyListeners();
      return;
    }
    if (page < 1 || page > 1000000) return;

    final previous = currentPage;
    state = NotificationsLoadState.loading;
    errorMessage = null;
    notifyListeners();
    try {
      final nextPage = await repository.loadPage(page: page, perPage: pageSize);
      if (page > 1 && previous != null) {
        final merged = <int, SafeContractsNotification>{
          for (final item in previous.notifications) item.id: item,
          for (final item in nextPage.notifications) item.id: item,
        };
        currentPage = NotificationPage(
          notifications:
              List<SafeContractsNotification>.unmodifiable(merged.values),
          page: nextPage.page,
          perPage: nextPage.perPage,
          hasMore: nextPage.hasMore,
        );
      } else {
        currentPage = nextPage;
      }
      _readIds.addAll(
        nextPage.notifications
            .where((item) => item.isRead)
            .map((item) => item.id),
      );
      state = NotificationsLoadState.ready;
    } on SafeContractsApiException catch (error) {
      currentPage = previous;
      errorMessage = error.message;
      state = NotificationsLoadState.error;
    } on Object catch (error) {
      currentPage = previous;
      errorMessage = error.toString();
      state = NotificationsLoadState.error;
    }
    notifyListeners();
  }

  Future<void> refresh() => loadPage(1);'''
    text, count = pattern.subn(replacement, text, count=1)
    if count != 1:
        raise SystemExit('Notifications loadPage marker not found')
    text = text.replace(
        "    if (page != null && page.hasMore && page.page < 5) {\n      await loadPage(page.page + 1);\n    }",
        "    if (page != null && page.hasMore) {\n      await loadPage(page.page + 1);\n    }",
        1,
    )
    marker = "  Future<SafeContractsDeepLink?> openNotification("
    silent = '''  Future<void> refreshSilently() async {
    if (!canAccess) return;
    final previous = currentPage;
    try {
      final first = await repository.loadPage(page: 1, perPage: pageSize);
      if (previous != null && previous.page > 1) {
        final merged = <int, SafeContractsNotification>{
          for (final item in first.notifications) item.id: item,
          for (final item in previous.notifications) item.id: item,
        };
        currentPage = NotificationPage(
          notifications:
              List<SafeContractsNotification>.unmodifiable(merged.values),
          page: previous.page,
          perPage: first.perPage,
          hasMore: previous.hasMore,
        );
      } else {
        currentPage = first;
      }
      state = NotificationsLoadState.ready;
      errorMessage = null;
      notifyListeners();
    } on Object {
      // Preserve last good snapshot during background refresh failures.
    }
  }

'''
    if 'Future<void> refreshSilently()' not in text:
        text = replace_once(text, marker, silent + marker, 'notifications silent refresh')
    return text


def patch_notifications_screen(text: str) -> str:
    text = replace_once(
        text,
        "final class _NotificationsScreenState extends State<NotificationsScreen> {\n  _NotificationFilter _filter = _NotificationFilter.all;",
        "final class _NotificationsScreenState extends State<NotificationsScreen> {\n  _NotificationFilter _filter = _NotificationFilter.all;\n  final ScrollController _scrollController = ScrollController();",
        'notifications scroll field',
    )
    text = replace_once(
        text,
        "  void initState() {\n    super.initState();\n    unawaited(widget.controller.ensureLoaded());\n  }",
        "  void initState() {\n    super.initState();\n    _scrollController.addListener(_loadNextOnScroll);\n    unawaited(widget.controller.ensureLoaded());\n  }\n\n  void _loadNextOnScroll() {\n    final page = widget.controller.currentPage;\n    if (page == null || !page.hasMore ||\n        widget.controller.state == NotificationsLoadState.loading) {\n      return;\n    }\n    if (!_scrollController.hasClients ||\n        _scrollController.position.extentAfter > 360) {\n      return;\n    }\n    unawaited(widget.controller.nextPage());\n  }\n\n  @override\n  void dispose() {\n    _scrollController.dispose();\n    super.dispose();\n  }",
        'notifications scroll lifecycle',
    )
    text = text.replace(
        "            child: ListView(\n              physics:",
        "            child: ListView(\n              controller: _scrollController,\n              physics:",
        1,
    )
    text = text.replace("                _PagingControls(controller: controller),\n", '')
    text = re.sub(
        r"\nfinal class _PagingControls extends StatelessWidget \{.*?\n\}\n\n(?=bool _isPaymentDue)",
        "\n",
        text,
        count=1,
        flags=re.S,
    )
    return text


edit('mobile/lib/features/contracts/contracts.dart', patch_contracts_model)
edit('mobile/lib/features/contracts/contracts_screen.dart', patch_contracts_screen)
edit('mobile/lib/features/customers/customers.dart', patch_customers_model)
edit('mobile/lib/features/customers/customers_screen.dart', patch_customers_screen)
edit('mobile/lib/features/notifications/notifications.dart', patch_notifications_model)
edit('mobile/lib/features/notifications/notifications_screen.dart', patch_notifications_screen)
print('Materialized server-authoritative infinite scrolling for contracts, customers, and notifications.')
