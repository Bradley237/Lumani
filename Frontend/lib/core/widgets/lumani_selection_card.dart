import 'package:flutter/material.dart';
import '../theme/app_colors.dart';
import '../theme/app_dimensions.dart';
import '../theme/app_radius.dart';
import '../theme/app_text_styles.dart';

class LumaniSelectionCard extends StatelessWidget {
  final String title;
  final String? subtitle;
  final Widget? icon;
  final Color? accentColor;
  final bool isSelected;
  final VoidCallback? onTap;

  const LumaniSelectionCard({
    super.key,
    required this.title,
    this.subtitle,
    this.icon,
    this.accentColor,
    required this.isSelected,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final effectiveAccent = accentColor ?? AppColors.cameroonGold;

    final backgroundColor = isSelected
        ? effectiveAccent.withValues(alpha: 0.12)
        : (isDark ? AppColors.surface : AppColors.surfaceLight);

    final borderColor = isSelected
        ? effectiveAccent
        : (isDark ? AppColors.glassEdge : AppColors.textSecondaryLight.withValues(alpha: 0.2));

    final titleStyle = isDark ? AppTextStyles.cardTitle : AppTextStyles.cardTitleLight;
    final subtitleStyle = isDark ? AppTextStyles.bodySecondary : AppTextStyles.bodySecondaryLight;

    return InkWell(
      onTap: onTap,
      borderRadius: AppRadius.borderRadius16,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.all(AppDimensions.space16),
        decoration: BoxDecoration(
          color: backgroundColor,
          borderRadius: AppRadius.borderRadius16,
          border: Border.all(
            color: borderColor,
            width: isSelected ? AppDimensions.borderWidthThick : AppDimensions.borderWidth,
          ),
        ),
        child: Row(
          children: [
            if (icon != null) ...[
              Container(
                padding: const EdgeInsets.all(AppDimensions.space12),
                decoration: BoxDecoration(
                  color: isSelected
                      ? effectiveAccent.withValues(alpha: 0.2)
                      : (isDark ? AppColors.surfaceVariant : AppColors.surfaceVariantLight),
                  borderRadius: AppRadius.borderRadius12,
                ),
                child: icon,
              ),
              const SizedBox(width: AppDimensions.space16),
            ],
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    title,
                    style: titleStyle.copyWith(
                      fontWeight: isSelected ? FontWeight.w700 : FontWeight.w600,
                    ),
                  ),
                  if (subtitle != null) ...[
                    const SizedBox(height: AppDimensions.space4),
                    Text(
                      subtitle!,
                      style: subtitleStyle,
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(width: AppDimensions.space12),
            Container(
              width: AppDimensions.iconMedium,
              height: AppDimensions.iconMedium,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: isSelected ? effectiveAccent : AppColors.transparent,
                border: Border.all(
                  color: isSelected ? effectiveAccent : (isDark ? AppColors.glassEdge : AppColors.textSecondaryLight.withValues(alpha: 0.4)),
                  width: AppDimensions.borderWidthFocused,
                ),
              ),
              child: isSelected
                  ? const Icon(
                      Icons.check,
                      size: AppDimensions.iconSmall,
                      color: AppColors.buttonTextOnAmber,
                    )
                  : null,
            ),
          ],
        ),
      ),
    );
  }
}
