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
        raise SystemExit(f"{label}: expected exactly one literal match, found {count}")
    return text.replace(old, new, 1)


def replace_region(text: str, start: str, end: str, new: str, label: str) -> str:
    a = text.find(start)
    if a < 0:
        raise SystemExit(f"{label}: start marker not found")
    b = text.find(end, a + len(start))
    if b < 0:
        raise SystemExit(f"{label}: end marker not found")
    return text[:a] + new + text[b:]


def sub_once(text: str, pattern: str, replacement: str, label: str, flags: int = 0) -> str:
    next_text, count = re.subn(pattern, replacement, text, count=1, flags=flags)
    if count != 1:
        raise SystemExit(f"{label}: expected exactly one regex match, found {count}")
    return next_text


# ---------------------------------------------------------------------------
# Customers: B002/B005/B007/B008/B009
# ---------------------------------------------------------------------------
customers_path = "mobile/lib/features/customers/customers_screen.dart"
customers = read(customers_path)

customers = replace_once(
    customers,
    "label: const Text('A–Z'),",
    "label: Text(ar ? 'أ–ي' : 'A–Z'),",
    "B008 A-Z localization",
)
customers = replace_once(
    customers,
    "label: const Text('Z–A'),",
    "label: Text(ar ? 'ي–أ' : 'Z–A'),",
    "B008 Z-A localization",
)
customers = replace_once(
    customers,
    "      if (customer.phone != null) customer.phone!,",
    "      if (customer.phone != null) '\\u2066${customer.phone!}\\u2069',",
    "B007 customer card phone isolate",
)

card_start = customers.index("final class _CustomerCard extends StatelessWidget {")
card_end = customers.index("final class _CustomerDetail extends StatefulWidget {", card_start)
card = customers[card_start:card_end]
card = replace_once(
    card,
    "                        maxLines: 1,\n                        overflow: TextOverflow.ellipsis,\n",
    "                        softWrap: true,\n",
    "B009 customer card detail wrap",
)
customers = customers[:card_start] + card + customers[card_end:]

state_start = customers.index("final class _CustomerDetailState extends State<_CustomerDetail> {")
state_end = customers.index("final class _CustomerHero extends StatelessWidget {", state_start)
detail_state = customers[state_start:state_end]
detail_state = sub_once(
    detail_state,
    r"  int\? _loadedId;\n  Future<CounterpartyBusinessSnapshot>\? _snapshot;\n\n  void _ensureSnapshot\(SafeContractsCustomer customer\) \{.*?\n  \}\n\n  @override\n  Widget build",
    """  int? _loadedId;
  Future<CounterpartyBusinessSnapshot>? _snapshot;

  Future<CounterpartyBusinessSnapshot> _loadSnapshot(
    SafeContractsCustomer customer,
  ) {
    return CounterpartyBusinessSnapshotRepository(
      widget.controller.repository.client,
    ).load(counterpartyType: 'customer', counterpartyId: customer.id);
  }

  void _ensureSnapshot(SafeContractsCustomer customer) {
    if (_loadedId == customer.id && _snapshot != null) return;
    _loadedId = customer.id;
    _snapshot = _loadSnapshot(customer);
  }

  Future<void> _retrySnapshot(SafeContractsCustomer customer) async {
    final next = _loadSnapshot(customer);
    setState(() {
      _loadedId = customer.id;
      _snapshot = next;
    });
    try {
      await next;
    } on Object {
      // FutureBuilder owns the visible server/parser failure state.
    }
  }

  @override
  Widget build""",
    "B002 linked-data retry state",
    flags=re.S,
)
detail_state = replace_once(
    detail_state,
    "label: Text(context.scL10n.t('Edit')),
",
    "label: Text(context.scL10n.isArabic ? 'تعديل' : 'Edit'),\n",
    "B008 customer edit label",
)
detail_state = sub_once(
    detail_state,
    r"              if \(snapshot\.hasError \|\| snapshot\.data == null\) \{.*?              \}\n              return _BusinessSnapshot\(",
    """              if (snapshot.hasError || snapshot.data == null) {
                final fallback = context.scL10n.isArabic
                    ? 'تعذر تحميل بيانات الأعمال المرتبطة بهذا العميل.'
                    : 'Unable to load this customer’s linked business data.';
                final rawError = snapshot.error?.toString();
                final message = rawError == null || rawError.trim().isEmpty
                    ? fallback
                    : '$fallback\n${rawError.trim()}';
                return _StateMessage(
                  icon: Icons.cloud_off_rounded,
                  message: context.scL10n.rawMessage(message),
                  action: () => _retrySnapshot(customer),
                );
              }
              return _BusinessSnapshot(""",
    "B002 linked-data visible error/retry",
    flags=re.S,
)
customers = customers[:state_start] + detail_state + customers[state_end:]

