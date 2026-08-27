import 'package:flutter/material.dart';

import 'app_colors.dart';

abstract final class AppTextStyles {
  static const String fontFamily = 'Poppins';

/// Display Large (32 / 700) - Hero numbers, major achievement headers
  static const TextStyle displayLarge = TextStyle(
    fontFamily: fontFamily,
    fontSize: 32.0,
    fontWeight: FontWeight.w700,
    height: 1.25,
    color: AppColors.textPrimary,
    letterSpacing: -0.5,
  );

  /// Display Medium (28 / 700) - Large score boards, prominent stat displays
  static const TextStyle displayMedium = TextStyle(
    fontFamily: fontFamily,
    fontSize: 28.0,
    fontWeight: FontWeight.w700,
    height: 1.25,
    color: AppColors.textPrimary,
    letterSpacing: -0.25,
  );

  /// Headline Large (24 / 700) - Main screen headers, modal titles
  static const TextStyle headlineLarge = TextStyle(
    fontFamily: fontFamily,
    fontSize: 24.0,
    fontWeight: FontWeight.w700,
    height: 1.30,
    color: AppColors.textPrimary,
    letterSpacing: -0.2,
  );

  /// Headline Medium (20 / 700) - Card group headers, secondary screen headers
  static const TextStyle headlineMedium = TextStyle(
    fontFamily: fontFamily,
    fontSize: 20.0,
    fontWeight: FontWeight.w700,
    height: 1.30,
    color: AppColors.textPrimary,
  );

  /// Title Large (20 / 600) - Primary card titles, dialog titles
  static const TextStyle titleLarge = TextStyle(
    fontFamily: fontFamily,
    fontSize: 20.0,
    fontWeight: FontWeight.w600,
    height: 1.35,
    color: AppColors.textPrimary,
  );

  /// Title Medium (18 / 600) - Subsection headings, question headers
  static const TextStyle titleMedium = TextStyle(
    fontFamily: fontFamily,
    fontSize: 18.0,
    fontWeight: FontWeight.w600,
    height: 1.35,
    color: AppColors.textPrimary,
  );

  /// Title Small (16 / 600) - Compact card titles, list item titles
  static const TextStyle titleSmall = TextStyle(
    fontFamily: fontFamily,
    fontSize: 16.0,
    fontWeight: FontWeight.w600,
    height: 1.40,
    color: AppColors.textPrimary,
  );

  /// Body Large (16 / 400) - Primary reading content, lesson narrative
  static const TextStyle bodyLarge = TextStyle(
    fontFamily: fontFamily,
    fontSize: 16.0,
    fontWeight: FontWeight.w400,
    height: 1.50,
    color: AppColors.textPrimary,
  );

  /// Body Medium (14 / 400) - Default body, explanations, descriptions
  static const TextStyle bodyMedium = TextStyle(
    fontFamily: fontFamily,
    fontSize: 14.0,
    fontWeight: FontWeight.w400,
    height: 1.45,
    color: AppColors.textSecondary,
  );

  /// Body Small (12 / 400) - Secondary body text, fine details
  static const TextStyle bodySmall = TextStyle(
    fontFamily: fontFamily,
    fontSize: 12.0,
    fontWeight: FontWeight.w400,
    height: 1.40,
    color: AppColors.textSecondary,
  );

  /// Label Large (14 / 600) - Standard button text, tabs, actionable items
  static const TextStyle labelLarge = TextStyle(
    fontFamily: fontFamily,
    fontSize: 14.0,
    fontWeight: FontWeight.w600,
    height: 1.20,
    color: AppColors.textPrimary,
    letterSpacing: 0.1,
  );

  /// Label Medium (12 / 600) - Compact buttons, chip labels
  static const TextStyle labelMedium = TextStyle(
    fontFamily: fontFamily,
    fontSize: 12.0,
    fontWeight: FontWeight.w600,
    height: 1.20,
    color: AppColors.textPrimary,
  );

  /// Label Small (10 / 600) - Micro labels, bottom nav labels
  static const TextStyle labelSmall = TextStyle(
    fontFamily: fontFamily,
    fontSize: 10.0,
    fontWeight: FontWeight.w600,
    height: 1.20,
    color: AppColors.textSecondary,
  );

  /// Caption (12 / 400) - Timestamps, helper text, footnote captions
  static const TextStyle caption = TextStyle(
    fontFamily: fontFamily,
    fontSize: 12.0,
    fontWeight: FontWeight.w400,
    height: 1.35,
    color: AppColors.textMuted,
  );

  /// Badge (12 / 600) - Pill status tags, difficulty chips, coin counts
  static const TextStyle badge = TextStyle(
    fontFamily: fontFamily,
    fontSize: 12.0,
    fontWeight: FontWeight.w600,
    height: 1.20,
    color: AppColors.textPrimary,
    letterSpacing: 0.2,
  );
}
