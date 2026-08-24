import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../ui/safecontracts_design.dart';
import 'report_printing.dart';

@immutable
final class MobileReportDefinition {
  const MobileReportDefinition({
    required this.id,
    required this.endpoint,
    required this.icon,
    required this.titleEn,
    required this.titleAr,
    required this.columns,
    this.query = const <String, String>{},
    this.paged = true,
  });

  final String id;
  final String endpoint;
  final IconData icon;
  final String titleEn;
  final String titleAr;
  final List<ReportColumnDefinition> columns;
  final Map<String, String> query;
  final bool paged;

  String title(bool ar) => ar ? titleAr : titleEn;
}

@immutable
final class ReportColumnDefinition {
  const ReportColumnDefinition(this.key, this.en, this.ar);
  final String key;
  final String en;
  final String ar;
  String label(bool isArabic) => isArabic ? ar : en;
}

const mobileReportDefinitions = <MobileReportDefinition>[
  MobileReportDefinition(
    id: 'customers',
    endpoint: 'customers',
    icon: Icons.groups_2_outlined,
    titleEn: 'Customers report',
    titleAr: 'تقرير العملاء',
    query: {'sort': 'name', 'order': 'asc'},
    columns: [
      ReportColumnDefinition('id', 'ID', 'الرقم'),
      ReportColumnDefinition('internal_code', 'Code', 'الكود'),
      ReportColumnDefinition('name', 'Customer', 'العميل'),
      ReportColumnDefinition('contact_name', 'Contact', 'جهة الاتصال'),
      ReportColumnDefinition('phone', 'Phone', 'الهاتف'),
      ReportColumnDefinition('email', 'Email', 'البريد'),
      ReportColumnDefinition('is_active', 'Active', 'نشط'),
    ],
  ),
  MobileReportDefinition(
    id: 'suppliers',
    endpoint: 'suppliers',
    icon: Icons.local_shipping_outlined,
    titleEn: 'Suppliers report',
    titleAr: 'تقرير الموردين',
    query: {'sort': 'name', 'order': 'asc'},
    columns: [
      ReportColumnDefinition('id', 'ID', 'الرقم'),
      ReportColumnDefinition('internal_code', 'Code', 'الكود'),
      ReportColumnDefinition('legal_name', 'Supplier', 'المورد'),
      ReportColumnDefinition('trading_name', 'Trading name', 'الاسم التجاري'),
      ReportColumnDefinition('contact_name', 'Contact', 'جهة الاتصال'),
      ReportColumnDefinition('phone', 'Phone', 'الهاتف'),
      ReportColumnDefinition('email', 'Email', 'البريد'),
      ReportColumnDefinition('default_currency', 'Currency', 'العملة'),
      ReportColumnDefinition('status', 'Status', 'الحالة'),
    ],
  ),
  MobileReportDefinition(
    id: 'contracts',
    endpoint: 'contracts',
    icon: Icons.folder_copy_outlined,
    titleEn: 'Contracts report',
    titleAr: 'تقرير العقود',
    query: {'sort': 'id', 'order': 'desc'},
    columns: [
      ReportColumnDefinition('id', 'ID', 'الرقم'),
      ReportColumnDefinition('contract_number', 'Contract', 'العقد'),
      ReportColumnDefinition('counterparty_name', 'Counterparty', 'الطرف'),
      ReportColumnDefinition('counterparty_type', 'Type', 'النوع'),
      ReportColumnDefinition('financial_direction', 'Direction', 'الاتجاه المالي'),
      ReportColumnDefinition('base_value', 'Value', 'القيمة'),
      ReportColumnDefinition('currency_code', 'Currency', 'العملة'),
      ReportColumnDefinition('status', 'Status', 'الحالة'),
      ReportColumnDefinition('start_date', 'Start date', 'تاريخ البداية'),
      ReportColumnDefinition('end_date', 'End date', 'تاريخ النهاية'),
    ],
  ),
  MobileReportDefinition(
    id: 'payments',
    endpoint: 'payments',
    icon: Icons.receipt_long_outlined,
    titleEn: 'Payments report',
    titleAr: 'تقرير الدفعات',
    query: {'sort': 'due_date', 'order': 'asc'},
    columns: [
      ReportColumnDefinition('id', 'ID', 'الرقم'),
      ReportColumnDefinition('reference', 'Reference', 'المرجع'),
      ReportColumnDefinition('contract_number', 'Contract', 'العقد'),
      ReportColumnDefinition('counterparty_name', 'Counterparty', 'الطرف'),
      ReportColumnDefinition('due_date', 'Due date', 'تاريخ الاستحقاق'),
      ReportColumnDefinition('original_amount', 'Original', 'الأصلي'),
      ReportColumnDefinition('paid_amount', 'Paid', 'المدفوع'),
      ReportColumnDefinition('remaining_amount', 'Remaining', 'المتبقي'),
      ReportColumnDefinition('currency_code', 'Currency', 'العملة'),
      ReportColumnDefinition('status', 'Status', 'الحالة'),
    ],
  ),
  MobileReportDefinition(
    id: 'collections',
    endpoint: 'collections',
    icon: Icons.payments_outlined,
    titleEn: 'Collections report',
    titleAr: 'تقرير التحصيلات',
    query: {'sort': 'id', 'order': 'desc'},
    columns: [
      ReportColumnDefinition('id', 'ID', 'الرقم'),
      ReportColumnDefinition('reference', 'Reference', 'المرجع'),
      ReportColumnDefinition('contract_number', 'Contract', 'العقد'),
      ReportColumnDefinition('counterparty_name', 'Counterparty', 'الطرف'),
      ReportColumnDefinition('amount', 'Amount', 'المبلغ'),
      ReportColumnDefinition('currency_code', 'Currency', 'العملة'),
      ReportColumnDefinition('collection_date', 'Collection date', 'تاريخ التحصيل'),
      ReportColumnDefinition('payment_method_name', 'Method', 'الطريقة'),
    ],
  ),
  MobileReportDefinition(
    id: 'followups',
    endpoint: 'followups',
    icon: Icons.timeline_outlined,
    titleEn: 'Follow-up report',
    titleAr: 'تقرير المتابعة',
    query: {'sort': 'due_date', 'order': 'asc'},
    columns: [
      ReportColumnDefinition('payment_id', 'Payment', 'الدفعة'),
      ReportColumnDefinition('reference', 'Reference', 'المرجع'),
      ReportColumnDefinition('due_date', 'Due date', 'الاستحقاق'),
      ReportColumnDefinition('expected_payment_date', 'Expected date', 'التاريخ المتوقع'),
      ReportColumnDefinition('remaining_amount', 'Remaining', 'المتبقي'),
      ReportColumnDefinition('status', 'Status', 'الحالة'),
      ReportColumnDefinition('followup_state', 'Follow-up state', 'حالة المتابعة'),
    ],
  ),
  MobileReportDefinition(
    id: 'notifications',
    endpoint: 'notifications',
    icon: Icons.notifications_outlined,
    titleEn: 'Notifications & events report',
    titleAr: 'تقرير الإشعارات والأحداث',
    query: {'sort': 'id', 'order': 'desc'},
    columns: [
      ReportColumnDefinition('id', 'ID', 'الرقم'),
      ReportColumnDefinition('title', 'Title', 'العنوان'),
      ReportColumnDefinition('body', 'Message', 'الرسالة'),
      ReportColumnDefinition('type', 'Type', 'النوع'),
      ReportColumnDefinition('created_at', 'Created', 'التاريخ'),
      ReportColumnDefinition('read_at', 'Read at', 'تاريخ القراءة'),
    ],
  ),
  MobileReportDefinition(
    id: 'finance',
    endpoint: 'finance/summary',
    icon: Icons.account_balance_wallet_outlined,
    titleEn: 'Finance summary report',
    titleAr: 'تقرير الملخص المالي',
    paged: false,
    columns: [
      ReportColumnDefinition('financial_direction', 'Direction', 'الاتجاه'),
      ReportColumnDefinition('currency_code', 'Currency', 'العملة'),
      ReportColumnDefinition('original_total', 'Original', 'الأصلي'),
      ReportColumnDefinition('paid_total', 'Paid', 'المدفوع'),
      ReportColumnDefinition('remaining_total', 'Remaining', 'المتبقي'),
      ReportColumnDefinition('overdue_total', 'Overdue', 'المتأخر'),
    ],
  ),
];

