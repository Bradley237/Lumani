import 'package:flutter/material.dart';
import '../theme/app_colors.dart';
import '../theme/app_dimensions.dart';
import '../theme/app_radius.dart';

class LumaniPageIndicator extends StatelessWidget {
  final int count;
  final int currentIndex;
  final Color? activeColor;
  final Color? inactiveColor;
  final ValueChanged<int>? onDotTap;

  const LumaniPageIndicator({
    super.key,
    required this.count,
    required this.currentIndex,
    this.activeColor,
    this.inactiveColor,
    this.onDotTap,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final effectiveActiveColor = activeColor ?? AppColors.cameroonGold;
    final effectiveInactiveColor = inactiveColor ?? (isDark ? AppColors.indicatorInactiveDark : AppColors.indicatorInactiveLight);

    return Row(
      mainAxisSize: MainAxisSize.min,
      mainAxisAlignment: MainAxisAlignment.center,
      children: List.generate(count, (index) {
        final isActive = index == currentIndex;

        return GestureDetector(
          onTap: onDotTap != null ? () => onDotTap!(index) : null,
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 250),
            curve: Curves.easeInOut,
            margin: const EdgeInsets.symmetric(horizontal: AppDimensions.space4),
            height: AppDimensions.indicatorHeight,
            width: isActive ? AppDimensions.indicatorActiveWidth : AppDimensions.indicatorInactiveSize,
            decoration: BoxDecoration(
              color: isActive ? effectiveActiveColor : effectiveInactiveColor,
              borderRadius: AppRadius.borderRadius999,
            ),
          ),
        );
      }),
    );
  }
}
