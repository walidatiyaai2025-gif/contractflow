from pathlib import Path

path = Path('mobile/lib/features/records/mobile_quick_add_flow.dart')
source = path.read_text(encoding='utf-8')

source = source.replace(
    "final class _MobileQuickAddScreenState extends State<MobileQuickAddScreen> {\n  final _name = TextEditingController();",
    "final class _MobileQuickAddScreenState extends State<MobileQuickAddScreen> {\n  static const _maxReferencePage = 5;\n\n  final _name = TextEditingController();",
    1,
)
source = source.replace(
    "  CustomerPage? _customerPage;\n  List<SafeContractsSupplier> _suppliers = const [];\n  List<SafeContractsContract> _contracts = const [];",
    "  CustomerPage? _customerPage;\n  ContractPage? _contractPage;\n  List<SafeContractsSupplier> _suppliers = const [];\n  List<SafeContractsContract> _contracts = const [];",
    1,
)
source = source.replace(
    "  Future<void> _loadReferences({int customerPage = 1}) async {",
    "  Future<void> _loadReferences({\n    int customerPage = 1,\n    int contractPage = 1,\n  }) async {",
    1,
)
source = source.replace(
    "        final customers = await CustomersRepository(widget.client)\n            .loadPage(page: customerPage, perPage: 100, order: 'asc');\n        var suppliers = const <SafeContractsSupplier>[];",
    "        final customers = await CustomersRepository(widget.client)\n            .loadPage(page: customerPage, perPage: 100, order: 'asc');\n        if (customers.page == _maxReferencePage && customers.hasMore) {\n          throw const FormatException(\n            'Customer references exceed the supported bounded mobile window.',\n          );\n        }\n        var suppliers = const <SafeContractsSupplier>[];",
    1,
)
old_payment_load = """      final contracts = await ContractsRepository(widget.client).loadPage(
        page: 1,
        perPage: 100,
        filters: const ContractsFilters(),
        sort: ContractSortOption.newest,
      );
      if (!mounted) return;
      setState(() {
        _contracts = contracts.contracts;
        _loading = false;
      });
"""
new_payment_load = """      final contracts = await ContractsRepository(widget.client).loadPage(
        page: contractPage,
        perPage: 100,
        filters: const ContractsFilters(),
        sort: ContractSortOption.newest,
      );
      if (contracts.page == _maxReferencePage && contracts.hasMore) {
        throw const FormatException(
          'Contract references exceed the supported bounded mobile window.',
        );
      }
      if (!mounted) return;
      setState(() {
        _contractPage = contracts;
        _contracts = contracts.contracts;
        if (!_contracts.any((contract) => contract.id == _paymentContractId)) {
          _paymentContractId = null;
        }
        _loading = false;
      });
"""
if old_payment_load not in source:
    raise SystemExit('payment reference load marker not found')
source = source.replace(old_payment_load, new_payment_load, 1)
source = source.replace("_customerPage!.page >= 5", "_customerPage!.page >= _maxReferencePage", 1)

old_payment_dropdown_tail = """          onChanged:
              _saving ? null : (v) => setState(() => _paymentContractId = v),
        ),
        _gap(),
        _field(
"""
new_payment_dropdown_tail = """          onChanged:
              _saving ? null : (v) => setState(() => _paymentContractId = v),
        ),
        if (_contractPage != null) ...[
          const SizedBox(height: 8),
          Row(
            children: [
              OutlinedButton(
                onPressed: _saving || _contractPage!.page <= 1
                    ? null
                    : () => unawaited(
                          _loadReferences(
                            contractPage: _contractPage!.page - 1,
                          ),
                        ),
                child: Text(ar ? 'السابق' : 'Previous'),
              ),
              const Spacer(),
              Text(ar ? 'الصفحة ${_contractPage!.page}' : 'Page ${_contractPage!.page}'),
              const Spacer(),
              OutlinedButton(
                onPressed: _saving ||
                        !_contractPage!.hasMore ||
                        _contractPage!.page >= _maxReferencePage
                    ? null
                    : () => unawaited(
                          _loadReferences(
                            contractPage: _contractPage!.page + 1,
                          ),
                        ),
                child: Text(ar ? 'التالي' : 'Next'),
              ),
            ],
          ),
        ],
        _gap(),
        _field(
"""
if old_payment_dropdown_tail not in source:
    raise SystemExit('payment form marker not found')
source = source.replace(old_payment_dropdown_tail, new_payment_dropdown_tail, 1)

path.write_text(source, encoding='utf-8')
