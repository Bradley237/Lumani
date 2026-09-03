import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/localization/app_localizations.dart';
import '../../../core/responsive/responsive_utils.dart';
import '../../../core/state/subsystem_cubit.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_dimensions.dart';
import '../../../core/theme/app_motion.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/cubit/auth_cubit.dart';
import '../../auth/cubit/auth_state.dart';

/// Lumani 4-Act Story Onboarding Experience.
///
/// Features:
/// - 4 narrative acts depicting the journey from student frustration to academic mastery
/// - Pre-cached full-screen photography background assets
/// - Multi-layered gradient scrim system (top and bottom) ensuring maximum readability
/// - 4-segment animated progress indicator and contextual Skip action
/// - Smooth page transitions with responsive maxWidth container for tablets
/// - Animated CTA transition to "Get Started" on Act 4 with haptic feedback
/// - Route hand-off with persistent `has_seen_onboarding` flag in [SharedPreferences]
class OnboardingScreen extends StatefulWidget {
  const OnboardingScreen({super.key});

  static const String hasSeenOnboardingKey = 'has_seen_onboarding';

  @override
  State<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends State<OnboardingScreen> {
  late final PageController _pageController;
  int _currentPage = 0;
  bool _assetsPrecached = false;

  static const List<String> _backgroundAssets = [
    'assets/images/branding/onboarding1.jpg',
    'assets/images/branding/onboarding2.jpg',
    'assets/images/branding/onboarding3.jpg',
    'assets/images/branding/onboarding4.jpg',
  ];

  @override
  void initState() {
    super.initState();
    _pageController = PageController();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_assetsPrecached) {
      _assetsPrecached = true;
      for (final asset in _backgroundAssets) {
        precacheImage(AssetImage(asset), context);
      }
    }
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  void _onPageChanged(int page) {
    setState(() {
      _currentPage = page;
    });
  }

  Future<void> _completeOnboarding() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(OnboardingScreen.hasSeenOnboardingKey, true);

    if (!mounted) return;

    final authState = context.read<AuthCubit>().state;
    final subsystemState = context.read<SubsystemCubit>().state;

    if (authState is Authenticated || authState is AuthenticatedOffline) {
      final user = authState is Authenticated
          ? authState.user
          : (authState as AuthenticatedOffline).user;
      if (user.activeSubsystem != Subsystem.none ||
          subsystemState.subsystem != Subsystem.none) {
        context.go('/home');
      } else {
        context.go('/subsystem');
      }
    } else {
      context.go('/auth');
    }
  }

