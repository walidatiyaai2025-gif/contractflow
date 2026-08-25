import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../ui/safecontracts_design.dart';
import 'mobile_excel_export.dart';
import 'mobile_report_export.dart';

final class MobileExcelExportScreen extends StatefulWidget {
  const MobileExcelExportScreen({required this.controller, super.key});

  final MobileExcelExportController controller;

  @override
  State<MobileExcelExportScreen> createState() =>
      _MobileExcelExportScreenState();
}

final class _MobileExcelExportScreenState extends State<MobileExcelExportScreen> {
  late final MobileReportExportController _reports;

  @override
  void initState() {
    super.initState();
    _reports = MobileReportExportController(
      repository: MobileReportRepository(widget.controller.repository.client),
      filtersProvider: widget.controller.filtersProvider,
      canExport: widget.controller.canExport,
    );
  }

  @override
  void dispose() {
    _reports.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    return AnimatedBuilder(
      animation: _reports,
      builder: (context, child) {
        final filters = widget.controller.filtersProvider();
        final busy = _reports.state == MobileReportExportState.loading;
        return SafeContractsBackdrop(
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
            children: <Widget>[
              SafeContractsPremiumHeader(
                title: l10n.isArabic ? 'التقارير والطباعة' : 'Reports & print',
                subtitle: l10n.isArabic
                    ? 'تقارير حقيقية من الخادم مع تنزيل Excel أو Word أو PDF'
                    : 'Server-authoritative reports downloaded as Excel, Word or PDF',
                leading: Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: const Icon(Icons.print_outlined, color: Colors.white),
                ),
              ),
              const SizedBox(height: 16),
              SafeContractsSectionTitle(
                title: l10n.isArabic ? 'نوع التقرير' : 'Report type',
                subtitle: l10n.isArabic
                    ? 'اختر البيانات ثم صيغة الملف'
                    : 'Choose the data set and file format',
              ),
              const SizedBox(height: 10),
              SafeContractsSurface(
                child: Column(
                  children: <Widget>[
                    DropdownButtonFormField<MobileReportType>(
                      value: _reports.reportType,
                      isExpanded: true,
                      decoration: InputDecoration(
                        labelText: l10n.isArabic ? 'التقرير' : 'Report',
                        prefixIcon: const Icon(Icons.assessment_outlined),
                      ),
                      items: MobileReportType.values
                          .map(
                            (type) => DropdownMenuItem<MobileReportType>(
                              value: type,
                              child: Text(_reportLabel(type, l10n.isArabic)),
                            ),
                          )
                          .toList(growable: false),
                      onChanged: busy
                          ? null
                          : (value) {
                              if (value != null) {
                                _reports.selectReportType(value);
                              }
                            },
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<MobileReportFormat>(
                      value: _reports.format,
                      isExpanded: true,
                      decoration: InputDecoration(
                        labelText: l10n.isArabic ? 'صيغة الملف' : 'File format',
                        prefixIcon: const Icon(Icons.description_outlined),
                      ),
                      items: MobileReportFormat.values
                          .map(
                            (format) => DropdownMenuItem<MobileReportFormat>(
                              value: format,
                              child: Text(format.name.toUpperCase()),
                            ),
                          )
                          .toList(growable: false),
                      onChanged: busy
                          ? null
                          : (value) {
                              if (value != null) _reports.selectFormat(value);
                            },
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              SafeContractsSectionTitle(
                title: l10n.isArabic ? 'نطاق التقرير' : 'Report scope',
                subtitle: l10n.isArabic
                    ? 'يتم استخدام الفلاتر الحالية من الداشبورد'
                    : 'Current Dashboard filters are preserved',
              ),
              const SizedBox(height: 10),
              SafeContractsSurface(
                elevated: false,
                child: Column(
                  children: <Widget>[
                    _ScopeRow(
                      icon: Icons.folder_copy_outlined,
                      label: l10n.isArabic ? 'العقد' : 'Contract',
                      value: filters.contractId?.toString() ??
                          (l10n.isArabic ? 'كل العقود' : 'All contracts'),
                    ),
                    _ScopeRow(
                      icon: Icons.flag_outlined,
                      label: l10n.isArabic ? 'الحالة' : 'Status',
                      value: filters.status == null
                          ? (l10n.isArabic ? 'كل الحالات' : 'All statuses')
                          : l10n.status(filters.status!),
                    ),
                    _ScopeRow(
                      icon: Icons.date_range_outlined,
                      label: l10n.isArabic ? 'من' : 'From',
                      value: filters.dueFrom ??
                          (l10n.isArabic ? 'أي تاريخ' : 'Any date'),
                    ),
                    _ScopeRow(
                      icon: Icons.event_available_outlined,
                      label: l10n.isArabic ? 'إلى' : 'To',
                      value: filters.dueTo ??
                          (l10n.isArabic ? 'أي تاريخ' : 'Any date'),
                      last: true,
                    ),
                  ],
                ),
              ),
              if (_reports.reportType == MobileReportType.attachments &&
                  filters.contractId == null) ...<Widget>[
                const SizedBox(height: 10),
                _Message(
                  icon: Icons.info_outline,
                  text: l10n.isArabic
                      ? 'اختر عقدًا من فلاتر الداشبورد أولًا لتقرير المرفقات.'
                      : 'Select a contract in Dashboard filters before exporting attachments.',
                ),
              ],
              const SizedBox(height: 18),
              FilledButton.icon(
                style: FilledButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 15),
                ),
                onPressed: busy || !_reports.canExport
                    ? null
                    : () => unawaited(_reports.download()),
                icon: busy
                    ? const SizedBox.square(
                        dimension: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.download_rounded),
                label: Text(
                  busy
                      ? (l10n.isArabic ? 'جاري تجهيز الملف…' : 'Preparing file…')
                      : (l10n.isArabic
                          ? 'تنزيل / طباعة ${_reports.format.name.toUpperCase()}'
                          : 'Download / print ${_reports.format.name.toUpperCase()}'),
                ),
              ),
              const SizedBox(height: 9),
              Text(
                l10n.isArabic
                    ? 'سيظهر اختيار مكان الحفظ في Android. لا يتم حفظ التقرير بصمت داخل Cache التطبيق.'
                    : 'Android Save As will open. Reports are not silently stored in the app cache.',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: SafeContractsVisual.muted,
                    ),
              ),
              if (_reports.state == MobileReportExportState.error &&
                  _reports.errorMessage != null) ...<Widget>[
                const SizedBox(height: 12),
                _Message(
                  icon: Icons.error_outline,
                  text: l10n.rawMessage(_reports.errorMessage!),
                  error: true,
                ),
              ],
              if (_reports.state == MobileReportExportState.ready &&
                  _reports.lastDocument != null) ...<Widget>[
                const SizedBox(height: 12),
                _Message(
                  icon: Icons.check_circle_outline,
                  text: l10n.isArabic
                      ? 'تم تنزيل ${_reports.lastDocument!.filename} بنجاح.'
                      : '${_reports.lastDocument!.filename} was downloaded successfully.',
                ),
              ],
            ],
          ),
        );
      },
    );
  }

