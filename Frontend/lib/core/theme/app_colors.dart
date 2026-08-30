import 'package:flutter/material.dart';

/// Semantic M3 Color System Tokens for Lumani
abstract final class AppColors {
  // --- Light Palette ---
  static const Color primaryLight = Color(0xFF0F2D59);
  static const Color onPrimaryLight = Color(0xFFFFFFFF);
  static const Color secondaryLight = Color(0xFFD97706);
  static const Color onSecondaryLight = Color(0xFFFFFFFF);
  static const Color tertiaryLight = Color(0xFF0D9488);
  static const Color onTertiaryLight = Color(0xFFFFFFFF);
  static const Color backgroundLight = Color(0xFFF8FAFC);
  static const Color onBackgroundLight = Color(0xFF0F172A);
  static const Color surfaceLight = Color(0xFFFFFFFF);
  static const Color onSurfaceLight = Color(0xFF0F172A);
  static const Color surfaceElevatedLight = Color(0xFFF1F5F9);
  static const Color textPrimaryLight = Color(0xFF0F172A);
  static const Color textSecondaryLight = Color(0xFF64748B);
  static const Color outlineLight = Color(0xFFE2E8F0);
  static const Color errorLight = Color(0xFFDC2626);
  static const Color onErrorLight = Color(0xFFFFFFFF);

  // --- Dark Palette ---
  static const Color primaryDark = Color(0xFF3B82F6);
  static const Color onPrimaryDark = Color(0xFF0A0F1D);
  static const Color secondaryDark = Color(0xFFF59E0B);
  static const Color onSecondaryDark = Color(0xFF0A0F1D);
  static const Color tertiaryDark = Color(0xFF14B8A6);
  static const Color onTertiaryDark = Color(0xFF0A0F1D);
  static const Color backgroundDark = Color(0xFF0A0F1D);
  static const Color onBackgroundDark = Color(0xFFF8FAFC);
  static const Color surfaceDark = Color(0xFF121829);
  static const Color onSurfaceDark = Color(0xFFF8FAFC);
  static const Color surfaceElevatedDark = Color(0xFF1E2640);
  static const Color textPrimaryDark = Color(0xFFF8FAFC);
  static const Color textSecondaryDark = Color(0xFF94A3B8);
  static const Color outlineDark = Color(0xFF334155);
  static const Color errorDark = Color(0xFFEF4444);
  static const Color onErrorDark = Color(0xFF0A0F1D);

  // --- Legacy Compatibility Tokens ---
  static const Color primary = primaryDark;
  static const Color background = backgroundDark;
  static const Color surface = surfaceDark;
  static const Color surfaceElevated = surfaceElevatedDark;
  static const Color textPrimary = textPrimaryDark;
  static const Color textSecondary = textSecondaryDark;
  static const Color textMuted = Color(0xFF64748B);
  static const Color border = outlineDark;
  static const Color borderSubtle = Color(0xFF192231);
  static const Color borderFocused = primaryDark;
  static const Color accentCyan = Color(0xFF00F2FE);
  static const Color accentViolet = Color(0xFF7B2CBF);
  static const Color success = Color(0xFF00E676);
  static const Color warning = secondaryDark;
  static const Color error = errorDark;
  static const Color correct = Color(0xFF00E676);
  static const Color incorrect = errorDark;
  static const Color selected = secondaryDark;
  static const Color locked = Color(0xFF475569);
  static const Color completed = Color(0xFF00E676);
  static const Color inProgress = Color(0xFF00F2FE);
  static const Color disabled = Color(0xFF334155);
}