hero_start = customers.index("final class _CustomerHero extends StatelessWidget {")
hero_end = customers.index("final class _ContactPanel extends StatelessWidget {", hero_start)
hero = customers[hero_start:hero_end]
hero = replace_once(
    hero,
    "                  maxLines: 2,\n                  overflow: TextOverflow.ellipsis,\n",
    "                  softWrap: true,\n",
    "B009 customer hero wrap",
)
customers = customers[:hero_start] + hero + customers[hero_end:]

contact_row_start = customers.index("final class _ContactRow extends StatelessWidget {")
contact_row_end = customers.index("final class _BusinessSnapshot", contact_row_start)
contact_row = customers[contact_row_start:contact_row_end]
contact_row = sub_once(
    contact_row,
    r"                Text\(\n                  actual == null \|\| actual\.isEmpty \? '—' : actual,\n                  maxLines: 2,\n                  overflow: TextOverflow\.ellipsis,\n                \),",
    """                Directionality(
                  textDirection: icon == Icons.phone_outlined
                      ? TextDirection.ltr
                      : Directionality.of(context),
                  child: Text(
                    actual == null || actual.isEmpty ? '—' : actual,
                    softWrap: true,
                    textAlign: icon == Icons.phone_outlined
                        ? TextAlign.start
                        : null,
                  ),
                ),""",
    "B007/B009 contact value direction/wrap",
)
customers = customers[:contact_row_start] + contact_row + customers[contact_row_end:]

customers = sub_once(
    customers,
    r"              TextField\(\n\s+controller: _phone,\n\s+keyboardType: TextInputType\.phone,\n\s+decoration:\n\s+InputDecoration\(labelText: ar \? 'الهاتف' : 'Phone'\)\),",
    """              TextField(
                controller: _phone,
                keyboardType: TextInputType.phone,
                textDirection: TextDirection.ltr,
                textAlign: TextAlign.start,
                decoration: InputDecoration(labelText: ar ? 'الهاتف' : 'Phone'),
              ),""",
    "B007 phone editor direction",
)

metric_start = customers.index("final class _Metric extends StatelessWidget {")
metric_end = customers.index("final class _StateMessage extends StatelessWidget {", metric_start)
metric = customers[metric_start:metric_end]
metric = replace_once(
    metric,
    "          Text(value,\n              maxLines: 1,\n              overflow: TextOverflow.ellipsis,\n              style: const TextStyle(fontWeight: FontWeight.w900)),",
    """          Text(
            value,
            softWrap: true,
            style: const TextStyle(fontWeight: FontWeight.w900),
          ),""",
    "B009 metric value wrap",
)
customers = customers[:metric_start] + metric + customers[metric_end:]
write(customers_path, customers)


# ---------------------------------------------------------------------------
# Landing: B006/B007
# ---------------------------------------------------------------------------
welcome_path = "mobile/lib/features/welcome/premium_compact_welcome_screen.dart"
welcome = read(welcome_path)
welcome = replace_once(
    welcome,
    "                            const SizedBox(height: 18),\n                            const _ProgressDots(),\n                            const SizedBox(height: 18),",
    "                            const SizedBox(height: 18),",
    "B006 remove misleading dots call",
)
welcome = replace_region(
    welcome,
    "final class _ProgressDots extends StatelessWidget {",
    "final class _ReferenceBackdrop extends StatelessWidget {",
    "",
    "B006 remove misleading dots widget",
)
welcome = replace_once(
    welcome,
    "                  Expanded(child: Text(content.phones.join('  •  '))),",
    """                  Expanded(
                    child: Directionality(
                      textDirection: TextDirection.ltr,
                      child: Text(
                        content.phones.join('  •  '),
                        textAlign: TextAlign.start,
                      ),
                    ),
                  ),""",
    "B007 landing phone direction",
)
write(welcome_path, welcome)


