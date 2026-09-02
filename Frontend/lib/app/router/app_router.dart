import 'package:go_router/go_router.dart';

import '../../features/auth/cubit/auth_cubit.dart';
import '../../features/auth/cubit/auth_state.dart';
import '../../features/auth/presentation/auth_entry_screen.dart';
import '../../features/home/presentation/home_screen.dart';
import '../../features/splash/presentation/splash_screen.dart';
import '../../features/subsystem/presentation/subsystem_selection_screen.dart';
import 'router_notifier.dart';

/// Routes that require authentication.
const _protectedRoutes = ['/home', '/subsystem'];

/// Routes that should not be reached once authenticated.
const _authOnlyRoutes = ['/auth'];

class AppRouter {
  AppRouter._();

  static GoRouter createRouter(AuthCubit authCubit) {
    final notifier = RouterNotifier(authCubit);

    return GoRouter(
      initialLocation: '/splash',
      refreshListenable: notifier,
      redirect: (context, state) {
        final authState = authCubit.state;
        final location = state.matchedLocation;

        // While auth state is still being resolved, stay on splash.
        if (authState is AuthInitial || authState is AuthLoading) {
          if (location != '/splash') return '/splash';
          return null;
        }

        final isAuthenticated = authState is Authenticated;

        // Authenticated user must not be stuck on auth-only routes.
        if (isAuthenticated && _authOnlyRoutes.contains(location)) {
          return '/home';
        }

        // Unauthenticated user must not access protected routes.
        if (!isAuthenticated && _protectedRoutes.contains(location)) {
          return '/auth';
        }

        return null;
      },
      routes: [
        GoRoute(
          path: '/splash',
          builder: (context, state) => const SplashScreen(),
        ),
        GoRoute(
          path: '/auth',
          builder: (context, state) => const AuthEntryScreen(),
        ),
        GoRoute(
          path: '/subsystem',
          builder: (context, state) => const SubsystemSelectionScreen(),
        ),
        GoRoute(path: '/home', builder: (context, state) => const HomeScreen()),
      ],
    );
  }
}
