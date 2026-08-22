import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/features/welcome/company_welcome_screen.dart';

void main() {
  testWidgets('company welcome renders core Alkenzy content and opens sign in',
      (
    tester,
  ) async {
    var signInRequested = false;

    await tester.pumpWidget(
      MaterialApp(
        home: AlkenzyCompanyWelcomeScreen(
          languageCode: 'en',
          onSignIn: () => signInRequested = true,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Alkenzy Advertising Agency'), findsWidgets);
    expect(find.text('Advertising built on experience'), findsOneWidget);
    expect(find.text('10+ years'), findsOneWidget);

    await tester.ensureVisible(find.byKey(const Key('companyWelcomeSignIn')));
    await tester.tap(find.byKey(const Key('companyWelcomeSignIn')));
    await tester.pump();

    expect(signInRequested, isTrue);
  });

  testWidgets('company welcome supports Arabic copy and language switching', (
    tester,
  ) async {
    String? requestedLanguage;

    await tester.pumpWidget(
      MaterialApp(
        home: AlkenzyCompanyWelcomeScreen(
          languageCode: 'ar',
          onLanguageChanged: (value) => requestedLanguage = value,
          onSignIn: () {},
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('الكنزي للإعلان'), findsWidgets);
    expect(find.text('خبرة إعلانية تصنع الفرق'), findsOneWidget);
    expect(find.text('تسجيل الدخول'), findsOneWidget);

    await tester.tap(find.text('EN'));
    await tester.pump();
    expect(requestedLanguage, 'en');
  });
}
