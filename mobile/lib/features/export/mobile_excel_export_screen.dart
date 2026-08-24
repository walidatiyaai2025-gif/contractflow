import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../ui/safecontracts_design.dart';
import 'mobile_excel_export.dart';

final class MobileExcelExportScreen extends StatelessWidget {
  const MobileExcelExportScreen({required this.controller, super.key});

  final MobileExcelExportController controller;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    return AnimatedBuilder(
      animation: controller,
      builder: (context, child) {
        final filters = controller.filtersProvider();
        final busy = controller.state == ExcelExportState.loading;

        return SafeContractsBackdrop(
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
            children: [
              SafeContractsPremiumHeader(
                title: l10n.isArabic ? 'التقارير والتصدير' : 'Reports & export',
                subtitle: l10n.isArabic
                    ? 'ملف Excel معتمد على الفلاتر الحالية وصلاحيات حسابك'
                    : 'Server-generated Excel using your current authorized filters',
                leading: Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: const Icon(
                    Icons.file_download_outlined,
                    color: Colors.white,
                  ),
                ),
                trailing: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Text(
                    'XLSX',
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                      fontSize: 11,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              SafeContractsSectionTitle(
                title: l10n.isArabic ? 'نوع التقرير' : 'Report format',
                subtitle: l10n.isArabic
                    ? 'يتم عرض الصيغ التي يدعمها الخادم فعليًا فقط'
                    : 'Only formats actually supported by the server are shown',
              ),
              const SizedBox(height: 10),
              SafeContractsSurface(
                accent: SafeContractsVisual.green,
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 48,
                      height: 48,
                      decoration: BoxDecoration(
                        color: SafeContractsVisual.greenSoft,
                        borderRadius: BorderRadius.circular(15),
                      ),
                      child: const Icon(
                        Icons.table_view_outlined,
                        color: SafeContractsVisual.greenDeep,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  l10n.t('Excel export'),
                                  style: Theme.of(context)
                                      .textTheme
                                      .titleMedium
                                      ?.copyWith(fontWeight: FontWeight.w900),
                                ),
                              ),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 4,
                                ),
                                decoration: BoxDecoration(
                                  color: SafeContractsVisual.greenSoft,
                                  borderRadius: BorderRadius.circular(99),
                                ),
                                child: Text(
                                  l10n.isArabic ? 'مدعوم' : 'Supported',
                                  style: const TextStyle(
                                    color: SafeContractsVisual.greenDeep,
                                    fontSize: 10,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 5),
                          Text(
                            l10n.t(
                              'The workbook is generated by SafeContracts on the server using your current authorized dashboard filters.',
                            ),
                            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                  color: SafeContractsVisual.muted,
                                ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 18),
              SafeContractsSectionTitle(
                title: l10n.t('Current filters'),
                subtitle: l10n.isArabic
                    ? 'راجع نطاق البيانات قبل إنشاء الملف'
                    : 'Review the data scope before generating the workbook',
              ),
              const SizedBox(height: 10),
              SafeContractsSurface(
                padding: const EdgeInsets.all(14),
                child: Column(
                  children: [
                    _FilterValue(
                      icon: Icons.person_outline_rounded,
                      label: l10n.t('Customer'),
                      value: filters.customerId?.toString() ??
                          l10n.t('All customers'),
                    ),
                    _FilterValue(
                      icon: Icons.folder_copy_outlined,
                      label: l10n.t('Contract'),
                      value: filters.contractId?.toString() ??
                          l10n.t('All contracts'),
                    ),
                    _FilterValue(
                      icon: Icons.flag_outlined,
                      label: l10n.t('Status'),
                      value: filters.status == null
                          ? l10n.t('All statuses')
                          : l10n.status(filters.status!),
                    ),
                    _FilterValue(
                      icon: Icons.date_range_outlined,
                      label: l10n.t('Due from'),
                      value: filters.dueFrom ?? l10n.t('Any date'),
                    ),
                    _FilterValue(
                      icon: Icons.event_available_outlined,
                      label: l10n.t('Due to'),
                      value: filters.dueTo ?? l10n.t('Any date'),
                      last: true,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              FilledButton.icon(
                style: FilledButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 14),
                ),
                onPressed: busy || !controller.canExport
                    ? null
                    : () => unawaited(controller.downloadCurrentFilters()),
                icon: busy
                    ? const SizedBox.square(
                        dimension: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.file_download_outlined),
                label: Text(
                  l10n.t(busy ? 'Generating Excel…' : 'Download Excel'),
                ),
              ),
              if (busy) ...[
                const SizedBox(height: 10),
                const LinearProgressIndicator(minHeight: 2),
              ],
              if (!controller.canExport) ...[
                const SizedBox(height: 12),
                _ExportMessage(
                  icon: Icons.lock_outline,
                  message:
                      l10n.t('Excel export is not authorized for this session.'),
                ),
              ],
              if (controller.state == ExcelExportState.error &&
                  controller.errorMessage != null) ...[
                const SizedBox(height: 12),
                _ExportMessage(
                  icon: Icons.error_outline,
                  message: l10n.rawMessage(controller.errorMessage!),
                  isError: true,
                ),
              ],
              if (controller.state == ExcelExportState.ready &&
                  controller.lastExport != null) ...[
                const SizedBox(height: 12),
                _ExportSuccess(
                  export: controller.lastExport!,
                  savedPath: controller.savedPath,
                  onClear: controller.clearResult,
                ),
              ],
              const SizedBox(height: 14),
              SafeContractsSurface(
                elevated: false,
                padding: const EdgeInsets.all(12),
                accent: SafeContractsVisual.navy,
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(
                      Icons.verified_user_outlined,
                      color: SafeContractsVisual.navy,
                    ),
                    const SizedBox(width: 9),
                    Expanded(
                      child: Text(
                        l10n.isArabic
                            ? 'اختيار السجلات وصلاحية التصدير وتجهيز ملف Excel تتم من خلال SafeContracts والخادم؛ التطبيق لا يصنع تقريرًا ماليًا موازيًا.'
                            : 'SafeContracts and the server control record scope, export authorization, and workbook generation. Mobile does not build a parallel financial report.',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: SafeContractsVisual.muted,
                            ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

final class _FilterValue extends StatelessWidget {
  const _FilterValue({
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
        padding: const EdgeInsets.symmetric(vertical: 10),
        decoration: BoxDecoration(
          border: last
              ? null
              : const Border(
                  bottom: BorderSide(color: SafeContractsVisual.contour),
                ),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                color: SafeContractsVisual.navySoft,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, size: 18, color: SafeContractsVisual.navy),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    label,
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                          color: SafeContractsVisual.muted,
                        ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    value,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: SafeContractsVisual.ink,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
}

final class _ExportSuccess extends StatelessWidget {
  const _ExportSuccess({
    required this.export,
    required this.savedPath,
    required this.onClear,
  });

  final MobileExcelExport export;
  final String? savedPath;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final sizeKb = (export.bytes.length / 1024).toStringAsFixed(1);
    return SafeContractsSurface(
      accent: SafeContractsVisual.green,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: SafeContractsVisual.greenSoft,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.check_circle_outline,
                  color: SafeContractsVisual.greenDeep,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      l10n.t('Excel export ready'),
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            color: SafeContractsVisual.greenDeep,
                            fontWeight: FontWeight.w900,
                          ),
                    ),
                    Text(
                      '$sizeKb KB',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: SafeContractsVisual.muted,
                          ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          SelectableText(
            export.filename,
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
          if (savedPath != null) ...[
            const SizedBox(height: 10),
            Text(
              l10n.t('Saved in app cache:'),
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    color: SafeContractsVisual.muted,
                  ),
            ),
            const SizedBox(height: 3),
            SelectableText(savedPath!),
          ],
          if (export.rowCounts.isNotEmpty) ...[
            const SizedBox(height: 14),
            Text(
              l10n.t('Rows exported'),
              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
            ),
            const SizedBox(height: 7),
            Wrap(
              spacing: 7,
              runSpacing: 7,
              children: [
                for (final entry in export.rowCounts.entries)
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
                    decoration: BoxDecoration(
                      color: SafeContractsVisual.greenSoft,
                      borderRadius: BorderRadius.circular(99),
                    ),
                    child: Text(
                      '${entry.key}: ${entry.value}',
                      style: const TextStyle(
                        color: SafeContractsVisual.greenDeep,
                        fontWeight: FontWeight.w800,
                        fontSize: 11,
                      ),
                    ),
                  ),
              ],
            ),
          ],
          const SizedBox(height: 10),
          Align(
            alignment: AlignmentDirectional.centerEnd,
            child: TextButton.icon(
              onPressed: onClear,
              icon: const Icon(Icons.close_rounded),
              label: Text(l10n.t('Clear result')),
            ),
          ),
        ],
      ),
    );
  }
}

final class _ExportMessage extends StatelessWidget {
  const _ExportMessage({
    required this.icon,
    required this.message,
    this.isError = false,
  });

  final IconData icon;
  final String message;
  final bool isError;

  @override
  Widget build(BuildContext context) {
    final color = isError
        ? SafeContractsVisual.redDeep
        : SafeContractsVisual.roseGoldDark;
    final soft = isError
        ? SafeContractsVisual.redSoft
        : SafeContractsVisual.roseGoldSoft;
    return SafeContractsSurface(
      elevated: false,
      accent: color,
      padding: const EdgeInsets.all(13),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: soft,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: color),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: TextStyle(color: color, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}
