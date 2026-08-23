import '../../core/api/api_client.dart';

final class ContractAttachment {
  const ContractAttachment({
    required this.id,
    required this.mediaId,
    required this.label,
    required this.role,
    required this.mimeType,
    required this.url,
    required this.createdAt,
  });

  final int id;
  final int mediaId;
  final String label;
  final String role;
  final String mimeType;
  final String url;
  final String createdAt;

  bool get isImage => mimeType.toLowerCase().startsWith('image/');

  factory ContractAttachment.fromData(Object? value) {
    final map = apiObjectMap(value, 'contract_attachment');
    return ContractAttachment(
      id: _positiveInt(map['id'], 'contract_attachment.id'),
      mediaId: _positiveInt(map['media_id'], 'contract_attachment.media_id'),
      label: _text(map['label']),
      role: _text(map['role'], fallback: 'supporting'),
      mimeType: _text(map['mime_type']),
      url: _requiredUrl(map['url'], 'contract_attachment.url'),
      createdAt: _text(map['created_at']),
    );
  }
}

final class ContractMedia {
  const ContractMedia({
    required this.contractId,
    required this.heroUrl,
    required this.heroSource,
    required this.attachments,
  });

  final int contractId;
  final String heroUrl;
  final String heroSource;
  final List<ContractAttachment> attachments;

  bool get usesCompanyLogo => heroSource == 'company';

  factory ContractMedia.fromData(Object? value) {
    final map = apiObjectMap(value, 'contract_media');
    final rows =
        apiObjectList(map['attachments'], 'contract_media.attachments');
    return ContractMedia(
      contractId:
          _positiveInt(map['contract_id'], 'contract_media.contract_id'),
      heroUrl: _requiredUrl(map['hero_url'], 'contract_media.hero_url'),
      heroSource: _text(map['hero_source'], fallback: 'company'),
      attachments: List<ContractAttachment>.unmodifiable(
        rows.map(ContractAttachment.fromData),
      ),
    );
  }
}

final class ContractMediaRepository {
  const ContractMediaRepository(this.client);

  final SafeContractsApiClient client;

  Future<ContractMedia> load(int contractId) async {
    if (contractId <= 0) {
      throw ArgumentError('Contract ID must be positive.');
    }
    final envelope = await client.get('contracts/$contractId/media');
    final media = ContractMedia.fromData(envelope.data);
    if (media.contractId != contractId) {
      throw const FormatException('Contract media ID does not match request.');
    }
    return media;
  }
}

int _positiveInt(Object? value, String field) {
  final parsed = switch (value) {
    final int value => value,
    final String value => int.tryParse(value),
    _ => null,
  };
  if (parsed == null || parsed <= 0) {
    throw FormatException('$field must be a positive integer.');
  }
  return parsed;
}

String _text(Object? value, {String fallback = ''}) {
  if (value is String && value.trim().isNotEmpty) return value.trim();
  return fallback;
}

String _requiredUrl(Object? value, String field) {
  final text = _text(value);
  final uri = Uri.tryParse(text);
  if (uri == null ||
      !uri.hasScheme ||
      (uri.scheme != 'https' && uri.scheme != 'http') ||
      uri.host.isEmpty) {
    throw FormatException('$field must be an absolute HTTP(S) URL.');
  }
  return text;
}
