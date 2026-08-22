abstract final class EnvConfig {
  EnvConfig._();

  static const String _useMock = String.fromEnvironment(
    'USE_MOCK',
    defaultValue: 'true',
  );

  static const bool isMock = _useMock == 'true';
}
