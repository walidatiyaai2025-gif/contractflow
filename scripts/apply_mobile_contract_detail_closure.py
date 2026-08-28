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
