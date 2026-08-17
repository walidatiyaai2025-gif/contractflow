import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/branding/safe_contracts_brand.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
import '../notifications/push_registration.dart';
import '../session/session_controller.dart';
import '../ui/mobile_layout.dart';
import '../ui/mobile_states.dart';
import '../ui/safecontracts_design.dart';
import 'profile.dart';

final class ProfileScreen extends StatefulWidget {
  const ProfileScreen({
    required this.session,
    required this.config,
    required this.controller,
    required this.pushRegistration,
    required this.languageCode,
    required this.onLanguageChanged,
    required this.onClearSession,
    super.key,
  });

  final SafeContractsSession session;
  final SafeContractsMobileConfig config;
  final ProfileController controller;
  final MobilePushRegistration pushRegistration;
  final String languageCode;
  final ValueChanged<String> onLanguageChanged;
  final VoidCallback onClearSession;

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

final class _ProfileScreenState extends State<ProfileScreen> {
  @override
  void initState() {
    super.initState();
    unawaited(widget.controller.ensureLoaded());
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, child) {
        return SafeContractsAdaptiveBody(
          child: ListView(
            padding: const EdgeInsets.only(bottom: 28),
            children: [
              TweenAnimationBuilder<double>(
                tween: Tween<double>(begin: 0, end: 1),
                duration: const Duration(milliseconds: 420),
                curve: Curves.easeOutCubic,
                builder: (context, value, child) {
                  return Opacity(
                    opacity: value,
                    child: Transform.translate(
                      offset: Offset(0, 18 * (1 - value)),
                      child: child,
                    ),
                  );
                },
                child: _profileHero(context),
              ),
              const SizedBox(height: 26),
              SafeContractsSectionTitle(
                title: _copy(context, 'Preferences', 'التفضيلات'),
                subtitle: _copy(
                  context,
                  'Tune the app to the way you work.',
                  'اضبط التطبيق بالطريقة الأنسب لاستخدامك.',
                ),
              ),
              const SizedBox(height: 12),
              _preferencesSection(context),
              const SizedBox(height: 26),
              SafeContractsSectionTitle(
                title: _copy(context, 'Account & support', 'الحساب والدعم'),
                subtitle: _copy(
                  context,
                  'Your session identity and mobile configuration.',
                  'بيانات الجلسة وإعدادات تطبيق الموبايل.',
                ),
              ),
              const SizedBox(height: 12),
              _accountSection(context),
              const SizedBox(height: 26),
              SafeContractsSectionTitle(
                title: _copy(
                  context,
                  'Notifications & devices',
                  'الإشعارات والأجهزة',
                ),
                subtitle: _copy(
                  context,
                  'Keep this device connected and review registered devices.',
                  'تابع اتصال هذا الجهاز والأجهزة المسجلة على حسابك.',
                ),
              ),
              const SizedBox(height: 12),
              _pushSection(context),
              const SizedBox(height: 12),
              SafeContractsSurface(
                child: _deviceSection(context),
              ),
              const SizedBox(height: 26),
              _sessionActionSection(context),
            ],
          ),
        );
      },
    );
  }

  Widget _profileHero(BuildContext context) {
    final scope = _scopeLabel(context, widget.session.scope);
    return SafeContractsSurface(
      padding: const EdgeInsets.all(20),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Stack(
            clipBehavior: Clip.none,
            children: [
              const SafeContractsBrandMark(size: 74, borderRadius: 24),
              PositionedDirectional(
                end: -2,
                bottom: -2,
                child: Container(
                  width: 22,
                  height: 22,
                  decoration: BoxDecoration(
                    color: SafeContractsVisual.green,
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: SafeContractsVisual.surface,
                      width: 3,
                    ),
                  ),
                  child: const Icon(
                    Icons.check_rounded,
                    size: 12,
                    color: Colors.white,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _copy(context, 'My profile', 'ملفي الشخصي'),
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        color: SafeContractsVisual.ink,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -0.5,
                      ),
                ),
                const SizedBox(height: 3),
                Text(
                  _copy(
                    context,
                    'User #${widget.session.userId}',
                    'المستخدم #${widget.session.userId}',
                  ),
                  style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                        color: SafeContractsVisual.muted,
                        fontWeight: FontWeight.w600,
                      ),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _ProfilePill(
                      icon: Icons.shield_outlined,
                      label: scope,
                      background: SafeContractsVisual.navySoft,
                      foreground: SafeContractsVisual.navy,
                    ),
                    _ProfilePill(
                      icon: Icons.circle,
                      label: _copy(context, 'Active session', 'جلسة نشطة'),
                      background: SafeContractsVisual.greenSoft,
                      foreground: SafeContractsVisual.green,
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _preferencesSection(BuildContext context) {
    final l10n = context.scL10n;
    final currencyCode = widget.config.currency.code.isEmpty
        ? l10n.t('Not configured')
        : widget.config.currency.code;
    final currencySymbol = widget.config.currency.symbol.isEmpty
        ? l10n.t('Not configured')
        : widget.config.currency.symbol;

    return SafeContractsSurface(
      child: Column(
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const _ProfileIcon(icon: Icons.translate_rounded),
              const SizedBox(width: 12),
              Expanded(
                child: DropdownButtonFormField<String>(
                  key: ValueKey(widget.languageCode),
                  initialValue: widget.languageCode,
                  decoration: InputDecoration(
                    labelText: l10n.t('Language'),
                    helperText: _copy(
                      context,
                      'Interface language',
                      'لغة واجهة التطبيق',
                    ),
                  ),
                  items: <DropdownMenuItem<String>>[
                    DropdownMenuItem(
                      value: 'ar',
                      child: Text(l10n.t('Arabic')),
                    ),
                    DropdownMenuItem(
                      value: 'en',
                      child: Text(l10n.t('English')),
                    ),
                  ],
                  onChanged: (value) {
                    if (value != null) widget.onLanguageChanged(value);
                  },
                ),
              ),
            ],
          ),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 14),
            child: Divider(height: 1),
          ),
          _ProfileInfoRow(
            icon: Icons.currency_exchange_rounded,
            label: l10n.t('Currency'),
            value: '$currencyCode  •  $currencySymbol',
          ),
        ],
      ),
    );
  }

  Widget _accountSection(BuildContext context) {
    final l10n = context.scL10n;
    return SafeContractsSurface(
      child: Column(
        children: [
          _ProfileInfoRow(
            icon: Icons.badge_outlined,
            label: l10n.t('User ID'),
            value: '${widget.session.userId}',
          ),
          const Divider(height: 24),
          _ProfileInfoRow(
            icon: Icons.data_object_rounded,
            label: l10n.t('Data scope'),
            value: _scopeLabel(context, widget.session.scope),
          ),
          const Divider(height: 24),
          _ProfileInfoRow(
            icon: Icons.view_list_outlined,
            label: l10n.t('Default page size'),
            value: '${widget.config.defaultPageSize}',
          ),
          if (widget.config.supportText.isNotEmpty) ...[
            const Divider(height: 24),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: SafeContractsVisual.amberSoft.withValues(alpha: 0.7),
                borderRadius: BorderRadius.circular(
                  SafeContractsVisual.compactRadius,
                ),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(
                    Icons.support_agent_rounded,
                    color: SafeContractsVisual.amber,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      widget.config.supportText,
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            color: SafeContractsVisual.ink,
                          ),
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

  Widget _pushSection(BuildContext context) {
    final l10n = context.scL10n;
    if (!widget.config.features.pushNotifications) {
      return SafeContractsSurface(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const _ProfileIcon(icon: Icons.notifications_off_outlined),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    l10n.t(
                      'Push notifications are disabled by mobile configuration.',
                    ),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    l10n.t(
                      'Enable Push notifications in SafeContracts → Mobile Configuration.',
                    ),
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: SafeContractsVisual.muted,
                        ),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
    }

    return ValueListenableBuilder<MobilePushRegistrationSnapshot>(
      valueListenable: widget.pushRegistration.status,
      builder: (context, status, child) {
        final registered = status.backendRegistered;
        return AnimatedSwitcher(
          duration: const Duration(milliseconds: 240),
          child: SafeContractsSurface(
            key: ValueKey<String>(
              '${status.backendState}-${status.permission}-${status.tokenAcquired}',
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    _ProfileIcon(
                      icon: registered
                          ? Icons.notifications_active_rounded
                          : Icons.sync_problem_rounded,
                      background: registered
                          ? SafeContractsVisual.greenSoft
                          : SafeContractsVisual.amberSoft,
                      foreground: registered
                          ? SafeContractsVisual.green
                          : SafeContractsVisual.amber,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            l10n.t(
                              registered
                                  ? 'Device registered with SafeContracts'
                                  : 'Device registration is not complete',
                            ),
                            style: Theme.of(context)
                                .textTheme
                                .titleMedium
                                ?.copyWith(fontWeight: FontWeight.w800),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            registered
                                ? _copy(
                                    context,
                                    'Push delivery is connected for this device.',
                                    'استقبال الإشعارات متصل على هذا الجهاز.',
                                  )
                                : _copy(
                                    context,
                                    'Review the status below or retry registration.',
                                    'راجع الحالة بالأسفل أو أعد محاولة التسجيل.',
                                  ),
                            style: Theme.of(context)
                                .textTheme
                                .bodySmall
                                ?.copyWith(color: SafeContractsVisual.muted),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                _CompactStatusRow(
                  label: l10n.t('Notification permission'),
                  value: l10n.t(_permissionLabel(status.permission)),
                ),
                const SizedBox(height: 7),
                _CompactStatusRow(
                  label: l10n.t('FCM token acquired'),
                  value: l10n.yesNo(status.tokenAcquired),
                ),
                const SizedBox(height: 7),
                _CompactStatusRow(
                  label: l10n.t('Backend registration'),
                  value: l10n.t(_backendLabel(status.backendState)),
                ),
                if (status.errorCode != null) ...[
                  const SizedBox(height: 7),
                  _CompactStatusRow(
                    label: l10n.t('Diagnostic code'),
                    value: status.errorCode!,
                  ),
                ],
                if (status.permission == MobilePushPermissionState.denied) ...[
                  const SizedBox(height: 12),
                  Text(
                    l10n.t(
                      'Android notification permission is denied. The device can still register, but notification display remains blocked until permission is enabled.',
                    ),
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: SafeContractsVisual.muted,
                        ),
                  ),
                ],
                const SizedBox(height: 14),
                FilledButton.tonalIcon(
                  onPressed:
                      status.backendState == MobilePushBackendState.registering
                          ? null
                          : () => unawaited(_retryPushRegistration()),
                  icon: const Icon(Icons.refresh_rounded),
                  label: Text(l10n.t('Retry device registration')),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Future<void> _retryPushRegistration() async {
    await widget.pushRegistration.refreshTokenAndRetry();
    if (widget.pushRegistration.status.value.backendRegistered) {
      await widget.controller.load();
    }
  }

  Widget _deviceSection(BuildContext context) {
    final l10n = context.scL10n;
    final controller = widget.controller;
    if (controller.state == ProfileDeviceLoadState.loading &&
        controller.snapshot == null) {
      return SizedBox(
        height: 120,
        child: SafeContractsStateView(
          kind: MobileStateKind.loading,
          message: l10n.t('Loading device state…'),
        ),
      );
    }
    if (controller.state == ProfileDeviceLoadState.error) {
      return SafeContractsStateView(
        kind: MobileStateKind.error,
        message: l10n.rawMessage(
          controller.errorMessage ?? 'Device state is unavailable.',
        ),
        onRetry: () => unawaited(controller.load()),
      );
    }

    final devices =
        controller.snapshot?.devices ?? const <SafeContractsDevice>[];
    if (devices.isEmpty) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 14),
        child: Column(
          children: [
            const Icon(
              Icons.devices_other_rounded,
              size: 36,
              color: SafeContractsVisual.muted,
            ),
            const SizedBox(height: 10),
            Text(
              l10n.t('No registered devices are currently visible.'),
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: SafeContractsVisual.muted,
                  ),
            ),
          ],
        ),
      );
    }

    return Column(
      children: devices.indexed.map((entry) {
        final index = entry.$1;
        final device = entry.$2;
        return Column(
          children: [
            if (index > 0) const Divider(height: 22),
            Row(
              children: [
                _ProfileIcon(icon: _deviceIcon(device.platform)),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        device.platform.toUpperCase(),
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        device.lastSeenAt == null
                            ? l10n.t('No last-seen timestamp')
                            : '${l10n.t('Last seen')}: ${device.lastSeenAt}',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: SafeContractsVisual.muted,
                            ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                _ProfilePill(
                  icon: Icons.circle,
                  label: l10n.status(device.isActive ? 'active' : 'inactive'),
                  background: device.isActive
                      ? SafeContractsVisual.greenSoft
                      : SafeContractsVisual.redSoft,
                  foreground: device.isActive
                      ? SafeContractsVisual.green
                      : SafeContractsVisual.red,
                ),
              ],
            ),
          ],
        );
      }).toList(growable: false),
    );
  }

  Widget _sessionActionSection(BuildContext context) {
    final l10n = context.scL10n;
    return SafeContractsSurface(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _ProfileIcon(
            icon: Icons.phonelink_erase_rounded,
            background: SafeContractsVisual.redSoft,
            foreground: SafeContractsVisual.red,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _copy(context, 'Local session', 'الجلسة المحلية'),
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                ),
                const SizedBox(height: 4),
                Text(
                  _copy(
                    context,
                    'Clear the saved session state on this device when you need to sign in again.',
                    'امسح بيانات الجلسة المحفوظة على هذا الجهاز عند الحاجة لتسجيل الدخول من جديد.',
                  ),
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: SafeContractsVisual.muted,
                      ),
                ),
                const SizedBox(height: 12),
                FilledButton.tonalIcon(
                  onPressed: widget.onClearSession,
                  icon: const Icon(Icons.logout_rounded),
                  label: Text(l10n.t('Clear local session state')),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

final class _ProfileIcon extends StatelessWidget {
  const _ProfileIcon({
    required this.icon,
    this.background = SafeContractsVisual.navySoft,
    this.foreground = SafeContractsVisual.navy,
  });

  final IconData icon;
  final Color background;
  final Color foreground;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 42,
      height: 42,
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Icon(icon, color: foreground, size: 22),
    );
  }
}

final class _ProfileInfoRow extends StatelessWidget {
  const _ProfileInfoRow({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        _ProfileIcon(icon: icon),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: SafeContractsVisual.muted,
                    ),
              ),
              const SizedBox(height: 2),
              Text(
                value,
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      color: SafeContractsVisual.ink,
                      fontWeight: FontWeight.w800,
                    ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

final class _CompactStatusRow extends StatelessWidget {
  const _CompactStatusRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Text(
            label,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: SafeContractsVisual.muted,
                ),
          ),
        ),
        const SizedBox(width: 12),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.end,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: SafeContractsVisual.ink,
                  fontWeight: FontWeight.w800,
                ),
          ),
        ),
      ],
    );
  }
}

final class _ProfilePill extends StatelessWidget {
  const _ProfilePill({
    required this.icon,
    required this.label,
    required this.background,
    required this.foreground,
  });

  final IconData icon;
  final String label;
  final Color background;
  final Color foreground;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(99),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: icon == Icons.circle ? 8 : 14, color: foreground),
          const SizedBox(width: 6),
          Text(
            label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: foreground,
                  fontWeight: FontWeight.w800,
                ),
          ),
        ],
      ),
    );
  }
}

