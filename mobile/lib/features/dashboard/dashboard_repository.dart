import '../../core/api/api_client.dart';
import 'dashboard_models.dart';

final class DashboardRepository {
  DashboardRepository(this.client);

  final SafeContractsApiClient client;

  Future<DashboardOverview> loadOverview(DashboardFilters filters) async {
    filters.validate();
    final response = await client.get(
      'dashboard',
      query: filters.toQuery(),
    );
    final overview = DashboardOverview.fromData(response.data);
    final customerId = filters.customerId;
    if (customerId != null &&
        overview.contracts.any((option) => option.customerId != customerId)) {
      throw const FormatException(
        'Dashboard contract options do not match the selected customer.',
      );
    }
    return overview;
  }

  Future<List<ContractOption>> loadContractOptions(int? customerId) async {
    if (customerId != null && customerId <= 0) {
      throw ArgumentError.value(customerId, 'customerId', 'Must be positive.');
    }
    final response = await client.get(
      'filters/contracts',
      query: <String, String>{
        if (customerId != null) 'customer_id': customerId.toString(),
      },
    );
    final items = apiObjectList(response.data, 'contract_options.data');
    final options = items.map(ContractOption.fromData).toList(growable: false);
    final seen = <int>{};
    for (final option in options) {
      if (!seen.add(option.id)) {
        throw const FormatException(
          'Dependent contract options contain a duplicate ID.',
        );
      }
      if (customerId != null && option.customerId != customerId) {
        throw const FormatException(
          'Dependent contract option does not match the selected customer.',
        );
      }
    }
    return List<ContractOption>.unmodifiable(options);
  }

  Future<DashboardLists> loadLists(
    DashboardFilters filters, {
    required int pageSize,
  }) async {
    filters.validate();
    final boundedPageSize = pageSize.clamp(10, 100).toInt();
    final contractResponse = await client.get(
      'contracts',
      query: <String, String>{
        ...filters.toQuery(includeDueRange: false),
        'page': '1',
        'per_page': boundedPageSize.toString(),
        'sort': 'id',
        'order': 'desc',
      },
    );
    final paymentResponse = await client.get(
      'payments',
      query: <String, String>{
        ...filters.toQuery(),
        'page': '1',
        'per_page': boundedPageSize.toString(),
        'sort': 'due_date',
        'order': 'asc',
      },
    );
    final collectionResponse = await client.get(
      'collections',
      query: <String, String>{
        ...filters.toQuery(),
        'page': '1',
        'per_page': boundedPageSize.toString(),
        'sort': 'collection_date',
        'order': 'desc',
      },
    );
    final followUpResponse = await client.get(
      'followups',
      query: <String, String>{
        ...filters.toQuery(),
        'page': '1',
        'per_page': boundedPageSize.toString(),
        'sort': 'due_date',
        'order': 'asc',
      },
    );

    return DashboardLists(
      contracts: _records(
        contractResponse,
        'contracts.data',
        DashboardRecord.contract,
      ),
      payments: _records(
        paymentResponse,
        'payments.data',
        DashboardRecord.payment,
      ),
      collections: _records(
        collectionResponse,
        'collections.data',
        DashboardRecord.collection,
      ),
      followUps: _records(
        followUpResponse,
        'followups.data',
        DashboardRecord.followUp,
      ),
    );
  }
}

List<DashboardRecord> _records(
  ApiEnvelope envelope,
  String field,
  DashboardRecord Function(Object?) parser,
) {
  final items = apiObjectList(envelope.data, field);
  return List<DashboardRecord>.unmodifiable(items.map(parser));
}
