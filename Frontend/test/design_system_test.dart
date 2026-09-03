import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lumani/core/responsive/responsive_utils.dart';
import 'package:lumani/core/theme/app_colors.dart';
import 'package:lumani/core/theme/app_dimensions.dart';
import 'package:lumani/core/theme/app_motion.dart';
import 'package:lumani/core/theme/app_radius.dart';
import 'package:lumani/core/theme/app_text_styles.dart';
import 'package:lumani/core/theme/app_theme.dart';

void main() {
  group('Lumani Design Tokens Verification', () {
    test('AppColors brand light and dark tokens have expected values', () {
      expect(AppColors.primaryLight, const Color(0xFF0F2D59));
      expect(AppColors.primaryDark, const Color(0xFF3B82F6));
      expect(AppColors.secondaryLight, const Color(0xFFD97706));
      expect(AppColors.secondaryDark, const Color(0xFFFFB800));
      expect(AppColors.tertiaryLight, const Color(0xFF0D9488));
      expect(AppColors.tertiaryDark, const Color(0xFF14B8A6));
      expect(AppColors.backgroundLight, const Color(0xFFF8FAFC));
      expect(AppColors.backgroundDark, const Color(0xFF090D16));
    });

    test('AppDimensions follow 4-point spacing scale', () {
      expect(AppDimensions.space4, 4.0);
      expect(AppDimensions.space8, 8.0);
      expect(AppDimensions.space12, 12.0);
      expect(AppDimensions.space16, 16.0);
      expect(AppDimensions.space20, 20.0);
      expect(AppDimensions.space24, 24.0);
      expect(AppDimensions.space32, 32.0);
      expect(AppDimensions.space40, 40.0);
      expect(AppDimensions.space48, 48.0);
      expect(AppDimensions.space64, 64.0);
      expect(AppDimensions.minTouchTarget, 48.0);
    });

    test('AppRadius definitions for cards and controls', () {
      expect(AppRadius.radius4, 4.0);
      expect(AppRadius.radius8, 8.0);
      expect(AppRadius.radius12, 12.0);
      expect(AppRadius.radius16, 16.0);
      expect(AppRadius.radius24, 24.0);
      expect(AppRadius.radiusPill, 999.0);
    });

    test('AppTextStyles uses Poppins font family with semantic scales', () {
      expect(AppTextStyles.fontFamily, 'Poppins');
      expect(AppTextStyles.displayLarge.fontSize, 32.0);
      expect(AppTextStyles.headlineLarge.fontSize, 24.0);
      expect(AppTextStyles.titleLarge.fontSize, 20.0);
      expect(AppTextStyles.bodyLarge.fontSize, 16.0);
      expect(AppTextStyles.bodyMedium.fontSize, 14.0);
      expect(AppTextStyles.labelLarge.fontSize, 14.0);
    });

    test('AppMotion durations and curves are configured', () {
      expect(AppMotion.instant, const Duration(milliseconds: 100));
      expect(AppMotion.fast, const Duration(milliseconds: 200));
      expect(AppMotion.normal, const Duration(milliseconds: 300));
      expect(AppMotion.slow, const Duration(milliseconds: 500));
      expect(AppMotion.standard, Curves.easeInOut);
    });
  });

  group('AppTheme Integration Tests', () {
    final lightTheme = AppTheme.lightTheme;
    final darkTheme = AppTheme.darkTheme;

    test('ThemeData is configured for light and dark modes', () {
      expect(lightTheme.brightness, Brightness.light);
      expect(darkTheme.brightness, Brightness.dark);
      expect(lightTheme.scaffoldBackgroundColor, AppColors.backgroundLight);
      expect(darkTheme.scaffoldBackgroundColor, AppColors.backgroundDark);
      expect(lightTheme.colorScheme.primary, AppColors.primaryLight);
      expect(darkTheme.colorScheme.primary, AppColors.primaryDark);
    });

    testWidgets('Renders within MaterialApp with Lumani light theme', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.lightTheme,
          home: Scaffold(
            appBar: AppBar(title: const Text('Theme Test')),
            body: Center(
              child: Card(
                child: Padding(
                  padding: AppDimensions.padding16,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text('Headline', style: AppTextStyles.headlineLarge),
                      const SizedBox(height: AppDimensions.space8),
                      Text('Body', style: AppTextStyles.bodyMedium),
                      const SizedBox(height: AppDimensions.space16),
                      ElevatedButton(
                        onPressed: () {},
                        child: const Text('Action'),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      );

      expect(find.text('Theme Test'), findsOneWidget);
      expect(find.text('Headline'), findsOneWidget);
      expect(find.text('Body'), findsOneWidget);
      expect(find.text('Action'), findsOneWidget);
    });
  });

  group('Responsive Utilities Tests', () {
    testWidgets('Context responsive extensions detect compact vs tablet', (
      WidgetTester tester,
    ) async {
      tester.view.physicalSize = const Size(400, 800);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);

      await tester.pumpWidget(
        MaterialApp(
          home: Builder(
            builder: (context) {
              expect(context.isCompact, isTrue);
              expect(context.isMobile, isTrue);
              expect(context.isTablet, isFalse);
              expect(
                context.responsiveValue(compact: 16.0, medium: 24.0),
                16.0,
              );
              return const SizedBox();
            },
          ),
        ),
      );
    });
  });
}