# ---------------------------------------------------------------------------
# Shell: B003/B009/B010/B013/B025/B026
# ---------------------------------------------------------------------------
shell_path = "mobile/lib/features/navigation/app_shell.dart"
shell = read(shell_path)
shell = replace_once(
    shell,
    """            Expanded(
              child: Text.rich(
                TextSpan(
                  children: [
                    const TextSpan(
                      text: SafeContractsBrand.name,
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    TextSpan(
                      text: '  •  ${_label(l10n, _selected)}',
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.76),
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),""",
    """            Expanded(
              child: FittedBox(
                fit: BoxFit.scaleDown,
                alignment: AlignmentDirectional.centerStart,
                child: Text(
                  '${SafeContractsBrand.name} • ${_label(l10n, _selected)}',
                  maxLines: 1,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ),""",
    "B009 shell title fit",
)

drawer = """      drawer: NavigationDrawer(
        backgroundColor: SafeContractsVisual.navyDeep,
        indicatorColor: SafeContractsVisual.roseGoldSoft,
        selectedIndex: widget.policy.destinations.indexOf(_selected),
        onDestinationSelected: (index) {
          final destination = widget.policy.destinations[index];
          _selectDestination(destination);
          Navigator.of(context).pop();
        },
        children: [
          Container(
            margin: const EdgeInsets.fromLTRB(12, 14, 12, 10),
            padding: const EdgeInsets.fromLTRB(16, 18, 16, 18),
            decoration: BoxDecoration(
              gradient: SafeContractsVisual.premiumHeaderGradient,
              borderRadius: BorderRadius.circular(SafeContractsVisual.radius),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x2B092944),
                  blurRadius: 20,
                  offset: Offset(0, 8),
                ),
              ],
            ),
            child: Row(
              children: [
                const SafeContractsBrandMark(size: 48, borderRadius: 13),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        SafeContractsBrand.name,
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                              fontWeight: FontWeight.w900,
                              color: Colors.white,
                            ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        l10n.isArabic
                            ? 'مساحة العمل التنفيذية'
                            : 'Executive workspace',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: Colors.white.withValues(alpha: 0.70),
                            ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          ...widget.policy.destinations.map((destination) {
            final selected = destination == _selected;
            return NavigationDrawerDestination(
              icon: Icon(
                _icon(destination),
                color: Colors.white.withValues(alpha: 0.86),
              ),
              selectedIcon: Icon(
                _icon(destination),
                color: SafeContractsVisual.navyDeep,
              ),
              label: Text(
                _label(l10n, destination),
                style: TextStyle(
                  color: selected
                      ? SafeContractsVisual.navyDeep
                      : Colors.white.withValues(alpha: 0.94),
                  fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
                ),
              ),
            );
          }),
        ],
      ),
"""
shell = replace_region(
    shell,
    "      drawer: NavigationDrawer(",
    "      body: SafeContractsBackdrop(",
    drawer,
    "B003/B013 drawer policy presentation",
)

bottom_region_start = shell.index("  List<MobileDestination> _bottomDestinations() {")
bottom_region_end = shell.index("  Widget _body() {", bottom_region_start)
bottom_region = shell[bottom_region_start:bottom_region_end]
bottom_region = replace_once(
    bottom_region,
    ".take(5)",
    ".take(4)",
    "B010/B025 bottom nav density",
)
shell = shell[:bottom_region_start] + bottom_region + shell[bottom_region_end:]

shell = replace_once(
    shell,
    """              labelFor: (destination) => _label(l10n, destination),
              onSelected: _selectDestination,""",
    """              labelFor: (destination) => _label(l10n, destination),
              centerGap: quickAdds.isNotEmpty,
              onSelected: _selectDestination,""",
    "B026 bottom nav FAB gap argument",
)

