import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/features/ads/mobile_ads.dart';

void main() {
  testWidgets('first banner load and removal preserve the app state', (
    tester,
  ) async {
    final showBanner = ValueNotifier<bool>(false);
    addTearDown(showBanner.dispose);

    var appMounts = 0;
    final rootApp = _AppProbe(onMount: () => appMounts++);
    await tester.pumpWidget(
      MaterialApp(
        home: ValueListenableBuilder<bool>(
          valueListenable: showBanner,
          child: rootApp,
          builder: (context, enabled, child) => SafeContractsAdsLayout(
            banner: enabled
                ? const SizedBox(
                    key: Key('production-banner'),
                    height: 50,
                    width: 320,
                  )
                : null,
            child: child!,
          ),
        ),
      ),
    );

    expect(appMounts, 1);
    expect(find.byKey(const Key('production-banner')), findsNothing);

    showBanner.value = true;
    await tester.pump();
    expect(find.byKey(const Key('production-banner')), findsOneWidget);
    expect(appMounts, 1, reason: 'Production ad must not restart the app');

    showBanner.value = false;
    await tester.pump();
    expect(find.byKey(const Key('production-banner')), findsNothing);
    expect(appMounts, 1, reason: 'Disabling ad must preserve login state');
  });

  testWidgets('ad privacy options do not remount authenticated content', (
    tester,
  ) async {
    var appMounts = 0;
    final privacy = ValueNotifier<bool>(false);
    addTearDown(privacy.dispose);

    await tester.pumpWidget(
      MaterialApp(
        home: ValueListenableBuilder<bool>(
          valueListenable: privacy,
          child: _AppProbe(onMount: () => appMounts++),
          builder: (context, enabled, child) => SafeContractsAdsLayout(
            privacyOptions: enabled
                ? const SizedBox(key: Key('privacy-options'), height: 28)
                : null,
            child: child!,
          ),
        ),
      ),
    );
    privacy.value = true;
    await tester.pump();
    expect(find.byKey(const Key('privacy-options')), findsOneWidget);
    expect(appMounts, 1);
  });
}

final class _AppProbe extends StatefulWidget {
  const _AppProbe({required this.onMount});

  final VoidCallback onMount;

  @override
  State<_AppProbe> createState() => _AppProbeState();
}

final class _AppProbeState extends State<_AppProbe> {
  @override
  void initState() {
    super.initState();
    widget.onMount();
  }

  @override
  Widget build(BuildContext context) =>
      const Center(child: Text('authenticated workspace'));
}
