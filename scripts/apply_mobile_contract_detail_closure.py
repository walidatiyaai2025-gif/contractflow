from pathlib import Path

contracts_path = Path('mobile/lib/features/contracts/contracts.dart')
text = contracts_path.read_text(encoding='utf-8')
api_import = "import '../../core/api/api_client.dart';"
diag_import = "import '../diagnostics/mobile_runtime_diagnostics.dart';"
if diag_import not in text:
    if api_import not in text:
        raise SystemExit('contracts import marker missing')
    text = text.replace(api_import, api_import + '\n' + diag_import, 1)

old_load = """  Future<SafeContractsContract> loadContract(int id) async {
    if (id <= 0) throw ArgumentError('Contract ID must be positive.');
    final envelope = await client.get('contracts/$id');
    final contract = SafeContractsContract.fromData(envelope.data);
    if (contract.id != id) {
      throw const FormatException('Contract detail ID does not match request.');
    }
    return contract;
  }
"""
new_load = """  Future<SafeContractsContract> loadContract(int id) async {
    if (id <= 0) throw ArgumentError('Contract ID must be positive.');
    try {
      final envelope = await client.get('contracts/$id');
      final contract = SafeContractsContract.fromData(envelope.data);
      if (contract.id != id) {
        throw const FormatException(
          'Contract detail ID does not match request.',
        );
      }
      return contract;
    } on SafeContractsApiException catch (error) {
      MobileRuntimeDiagnostics.record(
        operation: 'contract.detail',
        stage: 'direct.api',
        error: error,
        context: <String, Object?>{
          'contract_id': id,
          'status_code': error.statusCode,
          'api_code': error.code,
        },
      );
      if (error.statusCode == 401 || error.statusCode == 403) rethrow;
      final fallback = await _loadContractFromList(id);
      if (fallback != null) return fallback;
      rethrow;
    } on Object catch (error) {
      MobileRuntimeDiagnostics.record(
        operation: 'contract.detail',
        stage: 'direct.parse',
        error: error,
        context: <String, Object?>{'contract_id': id},
      );
      final fallback = await _loadContractFromList(id);
      if (fallback != null) return fallback;
      rethrow;
    }
  }

  Future<SafeContractsContract?> _loadContractFromList(int id) async {
    try {
      final envelope = await client.get(
        'contracts',
        query: <String, String>{
          'contract_id': '$id',
          'page': '1',
          'per_page': '1',
          'sort': 'id',
          'order': 'desc',
        },
      );
      final page = ContractPage.fromEnvelope(envelope);
      for (final contract in page.contracts) {
        if (contract.id == id) return contract;
      }
      MobileRuntimeDiagnostics.record(
        operation: 'contract.detail',
        stage: 'fallback.missing',
        error: StateError('Filtered contract list returned no exact match.'),
        context: <String, Object?>{
          'contract_id': id,
          'returned': page.contracts.length,
        },
      );
      return null;
    } on SafeContractsApiException catch (error) {
      MobileRuntimeDiagnostics.record(
        operation: 'contract.detail',
        stage: 'fallback.api',
        error: error,
        context: <String, Object?>{
          'contract_id': id,
          'status_code': error.statusCode,
          'api_code': error.code,
        },
      );
      if (error.statusCode == 401 || error.statusCode == 403) rethrow;
      return null;
    } on Object catch (error) {
      MobileRuntimeDiagnostics.record(
        operation: 'contract.detail',
        stage: 'fallback.parse',
        error: error,
        context: <String, Object?>{'contract_id': id},
      );
      return null;
    }
  }
"""
if old_load not in text:
    raise SystemExit('loadContract marker missing')
text = text.replace(old_load, new_load, 1)

old_open = """    selectedContractId = id;
    selectedContract = null;
    detailErrorMessage = null;
    detailState = ContractDetailLoadState.loading;
    notifyListeners();
    try {
"""
new_open = """    final page = currentPage;
    if (page != null) {
      for (final cached in page.contracts) {
        if (cached.id == id) {
          selectedContractId = id;
          selectedContract = cached;
          detailErrorMessage = null;
          detailState = ContractDetailLoadState.ready;
          notifyListeners();
          return;
        }
      }
    }
    selectedContractId = id;
    selectedContract = null;
    detailErrorMessage = null;
    detailState = ContractDetailLoadState.loading;
    notifyListeners();
    try {
"""
if old_open not in text:
    raise SystemExit('openContract marker missing')
text = text.replace(old_open, new_open, 1)
contracts_path.write_text(text, encoding='utf-8')