fab_start = shell.index("final class _QuickAddFabState extends State<_QuickAddFab>")
fab_end = shell.index("final class _QuickAddSheet extends StatelessWidget {", fab_start)
fab = shell[fab_start:fab_end]
fab = replace_once(fab, "            width: 3,", "            width: 2,", "B026 FAB border")
fab = replace_once(fab, "              blurRadius: 24,", "              blurRadius: 18,", "B026 FAB shadow")
fab = replace_once(
    fab,
    """        child: FloatingActionButton(
          tooltip: widget.tooltip,
          onPressed: widget.onPressed,
          backgroundColor: SafeContractsVisual.roseGold,
          foregroundColor: Colors.white,
          elevation: 0,
          child: const Icon(Icons.add_rounded, size: 34),
        ),""",
    """        child: SizedBox.square(
          dimension: 50,
          child: FloatingActionButton(
            tooltip: widget.tooltip,
            onPressed: widget.onPressed,
            backgroundColor: SafeContractsVisual.roseGold,
            foregroundColor: Colors.white,
            elevation: 0,
            child: const Icon(Icons.add_rounded, size: 26),
          ),
        ),""",
    "B026 FAB compact size",
)
shell = shell[:fab_start] + fab + shell[fab_end:]

bottom_nav = """final class _SafeContractsBottomNavigation extends StatelessWidget {
  const _SafeContractsBottomNavigation({
    required this.destinations,
    required this.selected,
    required this.labelFor,
    required this.centerGap,
    required this.onSelected,
  });

  final List<MobileDestination> destinations;
  final MobileDestination selected;
  final String Function(MobileDestination destination) labelFor;
  final bool centerGap;
  final ValueChanged<MobileDestination> onSelected;

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final compact = width <= 360;
    final split = (destinations.length + 1) ~/ 2;
    final left = destinations.take(split).toList(growable: false);
    final right = destinations.skip(split).toList(growable: false);

    Widget group(List<MobileDestination> values) {
      return Row(
        children: values
            .map(
              (destination) => Expanded(
                child: _BottomNavItem(
                  destination: destination,
                  selected: destination == this.selected,
                  label: labelFor(destination),
                  compact: compact,
                  onTap: () => onSelected(destination),
                ),
              ),
            )
            .toList(growable: false),
      );
    }

    final row = centerGap && right.isNotEmpty
        ? Row(
            children: [
              Expanded(child: group(left)),
              const SizedBox(width: 56),
              Expanded(child: group(right)),
            ],
          )
        : group(destinations);

    return SafeArea(
      top: false,
      child: DecoratedBox(
        decoration: const BoxDecoration(
          color: SafeContractsVisual.surface,
          border: Border(
            top: BorderSide(color: SafeContractsVisual.outline),
          ),
          boxShadow: [
            BoxShadow(
              color: Color(0x205E5142),
              blurRadius: 18,
              offset: Offset(0, -4),
            ),
          ],
        ),
        child: Padding(
          padding: EdgeInsets.fromLTRB(5, compact ? 6 : 7, 5, 5),
          child: row,
        ),
      ),
    );
  }
}

final class _BottomNavItem extends StatelessWidget {
  const _BottomNavItem({
    required this.destination,
    required this.selected,
    required this.label,
    required this.compact,
    required this.onTap,
  });

  final MobileDestination destination;
  final bool selected;
  final String label;
  final bool compact;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      key: ValueKey<String>('bottomNav_${destination.name}'),
      borderRadius: BorderRadius.circular(13),
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        curve: Curves.easeOutCubic,
        padding: EdgeInsets.symmetric(vertical: compact ? 3 : 4, horizontal: 2),
        decoration: BoxDecoration(
          color: selected
              ? SafeContractsVisual.roseGoldSoft.withValues(alpha: 0.86)
              : Colors.transparent,
          borderRadius: BorderRadius.circular(13),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              _icon(destination),
              size: compact ? 19 : 20,
              color: selected
                  ? SafeContractsVisual.navy
                  : SafeContractsVisual.muted,
            ),
            const SizedBox(height: 2),
            SizedBox(
              height: compact ? 13 : 14,
              child: FittedBox(
                fit: BoxFit.scaleDown,
                child: Text(
                  label,
                  maxLines: 1,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        fontSize: compact ? 9.5 : 10.5,
                        color: selected
                            ? SafeContractsVisual.navy
                            : SafeContractsVisual.muted,
                        fontWeight:
                            selected ? FontWeight.w800 : FontWeight.w600,
                      ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

"""
shell = replace_region(
    shell,
    "final class _SafeContractsBottomNavigation extends StatelessWidget {",
    "String _label(",
    bottom_nav,
    "B010/B025 bottom navigation implementation",
)
write(shell_path, shell)


