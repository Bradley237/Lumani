import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../core/state/subsystem_cubit.dart';
import '../../../core/theme/app_dimensions.dart';
import '../../auth/cubit/auth_cubit.dart';
import '../../auth/cubit/auth_state.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    _checkInitialState();
  }

  Future<void> _checkInitialState() async {
    await context.read<AuthCubit>().checkAuthStatus();
  }

  void _navigateBasedOnState(
    AuthState authState,
    SubsystemState subsystemState,
  ) {
    if (authState is Unauthenticated || authState is AuthError) {
      context.go('/auth');
    } else if (authState is Authenticated) {
      final userExamSystem = authState.user['exam_system'];
      if (userExamSystem == 'gce' || userExamSystem == 'obc') {
        context.go('/home');
      } else if (subsystemState.subsystem != Subsystem.none) {
        context.go('/home');
      } else {
        context.go('/subsystem');
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return MultiBlocListener(
      listeners: [
        BlocListener<AuthCubit, AuthState>(
          listener: (context, authState) {
            final subsystemState = context.read<SubsystemCubit>().state;
            _navigateBasedOnState(authState, subsystemState);
          },
        ),
        BlocListener<SubsystemCubit, SubsystemState>(
          listener: (context, subsystemState) {
            final authState = context.read<AuthCubit>().state;
            _navigateBasedOnState(authState, subsystemState);
          },
        ),
      ],
      child: Scaffold(
        backgroundColor: theme.colorScheme.surface,
        body: SafeArea(
          child: Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  width: 96,
                  height: 96,
                  decoration: BoxDecoration(
                    color: theme.colorScheme.primary,
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Icon(
                    Icons.school_rounded,
                    size: 56,
                    color: theme.colorScheme.onPrimary,
                  ),
                ),
                const SizedBox(height: AppDimensions.space24),
                Text(
                  'LUMANI',
                  style: theme.textTheme.displayMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                    letterSpacing: 2.0,
                    color: theme.colorScheme.primary,
                  ),
                ),
                const SizedBox(height: AppDimensions.space8),
                Text(
                  'Empowering Cameroonian Excellence',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
                const SizedBox(height: AppDimensions.space48),
                SizedBox(
                  width: 24,
                  height: 24,
                  child: CircularProgressIndicator(
                    strokeWidth: 2.5,
                    color: theme.colorScheme.primary,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