inspector_path = Path('mobile/lib/features/diagnostics/runtime_inspector_screen.dart')
inspector = inspector_path.read_text(encoding='utf-8')
diag_screen_import = "import 'mobile_runtime_diagnostics.dart';"
session_import = "import '../session/session_controller.dart';"
if diag_screen_import not in inspector:
    if session_import not in inspector:
        raise SystemExit('inspector import marker missing')
    inspector = inspector.replace(
        session_import,
        session_import + '\n' + diag_screen_import,
        1,
    )
card_marker = """          _EnvironmentCard(
            environment: snapshot.environment,
            eventCount: snapshot.events.length,
            isArabic: isArabic,
          ),
          const SizedBox(height: 12),
          if (snapshot.events.isEmpty)
"""
card_replacement = """          _EnvironmentCard(
            environment: snapshot.environment,
            eventCount: snapshot.events.length,
            isArabic: isArabic,
          ),
          const SizedBox(height: 12),
          MobileDiagnosticsCard(isArabic: isArabic),
          const SizedBox(height: 12),
          if (snapshot.events.isEmpty)
"""
if card_marker not in inspector:
    raise SystemExit('inspector card marker missing')
inspector = inspector.replace(card_marker, card_replacement, 1)
inspector_path.write_text(inspector, encoding='utf-8')

shell_path = Path('mobile/lib/features/navigation/app_shell.dart')
shell = shell_path.read_text(encoding='utf-8')
old_shell_open = """  void _openContract(int contractId) {
    final canOpenContractEditor = widget.contractsController.canEditContract ||
        widget.session.can('safecontracts_assign_contracts');
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (context) => PremiumContractDetailsScreen(
          repository: ContractsRepository(
            widget.contractsController.repository.client,
          ),
          contractId: contractId,
          currency: widget.config.currency,
          onEditContract: canOpenContractEditor
              ? () => _openContractEdit(contractId)
              : null,
          onOpenLegacy: () => _openLegacyContract(contractId),
        ),
      ),
    );
  }
"""
new_shell_open = """  void _openContract(int contractId) {
    final canOpenContractEditor = widget.contractsController.canEditContract ||
        widget.session.can('safecontracts_assign_contracts');
    SafeContractsContract? initialContract;
    final currentPage = widget.contractsController.currentPage;
    if (currentPage != null) {
      for (final contract in currentPage.contracts) {
        if (contract.id == contractId) {
          initialContract = contract;
          break;
        }
      }
    }
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (context) => PremiumContractDetailsScreen(
          repository: ContractsRepository(
            widget.contractsController.repository.client,
          ),
          contractId: contractId,
          currency: widget.config.currency,
          initialContract: initialContract,
          onEditContract: canOpenContractEditor
              ? () => _openContractEdit(contractId)
              : null,
          onOpenLegacy: () => _openLegacyContract(contractId),
        ),
      ),
    );
  }
"""
if old_shell_open not in shell:
    raise SystemExit('app shell contract open marker missing')
shell = shell.replace(old_shell_open, new_shell_open, 1)
shell_path.write_text(shell, encoding='utf-8')

premium_path = Path('mobile/lib/features/contracts/premium_contract_details_screen.dart')
premium = premium_path.read_text(encoding='utf-8')
if not premium.startswith("import 'dart:async';"):
    premium = "import 'dart:async';\n\n" + premium
premium_diag_import = "import '../diagnostics/mobile_runtime_diagnostics.dart';"
premium_finance_import = "import '../finance/finance.dart';"
if premium_diag_import not in premium:
    if premium_finance_import not in premium:
        raise SystemExit('premium diagnostics import marker missing')
    premium = premium.replace(
        premium_finance_import,
        premium_diag_import + '\n' + premium_finance_import,
        1,
    )

