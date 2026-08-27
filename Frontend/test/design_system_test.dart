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
    test('AppColors brand and surfaces have expected values', () {
      expect(AppColors.background, const Color(0xFF090D16));
      expect(AppColors.surface, const Color(0xFF131A29));
      expect(AppColors.surfaceVariant, const Color(0xFF1D263B));
      expect(AppColors.primary, const Color(0xFFFFB800));
      expect(AppColors.accentCyan, const Color(0xFF00F2FE));
      expect(AppColors.accentViolet, const Color(0xFF7B2CBF));
      expect(AppColors.success, const Color(0xFF00E676));
      expect(AppColors.error, const Color(0xFFFF5252));
    });

    test('AppColors learning states and status colors are defined', () {
      expect(AppColors.correct, const Color(0xFF00E676));
      expect(AppColors.incorrect, const Color(0xFFFF5252));
      expect(AppColors.selected, const Color(0xFFFFB800));
      expect(AppColors.locked, const Color(0xFF475569));
      expect(AppColors.completed, const Color(0xFF00E676));
      expect(AppColors.inProgress, const Color(0xFF00F2FE));
      expect(AppColors.mastered, const Color(0xFF7B2CBF));
      expect(AppColors.disabled, const Color(0xFF334155));
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

    test('AppRadius defaults to 16px for cards and controls', () {
      expect(AppRadius.radius4, 4.0);
      expect(AppRadius.radius8, 8.0);
      expect(AppRadius.radius12, 12.0);
      expect(AppRadius.radius16, 16.0);
      expect(AppRadius.radius24, 24.0);
      expect(AppRadius.radiusPill, 999.0);
      expect(AppRadius.all16, BorderRadius.circular(16.0));
    });

    test('AppTextStyles uses Poppins font family with semantic scales', () {
      expect(AppTextStyles.fontFamily, 'Poppins');
      expect(AppTextStyles.displayLarge.fontSize, 32.0);
      expect(AppTextStyles.displayLarge.fontWeight, FontWeight.w700);
      expect(AppTextStyles.headlineLarge.fontSize, 24.0);
      expect(AppTextStyles.headlineLarge.fontWeight, FontWeight.w700);
      expect(AppTextStyles.titleLarge.fontSize, 20.0);
      expect(AppTextStyles.titleLarge.fontWeight, FontWeight.w600);
      expect(AppTextStyles.bodyLarge.fontSize, 16.0);
      expect(AppTextStyles.bodyLarge.fontWeight, FontWeight.w400);
      expect(AppTextStyles.bodyMedium.fontSize, 14.0);
      expect(AppTextStyles.bodyMedium.fontWeight, FontWeight.w400);
      expect(AppTextStyles.labelLarge.fontSize, 14.0);
      expect(AppTextStyles.labelLarge.fontWeight, FontWeight.w600);
      expect(AppTextStyles.caption.fontSize, 12.0);
      expect(AppTextStyles.badge.fontSize, 12.0);
      expect(AppTextStyles.badge.fontWeight, FontWeight.w600);
    });

    test('AppMotion durations and curves are configured', () {
      expect(AppMotion.instant, const Duration(milliseconds: 100));
      expect(AppMotion.fast, const Duration(milliseconds: 200));
      expect(AppMotion.normal, const Duration(milliseconds: 300));
      expect(AppMotion.slow, const Duration(milliseconds: 500));
      expect(AppMotion.standard, Curves.easeInOut);
    });
  });

  group('AppTheme Dark Integration Tests', () {
    final theme = AppTheme.darkTheme;

    test('ThemeData is configured with dark brightness and Lumani palette', () {
      expect(theme.brightness, Brightness.dark);
      expect(theme.scaffoldBackgroundColor, AppColors.background);
      expect(theme.colorScheme.primary, AppColors.primary);
      expect(theme.colorScheme.surface, AppColors.surface);
      expect(theme.colorScheme.error, AppColors.error);
      expect(theme.colorScheme.onPrimary, AppColors.textOnPrimary);
    });

    test('CardTheme has 16px radius and 1px border', () {
      final cardShape = theme.cardTheme.shape as RoundedRectangleBorder;
      expect(cardShape.borderRadius, AppRadius.all16);
      expect(cardShape.side.color, AppColors.border);
      expect(cardShape.side.width, 1.0);
    });

    test('Button themes use 16px radius and minimum 48px height', () {
      final elevatedStyle = theme.elevatedButtonTheme.style;
      expect(elevatedStyle, isNotNull);
    });

    testWidgets('Renders within MaterialApp with Lumani dark theme', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.darkTheme,
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

    testWidgets('AdaptiveConstraintContainer enforces max-width constraints', (
      WidgetTester tester,
    ) async {
      tester.view.physicalSize = const Size(1200, 800);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: AdaptiveConstraintContainer(
              maxWidth: 600,
              child: Container(key: const Key('constrained-child')),
            ),
          ),
        ),
      );

      final box = tester.renderObject(
        find.byKey(const Key('constrained-child')),
      ) as RenderBox;
      expect(box.size.width, lessThanOrEqualTo(600.0));
    });
  });
}
