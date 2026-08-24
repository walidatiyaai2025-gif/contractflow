import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/features/ui/alkenzy_reference_components.dart';

void main() {
  testWidgets('reference feature tile remains usable in narrow RTL layout',
      (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Directionality(
          textDirection: TextDirection.rtl,
          child: Scaffold(
            body: Center(
              child: SizedBox(
                width: 320,
                child: AlkenzyReferenceFeatureTile(
                  icon: Icons.description_outlined,
                  title: 'إدارة العقود بسهولة',
                  description:
                      'أنشئ العقود وتابع حالتها من البداية حتى الإغلاق دون فقدان بيانات النظام.',
                ),
              ),
            ),
          ),
        ),
      ),
    );

    await tester.pump();

    expect(find.text('إدارة العقود بسهولة'), findsOneWidget);
    expect(find.byIcon(Icons.description_outlined), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('reference primary action exposes requested label',
      (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: AlkenzyReferencePrimaryButton(
            label: 'ابدأ الآن',
            icon: Icons.arrow_back_rounded,
            onPressed: () {},
          ),
        ),
      ),
    );

    expect(find.text('ابدأ الآن'), findsOneWidget);
    expect(find.byType(FilledButton), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
