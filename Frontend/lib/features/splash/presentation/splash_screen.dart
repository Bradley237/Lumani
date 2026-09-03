import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/localization/app_localizations.dart';
import '../../../core/responsive/responsive_utils.dart';
import '../../../core/state/subsystem_cubit.dart';
import '../../../core/theme/app_dimensions.dart';
import '../../../core/theme/app_motion.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/cubit/auth_cubit.dart';
import '../../auth/cubit/auth_state.dart';

/// Premium Lumani Splash Screen.
///
/// Features:
/// - Full-screen Lumani theme background
/// - Centered approved Lumani logo as the primary visual focus
/// - Subtle indeterminate loading indicator beneath the logo
/// - Minimal localized status text resolved from device/app locale
/// - Smooth entry and exit transitions
/// - Seamless routing to next screen upon initialization
class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _animationController;
  late final Animation<double> _fadeAnimation;
  Timer? _splashTimer;
  Completer<void>? _timerCompleter;

  /// Intentional minimum startup duration to present the Lumani brand elegantly.
  static const Duration _minimumSplashDuration = Duration(milliseconds: 1200);

  @override
  void initState() {
    super.initState();
    _animationController = AnimationController(
      vsync: this,
      duration: AppMotion.slow,
    );

    _fadeAnimation = CurvedAnimation(
      parent: _animationController,
      curve: AppMotion.decelerate,
    );

    _animationController.forward();
    _initializeApp();
  }

  @override
  void dispose() {
    _splashTimer?.cancel();
    if (_timerCompleter != null && !_timerCompleter!.isCompleted) {
      _timerCompleter!.complete();
    }
    _animationController.dispose();
    super.dispose();
  }

  Future<void> _waitForMinimumDuration() {
    final completer = Completer<void>();
    _timerCompleter = completer;
    _splashTimer = Timer(_minimumSplashDuration, () {
      if (!completer.isCompleted) completer.complete();
    });
    return completer.future;
  }

  /// Concurrently executes auth initialization and a brief minimum delay,
  /// then triggers a smooth exit transition to the appropriate destination route.
  Future<void> _initializeApp() async {
    final authCheck = context.read<AuthCubit>().checkAuthStatus();
    final timerDelay = _waitForMinimumDuration();

    // Wait for both startup resolution and the intentional minimum duration
    await Future.wait([authCheck, timerDelay]);

    if (!mounted) return;

    // Smooth exit animation out of the splash
    await _animationController.reverse();

    if (!mounted) return;

    final authState = context.read<AuthCubit>().state;
    final subsystemState = context.read<SubsystemCubit>().state;
    await _navigateBasedOnState(authState, subsystemState);
  }

  Future<void> _navigateBasedOnState(
    AuthState authState,
    SubsystemState subsystemState,
  ) async {
    if (authState is Authenticated) {
      _routeAuthenticated(authState.user.activeSubsystem, subsystemState);
      return;
    } else if (authState is AuthenticatedOffline) {
      _routeAuthenticated(authState.user.activeSubsystem, subsystemState);
      return;
    }

    // Unauthenticated or AuthError: Check if onboarding has been seen.
    final prefs = await SharedPreferences.getInstance();
    final hasSeenOnboarding = prefs.getBool('has_seen_onboarding') ?? false;

    if (!mounted) return;

    if (!hasSeenOnboarding) {
      context.go('/onboarding');
    } else {
      context.go('/auth');
    }
  }

  void _routeAuthenticated(
    Subsystem userSubsystem,
    SubsystemState subsystemState,
  ) {
    if (userSubsystem != Subsystem.none) {
      context.go('/home');
    } else if (subsystemState.subsystem != Subsystem.none) {
      context.go('/home');
    } else {
      context.go('/subsystem');
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final l10n = AppLocalizations.of(context);

    final logoWidth = context.responsiveValue<double>(
      compact: (context.screenWidth * 0.48).clamp(160.0, 220.0),
      medium: 260.0,
      expanded: 300.0,
    );

    final logoBreathingRoom = context.responsiveValue<double>(
      compact: AppDimensions.space32,
      medium: AppDimensions.space48,
    );

    return Scaffold(
      backgroundColor: theme.scaffoldBackgroundColor,
      body: SafeArea(
        child: FadeTransition(
          opacity: _fadeAnimation,
          child: Center(
            child: SingleChildScrollView(
              padding: EdgeInsets.symmetric(
                horizontal: context.horizontalGutter,
              ),
              child: ConstrainedBox(
                constraints: const BoxConstraints(
                  maxWidth: AppBreakpoints.maxContentWidth,
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.center,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // Approved Lumani Brand Logo Asset
                    Image.asset(
                      'assets/images/branding/Lumani_logo.jpg',
                      width: logoWidth,
                      fit: BoxFit.contain,
                      semanticLabel: 'Lumani Logo',
                    ),

                    SizedBox(height: logoBreathingRoom),

                    // Subtle Supporting Loading Treatment
                    SizedBox(
                      width: AppDimensions.iconLg,
                      height: AppDimensions.iconLg,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.0,
                        color: theme.colorScheme.primary,
                      ),
                    ),

                    const SizedBox(height: AppDimensions.space16),

                    // Minimal Localized Status Text
                    ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 320.0),
                      child: Text(
                        l10n.splashPreparingLearningExperience,
                        style: AppTextStyles.bodyMedium.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                        ),
                        textAlign: TextAlign.center,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