# ---------------------------------------------------------------------------
# Regression tests: real request state and linked business retry.
# ---------------------------------------------------------------------------
Path("mobile/test/alkenzy_w1_bug_closure_test.dart").write_text(r'''import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/features/customers/customers.dart';
import 'package:safecontracts_mobile/features/customers/customers_screen.dart';

import 'fake_api_transport.dart';

void main() {
  test('B005 Retry performs a real second customer request', () async {
    var calls = 0;
    final transport = FakeApiTransport((uri) {
      if (!uri.path.endsWith('/customers')) {
        return _error(404, 'not_found', 'Not found');
      }
      calls += 1;
      if (calls == 1) {
        return _error(
          503,
          'temporarily_unavailable',
          'Customer service unavailable',
        );
      }
      return _customerPage();
    });
    final controller = CustomersController(
      repository: CustomersRepository(_client(transport)),
      pageSize: 25,
      canAccess: true,
    );

    await controller.ensureLoaded();
    expect(controller.state, CustomersLoadState.error);
    expect(calls, 1);

    await controller.loadPage(1);
    expect(controller.state, CustomersLoadState.ready);
    expect(calls, 2);
    expect(controller.currentPage!.customers.single.id, 7);
    controller.dispose();
  });

  testWidgets('B002 linked-data failure is visible and Retry reissues request',
      (tester) async {
    var contractCalls = 0;
    final transport = FakeApiTransport((uri) {
      if (uri.path.endsWith('/customers/7')) return _customerDetail();
      if (uri.path.endsWith('/customers')) return _customerPage();
      if (uri.path.endsWith('/contracts')) {
        contractCalls += 1;
        if (contractCalls == 1) {
          return _error(
            503,
            'linked_data_down',
            'Linked contract service unavailable',
          );
        }
        return _page(<Object?>[], sort: 'end_date', order: 'asc');
      }
      if (uri.path.endsWith('/payments')) {
        return _page(<Object?>[], sort: 'due_date', order: 'asc');
      }
      if (uri.path.endsWith('/finance/summary')) {
        return _ok(<Object?>[]);
      }
      if (uri.path.endsWith('/collections')) {
        return _page(
          <Object?>[],
          sort: 'collection_date',
          order: 'desc',
        );
      }
      return _error(404, 'not_found', 'Not found');
    });
    final controller = CustomersController(
      repository: CustomersRepository(_client(transport)),
      pageSize: 25,
      canAccess: true,
    );

    await tester.pumpWidget(
      MaterialApp(home: CustomersScreen(controller: controller)),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Acme Customer'));
    await tester.pumpAndSettle();

    expect(contractCalls, 1);
    expect(find.textContaining('Unable to load this customer'), findsOneWidget);
    expect(
      find.textContaining('Linked contract service unavailable'),
      findsOneWidget,
    );

    await tester.tap(find.text('Retry'));
    await tester.pumpAndSettle();
    expect(contractCalls, 2);
    expect(find.text('Contracts (0)'), findsOneWidget);
    controller.dispose();
  });
}

SafeContractsApiClient _client(SafeContractsTransport transport) {
  return SafeContractsApiClient(
    environment: AppEnvironment.fromValues(
      name: 'local',
      apiBaseUrl: 'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
    ),
    transport: transport,
  );
}

ApiTransportResponse _customerPage() => _page(
      <Object?>[
        <String, Object?>{
          'id': 7,
          'internal_code': 'C7',
          'name': 'Acme Customer',
          'contact_name': 'Operations',
          'email': 'ops@example.test',
          'phone': '+96555555555',
          'is_active': '1',
        },
      ],
      sort: 'name',
      order: 'asc',
    );

ApiTransportResponse _customerDetail() => _ok(<String, Object?>{
      'id': 7,
      'internal_code': 'C7',
      'name': 'Acme Customer',
      'contact_name': 'Operations',
      'email': 'ops@example.test',
      'phone': '+96555555555',
      'is_active': '1',
    });

ApiTransportResponse _page(
  Object? data, {
  required String sort,
  required String order,
}) =>
    _ok(data, meta: <String, Object?>{
      'api_version': 'v1',
      'scope': 'assigned',
      'page': 1,
      'per_page': 25,
      'sort': sort,
      'order': order,
      'returned': data is List ? data.length : 0,
      'available_in_bounded_read': data is List ? data.length : 0,
      'bounded_window': 500,
      'has_more': false,
    });

ApiTransportResponse _ok(
  Object? data, {
  Map<String, Object?> meta = const <String, Object?>{'api_version': 'v1'},
}) =>
    ApiTransportResponse(
      statusCode: 200,
      headers: const <String, String>{'content-type': 'application/json'},
      body: jsonEncode(<String, Object?>{'data': data, 'meta': meta}),
    );

ApiTransportResponse _error(int status, String code, String message) =>
    ApiTransportResponse(
      statusCode: status,
      headers: const <String, String>{'content-type': 'application/json'},
      body: jsonEncode(<String, Object?>{
        'code': code,
        'message': message,
        'data': <String, Object?>{'status': status},
      }),
    );
''')


