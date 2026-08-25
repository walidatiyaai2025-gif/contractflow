#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path):
    return (ROOT / path).read_text(encoding='utf-8')


def write(path, text):
    (ROOT / path).write_text(text, encoding='utf-8')


def replace_once(text, old, new, label):
    if old not in text:
        raise SystemExit(f'Marker not found: {label}')
    return text.replace(old, new, 1)

# Remove the unavailable Arabic reshaper package use. The PDF widget already
# handles bidi direction and Cairo embeds Arabic glyphs.
path = 'mobile/lib/features/export/mobile_report_export.dart'
text = read(path)
text = text.replace("import 'package:arabic_reshaper/arabic_reshaper.dart';\n", '')
text = text.replace(
    '      final rendered = rtl ? ArabicReshaper.instance.reshape(value) : value;\n',
    '      final rendered = value;\n',
)
write(path, text)

# Profile repository/controller: real server upload and silent refresh.
path = 'mobile/lib/features/profile/profile.dart'
text = read(path)
if "import 'dart:convert';" not in text:
    text = text.replace(
        "import 'package:flutter/foundation.dart';\n",
        "import 'dart:convert';\nimport 'dart:typed_data';\n\nimport 'package:flutter/foundation.dart';\n",
        1,
    )
repo_marker = '''  Future<DevicesSnapshot> loadDevices() async {
    final envelope = await client.get('devices');
    return DevicesSnapshot.fromEnvelope(envelope);
  }
'''
repo_replacement = repo_marker + '''
  Future<String> uploadAvatar({
    required Uint8List bytes,
    required String mimeType,
  }) async {
    if (bytes.isEmpty || bytes.length > 2097152) {
      throw ArgumentError('Profile image must be 2 MB or smaller.');
    }
    if (!const <String>{'image/jpeg', 'image/png', 'image/webp'}
        .contains(mimeType)) {
      throw ArgumentError('Profile image must be JPEG, PNG, or WebP.');
    }
    final envelope = await client.post(
      'profile/avatar',
      body: <String, Object?>{
        'mime_type': mimeType,
        'base64': base64Encode(bytes),
      },
    );
    final data = apiObjectMap(envelope.data, 'profile.avatar.data');
    final value = data['avatar_url'];
    if (value is! String || value.trim().isEmpty) {
      throw const FormatException('Profile avatar response is invalid.');
    }
    final uri = Uri.tryParse(value.trim());
    if (uri == null || !uri.hasAuthority ||
        (uri.scheme != 'https' && uri.scheme != 'http')) {
      throw const FormatException('Profile avatar URL is invalid.');
    }
    return value.trim();
  }
'''
if 'Future<String> uploadAvatar' not in text:
    text = replace_once(text, repo_marker, repo_replacement, 'profile repo upload')
text = replace_once(
    text,
    "  DevicesSnapshot? snapshot;\n  String? errorMessage;",
    "  DevicesSnapshot? snapshot;\n  String? errorMessage;\n  String? avatarUrlOverride;\n  bool avatarUploadInFlight = false;",
    'profile controller fields',
)
controller_marker = '''  Future<void> load() async {
    state = ProfileDeviceLoadState.loading;
    errorMessage = null;
    notifyListeners();
    try {
      snapshot = await repository.loadDevices();
      state = ProfileDeviceLoadState.ready;
    } on SafeContractsApiException catch (error) {
      snapshot = null;
      errorMessage = error.message;
      state = ProfileDeviceLoadState.error;
    } on Object catch (error) {
      snapshot = null;
      errorMessage = error.toString();
      state = ProfileDeviceLoadState.error;
    }
    notifyListeners();
  }
'''
controller_replacement = controller_marker + '''
  Future<void> refreshSilently() async {
    try {
      snapshot = await repository.loadDevices();
      state = ProfileDeviceLoadState.ready;
      errorMessage = null;
      notifyListeners();
    } on Object {
      // Keep the last profile snapshot during background refresh failures.
    }
  }

  Future<String> uploadAvatar({
    required Uint8List bytes,
    required String mimeType,
  }) async {
    if (avatarUploadInFlight) {
      throw StateError('A profile image upload is already in progress.');
    }
    avatarUploadInFlight = true;
    errorMessage = null;
    notifyListeners();
    try {
      final url = await repository.uploadAvatar(bytes: bytes, mimeType: mimeType);
      avatarUrlOverride = url;
      return url;
    } finally {
      avatarUploadInFlight = false;
      notifyListeners();
    }
  }
'''
if 'Future<String> uploadAvatar({' not in text[text.find('final class ProfileController'):]:
    text = replace_once(text, controller_marker, controller_replacement, 'profile controller upload')
write(path, text)