  void _nextPage() {
    HapticFeedback.lightImpact();
    if (_currentPage < _backgroundAssets.length - 1) {
      _pageController.nextPage(
        duration: AppMotion.normal,
        curve: Curves.easeInOut,
      );
    } else {
      _completeOnboarding();
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final size = MediaQuery.sizeOf(context);

    final actTitles = [
      l10n.onboardingAct1Title,
      l10n.onboardingAct2Title,
      l10n.onboardingAct3Title,
      l10n.onboardingAct4Title,
    ];

    final actBodies = [
      l10n.onboardingAct1Body,
      l10n.onboardingAct2Body,
      l10n.onboardingAct3Body,
      l10n.onboardingAct4Body,
    ];

    return Scaffold(
      backgroundColor: AppColors.backgroundDark,
      body: Stack(
        children: [
          // -------------------------------------------------------------------
          // Layer 1 to 4: PageView with Images, Scrims, and Typography
          // -------------------------------------------------------------------
          PageView.builder(
            controller: _pageController,
            onPageChanged: _onPageChanged,
            itemCount: _backgroundAssets.length,
            itemBuilder: (context, index) {
              return Stack(
                fit: StackFit.expand,
                children: [
                  // Layer 1: Background Image
                  Positioned.fill(
                    child: Image.asset(
                      _backgroundAssets[index],
                      fit: BoxFit.cover,
                      alignment: Alignment.center,
                      errorBuilder: (context, error, stackTrace) {
                        return Container(color: AppColors.surfaceDark);
                      },
                    ),
                  ),

                  // Layer 2: Top Gradient Scrim (Protect status bar and indicators)
                  Positioned(
                    top: 0,
                    left: 0,
                    right: 0,
                    height: 140.0,
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topCenter,
                          end: Alignment.bottomCenter,
                          colors: [
                            Colors.black.withValues(alpha: 0.60),
                            Colors.transparent,
                          ],
                        ),
                      ),
                    ),
                  ),

                  // Layer 3: Bottom Gradient Scrim (Deep Obsidian reading plane)
                  Positioned(
                    left: 0,
                    right: 0,
                    bottom: 0,
                    top: size.height * 0.42,
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topCenter,
                          end: Alignment.bottomCenter,
                          colors: [
                            Colors.transparent,
                            AppColors.backgroundDark.withValues(alpha: 0.70),
                            AppColors.backgroundDark.withValues(alpha: 0.95),
                            AppColors.backgroundDark,
                          ],
                          stops: const [0.0, 0.28, 0.65, 1.0],
                        ),
                      ),
                    ),
                  ),

                  // Layer 4: Foreground Content Layer (Narrative Text)
                  Positioned(
                    left: 0,
                    right: 0,
                    bottom: 120.0,
                    child: SafeArea(
                      child: Padding(
                        padding: EdgeInsets.symmetric(
                          horizontal: context.horizontalGutter,
                        ),
                        child: Center(
                          child: ConstrainedBox(
                            constraints: const BoxConstraints(maxWidth: 440.0),
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  actTitles[index],
                                  style: AppTextStyles.displayMedium.copyWith(
                                    fontSize: context.responsiveValue<double>(
                                      compact: 24.0,
                                      medium: 26.0,
                                      expanded: 28.0,
                                    ),
                                    color: Colors.white,
                                    fontWeight: FontWeight.w700,
                                    height: 1.25,
                                  ),
                                ),
                                const SizedBox(height: AppDimensions.space16),
                                Text(
                                  actBodies[index],
                                  style: AppTextStyles.bodyMedium.copyWith(
                                    fontSize: 15.0,
                                    color: const Color(0xFFCBD5E1),
                                    height: 1.45,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
              );
            },
          ),

          // -------------------------------------------------------------------
          // Fixed Top Bar: Segmented Progress Indicator & Skip Action
          // -------------------------------------------------------------------
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            child: SafeArea(
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: AppDimensions.space20,
                  vertical: AppDimensions.space12,
                ),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 440.0),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        // Segmented Progress Indicator Row
                        Row(
                          children: List.generate(_backgroundAssets.length, (
                            index,
                          ) {
                            final isPassedOrCurrent = index <= _currentPage;
                            return Expanded(
                              child: Padding(
                                padding: EdgeInsets.only(
                                  right: index < _backgroundAssets.length - 1
                                      ? 6.0
                                      : 0.0,
                                ),
                                child: AnimatedContainer(
                                  duration: const Duration(milliseconds: 250),
                                  curve: Curves.easeInOut,
                                  height: 4.0,
                                  decoration: BoxDecoration(
                                    color: isPassedOrCurrent
                                        ? AppColors.secondaryDark
                                        : Colors.white.withValues(alpha: 0.25),
                                    borderRadius: BorderRadius.circular(2.0),
                                  ),
                                ),
                              ),
                            );
                          }),
                        ),
                        const SizedBox(height: AppDimensions.space8),
                        // Top Navigation Header with Skip Button
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const SizedBox(width: 48.0),
                            // Skip Action (Acts 1–3, hidden on Act 4)
                            AnimatedOpacity(
                              opacity: _currentPage < 3 ? 1.0 : 0.0,
                              duration: const Duration(milliseconds: 200),
                              child: IgnorePointer(
                                ignoring: _currentPage >= 3,
                                child: TextButton(
                                  key: const Key('onboarding_skip_button'),
                                  onPressed: () {
                                    HapticFeedback.lightImpact();
                                    _completeOnboarding();
                                  },
                                  style: TextButton.styleFrom(
                                    foregroundColor: Colors.white70,
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: AppDimensions.space12,
                                      vertical: AppDimensions.space8,
                                    ),
                                  ),
                                  child: Text(
                                    l10n.onboardingSkip,
                                    style: AppTextStyles.labelLarge.copyWith(
                                      color: Colors.white,
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),

          // -------------------------------------------------------------------
          // Fixed Bottom Controls: Pagination Dots + Next / Get Started CTA
          // -------------------------------------------------------------------
          Positioned(
            left: 0,
            right: 0,
            bottom: 0,
            child: SafeArea(
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: AppDimensions.space24,
                  vertical: AppDimensions.space16,
                ),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 440.0),
                    child: AnimatedCrossFade(
                      duration: const Duration(milliseconds: 300),
                      firstCurve: Curves.easeInOut,
                      secondCurve: Curves.easeInOut,
                      crossFadeState: _currentPage < 3
                          ? CrossFadeState.showFirst
                          : CrossFadeState.showSecond,
                      // Acts 1–3: Dot Indicators and Round Next Button
                      firstChild: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          // Page Dots Indicator
                          Row(
                            children: List.generate(
                              _backgroundAssets.length,
                              (index) => AnimatedContainer(
                                duration: const Duration(milliseconds: 200),
                                margin: const EdgeInsets.only(right: 6.0),
                                width: _currentPage == index ? 20.0 : 6.0,
                                height: 6.0,
                                decoration: BoxDecoration(
                                  color: _currentPage == index
                                      ? AppColors.secondaryDark
                                      : Colors.white.withValues(alpha: 0.3),
                                  borderRadius: BorderRadius.circular(3.0),
                                ),
                              ),
                            ),
                          ),

                          // Elevated Round Next Button
                          IconButton.filled(
                            key: const Key('onboarding_next_button'),
                            onPressed: _nextPage,
                            style: IconButton.styleFrom(
                              backgroundColor: AppColors.secondaryDark,
                              foregroundColor: AppColors.backgroundDark,
                              minimumSize: const Size(52.0, 52.0),
                              shape: const CircleBorder(),
                              elevation: 2,
                            ),
                            icon: const Icon(
                              Icons.arrow_forward_rounded,
                              size: 24.0,
                            ),
                            tooltip: l10n.onboardingNext,
                          ),
                        ],
                      ),
                      // Act 4: Full-width "Get Started" CTA Button
                      secondChild: SizedBox(
                        width: double.infinity,
                        height: AppDimensions.buttonHeightLarge,
                        child: ElevatedButton(
                          key: const Key('onboarding_get_started_button'),
                          onPressed: () {
                            HapticFeedback.lightImpact();
                            _completeOnboarding();
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.secondaryDark,
                            foregroundColor: AppColors.backgroundDark,
                            elevation: 4,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12.0),
                            ),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text(
                                l10n.onboardingGetStarted,
                                style: AppTextStyles.labelLarge.copyWith(
                                  color: AppColors.backgroundDark,
                                  fontWeight: FontWeight.w700,
                                  fontSize: 16.0,
                                ),
                              ),
                              const SizedBox(width: AppDimensions.space8),
                              const Icon(
                                Icons.arrow_forward_rounded,
                                color: AppColors.backgroundDark,
                                size: 20.0,
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