String _scopeLabel(BuildContext context, SafeContractsDataScope scope) {
  final arabic = context.scL10n.isArabic;
  return switch (scope) {
    SafeContractsDataScope.all => arabic ? 'نطاق كامل' : 'Full scope',
    SafeContractsDataScope.assigned =>
      arabic ? 'السجلات المسندة' : 'Assigned records',
    SafeContractsDataScope.none => arabic ? 'بدون نطاق بيانات' : 'No data scope',
  };
}

String _copy(BuildContext context, String english, String arabic) {
  return context.scL10n.isArabic ? arabic : english;
}

String _permissionLabel(MobilePushPermissionState permission) {
  return switch (permission) {
    MobilePushPermissionState.authorized => 'Allowed',
    MobilePushPermissionState.provisional => 'Provisional',
    MobilePushPermissionState.denied => 'Denied',
    MobilePushPermissionState.unknown => 'Unknown',
  };
}

String _backendLabel(MobilePushBackendState state) {
  return switch (state) {
    MobilePushBackendState.idle => 'Not started',
    MobilePushBackendState.registering => 'Registering…',
    MobilePushBackendState.registered => 'Registered',
    MobilePushBackendState.error => 'Failed',
  };
}

IconData _deviceIcon(String platform) {
  return switch (platform) {
    'android' => Icons.android,
    'ios' => Icons.phone_iphone,
    'web' => Icons.language,
    _ => Icons.devices_other,
  };
}
