import 'package:flutter/material.dart';

import '../theme/app_dimensions.dart';

/// Screen size breakpoints for adaptive mobile & tablet rendering.
abstract final class AppBreakpoints {
  /// Maximum width for compact / standard mobile phone screens
  static const double compact = 600.0;

  /// Maximum width for medium / tablet portrait screens
  static const double medium = 840.0;

  /// Maximum content width to maintain optimal reading line length on large screens / tablets
  static const double maxContentWidth = 720.0;
}

/// Responsive extensions on [BuildContext] to simplify responsive layouts and
/// avoid hardcoded device dimensions.
extension ResponsiveContext on BuildContext {
  /// Total width of the current window/screen
  double get screenWidth => MediaQuery.sizeOf(this).width;

  /// Total height of the current window/screen
  double get screenHeight => MediaQuery.sizeOf(this).height;

  /// Top safe area padding (e.g., status bar, notch)
  double get safePaddingTop => MediaQuery.paddingOf(this).top;

  /// Bottom safe area padding (e.g., home indicator bar)
  double get safePaddingBottom => MediaQuery.paddingOf(this).bottom;

  /// Whether the current screen is a compact phone screen (< 600px)
  bool get isCompact => screenWidth < AppBreakpoints.compact;

  /// Alias for [isCompact]
  bool get isMobile => isCompact;

  /// Whether the current screen is a medium tablet screen (600px - 840px)
  bool get isTablet =>
      screenWidth >= AppBreakpoints.compact &&
      screenWidth < AppBreakpoints.medium;

  /// Whether the screen is expanded (> 840px)
  bool get isExpanded => screenWidth >= AppBreakpoints.medium;

  /// Adaptive horizontal gutter padding for screens (16px on phone, 24px on tablet)
  double get horizontalGutter =>
      isCompact ? AppDimensions.space16 : AppDimensions.space24;

  /// Returns a responsive value based on the current screen size.
  T responsiveValue<T>({required T compact, T? medium, T? expanded}) {
    if (isExpanded && expanded != null) return expanded;
    if (isTablet && medium != null) return medium;
    return compact;
  }
}

/// A lightweight wrapper widget that constrains content to a readable maximum width
/// and centers it horizontally on large screens or tablets.
class AdaptiveConstraintContainer extends StatelessWidget {
  const AdaptiveConstraintContainer({
    super.key,
    required this.child,
    this.maxWidth = AppBreakpoints.maxContentWidth,
    this.padding,
    this.alignment = Alignment.topCenter,
  });

  final Widget child;
  final double maxWidth;
  final EdgeInsetsGeometry? padding;
  final AlignmentGeometry alignment;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: alignment,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: maxWidth),
        child: Padding(padding: padding ?? EdgeInsets.zero, child: child),
      ),
    );
  }
}