# ---------------------------------------------------------------------------
# Responsive evidence: make existing shell/landing harness width+locale aware
# and persist PNGs; add dedicated customer list/detail captures.
# ---------------------------------------------------------------------------
visual_path = "mobile/test/worker1_visual_capture_test.dart"
visual = read(visual_path)
visual = replace_once(
    visual,
    "import 'dart:convert';\n",
    "import 'dart:convert';\nimport 'dart:io';\n",
    "visual import io",
)
visual = replace_once(
    visual,
    "    await tester.binding.setSurfaceSize(const Size(390, 844));",
    """    const captureWidth = int.fromEnvironment(
      'W1_CAPTURE_WIDTH',
      defaultValue: 390,
    );
    const captureLanguage = String.fromEnvironment(
      'W1_CAPTURE_LANG',
      defaultValue: 'ar',
    );
    await tester.binding.setSurfaceSize(Size(captureWidth.toDouble(), 844));""",
    "visual width locale defines",
)
visual = replace_once(
    visual,
    "          languageCode: 'ar',",
    "          languageCode: captureLanguage,",
    "visual app locale",
)
for name in ("welcome_ar", "login_ar", "shell_ar", "drawer_ar", "quick_add_ar"):
    visual = replace_once(
        visual,
        f"await _capture(tester, '{name}');",
        f"await _capture(tester, '${{captureWidth}}_${{captureLanguage}}_{name.replace('_ar', '')}');",
        f"visual capture name {name}",
    )
visual = replace_once(
    visual,
    """  final bytes = data!.buffer.asUint8List();
  // Intentionally emitted only by this temporary visual-QA test.""",
    """  final bytes = data!.buffer.asUint8List();
  final directory = Directory('build/w1-bug-closure');
  directory.createSync(recursive: true);
  File('${directory.path}/$name.png').writeAsBytesSync(bytes);
  // Intentionally emitted only by this visual-QA test.""",
    "visual persist png",
)
write(visual_path, visual)

