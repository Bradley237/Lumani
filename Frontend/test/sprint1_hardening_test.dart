import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lumani/core/network/api_client.dart';
import 'package:lumani/core/state/subsystem_cubit.dart';
import 'package:lumani/features/auth/cubit/auth_cubit.dart';
import 'package:lumani/features/auth/cubit/auth_state.dart';

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
  });
}
