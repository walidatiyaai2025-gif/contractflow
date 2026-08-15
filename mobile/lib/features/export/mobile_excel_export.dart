import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';

import '../../core/api/api_client.dart';
import '../dashboard/dashboard_models.dart';

final class MobileExcelExport {
  MobileExcelExport({
    required this.filename,
    required this.contentType,
    required this.bytes,
    required this.filters,
    required this.rowCounts,
  });

  static const xlsxContentType =
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

  final String filename;
  final String contentType;
  final Uint8List bytes;
  final Map<String, Object?> filters;
  final Map<String, int> rowCounts;

  factory MobileExcelExport.fromData(Object? value) {
    final data = apiObjectMap(value, 'excel_export.data');
    final encoding = _requiredString(data['encoding'], 'encoding');
    if (encoding != 'base64') {
      throw const FormatException('Excel export encoding must be base64.');
    }

    final contentType = _requiredString(data['content_type'], 'content_type');
    if (contentType != xlsxContentType) {
      throw const FormatException('Excel export content type is invalid.');
    }

    final filename = _safeFilename(
      _requiredString(data['filename'], 'filename'),
    );
    final contentBase64 = _requiredString(
      data['content_base64'],
      'content_base64',
    );
    final bytes = Uint8List.fromList(base64Decode(contentBase64));
    if (!_looksLikeZip(bytes)) {
      throw const FormatException(
          'Excel export payload is not a valid XLSX file.');
    }

    final filters = apiObjectMap(data['filters'], 'excel_export.filters');
    final rawCounts = apiObjectMap(
      data['row_counts'],
      'excel_export.row_counts',
    );
    final rowCounts = <String, int>{};
    for (final entry in rawCounts.entries) {
      final count = _nonNegativeInt(entry.value, 'row_counts.${entry.key}');
      rowCounts[entry.key] = count;
    }

    return MobileExcelExport(
      filename: filename,
      contentType: contentType,
      bytes: bytes,
      filters: Map<String, Object?>.unmodifiable(filters),
      rowCounts: Map<String, int>.unmodifiable(rowCounts),
    );
  }
}

final class MobileExcelExportRepository {
  MobileExcelExportRepository(this.client);

  final SafeContractsApiClient client;

  Future<MobileExcelExport> download(DashboardFilters filters) async {
    final response = await client.get(
      'reports/excel',
      query: filters.toQuery(),
    );
    return MobileExcelExport.fromData(response.data);
  }
}

abstract interface class ExcelExportSaver {
  Future<String> save(MobileExcelExport export);
}

final class IoExcelExportSaver implements ExcelExportSaver {
  IoExcelExportSaver({Directory? directory}) : _directory = directory;

  final Directory? _directory;

  @override
  Future<String> save(MobileExcelExport export) async {
    final directory = _directory ??
        Directory(
          '${Directory.systemTemp.path}${Platform.pathSeparator}safecontracts_exports',
        );
    await directory.create(recursive: true);
    final file = File(
      '${directory.path}${Platform.pathSeparator}${export.filename}',
    );
    await file.writeAsBytes(export.bytes, flush: true);
    return file.path;
  }
}

enum ExcelExportState { idle, loading, ready, error }

final class MobileExcelExportController extends ChangeNotifier {
  MobileExcelExportController({
    required this.repository,
    required this.filtersProvider,
    required this.canExport,
    ExcelExportSaver? saver,
  }) : saver = saver ?? IoExcelExportSaver();

  final MobileExcelExportRepository repository;
  final DashboardFilters Function() filtersProvider;
  final bool canExport;
  final ExcelExportSaver saver;

  ExcelExportState state = ExcelExportState.idle;
  MobileExcelExport? lastExport;
  String? savedPath;
  String? errorMessage;

  Future<void> downloadCurrentFilters() async {
    if (!canExport) {
      lastExport = null;
      savedPath = null;
      errorMessage = 'Excel export is not authorized for this session.';
      state = ExcelExportState.error;
      notifyListeners();
      return;
    }

    state = ExcelExportState.loading;
    errorMessage = null;
    notifyListeners();

    try {
      final export = await repository.download(filtersProvider());
      final path = await saver.save(export);
      lastExport = export;
      savedPath = path;
      state = ExcelExportState.ready;
    } on SafeContractsApiException catch (error) {
      lastExport = null;
      savedPath = null;
      errorMessage = error.message;
      state = ExcelExportState.error;
    } on Object catch (error) {
      lastExport = null;
      savedPath = null;
      errorMessage = error.toString();
      state = ExcelExportState.error;
    }
    notifyListeners();
  }

  void clearResult() {
    lastExport = null;
    savedPath = null;
    errorMessage = null;
    state = ExcelExportState.idle;
    notifyListeners();
  }
}

String _requiredString(Object? value, String field) {
  if (value is! String || value.trim().isEmpty) {
    throw FormatException('$field must be a non-empty string.');
  }
  return value.trim();
}

int _nonNegativeInt(Object? value, String field) {
  final int? parsed;
  if (value is int) {
    parsed = value;
  } else if (value is String) {
    parsed = int.tryParse(value);
  } else {
    parsed = null;
  }
  if (parsed == null || parsed < 0) {
    throw FormatException('$field must be a non-negative integer.');
  }
  return parsed;
}

String _safeFilename(String value) {
  final basename = value.replaceAll('\\', '/').split('/').last;
  final normalized = basename.replaceAll(
    RegExp(r'[^A-Za-z0-9._-]'),
    '_',
  );
  if (normalized.isEmpty ||
      normalized.length > 128 ||
      !normalized.toLowerCase().endsWith('.xlsx')) {
    throw const FormatException('Excel export filename is invalid.');
  }
  return normalized;
}

bool _looksLikeZip(Uint8List bytes) {
  return bytes.length >= 4 && bytes[0] == 0x50 && bytes[1] == 0x4b;
}