premium = premium.replace(
    """    required this.currency,
    this.onEditContract,
""",
    """    required this.currency,
    this.initialContract,
    this.onEditContract,
""",
    1,
)
premium = premium.replace(
    """  final MobileCurrencyConfig currency;
  final VoidCallback? onEditContract;
""",
    """  final MobileCurrencyConfig currency;
  final SafeContractsContract? initialContract;
  final VoidCallback? onEditContract;
""",
    1,
)
old_premium_load = """  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<_PremiumContractBundle> _load() async {
    final client = widget.repository.client;
    final contract = await widget.repository.loadContract(widget.contractId);

    ContractMedia? media;
    try {
      media = await ContractMediaRepository(client).load(widget.contractId);
    } on Object {
      // Contract details remain usable with a neutral image placeholder.
    }

    final paymentPage = await PaymentsRepository(client).loadPage(
      page: 1,
      perPage: 100,
      filters: DashboardFilters(contractId: widget.contractId),
    );

    var financeAuthorized = true;
    var finance = const <FinanceSummaryRow>[];
    try {
      final envelope = await client.get(
        'finance/summary',
        query: <String, String>{
          'contract_id': '${widget.contractId}',
          'financial_direction': contract.financialDirection,
          'page': '1',
          'per_page': '100',
          'sort': 'financial_direction',
          'order': 'asc',
        },
      );
      finance = List<FinanceSummaryRow>.unmodifiable(
        apiObjectList(envelope.data, 'contract_finance.data')
            .map(FinanceSummaryRow.fromData),
      );
    } on SafeContractsApiException catch (error) {
      if (error.statusCode != 403) rethrow;
      financeAuthorized = false;
    }

    return _PremiumContractBundle(
      contract: contract,
      media: media,
      payments: paymentPage.payments,
      finance: finance,
      financeAuthorized: financeAuthorized,
    );
  }

  Future<void> _refresh() async {
    final next = _load();
    setState(() => _future = next);
    await next;
  }
"""
new_premium_load = """  @override
  void initState() {
    super.initState();
    final initial = widget.initialContract;
    if (initial != null && initial.id == widget.contractId) {
      _future = Future<_PremiumContractBundle>.value(
        _PremiumContractBundle(
          contract: initial,
          media: null,
          payments: const <SafeContractsPayment>[],
          finance: const <FinanceSummaryRow>[],
          financeAuthorized: false,
        ),
      );
      unawaited(_hydrate(initial));
    } else {
      _future = _load();
    }
  }

  Future<_PremiumContractBundle> _load({
    SafeContractsContract? initialContract,
  }) async {
    final client = widget.repository.client;
    final contract = initialContract ??
        await widget.repository.loadContract(widget.contractId);

    ContractMedia? media;
    try {
      media = await ContractMediaRepository(client).load(widget.contractId);
    } on Object catch (error) {
      MobileRuntimeDiagnostics.record(
        operation: 'contract.detail',
        stage: 'premium.media',
        error: error,
        context: <String, Object?>{'contract_id': widget.contractId},
      );
    }

    var payments = const <SafeContractsPayment>[];
    try {
      final paymentPage = await PaymentsRepository(client).loadPage(
        page: 1,
        perPage: 100,
        filters: DashboardFilters(contractId: widget.contractId),
      );
      payments = paymentPage.payments;
    } on Object catch (error) {
      MobileRuntimeDiagnostics.record(
        operation: 'contract.detail',
        stage: 'premium.payments',
        error: error,
        context: <String, Object?>{'contract_id': widget.contractId},
      );
    }

    var financeAuthorized = true;
    var finance = const <FinanceSummaryRow>[];
    try {
      final envelope = await client.get(
        'finance/summary',
        query: <String, String>{
          'contract_id': '${widget.contractId}',
          'financial_direction': contract.financialDirection,
          'page': '1',
          'per_page': '100',
          'sort': 'financial_direction',
          'order': 'asc',
        },
      );
      finance = List<FinanceSummaryRow>.unmodifiable(
        apiObjectList(envelope.data, 'contract_finance.data')
            .map(FinanceSummaryRow.fromData),
      );
    } on SafeContractsApiException catch (error) {
      if (error.statusCode == 403) {
        financeAuthorized = false;
      } else {
        MobileRuntimeDiagnostics.record(
          operation: 'contract.detail',
          stage: 'premium.finance',
          error: error,
          context: <String, Object?>{
            'contract_id': widget.contractId,
            'status_code': error.statusCode,
            'api_code': error.code,
          },
        );
        financeAuthorized = false;
      }
    } on Object catch (error) {
      MobileRuntimeDiagnostics.record(
        operation: 'contract.detail',
        stage: 'premium.finance',
        error: error,
        context: <String, Object?>{'contract_id': widget.contractId},
      );
      financeAuthorized = false;
    }

    return _PremiumContractBundle(
      contract: contract,
      media: media,
      payments: payments,
      finance: finance,
      financeAuthorized: financeAuthorized,
    );
  }

  Future<void> _hydrate(SafeContractsContract initial) async {
    try {
      final next = await _load(initialContract: initial);
      if (!mounted) return;
      setState(() {
        _future = Future<_PremiumContractBundle>.value(next);
      });
    } on Object catch (error) {
      MobileRuntimeDiagnostics.record(
        operation: 'contract.detail',
        stage: 'premium.hydrate',
        error: error,
        context: <String, Object?>{'contract_id': widget.contractId},
      );
    }
  }

  Future<void> _refresh() async {
    final initial = widget.initialContract;
    final next = _load(
      initialContract:
          initial != null && initial.id == widget.contractId ? initial : null,
    );
    setState(() => _future = next);
    await next;
  }
"""
if old_premium_load not in premium:
    raise SystemExit('premium contract load marker missing')
premium = premium.replace(old_premium_load, new_premium_load, 1)
premium_path.write_text(premium, encoding='utf-8')
