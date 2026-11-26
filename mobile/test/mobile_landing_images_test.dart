import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/features/welcome/company_welcome_screen.dart';
import 'package:safecontracts_mobile/features/welcome/mobile_landing.dart';

import 'fake_api_transport.dart';

void main() {
  testWidgets('landing gallery follows uploaded image order and rapid swipes', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(390, 844));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final controller = _readyController(_contentWithImages());
    addTearDown(controller.dispose);

    await tester.pumpWidget(
      MaterialApp(
        home: AlkenzyCompanyWelcomeScreen(
          controller: controller,
          languageCode: 'en',
          onSignIn: () {},
        ),
      ),
    );
    await tester.pump();

    final carousel = find.byKey(const Key('companyWelcomeImageCarousel'));
    expect(carousel, findsOneWidget);
    expect(find.byKey(const Key('companyWelcomeImage-17')), findsOneWidget);
    expect(find.byKey(const Key('companyWelcomeImageDot-0')), findsOneWidget);

    await tester.fling(carousel, const Offset(-330, 0), 1800);
    await tester.pump(const Duration(milliseconds: 360));
    var position = tester.widget<Semantics>(
      find.byKey(const Key('companyWelcomeImagePosition')),
    );
    expect(position.properties.label, 'Image 2 of 3');

    await tester.fling(carousel, const Offset(-330, 0), 2200);
    await tester.pump(const Duration(milliseconds: 120));
    await tester.fling(carousel, const Offset(-330, 0), 2200);
    await tester.pumpAndSettle();
    position = tester.widget<Semantics>(
      find.byKey(const Key('companyWelcomeImagePosition')),
    );
    expect(position.properties.label, 'Image 3 of 3');
    expect(find.byKey(const Key('companyWelcomeSignIn')), findsOneWidget);
  });

  testWidgets('landing keeps branded fallback when no images are published', (
    tester,
  ) async {
    final controller = _readyController(MobileLandingContent.fallback);
    addTearDown(controller.dispose);

    await tester.pumpWidget(
      MaterialApp(
        home: AlkenzyCompanyWelcomeScreen(
          controller: controller,
          languageCode: 'ar',
          onSignIn: () {},
        ),
      ),
    );
    await tester.pump();

    expect(
      find.byKey(const Key('companyWelcomeImageCarousel')),
      findsNothing,
    );
    expect(find.byKey(const Key('companyWelcomeSignIn')), findsOneWidget);
  });

  for (final size in <Size>[
    const Size(320, 568),
    const Size(360, 640),
    const Size(390, 844),
  ]) {
    for (final language in <String>['ar', 'en']) {
      testWidgets(
        'landing fits ${size.width.toInt()}x${size.height.toInt()} '
        '$language with no vertical scrolling and actions visible',
        (tester) async {
          await tester.binding.setSurfaceSize(size);
          addTearDown(() => tester.binding.setSurfaceSize(null));
          final controller = _readyController(_contentWithImages());
          addTearDown(controller.dispose);

          await tester.pumpWidget(
            MaterialApp(
              home: AlkenzyCompanyWelcomeScreen(
                controller: controller,
                languageCode: language,
                onSignIn: () {},
              ),
            ),
          );
          await tester.pump();

          final vertical = tester
              .widgetList<Scrollable>(find.byType(Scrollable))
              .where((scroll) =>
                  scroll.axisDirection == AxisDirection.down ||
                  scroll.axisDirection == AxisDirection.up);
          expect(vertical, isEmpty, reason: 'Guest landing must not scroll');
          expect(find.byKey(const Key('companyWelcomeImageCarousel')),
              findsOneWidget);
          expect(find.byKey(const Key('alkenzyLandingSliderBanner')),
              findsOneWidget);
          expect(find.byKey(const Key('alkenzyStartFun')), findsOneWidget);
          final start = find.byKey(const Key('companyWelcomeSignIn'));
          expect(start, findsOneWidget);
          expect(tester.getBottomRight(start).dy, lessThanOrEqualTo(size.height));
          expect(tester.getTopLeft(start).dy, greaterThanOrEqualTo(0));
          expect(tester.takeException(), isNull);
        },
      );
    }
  }

  testWidgets('small landing retains fallback logo and no vertical scrolling',
      (tester) async {
    await tester.binding.setSurfaceSize(const Size(320, 568));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final controller = _readyController(MobileLandingContent.fallback);
    addTearDown(controller.dispose);
    await tester.pumpWidget(
      MaterialApp(
        home: AlkenzyCompanyWelcomeScreen(
          controller: controller,
          languageCode: 'ar',
          onSignIn: () {},
        ),
      ),
    );
    await tester.pump();
    expect(find.byType(SingleChildScrollView), findsNothing);
    expect(find.byKey(const Key('companyWelcomeSignIn')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

MobileLandingController _readyController(MobileLandingContent content) {
  final controller = MobileLandingController(
    MobileLandingRepository(
      SafeContractsApiClient(
        environment: AppEnvironment.fromValues(
          name: 'local',
          apiBaseUrl: 'https://example.test/wp-json/safecontracts/v1/',
        ),
        transport: FakeApiTransport((uri) {
          throw StateError('Unexpected request: $uri');
        }),
      ),
    ),
  );
  controller
    ..content = content
    ..state = MobileLandingState.ready;
  return controller;
}

MobileLandingContent _contentWithImages() {
  const localized = LandingLocalizedText(en: 'Alkenzy', ar: 'الكنزي');
  return const MobileLandingContent(
    brandName: 'Alkenzy ADV',
    agencyName: localized,
    headline: LandingLocalizedText(
      en: 'Advertising built on experience',
      ar: 'خبرة إعلانية تصنع الفرق',
    ),
    highlight: localized,
    summary: LandingLocalizedText(
      en: 'A concise company landing summary.',
      ar: 'ملخص موجز للشركة.',
    ),
    experienceYears: 10,
    services: <MobileLandingService>[
      MobileLandingService(
        key: 'strategy',
        title: localized,
        subtitle: localized,
      ),
    ],
    phones: <String>['01000272232'],
    officeAddress: localized,
    images: <MobileLandingImage>[
      MobileLandingImage(
        id: 17,
        url: 'https://example.test/uploads/landing-1.webp',
        alt: 'First campaign',
      ),
      MobileLandingImage(
        id: 21,
        url: 'https://example.test/uploads/landing-2.webp',
        alt: 'Second campaign',
      ),
      MobileLandingImage(
        id: 29,
        url: 'https://example.test/uploads/landing-3.webp',
        alt: 'Third campaign',
      ),
    ],
    signInLabel: LandingLocalizedText(en: 'Sign in', ar: 'تسجيل الدخول'),
    learnMoreLabel: LandingLocalizedText(en: 'Learn more', ar: 'اعرف المزيد'),
  );
}
