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

  testWidgets('banner renders above footer and does not restart navigation', (
    tester,
  ) async {
    final showBanner = ValueNotifier<bool>(false);
    addTearDown(showBanner.dispose);

    var footerMounts = 0;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: const Center(child: Text('workspace')),
          bottomNavigationBar: ValueListenableBuilder<bool>(
            valueListenable: showBanner,
            child: _AppProbe(onMount: () => footerMounts++),
            builder: (context, visible, footer) =>
                SafeContractsAdsAboveFooterLayout(
              banner: visible
                  ? const SizedBox(
                      key: Key('banner-above-footer'),
                      height: 50,
                      width: 320,
                    )
                  : null,
              footer: SizedBox(
                key: const Key('authenticated-footer'),
                height: 64,
                child: footer!,
              ),
            ),
          ),
        ),
      ),
    );

    expect(footerMounts, 1);
    expect(find.byKey(const Key('banner-above-footer')), findsNothing);

    showBanner.value = true;
    await tester.pump();
    final banner = find.byKey(const Key('banner-above-footer'));
    final footer = find.byKey(const Key('authenticated-footer'));
    expect(banner, findsOneWidget);
    expect(
      tester.getBottomLeft(banner).dy,
      lessThan(tester.getTopLeft(footer).dy),
      reason: 'Ads belong immediately ABOVE navigation, never underneath it',
    );
    expect(footerMounts, 1, reason: 'An arriving ad must not remount nav');

    showBanner.value = false;
    await tester.pump();
    expect(banner, findsNothing);
    expect(footerMounts, 1, reason: 'Turning ads off preserves nav state');
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