  String _reportLabel(MobileReportType type, bool arabic) {
    if (!arabic) return type.title;
    return switch (type) {
      MobileReportType.contracts => 'تقرير العقود',
      MobileReportType.payments => 'تقرير الدفعات',
      MobileReportType.customers => 'تقرير العملاء',
      MobileReportType.finance => 'التقرير المالي',
      MobileReportType.attachments => 'تقرير المرفقات',
      MobileReportType.notifications => 'تقرير الإشعارات',
    };
  }
}

final class _ScopeRow extends StatelessWidget {
  const _ScopeRow({
    required this.icon,
    required this.label,
    required this.value,
    this.last = false,
  });

  final IconData icon;
  final String label;
  final String value;
  final bool last;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(vertical: 9),
        decoration: BoxDecoration(
          border: last
              ? null
              : const Border(
                  bottom: BorderSide(color: SafeContractsVisual.contour),
                ),
        ),
        child: Row(
          children: <Widget>[
            Icon(icon, size: 19, color: SafeContractsVisual.navy),
            const SizedBox(width: 9),
            Expanded(
              child: Text(
                label,
                style: const TextStyle(
                  color: SafeContractsVisual.muted,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            Flexible(
              child: Text(
                value,
                textAlign: TextAlign.end,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
          ],
        ),
      );
}

final class _Message extends StatelessWidget {
  const _Message({
    required this.icon,
    required this.text,
    this.error = false,
  });

  final IconData icon;
  final String text;
  final bool error;

  @override
  Widget build(BuildContext context) {
    final color = error
        ? SafeContractsVisual.redDeep
        : SafeContractsVisual.greenDeep;
    final background = error
        ? SafeContractsVisual.redSoft
        : SafeContractsVisual.greenSoft;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(13),
      ),
      child: Row(
        children: <Widget>[
          Icon(icon, color: color),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              text,
              style: TextStyle(color: color, fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    );
  }
}