final class MobileReportsRepository {
  const MobileReportsRepository(this.client);
  final SafeContractsApiClient client;

  Future<ReportGrid> load(
    MobileReportDefinition definition, {
    required bool isArabic,
  }) async {
    final rows = <Map<String, Object?>>[];
    if (!definition.paged) {
      final envelope = await client.get(definition.endpoint, query: definition.query);
      rows.addAll(apiObjectList(envelope.data, 'reports.${definition.id}')
          .map((row) => Map<String, Object?>.from(row)));
    } else {
      var page = 1;
      while (page <= 100) {
        final query = <String, String>{
          ...definition.query,
          'page': '$page',
          'per_page': '100',
        };
        final envelope = await client.get(definition.endpoint, query: query);
        rows.addAll(apiObjectList(envelope.data, 'reports.${definition.id}')
            .map((row) => Map<String, Object?>.from(row)));
        final meta = envelope.meta;
        final hasMore = _boolish(meta['has_more']);
        if (!hasMore) break;
        page++;
      }
    }
    return ReportGrid(
      title: definition.title(isArabic),
      subtitle: isArabic
          ? 'بيانات فعلية من SafeContracts حسب صلاحيات المستخدم.'
          : 'Live SafeContracts data within the signed-in user scope.',
      fileStem: 'alkenzy_${definition.id}',
      columns: definition.columns.map((column) => column.label(isArabic)).toList(),
      rows: rows
          .map(
            (row) => definition.columns
                .map((column) => _display(row[column.key], isArabic))
                .toList(growable: false),
          )
          .toList(growable: false),
    );
  }
}

