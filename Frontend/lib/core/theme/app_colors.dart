import 'package:flutter/material.dart';

abstract final class AppColors {
  AppColors._();

  // Dark Theme Base
  static const Color background = Color(0xFF090D16);
  static const Color surface = Color(0xFF131A29);
  static const Color surfaceVariant = Color(0xFF1D263B);
  static const Color glassEdge = Color(0x1FFFFFFF);

  // Light Theme Base
  static const Color backgroundLight = Color(0xFFF8FAFC);
  static const Color surfaceLight = Color(0xFFFFFFFF);
  static const Color surfaceVariantLight = Color(0xFFF1F5F9);
  static const Color glassEdgeLight = Color(0x0F000000);

  // Brand / Accents
  static const Color electricCyan = Color(0xFF00F2FE);
  static const Color royalViolet = Color(0xFF7B2CBF);
  static const Color cameroonGold = Color(0xFFFFB800);
  static const Color vibrantEmerald = Color(0xFF00E676);
  static const Color coralRed = Color(0xFFFF5252);

  // Text Colors
  static const Color textPrimaryDark = Color(0xFFFFFFFF);
  static const Color textSecondaryDark = Color(0x99FFFFFF);
  static const Color textPrimaryLight = Color(0xFF0F172A);
  static const Color textSecondaryLight = Color(0xFF64748B);
  static const Color buttonTextOnAmber = Color(0xFF090D16);

  // Widget Helpers
  static const Color indicatorInactiveDark = Color(0x33FFFFFF);
  static const Color indicatorInactiveLight = Color(0x33000000);
  static const Color glowCenterAmber = Color(0x33FFB800);
  static const Color transparent = Color(0x00000000);
}