Path("mobile/test/w1_customer_visual_capture.dart").write_text(r'''import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/core/localization/safecontracts_localizations.dart';
import 'package:safecontracts_mobile/features/customers/customers.dart';
import 'package:safecontracts_mobile/features/customers/customers_screen.dart';
import 'package:safecontracts_mobile/features/ui/safecontracts_theme.dart';

import 'fake_api_transport.dart';

void main() {
  testWidgets('W1 customer responsive evidence', (tester) async {
    const captureWidth = int.fromEnvironment(
      'W1_CAPTURE_WIDTH',
      defaultValue: 390,
    );
    const captureLanguage = String.fromEnvironment(
      'W1_CAPTURE_LANG',
      defaultValue: 'ar',
    );
    await tester.binding.setSurfaceSize(Size(captureWidth.toDouble(), 844));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final transport = FakeApiTransport((uri) {
      if (uri.path.endsWith('/customers/7')) return _customerDetail();
      if (uri.path.endsWith('/customers')) return _customerPage();
      if (uri.path.endsWith('/contracts')) {
        return _page(<Object?>[], sort: 'end_date', order: 'asc');
      }
      if (uri.path.endsWith('/payments')) {
        return _page(<Object?>[], sort: 'due_date', order: 'asc');
      }
      if (uri.path.endsWith('/finance/summary')) return _ok(<Object?>[]);
      if (uri.path.endsWith('/collections')) {
        return _page(
          <Object?>[],
          sort: 'collection_date',
          order: 'desc',
        );
      }
      return _error(404, 'not_found', 'Not found');
    });
    final controller = CustomersController(
      repository: CustomersRepository(_client(transport)),
      pageSize: 25,
      canAccess: true,
      canEdit: true,
    );

    await tester.pumpWidget(
      RepaintBoundary(
        key: const Key('w1CustomerCapture'),
        child: MaterialApp(
          locale: Locale(captureLanguage),
          supportedLocales: SafeContractsLocalizations.supportedLocales,
          localizationsDelegates: const <LocalizationsDelegate<dynamic>>[
            SafeContractsLocalizations.delegate,
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          theme: SafeContractsTheme.build(captureLanguage),
          home: Scaffold(body: CustomersScreen(controller: controller)),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await _capture(
      tester,
      '${captureWidth}_${captureLanguage}_customers',
    );

    await tester.tap(find.text(_customerName(captureLanguage)));
    await tester.pumpAndSettle();
    await _capture(
      tester,
      '${captureWidth}_${captureLanguage}_customer_detail',
    );

    expect(tester.takeException(), isNull);
    controller.dispose();
  });
}

String _customerName(String languageCode) => languageCode == 'ar'
    ? 'شركة عميل تجريبي طويلة للاختبار'
    : 'Long Customer Company for Responsive Testing';

SafeContractsApiClient _client(SafeContractsTransport transport) {
  return SafeContractsApiClient(
    environment: AppEnvironment.fromValues(
      name: 'local',
      apiBaseUrl: 'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
    ),
    transport: transport,
  );
}

ApiTransportResponse _customerPage() => _page(
      <Object?>[
        <String, Object?>{
          'id': 7,
          'internal_code': 'C-007',
          'name': _customerName(
            const String.fromEnvironment('W1_CAPTURE_LANG', defaultValue: 'ar'),
          ),
          'contact_name': 'Operations',
          'email': 'ops@example.test',
          'phone': '+965 5555 1234',
          'is_active': '1',
        },
      ],
      sort: 'name',
      order: 'asc',
    );

ApiTransportResponse _customerDetail() => _ok(<String, Object?>{
      'id': 7,
      'internal_code': 'C-007',
      'name': _customerName(
        const String.fromEnvironment('W1_CAPTURE_LANG', defaultValue: 'ar'),
      ),
      'contact_name': 'Operations',
      'email': 'ops@example.test',
      'phone': '+965 5555 1234',
      'is_active': '1',
    });

ApiTransportResponse _page(
  Object? data, {
  required String sort,
  required String order,
}) =>
    _ok(data, meta: <String, Object?>{
      'api_version': 'v1',
      'scope': 'assigned',
      'page': 1,
      'per_page': 25,
      'sort': sort,
      'order': order,
      'returned': data is List ? data.length : 0,
      'available_in_bounded_read': data is List ? data.length : 0,
      'bounded_window': 500,
      'has_more': false,
    });

ApiTransportResponse _ok(
  Object? data, {
  Map<String, Object?> meta = const <String, Object?>{'api_version': 'v1'},
}) =>
    ApiTransportResponse(
      statusCode: 200,
      headers: const <String, String>{'content-type': 'application/json'},
      body: jsonEncode(<String, Object?>{'data': data, 'meta': meta}),
    );

ApiTransportResponse _error(int status, String code, String message) =>
    ApiTransportResponse(
      statusCode: status,
      headers: const <String, String>{'content-type': 'application/json'},
      body: jsonEncode(<String, Object?>{
        'code': code,
        'message': message,
        'data': <String, Object?>{'status': status},
      }),
    );

Future<void> _capture(WidgetTester tester, String name) async {
  final boundary = tester.renderObject<RenderRepaintBoundary>(
    find.byKey(const Key('w1CustomerCapture')),
  );
  final ui.Image image = await boundary.toImage(pixelRatio: 0.75);
  final data = await image.toByteData(format: ui.ImageByteFormat.png);
  final bytes = data!.buffer.asUint8List();
  final directory = Directory('build/w1-bug-closure');
  directory.createSync(recursive: true);
  File('${directory.path}/$name.png').writeAsBytesSync(bytes);
}
''')
