import 'package:flutter/material.dart';
import '../theme/app_colors.dart';
import '../theme/app_dimensions.dart';
import '../theme/app_radius.dart';
import '../theme/app_text_styles.dart';

class LumaniSegmentedControl extends StatelessWidget {
  final List<String> segments;
  final int selectedIndex;
  final ValueChanged<int> onValueChanged;

  const LumaniSegmentedControl({
    super.key,
    required this.segments,
    required this.selectedIndex,
    required this.onValueChanged,
  }) : assert(segments.length >= 2, 'Segments list must contain at least 2 items');

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final backgroundColor = isDark ? AppColors.surface : AppColors.surfaceVariantLight;

    return Container(
      height: AppDimensions.buttonHeight,
      padding: const EdgeInsets.all(AppDimensions.space4),
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: AppRadius.borderRadius16,
        border: Border.all(
          color: isDark ? AppColors.glassEdge : AppColors.textSecondaryLight.withValues(alpha: 0.2),
          width: AppDimensions.borderWidth,
        ),
      ),
      child: Row(
        children: List.generate(segments.length, (index) {
          final isSelected = index == selectedIndex;

          return Expanded(
            child: GestureDetector(
              onTap: () => onValueChanged(index),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                curve: Curves.easeInOut,
                decoration: BoxDecoration(
                  color: isSelected ? AppColors.cameroonGold : AppColors.transparent,
                  borderRadius: AppRadius.borderRadius12,
                ),
                alignment: Alignment.center,
                child: Text(
                  segments[index],
                  style: (isDark ? AppTextStyles.bodyPrimary : AppTextStyles.bodyPrimaryLight).copyWith(
                    fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                    color: isSelected
                        ? AppColors.buttonTextOnAmber
                        : (isDark ? AppColors.textSecondaryDark : AppColors.textSecondaryLight),
                  ),
                ),
              ),
            ),
          );
        }),
      ),
    );
  }
}