# Modern profile UI: visible change-photo action and live override.
path = 'mobile/lib/features/profile/modern_profile_content.dart'
text = read(path)
text = replace_once(
    text,
    "    required this.onUserGuide,\n    super.key,",
    "    required this.onUserGuide,\n    required this.avatarUrl,\n    required this.avatarUploading,\n    required this.onAvatarUpload,\n    super.key,",
    'modern profile constructor',
)
text = replace_once(
    text,
    "  final VoidCallback onUserGuide;",
    "  final VoidCallback onUserGuide;\n  final String? avatarUrl;\n  final bool avatarUploading;\n  final VoidCallback onAvatarUpload;",
    'modern profile fields',
)
text = replace_once(
    text,
    "              ProfileHero(session: session),\n              const SizedBox(height: 10),",
    "              ProfileHero(session: session, avatarUrlOverride: avatarUrl),\n              Align(\n                alignment: AlignmentDirectional.centerStart,\n                child: TextButton.icon(\n                  key: const Key('profileChangePhoto'),\n                  onPressed: avatarUploading ? null : onAvatarUpload,\n                  icon: avatarUploading\n                      ? const SizedBox.square(\n                          dimension: 16,\n                          child: CircularProgressIndicator(strokeWidth: 2),\n                        )\n                      : const Icon(Icons.photo_camera_outlined),\n                  label: Text(ar ? 'تغيير الصورة الشخصية' : 'Change profile photo'),\n                ),\n              ),\n              const SizedBox(height: 4),",
    'modern profile photo button',
)
write(path, text)

# Avatar hero supports freshly uploaded URL without a full session reload.
path = 'mobile/lib/features/profile/profile_identity_sections.dart'
text = read(path)
text = replace_once(
    text,
    "  const ProfileHero({required this.session, super.key});\n\n  final SafeContractsSession session;",
    "  const ProfileHero({\n    required this.session,\n    this.avatarUrlOverride,\n    super.key,\n  });\n\n  final SafeContractsSession session;\n  final String? avatarUrlOverride;",
    'profile hero constructor',
)
text = replace_once(
    text,
    "          _ProfileAvatar(session: session),",
    "          _ProfileAvatar(\n            session: session,\n            avatarUrlOverride: avatarUrlOverride,\n          ),",
    'profile hero avatar call',
)
text = replace_once(
    text,
    "final class _ProfileAvatar extends StatelessWidget {\n  const _ProfileAvatar({required this.session});\n\n  final SafeContractsSession session;",
    "final class _ProfileAvatar extends StatelessWidget {\n  const _ProfileAvatar({\n    required this.session,\n    required this.avatarUrlOverride,\n  });\n\n  final SafeContractsSession session;\n  final String? avatarUrlOverride;",
    'profile avatar constructor',
)
text = replace_once(
    text,
    "    final avatarUrl = session.avatarUrl;",
    "    final avatarUrl = avatarUrlOverride ?? session.avatarUrl;",
    'profile avatar override',
)
write(path, text)

# Profile screen chooses/compresses a photo and uploads via authenticated API.
path = 'mobile/lib/features/profile/profile_screen.dart'
text = read(path)
if "package:image_picker/image_picker.dart" not in text:
    text = text.replace(
        "import 'package:flutter/material.dart';\n",
        "import 'package:flutter/material.dart';\nimport 'package:image_picker/image_picker.dart';\n",
        1,
    )
state_marker = "final class _ProfileScreenState extends State<ProfileScreen> {\n"
state_replacement = state_marker + '''  final ImagePicker _imagePicker = ImagePicker();

  Future<void> _changeAvatar() async {
    final picked = await _imagePicker.pickImage(
      source: ImageSource.gallery,
      maxWidth: 1200,
      maxHeight: 1200,
      imageQuality: 86,
      requestFullMetadata: false,
    );
    if (picked == null || !mounted) return;
    final bytes = await picked.readAsBytes();
    if (!mounted) return;
    final lower = picked.name.toLowerCase();
    final mimeType = lower.endsWith('.png')
        ? 'image/png'
        : lower.endsWith('.webp')
            ? 'image/webp'
            : 'image/jpeg';
    try {
      await widget.controller.uploadAvatar(bytes: bytes, mimeType: mimeType);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            widget.languageCode == 'ar'
                ? 'تم تحديث الصورة الشخصية.'
                : 'Profile photo updated.',
          ),
        ),
      );
    } on Object catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.toString())),
      );
    }
  }

'''
if '_changeAvatar()' not in text:
    text = replace_once(text, state_marker, state_replacement, 'profile picker method')
old_build = '''  @override
  Widget build(BuildContext context) {
    return ModernProfileContent(
      session: widget.session,
      languageCode: widget.languageCode,
      onLanguageChanged: widget.onLanguageChanged,
      onLogout: widget.onClearSession,
      onUserGuide: () => unawaited(_openUserGuide()),
    );
  }
'''
new_build = '''  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, child) => ModernProfileContent(
        session: widget.session,
        languageCode: widget.languageCode,
        onLanguageChanged: widget.onLanguageChanged,
        onLogout: widget.onClearSession,
        onUserGuide: () => unawaited(_openUserGuide()),
        avatarUrl: widget.controller.avatarUrlOverride ?? widget.session.avatarUrl,
        avatarUploading: widget.controller.avatarUploadInFlight,
        onAvatarUpload: () => unawaited(_changeAvatar()),
      ),
    );
  }
'''
text = replace_once(text, old_build, new_build, 'profile build')
write(path, text)

