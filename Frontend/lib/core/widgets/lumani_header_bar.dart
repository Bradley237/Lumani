import 'package:flutter/material.dart';
import '../theme/app_colors.dart';
import '../theme/app_dimensions.dart';
import '../theme/app_radius.dart';
import '../theme/app_text_styles.dart';

class LumaniHeaderBar extends StatelessWidget implements PreferredSizeWidget {
  final String? title;
  final bool showBackButton;
  final VoidCallback? onBack;
  final String? actionLabel;
  final VoidCallback? onAction;
  final Widget? actionWidget;

  const LumaniHeaderBar({
    super.key,
    this.title,
    this.showBackButton = true,
    this.onBack,
    this.actionLabel,
    this.onAction,
    this.actionWidget,
  });

  @override
  Size get preferredSize => const Size.fromHeight(AppDimensions.headerHeight);

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textColor = isDark ? AppColors.textPrimaryDark : AppColors.textPrimaryLight;
    final canPop = Navigator.of(context).canPop();

    return SafeArea(
      bottom: false,
      child: Container(
        height: AppDimensions.headerHeight,
        padding: const EdgeInsets.symmetric(horizontal: AppDimensions.space16),
        child: Row(
          children: [
            if (showBackButton)
              IconButton(
                icon: const Icon(Icons.arrow_back_ios_new_rounded),
                iconSize: AppDimensions.iconMedium,
                color: textColor,
                onPressed: onBack ?? (canPop ? () => Navigator.of(context).maybePop() : null),
                style: IconButton.styleFrom(
                  shape: const RoundedRectangleBorder(borderRadius: AppRadius.borderRadius12),
                  backgroundColor: isDark ? AppColors.surfaceVariant : AppColors.surfaceVariantLight,
                ),
              )
            else
              const SizedBox(width: AppDimensions.space48),
            Expanded(
              child: title != null
                  ? Text(
                      title!,
                      textAlign: TextAlign.center,
                      style: (isDark ? AppTextStyles.cardTitle : AppTextStyles.cardTitleLight).copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    )
                  : const SizedBox.shrink(),
            ),
            if (actionWidget != null)
              actionWidget!
            else if (actionLabel != null)
              TextButton(
                onPressed: onAction,
                child: Text(
                  actionLabel!,
                  style: AppTextStyles.bodyPrimary.copyWith(
                    color: AppColors.cameroonGold,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              )
            else
              const SizedBox(width: AppDimensions.space48),
          ],
        ),
      ),
    );
  }
}
