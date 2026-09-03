import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lumani/core/models/user_model.dart';
import 'package:lumani/core/network/api_client.dart';
import 'package:lumani/core/state/locale_cubit.dart';
import 'package:lumani/core/state/subsystem_cubit.dart';
import 'package:lumani/features/auth/cubit/auth_cubit.dart';
import 'package:lumani/features/auth/cubit/auth_state.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Minimal in-memory secure storage for unit tests.
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

  // Unsupported methods — not needed for tests.
  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

void main() {
  group('Sprint 1 — Foundation Hardening Tests', () {
    // -----------------------------------------------------------------------
    // Subsystem enum serialisation
    // -----------------------------------------------------------------------
    group('Subsystem API value serialisation', () {
      test('GCE serialises to "gce"', () {
        expect(Subsystem.gce.apiValue, equals('gce'));
      });

      test('OBC serialises to "obc"', () {
        expect(Subsystem.obc.apiValue, equals('obc'));
      });

      test('none serialises to "none"', () {
        expect(Subsystem.none.apiValue, equals('none'));
      });

      test('fromApiValue("gce") returns Subsystem.gce', () {
        expect(SubsystemApiValue.fromApiValue('gce'), equals(Subsystem.gce));
      });

      test('fromApiValue("obc") returns Subsystem.obc', () {
        expect(SubsystemApiValue.fromApiValue('obc'), equals(Subsystem.obc));
      });

      test('fromApiValue(null) returns Subsystem.none', () {
        expect(SubsystemApiValue.fromApiValue(null), equals(Subsystem.none));
      });

      test('fromApiValue("anglophone") returns Subsystem.none (old value rejected)', () {
        expect(
          SubsystemApiValue.fromApiValue('anglophone'),
          equals(Subsystem.none),
        );
      });

      test('fromApiValue("francophone") returns Subsystem.none (old value rejected)', () {
        expect(
          SubsystemApiValue.fromApiValue('francophone'),
          equals(Subsystem.none),
        );
      });
    });

    // -----------------------------------------------------------------------
    // ApiConfig base URL
    // -----------------------------------------------------------------------
    group('ApiConfig', () {
      test('baseUrl has a non-empty default value', () {
        expect(ApiConfig.baseUrl, isNotEmpty);
      });
    });

    // -----------------------------------------------------------------------
    // Token preservation rules
    // -----------------------------------------------------------------------
    group('AuthCubit — token preservation rules', () {
      late _FakeSecureStorage fakeStorage;
      late ApiClient apiClient;
      late AuthCubit cubit;

      setUp(() {
        fakeStorage = _FakeSecureStorage();
        apiClient = ApiClient(storage: fakeStorage);
        cubit = AuthCubit(apiClient: apiClient);
      });

      tearDown(() => cubit.close());

      test(
        'forceUnauthenticated emits Unauthenticated without clearing token',
        () async {
          // Arrange: store a token
          await apiClient.saveToken('valid-token');

          // Act: simulate global 401 callback
          cubit.forceUnauthenticated();

          // Assert: state changed but token is preserved
          expect(cubit.state, isA<Unauthenticated>());
          expect(await apiClient.getToken(), equals('valid-token'));
        },
      );

      test('forceUnauthenticated is idempotent — does not re-emit if already Unauthenticated', () async {
        // Pre-condition: cubit starts as AuthInitial; emit Unauthenticated first.
        cubit.forceUnauthenticated();
        final firstState = cubit.state;

        // Call again — should not emit a new event.
        final states = <AuthState>[];
        final sub = cubit.stream.listen(states.add);
        cubit.forceUnauthenticated();
        await Future<void>.delayed(Duration.zero);
        await sub.cancel();

        expect(firstState, isA<Unauthenticated>());
        expect(
          states,
          isEmpty,
          reason: 'No new emission when already Unauthenticated',
        );
      });
    });

    // -----------------------------------------------------------------------
    // UserModel domain model tests
    // -----------------------------------------------------------------------
    group('UserModel domain model', () {
      test('deserialises full JSON correctly', () {
        final json = {
          'id': 42,
          'email': 'student@lumani.cm',
          'first_name': 'Ambe',
          'last_name': 'Ngu',
          'phone_number': '+237670000000',
          'exam_system': 'gce',
          'academic_level': 'ordinary_level',
          'coin_balance': 150,
          'streak_count': 7,
          'subscription_status': 'premium',
          'preferred_language': 'en',
        };

        final user = UserModel.fromJson(json);

        expect(user.id, equals(42));
        expect(user.email, equals('student@lumani.cm'));
        expect(user.firstName, equals('Ambe'));
        expect(user.lastName, equals('Ngu'));
        expect(user.phoneNumber, equals('+237670000000'));
        expect(user.activeSubsystem, equals(Subsystem.gce));
        expect(user.academicLevel, equals(ExamLevel.ordinaryLevel));
        expect(user.coinBalance, equals(150));
        expect(user.streakCount, equals(7));
        expect(user.subscriptionStatus, equals('premium'));
        expect(user.preferredLanguage, equals('en'));
        expect(user.displayName, equals('Ambe Ngu'));
      });

      test('deserialises empty/null JSON safely with defaults', () {
        final user = UserModel.fromJson({});

        expect(user.id, equals(0));
        expect(user.email, isEmpty);
        expect(user.firstName, isEmpty);
        expect(user.lastName, isEmpty);
        expect(user.activeSubsystem, equals(Subsystem.none));
        expect(user.academicLevel, isNull);
        expect(user.coinBalance, equals(0));
        expect(user.streakCount, equals(0));
        expect(user.subscriptionStatus, equals('free'));
        expect(user.displayName, equals('Student'));
      });

      test('serialises to JSON matching API schema', () {
        const user = UserModel(
          id: 10,
          email: 'test@lumani.cm',
          firstName: 'Jean',
          lastName: 'Kamga',
          activeSubsystem: Subsystem.obc,
          academicLevel: ExamLevel.baccalaureat,
          coinBalance: 50,
          streakCount: 3,
        );

        final json = user.toJson();

        expect(json['id'], equals(10));
        expect(json['email'], equals('test@lumani.cm'));
        expect(json['first_name'], equals('Jean'));
        expect(json['last_name'], equals('Kamga'));
        expect(json['exam_system'], equals('obc'));
        expect(json['academic_level'], equals('bac'));
        expect(json['coin_balance'], equals(50));
        expect(json['streak_count'], equals(3));
      });

      test('copyWith creates updated immutable copy', () {
        const user = UserModel(
          id: 1,
          email: 'a@b.com',
          firstName: 'A',
          lastName: 'B',
        );

        final updated = user.copyWith(coinBalance: 200, streakCount: 5);

        expect(updated.id, equals(1));
        expect(updated.coinBalance, equals(200));
        expect(updated.streakCount, equals(5));
        expect(user.coinBalance, equals(0)); // Original is unchanged
      });
    });

    // -----------------------------------------------------------------------
    // ExamLevel and ExamOptionModel cascading levels
    // -----------------------------------------------------------------------
    group('ExamLevel and ExamOptionModel', () {
      test('GCE cascading levels contain O-Level and A-Level', () {
        final gceLevels = ExamLevelApiValue.levelsFor(Subsystem.gce);
        expect(
          gceLevels,
          containsAll([ExamLevel.ordinaryLevel, ExamLevel.advancedLevel]),
        );
        expect(gceLevels.length, equals(2));
      });

      test('OBC cascading levels contain BEPC, Probatoire, and Bac', () {
        final obcLevels = ExamLevelApiValue.levelsFor(Subsystem.obc);
        expect(
          obcLevels,
          containsAll([
            ExamLevel.bepc,
            ExamLevel.probatoire,
            ExamLevel.baccalaureat,
          ]),
        );
        expect(obcLevels.length, equals(3));
      });

      test('Subsystem.none returns empty level list', () {
        expect(ExamLevelApiValue.levelsFor(Subsystem.none), isEmpty);
      });

      test('ExamOptionModel serialises and deserialises accurately', () {
        const option = ExamOptionModel(
          subsystem: Subsystem.gce,
          selectedLevel: ExamLevel.advancedLevel,
        );

        final json = option.toJson();
        expect(json['subsystem'], equals('gce'));
        expect(json['selected_level'], equals('advanced_level'));

        final parsed = ExamOptionModel.fromJson(json);
        expect(parsed, equals(option));
      });
    });

    // -----------------------------------------------------------------------
    // LocaleCubit Curriculum Lock
    // -----------------------------------------------------------------------
    group('LocaleCubit Curriculum Lock', () {
      setUp(() {
        SharedPreferences.setMockInitialValues({});
      });

      test('GCE subsystem locks locale to English', () async {
        final cubit = LocaleCubit();
        await cubit.lockToSubsystem('gce');

        expect(cubit.state.languageCode, equals('en'));

        // Attempt to manually switch to French must be ignored
        await cubit.setLocale('fr');
        expect(cubit.state.languageCode, equals('en'));

        await cubit.close();
      });

      test('OBC subsystem locks locale to French', () async {
        final cubit = LocaleCubit();
        await cubit.lockToSubsystem('obc');

        expect(cubit.state.languageCode, equals('fr'));

        // Attempt to manually switch to English must be ignored
        await cubit.setLocale('en');
        expect(cubit.state.languageCode, equals('fr'));

        await cubit.close();
      });

      test('Unlocking with none allows manual locale changes', () async {
        final cubit = LocaleCubit();
        await cubit.lockToSubsystem('gce');
        expect(cubit.state.languageCode, equals('en'));

        // Unlock
        await cubit.lockToSubsystem('none');

        // Manual switch should now succeed
        await cubit.setLocale('fr');
        expect(cubit.state.languageCode, equals('fr'));

        await cubit.close();
      });
    });

    // -----------------------------------------------------------------------
    // Session Resilience & AuthenticatedOffline
    // -----------------------------------------------------------------------
    group('AuthCubit — Session Resilience', () {
      late _FakeSecureStorage fakeStorage;
      late ApiClient apiClient;
      late AuthCubit cubit;

      setUp(() {
        fakeStorage = _FakeSecureStorage();
        apiClient = ApiClient(storage: fakeStorage);
        cubit = AuthCubit(apiClient: apiClient);
      });

      tearDown(() => cubit.close());

      test('checkAuthStatus with stored token and cached user enters AuthenticatedOffline on network error', () async {
        // Arrange: valid token and previously cached user
        await apiClient.saveToken('persisted-offline-token');
        await apiClient.secureStorage.write(
          key: 'cached_user_json',
          value: '{"id":1,"email":"offline@lumani.cm","first_name":"Paul","last_name":"Biya","exam_system":"obc"}',
        );

        // Act: network check will fail because localhost/mock has no active backend
        await cubit.checkAuthStatus();

        // Assert: AuthenticatedOffline state emitted with cached user profile, token NOT deleted
        expect(cubit.state, isA<AuthenticatedOffline>());
        final offlineState = cubit.state as AuthenticatedOffline;
        expect(offlineState.user.firstName, equals('Paul'));
        expect(offlineState.user.activeSubsystem, equals(Subsystem.obc));
        expect(offlineState.token, equals('persisted-offline-token'));
        expect(await apiClient.getToken(), equals('persisted-offline-token'));
      });
    });
  });
}
