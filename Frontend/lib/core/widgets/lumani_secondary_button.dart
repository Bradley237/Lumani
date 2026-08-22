import 'package:flutter/material.dart';
import '../theme/app_colors.dart';
import '../theme/app_dimensions.dart';
import '../theme/app_radius.dart';
import '../theme/app_text_styles.dart';

class LumaniSecondaryButton extends StatefulWidget {
  final String text;
  final VoidCallback? onPressed;
  final bool isLoading;
  final Widget? icon;
  final bool isEnabled;
  final Color? borderColor;
  final Color? textColor;

  const LumaniSecondaryButton({
    super.key,
    required this.text,
    this.onPressed,
    this.isLoading = false,
    this.icon,
    this.isEnabled = true,
    this.borderColor,
    this.textColor,
  });

  @override
  State<LumaniSecondaryButton> createState() => _LumaniSecondaryButtonState();
}

class _LumaniSecondaryButtonState extends State<LumaniSecondaryButton> {
  bool _isPressed = false;

  bool get _canInteract => widget.isEnabled && !widget.isLoading && widget.onPressed != null;

  void _handleTapDown(TapDownDetails details) {
    if (_canInteract) {
      setState(() => _isPressed = true);
    }
  }

  void _handleTapUp(TapUpDetails details) {
    if (_isPressed) {
      setState(() => _isPressed = false);
    }
  }

  void _handleTapCancel() {
    if (_isPressed) {
      setState(() => _isPressed = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final scale = _isPressed ? AppDimensions.buttonScalePressed : 1.0;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final defaultBorderColor = isDark ? AppColors.glassEdge : AppColors.textSecondaryLight.withValues(alpha: 0.3);
    final effectiveBorderColor = widget.borderColor ?? defaultBorderColor;
    final effectiveTextColor = widget.textColor ?? (isDark ? AppColors.textPrimaryDark : AppColors.textPrimaryLight);

    return AnimatedScale(
      scale: scale,
      duration: const Duration(milliseconds: 100),
      child: GestureDetector(
        onTapDown: _handleTapDown,
        onTapUp: _handleTapUp,
        onTapCancel: _handleTapCancel,
        onTap: _canInteract ? widget.onPressed : null,
        child: Container(
          height: AppDimensions.buttonHeight,
          width: double.infinity,
          decoration: BoxDecoration(
            color: AppColors.transparent,
            borderRadius: AppRadius.borderRadius16,
            border: Border.all(
              color: widget.isEnabled ? effectiveBorderColor : effectiveBorderColor.withValues(alpha: 0.4),
              width: AppDimensions.borderWidthFocused,
            ),
          ),
          alignment: Alignment.center,
          child: widget.isLoading
              ? SizedBox(
                  height: AppDimensions.iconMedium,
                  width: AppDimensions.iconMedium,
                  child: CircularProgressIndicator(
                    strokeWidth: AppDimensions.borderWidthThick,
                    valueColor: AlwaysStoppedAnimation<Color>(effectiveTextColor),
                  ),
                )
              : Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (widget.icon != null) ...[
                      widget.icon!,
                      const SizedBox(width: AppDimensions.space8),
                    ],
                    Text(
                      widget.text,
                      style: AppTextStyles.bodyPrimary.copyWith(
                        color: effectiveTextColor,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
        ),
      ),
    );
  }
}
