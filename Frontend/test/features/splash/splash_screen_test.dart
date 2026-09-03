import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:lumani/app/router/app_router.dart';
import 'package:lumani/core/localization/app_localizations.dart';
import 'package:lumani/core/network/api_client.dart';
import 'package:lumani/core/state/subsystem_cubit.dart';
import 'package:lumani/core/theme/app_theme.dart';
import 'package:lumani/features/auth/cubit/auth_cubit.dart';
import 'package:lumani/features/auth/presentation/auth_entry_screen.dart';
import 'package:lumani/features/onboarding/presentation/onboarding_screen.dart';
import 'package:lumani/features/splash/presentation/splash_screen.dart';
import 'package:shared_preferences/shared_preferences.dart';

class _FakeSecureStorage implements FlutterSecureStorage {
  final Map<String, String> _data = {};

  @override
  Future<String?> read({
    required String key,
    IOSOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    MacOsOptions? mOptions,
    WindowsOptions? wOptions,
  }) async => _data[key];

  @override
  Future<void> write({
    required String key,
    required String? value,
    IOSOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    MacOsOptions? mOptions,
    WindowsOptions? wOptions,
  }) async {
    if (value == null) {
      _data.remove(key);
    } else {
      _data[key] = value;
    }
  }

  @override
  Future<void> delete({
    required String key,
    IOSOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    MacOsOptions? mOptions,
    WindowsOptions? wOptions,
  }) async => _data.remove(key);

  @override
  Future<bool> containsKey({
    required String key,
    IOSOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    MacOsOptions? mOptions,
    WindowsOptions? wOptions,
  }) async => _data.containsKey(key);

  @override
  Future<Map<String, String>> readAll({
    IOSOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    MacOsOptions? mOptions,
    WindowsOptions? wOptions,
  }) async => Map.unmodifiable(_data);

