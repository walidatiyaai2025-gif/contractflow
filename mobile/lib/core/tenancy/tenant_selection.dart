final class EnterpriseTenantSelection {
  int? _tenantId;

  int? get tenantId => _tenantId;

  Future<int?> provideTenantId() async => _tenantId;

  void select(int tenantId) {
    if (tenantId <= 0) {
      throw const FormatException(
        'Enterprise tenant id must be a positive integer.',
      );
    }
    _tenantId = tenantId;
  }

  void clear() {
    _tenantId = null;
  }
}
