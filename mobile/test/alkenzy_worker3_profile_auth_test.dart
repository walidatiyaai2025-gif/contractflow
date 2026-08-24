import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:safecontracts_mobile/app.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/auth/mobile_token_store.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/core/localization/safecontracts_localizations.dart';
import 'package:safecontracts_mobile/features/auth/login_screen.dart';
import 'package:safecontracts_mobile/features/auth/mobile_auth.dart';
import 'package:safecontracts_mobile/features/navigation/app_shell.dart';
import 'package:safecontracts_mobile/features/profile/modern_profile_content.dart';
import 'package:safecontracts_mobile/features/session/session_controller.dart';
import 'package:safecontracts_mobile/features/ui/mobile_layout.dart';
import 'package:safecontracts_mobile/features/welcome/company_welcome_screen.dart';

void main() {
  setUpAll(() => GoogleFonts.config.allowRuntimeFetching = false);
  tearDownAll(() => GoogleFonts.config.allowRuntimeFetching = true);

  test('B036 repository logout revokes remotely and always clears local token',
      () async {
    final token = 'scm_${List<String>.filled(43, 'L').join()}';
    final tokenStore = MemoryMobileTokenStore(token);
    final transport = _RecordingTransport((request) {
      expect(request.method, 'POST');
      expect(request.uri.path, endsWith('/auth/logout'));
      return _ok(<String, Object?>{'logged_out': true});
    });
    final repository = MobileAuthRepository(
      client: SafeContractsApiClient(
        environment: _environment(),
        transport: transport,
      ),
      tokenStore: tokenStore,
    );

    await repository.logout();

    expect(transport.requests, hasLength(1));
    expect(await tokenStore.read(), isNull);
  });

  test('B036 local token is cleared even when remote logout fails', () async {
    final token = 'scm_${List<String>.filled(43, 'F').join()}';
    final tokenStore = MemoryMobileTokenStore(token);
    final repository = MobileAuthRepository(
      client: SafeContractsApiClient(
        environment: _environment(),
        transport: _RecordingTransport(
          (_) => _error(500, 'logout_failed'),
        ),
      ),
      tokenStore: tokenStore,
    );

    await expectLater(
      repository.logout(),
      throwsA(isA<SafeContractsApiException>()),
    );
    expect(await tokenStore.read(), isNull);
  });

  testWidgets(
      'B036 logout removes protected shell, shows Login, and relaunch cannot resurrect session',
      (tester) async {
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.binding.setSurfaceSize(const Size(390, 844));

    final token = 'scm_${List<String>.filled(43, 'A').join()}';
    final tokenStore = MemoryMobileTokenStore(token);
    final transport = _LogoutAwareTransport(token);
    final client = SafeContractsApiClient(
      environment: _environment(),
      transport: transport,
      headersProvider: () async {
        final current = await tokenStore.read();
        return current == null
            ? <String, String>{}
            : <String, String>{'Authorization': 'Bearer $current'};
      },
    );

    await tester.pumpWidget(
      SafeContractsApp(
        environment: _environment(),
        client: client,
        tokenStore: tokenStore,
      ),
    );
    await _pumpBounded(tester, cycles: 24);
    expect(find.byType(SafeContractsShell), findsOneWidget);

    await tester.tap(find.text('Profile').last);
    await _pumpBounded(tester, cycles: 8);
    expect(find.byKey(const Key('profileLogoutButton')), findsOneWidget);

    await tester.tap(find.byKey(const Key('profileLogoutButton')));
    await _pumpBounded(tester, cycles: 20);

    expect(transport.logoutCalls, 1);
    expect(await tokenStore.read(), isNull);
    expect(find.byType(SafeContractsShell), findsNothing);
    expect(find.byType(SafeContractsLoginScreen), findsOneWidget);

    final backButton = find.byIcon(Icons.arrow_back_rounded);
    if (backButton.evaluate().isNotEmpty) {
      await tester.tap(backButton.first);
      await _pumpBounded(tester, cycles: 4);
    }
    expect(find.byType(SafeContractsShell), findsNothing);

    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump();
    await tester.pumpWidget(
      SafeContractsApp(
        environment: _environment(),
        client: client,
        tokenStore: tokenStore,
      ),
    );
    await _pumpBounded(tester, cycles: 20);

    expect(find.byType(SafeContractsShell), findsNothing);
    expect(find.byType(AlkenzyCompanyWelcomeScreen), findsOneWidget);
  });

  test('B051 session accepts server-authoritative display name and avatar', () {
    final session = SafeContractsSession.fromData(<String, Object?>{
      'authenticated': true,
      'user_id': 42,
      'display_name': 'Alkenzy User',
      'avatar_url': 'https://example.test/avatar.png',
      'scope': 'assigned',
      'capabilities': <String, Object?>{
        'safecontracts_access': true,
      },
    });

    expect(session.displayName, 'Alkenzy User');
    expect(session.avatarUrl, 'https://example.test/avatar.png');
  });

  for (final width in <double>[320, 360, 375, 390, 412, 430]) {
    for (final language in <String>['en', 'ar']) {
      testWidgets(
          'B031-B054 compact profile fits ${width.toInt()}px in $language without core scroll',
          (tester) async {
        addTearDown(() => tester.binding.setSurfaceSize(null));
        await tester.binding.setSurfaceSize(Size(width, 844));

        await tester.pumpWidget(_ProfileHarness(initialLanguage: language));
        await tester.pumpAndSettle();

        expect(find.text('Account Information'), findsNothing);
        expect(find.text('Session History'), findsNothing);
        expect(find.text('Currency'), findsNothing);
        expect(find.textContaining('Full scope'), findsNothing);
        expect(find.textContaining('Active session'), findsNothing);
        expect(find.byKey(const Key('profileLogoutButton')), findsOneWidget);
        expect(find.byKey(const Key('profileUserGuideButton')), findsOneWidget);
        expect(find.byKey(const Key('profileShortDeviceScroll')), findsNothing);
        expect(tester.takeException(), isNull);
      });
    }
  }

  testWidgets(
      'B038-B039 language segmented control switches direction immediately',
      (tester) async {
    await tester.pumpWidget(const _ProfileHarness(initialLanguage: 'en'));
    await tester.pumpAndSettle();

    var direction = Directionality.of(
      tester.element(find.byKey(const Key('profileLogoutButton'))),
    );
    expect(direction, TextDirection.ltr);

    await tester.tap(find.text('العربية'));
    await tester.pumpAndSettle();
    direction = Directionality.of(
      tester.element(find.byKey(const Key('profileLogoutButton'))),
    );
    expect(direction, TextDirection.rtl);
    expect(find.text('تسجيل الخروج'), findsOneWidget);

    await tester.tap(find.text('English'));
    await tester.pumpAndSettle();
    direction = Directionality.of(
      tester.element(find.byKey(const Key('profileLogoutButton'))),
    );
    expect(direction, TextDirection.ltr);
    expect(find.text('Log out'), findsOneWidget);
  });

  testWidgets('B049 short devices enable responsive scrolling only when needed',
      (tester) async {
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.binding.setSurfaceSize(const Size(320, 420));

    await tester.pumpWidget(const _ProfileHarness(initialLanguage: 'ar'));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('profileShortDeviceScroll')), findsOneWidget);
    expect(find.byKey(const Key('profileLogoutButton')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

final class _ProfileHarness extends StatefulWidget {
  const _ProfileHarness({required this.initialLanguage});

  final String initialLanguage;

  @override
  State<_ProfileHarness> createState() => _ProfileHarnessState();
}

final class _ProfileHarnessState extends State<_ProfileHarness> {
  late String languageCode = widget.initialLanguage;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      locale: Locale(languageCode),
      supportedLocales: SafeContractsLocalizations.supportedLocales,
      localizationsDelegates: const <LocalizationsDelegate<dynamic>>[
        SafeContractsLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      home: SafeContractsDirectionScope(
        languageCode: languageCode,
        child: Scaffold(
          body: ModernProfileContent(
            session: const SafeContractsSession(
              userId: 42,
              displayName: 'Alkenzy User',
              scope: SafeContractsDataScope.assigned,
              capabilities: <String, bool>{
                'safecontracts_access': true,
              },
            ),
            languageCode: languageCode,
            onLanguageChanged: (value) {
              setState(() => languageCode = value);
            },
            onLogout: () {},
            onUserGuide: () {},
          ),
        ),
      ),
    );
  }
}

final class _Request {
  const _Request({
    required this.uri,
    required this.method,
    required this.headers,
  });

  final Uri uri;
  final String method;
  final Map<String, String> headers;
}

typedef _RequestHandler = ApiTransportResponse Function(_Request request);

final class _RecordingTransport implements SafeContractsTransport {
  _RecordingTransport(this.handler);

  final _RequestHandler handler;
  final List<_Request> requests = <_Request>[];

  @override
  Future<ApiTransportResponse> send({
    required Uri uri,
    required String method,
    Map<String, String> headers = const <String, String>{},
    String? body,
  }) async {
    final request = _Request(
      uri: uri,
      method: method,
      headers: Map<String, String>.unmodifiable(headers),
    );
    requests.add(request);
    return handler(request);
  }
}

final class _LogoutAwareTransport implements SafeContractsTransport {
  _LogoutAwareTransport(this.token);

  final String token;
  bool serverTokenActive = true;
  int logoutCalls = 0;

  @override
  Future<ApiTransportResponse> send({
    required Uri uri,
    required String method,
    Map<String, String> headers = const <String, String>{},
    String? body,
  }) async {
    final authorized =
        serverTokenActive && headers['Authorization'] == 'Bearer $token';

    if (uri.path.endsWith('/session')) {
      if (!authorized) return _error(401, 'unauthorized');
      return _ok(<String, Object?>{
        'authenticated': true,
        'user_id': 42,
        'display_name': 'Alkenzy User',
        'avatar_url': null,
        'scope': 'assigned',
        'capabilities': <String, Object?>{
          'safecontracts_access': true,
          'safecontracts_view_assigned': true,
        },
      });
    }
    if (uri.path.endsWith('/auth/logout') && method == 'POST') {
      if (!authorized) return _error(401, 'unauthorized');
      logoutCalls++;
      serverTokenActive = false;
      return _ok(<String, Object?>{'logged_out': true});
    }
    if (uri.path.endsWith('/mobile-config')) {
      return _ok(<String, Object?>{
        'support_text': '',
        'default_page_size': 25,
        'features': <String, Object?>{
          'excel_export': false,
          'push_notifications': false,
          'collection_entry': false,
        },
      });
    }
    if (uri.path.endsWith('/dashboard')) {
      return _ok(<String, Object?>{
        'filters': <String, Object?>{},
        'kpis': <String, Object?>{
          'contract_count': '0',
          'scheduled_total': '0.0000',
          'remaining_total': '0.0000',
          'overdue_exposure': '0.0000',
          'collected_total': '0.0000',
        },
        'customers': <Object?>[],
        'contracts': <Object?>[],
      });
    }
    if (uri.path.endsWith('/devices')) {
      return _ok(<Object?>[]);
    }
    if (uri.path.endsWith('/contracts') ||
        uri.path.endsWith('/customers') ||
        uri.path.endsWith('/suppliers') ||
        uri.path.endsWith('/payments') ||
        uri.path.endsWith('/collections') ||
        uri.path.endsWith('/followups') ||
        uri.path.endsWith('/notifications')) {
      return _page(<Object?>[]);
    }
    return _error(404, 'not_found');
  }
}

AppEnvironment _environment() => AppEnvironment.fromValues(
      name: 'local',
      apiBaseUrl: 'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
    );

ApiTransportResponse _page(Object? data) {
  return ApiTransportResponse(
    statusCode: 200,
    headers: const <String, String>{'content-type': 'application/json'},
    body: jsonEncode(<String, Object?>{
      'data': data,
      'meta': <String, Object?>{
        'api_version': 'v1',
        'scope': 'assigned',
        'page': 1,
        'per_page': 25,
        'sort': 'id',
        'order': 'desc',
        'has_more': false,
        'bounded_window': 500,
      },
    }),
  );
}

ApiTransportResponse _ok(Object? data) {
  return ApiTransportResponse(
    statusCode: 200,
    headers: const <String, String>{'content-type': 'application/json'},
    body: jsonEncode(<String, Object?>{
      'data': data,
      'meta': <String, Object?>{'api_version': 'v1'},
    }),
  );
}

ApiTransportResponse _error(int statusCode, String code) {
  return ApiTransportResponse(
    statusCode: statusCode,
    headers: const <String, String>{'content-type': 'application/json'},
    body: jsonEncode(<String, Object?>{
      'code': code,
      'message': code,
      'data': <String, Object?>{
        'status': statusCode,
        'api_version': 'v1',
      },
    }),
  );
}

Future<void> _pumpBounded(
  WidgetTester tester, {
  int cycles = 16,
}) async {
  for (var index = 0; index < cycles; index++) {
    await tester.pump(const Duration(milliseconds: 50));
  }
}