# Payment/collection mutations immediately refresh only dashboard aggregates.
path = 'mobile/lib/features/payments/payments_screen.dart'
text = read(path)
text = replace_once(
    text,
    "    this.onRecordCollection,\n    this.refreshRevision = 0,",
    "    this.onRecordCollection,\n    this.onDataChanged,\n    this.refreshRevision = 0,",
    'payments callback constructor',
)
text = replace_once(
    text,
    "  final PaymentAction? onRecordCollection;\n  final int refreshRevision;",
    "  final PaymentAction? onRecordCollection;\n  final VoidCallback? onDataChanged;\n  final int refreshRevision;",
    'payments callback field',
)
text = replace_once(
    text,
    "    if (mounted) unawaited(_load(_pageNumber));",
    "    if (mounted) {\n      unawaited(_load(_pageNumber));\n      widget.onDataChanged?.call();\n    }",
    'payments detail targeted refresh',
)
text = replace_once(
    text,
    "    ScaffoldMessenger.of(context).showSnackBar(\n      SnackBar(content: Text(context.scL10n.collectionRecorded(receipt.id))),\n    );",
    "    ScaffoldMessenger.of(context).showSnackBar(\n      SnackBar(content: Text(context.scL10n.collectionRecorded(receipt.id))),\n    );\n    widget.onDataChanged?.call();",
    'payments collection targeted refresh',
)
write(path, text)

path = 'mobile/lib/features/navigation/app_shell.dart'
text = read(path)
needle = "          refreshRevision: _liveRefreshRevision,\n        ),"
replacement = "          onDataChanged: () =>\n              unawaited(widget.dashboardController.refreshSilently()),\n          refreshRevision: _liveRefreshRevision,\n        ),"
text = text.replace(needle, replacement, 2)
write(path, text)

# Suppliers have no server paging endpoint; progressively render the bounded
# authorized server result so the screen still reveals data in scroll batches.
path = 'mobile/lib/features/suppliers/suppliers_screen.dart'
text = read(path)
text = replace_once(
    text,
    "  final _searchController = TextEditingController();\n  String _status = '';",
    "  final _searchController = TextEditingController();\n  String _status = '';\n  int _visibleLimit = 30;",
    'supplier visible limit',
)
old_getter = '''  List<SafeContractsSupplier> get _visibleSuppliers {
    if (_status.isEmpty) return widget.controller.suppliers;
    return widget.controller.suppliers
        .where((supplier) => supplier.status == _status)
        .toList(growable: false);
  }
'''
new_getter = '''  List<SafeContractsSupplier> get _filteredSuppliers {
    if (_status.isEmpty) return widget.controller.suppliers;
    return widget.controller.suppliers
        .where((supplier) => supplier.status == _status)
        .toList(growable: false);
  }

  List<SafeContractsSupplier> get _visibleSuppliers =>
      _filteredSuppliers.take(_visibleLimit).toList(growable: false);
'''
text = replace_once(text, old_getter, new_getter, 'supplier filtered getter')
text = text.replace("      _status = '';\n      unawaited(widget.controller.ensureLoaded());", "      _status = '';\n      _visibleLimit = 30;\n      unawaited(widget.controller.ensureLoaded());", 1)
text = replace_once(
    text,
    "        final visible = _visibleSuppliers;",
    "        final filtered = _filteredSuppliers;\n        final visible = filtered.take(_visibleLimit).toList(growable: false);",
    'supplier build filtered',
)
text = replace_once(
    text,
    "                      onStatusChanged: (value) =>\n                          setState(() => _status = value),",
    "                      onStatusChanged: (value) => setState(() {\n                        _status = value;\n                        _visibleLimit = 30;\n                      }),",
    'supplier filter reset',
)
old_body = '''                  Expanded(
                    child: _SupplierBody(
                      controller: widget.controller,
                      suppliers: visible,
                      split: split,
                      onEdit: (supplier) => unawaited(_openEditor(supplier)),
                      onArchive: (supplier) => unawaited(_archive(supplier)),
                    ),
                  ),'''
new_body = '''                  Expanded(
                    child: NotificationListener<ScrollNotification>(
                      onNotification: (notification) {
                        if (!mobileDetail &&
                            notification.metrics.extentAfter <= 360 &&
                            _visibleLimit < filtered.length) {
                          setState(() => _visibleLimit =
                              (_visibleLimit + 30).clamp(0, filtered.length));
                        }
                        return false;
                      },
                      child: _SupplierBody(
                        controller: widget.controller,
                        suppliers: visible,
                        split: split,
                        onEdit: (supplier) => unawaited(_openEditor(supplier)),
                        onArchive: (supplier) => unawaited(_archive(supplier)),
                      ),
                    ),
                  ),'''
text = replace_once(text, old_body, new_body, 'supplier progressive body')
write(path, text)

print('Materialized profile upload, targeted KPI refresh, supplier progressive rendering, and report dependency cleanup.')
