redirect: (context, state) {
  final onboarding = ref.read(onboardingProvider);
  final subsystem = ref.read(subsystemProvider);
  final location = state.uri.path;

  if (!onboarding && location != '/onboarding') {
    return '/onboarding';
  }
  if (onboarding && subsystem == null && location != '/subsystem') {
    return '/subsystem';
  }
  if (onboarding && subsystem != null && location == '/') {
    return '/home';
  }
  return null;
}