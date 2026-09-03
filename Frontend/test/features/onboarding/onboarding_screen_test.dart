import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:lumani/app/router/app_router.dart';
import 'package:lumani/core/localization/app_en.dart';
import 'package:lumani/core/localization/app_fr.dart';
import 'package:lumani/core/localization/app_localizations.dart';
import 'package:lumani/core/network/api_client.dart';
import 'package:lumani/core/state/subsystem_cubit.dart';
import 'package:lumani/core/theme/app_theme.dart';
import 'package:lumani/features/auth/cubit/auth_cubit.dart';
import 'package:lumani/features/auth/presentation/auth_entry_screen.dart';
import 'package:lumani/features/onboarding/presentation/onboarding_screen.dart';
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
  TestWidgetsFlutterBinding.ensureInitialized();

  // ===========================================================================
  // 1. Localization Unit Tests for Onboarding
  // ===========================================================================
  group('AppLocalizations Onboarding Unit Tests', () {
    const en = AppLocalizationsEn();
    const fr = AppLocalizationsFr();

    test('English onboarding strings match exact specification', () {
      expect(en.onboardingAct1Title, "Learning shouldn't feel this difficult.");
      expect(
        en.onboardingAct1Body,
        'National exams are demanding, but fragmented notes, missing past papers, and studying in isolation make preparation harder than it has to be.',
      );

      expect(
        en.onboardingAct2Title,
        "There's a better way to master your syllabus.",
      );
      expect(
        en.onboardingAct2Body,
        'Access structured, curriculum-aligned lessons tailored precisely to the official GCE Board and OBC examination standards.',
      );

      expect(en.onboardingAct3Title, 'Ask whenever you are stuck.');
      expect(
        en.onboardingAct3Body,
        'Get 24/7 step-by-step academic explanations from Lumani AI Tutor. Break down complex math formulas, science concepts, and essay frameworks instantly.',
      );

      expect(en.onboardingAct4Title, 'Your education is leading somewhere.');
      expect(
        en.onboardingAct4Body,
        'Turn exam success into real-world opportunities. Unlock university tracks, career roadmaps, and build verified academic mastery.',
      );

      expect(en.onboardingSkip, 'Skip');
      expect(en.onboardingNext, 'Next');
      expect(en.onboardingGetStarted, 'Get Started');
    });

    test('French onboarding strings match exact specification', () {
      expect(
        fr.onboardingAct1Title,
        'Apprendre ne devrait pas être si difficile.',
      );
      expect(
        fr.onboardingAct1Body,
        "Les examens nationaux sont exigeants, mais les cours dispersés, le manque d'épreuves et l'isolement compliquent inutilement votre préparation.",
      );

      expect(
        fr.onboardingAct2Title,
        'Une méthode claire pour maîtriser le programme.',
      );
      expect(
        fr.onboardingAct2Body,
        "Accédez à des cours structurés et conformes aux exigences officielles de l'Office du Baccalauréat et du GCE Board.",
      );

      expect(fr.onboardingAct3Title, 'Posez vos questions à tout moment.');
      expect(
        fr.onboardingAct3Body,
        "Profitez d'explications détaillées 24h/24 avec le tuteur Lumani IA. Maîtrisez les formules complexes et les dissertations sans attendre.",
      );

      expect(fr.onboardingAct4Title, 'Votre parcours vous mène plus loin.');
      expect(
        fr.onboardingAct4Body,
        'Transformez votre réussite scolaire en opportunités réelles : préparez vos filières universitaires et bâtissez votre avenir.',
      );

      expect(fr.onboardingSkip, 'Passer');
      expect(fr.onboardingNext, 'Suivant');
      expect(fr.onboardingGetStarted, 'Commencer');
    });
  });

  // ===========================================================================
  // 2. OnboardingScreen Widget Tests
  // ===========================================================================
  group('OnboardingScreen Widget Tests', () {
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

    Widget createDirectOnboardingWidget({required Locale locale}) {
      return MultiBlocProvider(
        providers: [
          BlocProvider(create: (_) => SubsystemCubit()),
          BlocProvider.value(value: authCubit),
        ],
        child: MaterialApp(
          locale: locale,
          theme: AppTheme.darkTheme,
          localizationsDelegates: const [
            AppLocalizations.delegate,
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          supportedLocales: AppLocalizations.supportedLocales,
          home: const OnboardingScreen(),
        ),
      );
    }

    testWidgets('Renders Act 1 with English text, Skip button, and PageView', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        createDirectOnboardingWidget(locale: const Locale('en')),
      );
      await tester.pumpAndSettle();

      expect(find.byType(OnboardingScreen), findsOneWidget);
      expect(find.byType(PageView), findsOneWidget);

      // Act 1 title and body
      expect(
        find.text("Learning shouldn't feel this difficult."),
        findsOneWidget,
      );
      expect(
        find.textContaining('National exams are demanding'),
        findsOneWidget,
      );

      // Skip button visible on Act 1
      expect(find.byKey(const Key('onboarding_skip_button')), findsOneWidget);

      // Next icon button present
      expect(find.byKey(const Key('onboarding_next_button')), findsOneWidget);
    });

    testWidgets('Renders Act 1 with French text and Passer button', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        createDirectOnboardingWidget(locale: const Locale('fr')),
      );
      await tester.pumpAndSettle();

      // French Act 1 text
      expect(
        find.text('Apprendre ne devrait pas être si difficile.'),
        findsOneWidget,
      );
      expect(find.text('Passer'), findsOneWidget);
    });

    testWidgets('Advances through all 4 Acts to Get Started CTA', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        createDirectOnboardingWidget(locale: const Locale('en')),
      );
      await tester.pumpAndSettle();

      // Act 1
      expect(
        find.text("Learning shouldn't feel this difficult."),
        findsOneWidget,
      );

      // Tap Next to advance to Act 2
      final nextBtn = find.byKey(const Key('onboarding_next_button'));
      await tester.tap(nextBtn);
      await tester.pumpAndSettle();

      // Act 2
      expect(
        find.text("There's a better way to master your syllabus."),
        findsOneWidget,
      );
      expect(find.text('Skip'), findsOneWidget);

      // Tap Next to advance to Act 3
      await tester.tap(nextBtn);
      await tester.pumpAndSettle();

      // Act 3
      expect(find.text('Ask whenever you are stuck.'), findsOneWidget);
      expect(find.text('Skip'), findsOneWidget);

      // Tap Next to advance to Act 4
      await tester.tap(nextBtn);
      await tester.pumpAndSettle();

      // Act 4
      expect(find.text('Your education is leading somewhere.'), findsOneWidget);

      // On Act 4: "Get Started" CTA button is visible
      expect(find.text('Get Started'), findsOneWidget);
    });

    testWidgets(
      'Tapping Skip persists has_seen_onboarding and routes to /auth',
      (WidgetTester tester) async {
        SharedPreferences.setMockInitialValues({'has_seen_onboarding': false});

        final router = GoRouter(
          initialLocation: '/onboarding',
          routes: [
            GoRoute(
              path: '/onboarding',
              builder: (context, state) => const OnboardingScreen(),
            ),
            GoRoute(
              path: '/auth',
              builder: (context, state) => const AuthEntryScreen(),
            ),
          ],
        );

        await tester.pumpWidget(
          createTestApp(locale: const Locale('en'), router: router),
        );
        await tester.pumpAndSettle();

        expect(find.byType(OnboardingScreen), findsOneWidget);

        // Tap Skip
        await tester.tap(find.text('Skip'));
        await tester.pumpAndSettle();

        // Check persistence
        final prefs = await SharedPreferences.getInstance();
        expect(prefs.getBool('has_seen_onboarding'), isTrue);

        // Navigates to AuthEntryScreen
        expect(find.byType(AuthEntryScreen), findsOneWidget);
      },
    );

    testWidgets(
      'Tapping Get Started on Act 4 persists has_seen_onboarding and routes to /auth',
      (WidgetTester tester) async {
        SharedPreferences.setMockInitialValues({'has_seen_onboarding': false});

        final router = GoRouter(
          initialLocation: '/onboarding',
          routes: [
            GoRoute(
              path: '/onboarding',
              builder: (context, state) => const OnboardingScreen(),
            ),
            GoRoute(
              path: '/auth',
              builder: (context, state) => const AuthEntryScreen(),
            ),
          ],
        );

        await tester.pumpWidget(
          createTestApp(locale: const Locale('en'), router: router),
        );
        await tester.pumpAndSettle();

        // Advance to Act 4
        final nextBtn = find.byKey(const Key('onboarding_next_button'));
        await tester.tap(nextBtn);
        await tester.pumpAndSettle();
        await tester.tap(nextBtn);
        await tester.pumpAndSettle();
        await tester.tap(nextBtn);
        await tester.pumpAndSettle();

        // Tap Get Started
        final getStartedBtn = find.byKey(
          const Key('onboarding_get_started_button'),
        );
        expect(getStartedBtn, findsOneWidget);
        await tester.tap(getStartedBtn);
        await tester.pumpAndSettle();

        // Check persistence
        final prefs = await SharedPreferences.getInstance();
        expect(prefs.getBool('has_seen_onboarding'), isTrue);

        // Navigates to AuthEntryScreen
        expect(find.byType(AuthEntryScreen), findsOneWidget);
      },
    );
  });
}