  @override
  Future<void> deleteAll({
    IOSOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    MacOsOptions? mOptions,
    WindowsOptions? wOptions,
  }) async => _data.clear();

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

void main() {
  group('AppLocalizations Unit Tests', () {
    test('English localization returns exact approved string', () {
      final l10n = AppLocalizations.fromLocale(const Locale('en'));
      expect(
        l10n.splashPreparingLearningExperience,
        equals('Preparing your learning experience...'),
      );
    });

    test('French localization returns exact approved string', () {
      final l10n = AppLocalizations.fromLocale(const Locale('fr'));
      expect(
        l10n.splashPreparingLearningExperience,
        equals('Préparation de votre espace d’apprentissage...'),
      );
    });

    test('Unsupported locale falls back to English', () {
      final l10nEs = AppLocalizations.fromLocale(const Locale('es'));
      expect(
        l10nEs.splashPreparingLearningExperience,
        equals('Preparing your learning experience...'),
      );

      final l10nDe = AppLocalizations.fromLocale(const Locale('de'));
      expect(
        l10nDe.splashPreparingLearningExperience,
        equals('Preparing your learning experience...'),
      );

      final l10nNull = AppLocalizations.fromLocale(null);
      expect(
        l10nNull.splashPreparingLearningExperience,
        equals('Preparing your learning experience...'),
      );
    });

    test('Supported locales include English and French', () {
      expect(AppLocalizations.supportedLocales, contains(const Locale('en')));
      expect(AppLocalizations.supportedLocales, contains(const Locale('fr')));
      expect(AppLocalizations.supportedLocales.length, equals(2));
    });
  });

  group('SplashScreen Widget Tests', () {
    late ApiClient apiClient;
    late AuthCubit authCubit;

    setUp(() {
      SharedPreferences.setMockInitialValues({});
      apiClient = ApiClient(storage: _FakeSecureStorage());
      authCubit = AuthCubit(apiClient: apiClient);
    });

    Widget createTestApp({required Locale locale, GoRouter? router}) {
      final testRouter = router ?? AppRouter.createRouter(authCubit);
      return MultiBlocProvider(
        providers: [
          BlocProvider(create: (_) => SubsystemCubit()),
          BlocProvider.value(value: authCubit),
        ],
        child: MaterialApp.router(
          locale: locale,
          theme: AppTheme.lightTheme,
          darkTheme: AppTheme.darkTheme,
          localizationsDelegates: const [
            AppLocalizations.delegate,
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          supportedLocales: AppLocalizations.supportedLocales,
          routerConfig: testRouter,
        ),
      );
    }

    testWidgets('Renders splash screen with English text when locale is en', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(createTestApp(locale: const Locale('en')));
      await tester.pump();

      // Splash screen is rendered
      expect(find.byType(SplashScreen), findsOneWidget);

      // Approved logo asset is present
      final imageFinder = find.byType(Image);
      expect(imageFinder, findsOneWidget);
      final image = tester.widget<Image>(imageFinder);
      expect(
        (image.image as AssetImage).assetName,
        equals('assets/images/branding/Lumani_logo.jpg'),
      );

      // Subtle loading indicator is present
      expect(find.byType(CircularProgressIndicator), findsOneWidget);

      // English status text is displayed
      expect(
        find.text('Preparing your learning experience...'),
        findsOneWidget,
      );

      // Let timer complete cleanly
      await tester.pump(const Duration(milliseconds: 1600));
    });

    testWidgets('Renders splash screen with French text when locale is fr', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(createTestApp(locale: const Locale('fr')));
      await tester.pump();

      expect(find.byType(SplashScreen), findsOneWidget);

      // French status text is displayed
      expect(
        find.text('Préparation de votre espace d’apprentissage...'),
        findsOneWidget,
      );

      // Let timer complete cleanly
      await tester.pump(const Duration(milliseconds: 1600));
    });

    testWidgets(
      'Falls back to English text when locale is neither English nor French',
      (WidgetTester tester) async {
        await tester.pumpWidget(createTestApp(locale: const Locale('es')));
        await tester.pump();

        expect(find.byType(SplashScreen), findsOneWidget);
        expect(
          find.text('Preparing your learning experience...'),
          findsOneWidget,
        );

        await tester.pump(const Duration(milliseconds: 1600));
      },
    );

    testWidgets(
      'Transitions smoothly to /onboarding for first-time unauthenticated users',
      (WidgetTester tester) async {
        SharedPreferences.setMockInitialValues({'has_seen_onboarding': false});

        await tester.pumpWidget(createTestApp(locale: const Locale('en')));
        await tester.pump();

        // Initially on splash screen
        expect(find.byType(SplashScreen), findsOneWidget);

        // 1. Advance past 1200ms startup timer
        await tester.pump(const Duration(milliseconds: 1300));
        // 2. Advance past 500ms reverse fade animation
        await tester.pump(const Duration(milliseconds: 600));
        // 3. Pump route transition frame
        await tester.pump();
        await tester.pump();

        // First-time user navigates to OnboardingScreen
        expect(find.byType(OnboardingScreen), findsOneWidget);
      },
    );

    testWidgets(
      'Transitions smoothly to /auth when has_seen_onboarding is true',
      (WidgetTester tester) async {
        SharedPreferences.setMockInitialValues({'has_seen_onboarding': true});

        await tester.pumpWidget(createTestApp(locale: const Locale('en')));
        await tester.pump();

        // Initially on splash screen
        expect(find.byType(SplashScreen), findsOneWidget);

        // 1. Advance past 1200ms startup timer
        await tester.pump(const Duration(milliseconds: 1300));
        // 2. Advance past 500ms reverse fade animation
        await tester.pump(const Duration(milliseconds: 600));
        // 3. Pump route transition frame
        await tester.pump();
        await tester.pump();

        // Returning user navigates to AuthEntryScreen
        expect(find.byType(AuthEntryScreen), findsOneWidget);
      },
    );
  });
}
