import 'package:flutter/material.dart';
import '../theme/app_colors.dart';
import '../theme/app_dimensions.dart';
import '../theme/app_radius.dart';
import '../theme/app_text_styles.dart';

class LumaniPrimaryButton extends StatefulWidget {
  final String text;
  final VoidCallback? onPressed;
  final bool isLoading;
  final Widget? icon;
  final bool isEnabled;

  const LumaniPrimaryButton({
    super.key,
    required this.text,
    this.onPressed,
    this.isLoading = false,
    this.icon,
    this.isEnabled = true,
  });

  @override
  State<LumaniPrimaryButton> createState() => _LumaniPrimaryButtonState();
}

class _LumaniPrimaryButtonState extends State<LumaniPrimaryButton> {
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
            color: widget.isEnabled ? AppColors.cameroonGold : AppColors.cameroonGold.withValues(alpha: 0.5),
            borderRadius: AppRadius.borderRadius16,
          ),
          alignment: Alignment.center,
          child: widget.isLoading
              ? const SizedBox(
                  height: AppDimensions.iconMedium,
                  width: AppDimensions.iconMedium,
                  child: CircularProgressIndicator(
                    strokeWidth: AppDimensions.borderWidthThick,
                    valueColor: AlwaysStoppedAnimation<Color>(AppColors.buttonTextOnAmber),
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
                        color: AppColors.buttonTextOnAmber,
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
