import 'package:go_router/go_router.dart';

import '../../features/auth/presentation/auth_entry_screen.dart';
import '../../features/home/presentation/home_screen.dart';
import '../../features/splash/presentation/splash_screen.dart';
import '../../features/subsystem/presentation/subsystem_selection_screen.dart';

class AppRouter {
  static final GoRouter router = GoRouter(
    initialLocation: '/splash',
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
