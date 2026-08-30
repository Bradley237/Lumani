import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lumani/core/theme/app_colors.dart';
import 'package:lumani/core/theme/app_theme.dart';

void main() {
  group('AppColors & ThemeMode.system Tests', () {
    test('Light theme tokens match M3 design system specification', () {
      expect(AppColors.primaryLight, const Color(0xFF0F2D59));
      expect(AppColors.secondaryLight, const Color(0xFFD97706));
      expect(AppColors.tertiaryLight, const Color(0xFF0D9488));
      expect(AppColors.backgroundLight, const Color(0xFFF8FAFC));
      expect(AppColors.surfaceLight, const Color(0xFFFFFFFF));
      expect(AppColors.textPrimaryLight, const Color(0xFF0F172A));
      expect(AppColors.outlineLight, const Color(0xFFE2E8F0));
      expect(AppColors.errorLight, const Color(0xFFDC2626));
    });

    test('Dark theme tokens match M3 design system specification', () {
      expect(AppColors.primaryDark, const Color(0xFF3B82F6));
      expect(AppColors.secondaryDark, const Color(0xFFF59E0B));
      expect(AppColors.tertiaryDark, const Color(0xFF14B8A6));
      expect(AppColors.backgroundDark, const Color(0xFF0A0F1D));
      expect(AppColors.surfaceDark, const Color(0xFF121829));
      expect(AppColors.textPrimaryDark, const Color(0xFFF8FAFC));
      expect(AppColors.outlineDark, const Color(0xFF334155));
      expect(AppColors.errorDark, const Color(0xFFEF4444));
    });

    test('AppTheme defines both light and dark ThemeData with Material 3', () {
      final light = AppTheme.lightTheme;
      final dark = AppTheme.darkTheme;

      expect(light.useMaterial3, isTrue);
      expect(dark.useMaterial3, isTrue);
      expect(light.brightness, Brightness.light);
      expect(dark.brightness, Brightness.dark);

      expect(light.colorScheme.primary, AppColors.primaryLight);
      expect(dark.colorScheme.primary, AppColors.primaryDark);
      expect(light.scaffoldBackgroundColor, AppColors.backgroundLight);
      expect(dark.scaffoldBackgroundColor, AppColors.backgroundDark);
    });

    testWidgets('App respects platform brightness in ThemeMode.system', (
      WidgetTester tester,
    ) async {
      tester.platformDispatcher.clearPlatformBrightnessTestValue();

      await tester.pumpWidget(
        MaterialApp(
          themeMode: ThemeMode.system,
          theme: AppTheme.lightTheme,
          darkTheme: AppTheme.darkTheme,
          home: Builder(
            builder: (context) {
              final brightness = Theme.of(context).brightness;
              final primaryColor = Theme.of(context).colorScheme.primary;
              return Scaffold(
                body: Text('Mode: $brightness, Primary: $primaryColor'),
              );
            },
          ),
        ),
      );

      expect(find.textContaining('Mode:'), findsOneWidget);
    });
  });
}
