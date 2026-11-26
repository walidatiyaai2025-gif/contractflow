import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/features/ads/alkenzy_ad_units.dart';
import 'package:safecontracts_mobile/features/ads/alkenzy_xo_match.dart';
import 'package:safecontracts_mobile/features/ads/alkenzy_rewarded_fun.dart';

void main() {
  test('production and official Google test rewarded unit are distinct', () {
    expect(AlkenzyAdUnits.rewardedUnit(testMode: false),
        'ca-app-pub-3218037275900725/1456680066');
    expect(AlkenzyAdUnits.rewardedUnit(testMode: true),
        'ca-app-pub-3940256099942544/5224354917');
  });

  test('XO evaluates wins across rows, columns and diagonals', () {
    expect(AlkenzyXoMatch(initial: [
      XoPiece.human, XoPiece.human, XoPiece.human,
      XoPiece.empty, XoPiece.empty, XoPiece.empty,
      XoPiece.empty, XoPiece.empty, XoPiece.empty,
    ]).outcome, XoOutcome.humanWon);
    expect(AlkenzyXoMatch(initial: [
      XoPiece.computer, XoPiece.empty, XoPiece.empty,
      XoPiece.computer, XoPiece.empty, XoPiece.empty,
      XoPiece.computer, XoPiece.empty, XoPiece.empty,
    ]).outcome, XoOutcome.computerWon);
    expect(AlkenzyXoMatch(initial: [
      XoPiece.human, XoPiece.empty, XoPiece.empty,
      XoPiece.empty, XoPiece.human, XoPiece.empty,
      XoPiece.empty, XoPiece.empty, XoPiece.human,
    ]).outcome, XoOutcome.humanWon);
  });

  test('computer wins when possible and blocks immediate loss', () {
    expect(AlkenzyXoMatch(initial: [
      XoPiece.computer, XoPiece.computer, XoPiece.empty,
      XoPiece.human, XoPiece.empty, XoPiece.empty,
      XoPiece.human, XoPiece.empty, XoPiece.empty,
    ]).chooseComputerMove(), 2);
    expect(AlkenzyXoMatch(initial: [
      XoPiece.human, XoPiece.human, XoPiece.empty,
      XoPiece.empty, XoPiece.computer, XoPiece.empty,
      XoPiece.empty, XoPiece.empty, XoPiece.empty,
    ]).chooseComputerMove(), 2);
  });

  test('human cannot claim occupied cell or move after finished game', () {
    final game = AlkenzyXoMatch();
    expect(game.humanMove(4), isTrue);
    expect(game.cells[4], XoPiece.human);
    expect(game.humanMove(4), isFalse);
    expect(game.cells.where((piece) => piece == XoPiece.computer).length, 1);
    expect(game.humanMove(-1), isFalse);
  });

  test('rewarded ticket is account-independent and guarded by earned callback', () {
    final source = File('lib/features/ads/alkenzy_rewarded_fun_service.dart')
        .readAsStringSync();
    expect(source, contains('onUserEarnedReward:'));
    expect(source, contains('entitlement ??= _grantTicket()'));
    expect(source, contains('Future<bool> takeTicket()'));
    expect(source, contains('Duration(minutes: 2)'));
    expect(source, isNot(contains('Authorization')));
    expect(source, isNot(contains('MobileSession')));
    expect(source, contains('FlutterSecureStorage'));
  });

  testWidgets('XO popup offers exactly one game without restart',
      (tester) async {
    await tester.pumpWidget(const MaterialApp(
      home: Scaffold(body: AlkenzyXoGameDialog(languageCode: 'ar')),
    ));
    expect(find.text('لعبة إكس أو'), findsOneWidget);
    expect(find.byKey(const Key('alkenzyXoCell4')), findsOneWidget);
    await tester.tap(find.byKey(const Key('alkenzyXoCell4')));
    await tester.pump();
    expect(find.text('X'), findsOneWidget);
    expect(find.text('O'), findsOneWidget);
    expect(find.text('إعادة اللعب'), findsNothing);
  });

  test('guest landing and signed-in shell both expose start button', () {
    final welcome = File('lib/features/welcome/company_welcome_screen.dart')
        .readAsStringSync();
    final shell = File('lib/features/navigation/app_shell.dart').readAsStringSync();
    expect(welcome, contains('AlkenzyRewardedFunButton('));
    expect(shell, contains('AlkenzyRewardedFunButton('));
  });
}