final class ReportsScreen extends StatefulWidget {
  const ReportsScreen({required this.repository, super.key});
  final MobileReportsRepository repository;

  @override
  State<ReportsScreen> createState() => _ReportsScreenState();
}

final class _ReportsScreenState extends State<ReportsScreen> {
  String? _loadingId;
  String? _error;
  ReportGrid? _loadedReport;
  MobileReportDefinition? _selected;

  Future<void> _openReport(MobileReportDefinition definition) async {
    if (_loadingId != null) return;
    setState(() {
      _loadingId = definition.id;
      _error = null;
      _selected = definition;
    });
    try {
      final report = await widget.repository.load(
        definition,
        isArabic: context.scL10n.isArabic,
      );
      if (!mounted) return;
      setState(() => _loadedReport = report);
    } on Object catch (error) {
      if (!mounted) return;
      setState(() {
        _loadedReport = null;
        _error = error.toString();
      });
    } finally {
      if (mounted) setState(() => _loadingId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    return SafeContractsBackdrop(
      child: ListView(
        padding: const EdgeInsets.fromLTRB(14, 10, 14, 24),
        children: [
          SafeContractsPremiumHeader(
            compact: true,
            leading: Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                gradient: SafeContractsVisual.roseGradient,
                borderRadius: BorderRadius.circular(14),
              ),
              child: const Icon(Icons.analytics_outlined),
            ),
            title: ar ? 'التقارير' : 'Reports',
            subtitle: ar
                ? 'تقارير العملاء والموردين والعقود والدفعات والتحصيلات والمتابعة والمالية.'
                : 'Customers, suppliers, contracts, payments, collections, follow-up and finance reports.',
          ),
          const SizedBox(height: 12),
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: MediaQuery.sizeOf(context).width >= 700 ? 3 : 2,
              childAspectRatio: 1.12,
              mainAxisSpacing: 10,
              crossAxisSpacing: 10,
            ),
            itemCount: mobileReportDefinitions.length,
            itemBuilder: (context, index) {
              final definition = mobileReportDefinitions[index];
              final loading = _loadingId == definition.id;
              return Material(
                color: SafeContractsVisual.surface,
                borderRadius: BorderRadius.circular(18),
                child: InkWell(
                  borderRadius: BorderRadius.circular(18),
                  onTap: _loadingId == null
                      ? () => unawaited(_openReport(definition))
                      : null,
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        CircleAvatar(
                          backgroundColor: Theme.of(context).colorScheme.primaryContainer,
                          foregroundColor: Theme.of(context).colorScheme.primary,
                          child: Icon(definition.icon),
                        ),
                        const Spacer(),
                        Text(
                          definition.title(ar),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.w900,
                              ),
                        ),
                        const SizedBox(height: 6),
                        if (loading)
                          const LinearProgressIndicator(minHeight: 2)
                        else
                          Text(
                            ar ? 'فتح التقرير' : 'Open report',
                            style: Theme.of(context).textTheme.labelMedium?.copyWith(
                                  color: Theme.of(context).colorScheme.primary,
                                  fontWeight: FontWeight.w700,
                                ),
                          ),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
          if (_error != null) ...[
            const SizedBox(height: 12),
            Material(
              color: Theme.of(context).colorScheme.errorContainer,
              borderRadius: BorderRadius.circular(14),
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Text(
                  context.scL10n.rawMessage(_error!),
                  style: TextStyle(color: Theme.of(context).colorScheme.onErrorContainer),
                ),
              ),
            ),
          ],
          if (_loadedReport != null && _selected != null) ...[
            const SizedBox(height: 14),
            SafeContractsSurface(
              elevated: false,
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _loadedReport!.title,
                              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                    fontWeight: FontWeight.w900,
                                  ),
                            ),
                            Text(
                              ar
                                  ? '${_loadedReport!.rows.length} سجل'
                                  : '${_loadedReport!.rows.length} rows',
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          ],
                        ),
                      ),
                      GridPrintButton(report: _loadedReport!, compact: true),
                    ],
                  ),
                  const SizedBox(height: 12),
                  if (_loadedReport!.rows.isEmpty)
                    Text(ar ? 'لا توجد بيانات متاحة لهذا التقرير.' : 'No data is available for this report.')
                  else
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: DataTable(
                        columns: _loadedReport!.columns
                            .map((label) => DataColumn(label: Text(label)))
                            .toList(),
                        rows: _loadedReport!.rows
                            .take(50)
                            .map(
                              (row) => DataRow(
                                cells: List.generate(
                                  _loadedReport!.columns.length,
                                  (index) => DataCell(Text(index < row.length ? row[index] : '')),
                                ),
                              ),
                            )
                            .toList(),
                      ),
                    ),
                  if (_loadedReport!.rows.length > 50)
                    Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Text(
                        ar
                            ? 'المعاينة تعرض أول 50 سجل؛ الطباعة/التصدير تشمل كل السجلات المحملة.'
                            : 'Preview shows the first 50 rows; print/export includes every loaded row.',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

bool _boolish(Object? value) {
  if (value is bool) return value;
  if (value is int) return value != 0;
  if (value is String) return value == '1' || value.toLowerCase() == 'true';
  return false;
}

String _display(Object? value, bool ar) {
  if (value == null) return '';
  if (value is bool) return value ? (ar ? 'نعم' : 'Yes') : (ar ? 'لا' : 'No');
  if (value is List || value is Map) return jsonEncode(value);
  return value.toString();
}
