import '../../core/api/api_client.dart';
import 'dashboard_models.dart';

final class DashboardRepository {
  DashboardRepository(this.client);

  final SafeContractsApiClient client;

  Future<DashboardOverview> loadOverview(DashboardFilters filters) async {
    final response = await client.get(
      'dashboard',
      query: filters.toQuery(),
    );
    return DashboardOverview.fromData(response.data);
  }

  Future<List<ContractOption>> loadContractOptions(int? customerId) async {
    final response = await client.get(
      'filters/contracts',
      query: <String, String>{
        if (customerId != null) 'customer_id': customerId.toString(),
      },
    );
    final items = apiObjectList(response.data, 'contract_options.data');
    return List<ContractOption>.unmodifiable(
      items.map(ContractOption.fromData),
    );
  }

  Future<DashboardLists> loadLists(
    DashboardFilters filters, {
    required int pageSize,
  }) async {
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
