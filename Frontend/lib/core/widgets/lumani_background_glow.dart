import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

class LumaniBackgroundGlow extends StatelessWidget {
  final Widget? child;
  final Color? glowColor;
  final AlignmentGeometry alignment;
  final double radius;

  const LumaniBackgroundGlow({
    super.key,
    this.child,
    this.glowColor,
    this.alignment = Alignment.topCenter,
    this.radius = 0.8,
  });

  @override
  Widget build(BuildContext context) {
    final effectiveGlow = glowColor ?? AppColors.glowCenterAmber;

    return Container(
      decoration: BoxDecoration(
        gradient: RadialGradient(
          center: alignment as Alignment,
          radius: radius,
          colors: [
            effectiveGlow,
            AppColors.transparent,
          ],
          stops: const [0.0, 1.0],
        ),
      ),
      child: child,
    );
  }
}
