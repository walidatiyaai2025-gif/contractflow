import 'contracts.dart';

extension ContractsVisibleTabActivation on ContractsController {
  /// Re-enters the authoritative contracts read path whenever the visible
  /// Contracts destination is activated. Empty/error snapshots are refreshed
  /// visibly so a transport/parsing failure cannot masquerade as "no data".
  Future<void> activateForVisibleTab() async {
    if (pageRequestInFlight) return;

    final page = currentPage;
    final needsVisibleReload = state == ContractsLoadState.idle ||
        state == ContractsLoadState.error ||
        page == null ||
        page.contracts.isEmpty;

    if (needsVisibleReload) {
      await loadPage(1);
      return;
    }

    await refreshSilently();
  }
}
