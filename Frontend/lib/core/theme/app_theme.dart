import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'app_colors.dart';
import 'app_dimensions.dart';
import 'app_text_styles.dart';

abstract final class AppTheme {
  /// Material 3 Corner Radii according to Design Specification:
  /// 12dp for standard cards, 10dp for primary buttons and input fields.
  static const BorderRadius buttonRadius = BorderRadius.all(
    Radius.circular(10.0),
  );
  static const BorderRadius cardRadius = BorderRadius.all(
    Radius.circular(12.0),
  );
  static const BorderRadius inputRadius = BorderRadius.all(
    Radius.circular(10.0),
  );

  /// Light Theme for Lumani
  static ThemeData get lightTheme {
    const colorScheme = ColorScheme(
      brightness: Brightness.light,
      primary: AppColors.primaryLight,
      onPrimary: AppColors.onPrimaryLight,
      secondary: AppColors.secondaryLight,
      onSecondary: AppColors.onSecondaryLight,
      tertiary: AppColors.tertiaryLight,
      onTertiary: AppColors.onTertiaryLight,
      error: AppColors.errorLight,
      onError: AppColors.onErrorLight,
      surface: AppColors.surfaceLight,
      onSurface: AppColors.onSurfaceLight,
      onSurfaceVariant: AppColors.textSecondaryLight,
      outline: AppColors.outlineLight,
      shadow: Colors.black12,
      scrim: Colors.black38,
    );

    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,
      fontFamily: AppTextStyles.fontFamily,
      scaffoldBackgroundColor: AppColors.backgroundLight,
      colorScheme: colorScheme,

      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.backgroundLight,
        foregroundColor: AppColors.textPrimaryLight,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: true,
        titleTextStyle: AppTextStyles.titleLarge,
        iconTheme: IconThemeData(
          color: AppColors.textPrimaryLight,
          size: AppDimensions.iconLg,
        ),
        systemOverlayStyle: SystemUiOverlayStyle(
          statusBarColor: Colors.transparent,
          statusBarIconBrightness: Brightness.dark,
          statusBarBrightness: Brightness.light,
        ),
      ),

      textTheme: _textTheme(
        AppColors.textPrimaryLight,
        AppColors.textSecondaryLight,
      ),

      cardTheme: const CardThemeData(
        color: AppColors.surfaceLight,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: cardRadius,
          side: BorderSide(color: AppColors.outlineLight, width: 1.0),
        ),
      ),

      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.primaryLight,
          foregroundColor: AppColors.onPrimaryLight,
          disabledBackgroundColor: AppColors.outlineLight,
          disabledForegroundColor: AppColors.textSecondaryLight,
          elevation: 0,
          minimumSize: const Size(
            AppDimensions.minTouchTarget,
            AppDimensions.buttonHeight,
          ),
          padding: const EdgeInsets.symmetric(
            horizontal: AppDimensions.space20,
            vertical: AppDimensions.space12,
          ),
          textStyle: AppTextStyles.labelLarge,
          shape: const RoundedRectangleBorder(borderRadius: buttonRadius),
        ),
      ),

      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.textPrimaryLight,
          disabledForegroundColor: AppColors.textSecondaryLight,
          elevation: 0,
          minimumSize: const Size(
            AppDimensions.minTouchTarget,
            AppDimensions.buttonHeight,
          ),
          side: const BorderSide(color: AppColors.outlineLight, width: 1.0),
          padding: const EdgeInsets.symmetric(
            horizontal: AppDimensions.space20,
            vertical: AppDimensions.space12,
          ),
          textStyle: AppTextStyles.labelLarge,
          shape: const RoundedRectangleBorder(borderRadius: buttonRadius),
        ),
      ),

      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.primaryLight,
          disabledForegroundColor: AppColors.textSecondaryLight,
          minimumSize: const Size(
            AppDimensions.minTouchTarget,
            AppDimensions.buttonHeight,
          ),
          padding: const EdgeInsets.symmetric(
            horizontal: AppDimensions.space16,
            vertical: AppDimensions.space8,
          ),
          textStyle: AppTextStyles.labelLarge,
          shape: const RoundedRectangleBorder(borderRadius: buttonRadius),
        ),
      ),

      inputDecorationTheme: const InputDecorationTheme(
        filled: true,
        fillColor: AppColors.surfaceLight,
        hintStyle: TextStyle(
          fontFamily: AppTextStyles.fontFamily,
          fontSize: 14.0,
          fontWeight: FontWeight.w400,
          color: AppColors.textSecondaryLight,
        ),
        labelStyle: TextStyle(
          fontFamily: AppTextStyles.fontFamily,
          fontSize: 14.0,
          fontWeight: FontWeight.w400,
          color: AppColors.textSecondaryLight,
        ),
        contentPadding: EdgeInsets.symmetric(
          horizontal: AppDimensions.space16,
          vertical: AppDimensions.space16,
        ),
        border: OutlineInputBorder(
          borderRadius: inputRadius,
          borderSide: BorderSide(color: AppColors.outlineLight, width: 1.0),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: inputRadius,
          borderSide: BorderSide(color: AppColors.outlineLight, width: 1.0),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: inputRadius,
          borderSide: BorderSide(color: AppColors.primaryLight, width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: inputRadius,
          borderSide: BorderSide(color: AppColors.errorLight, width: 1.0),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: inputRadius,
          borderSide: BorderSide(color: AppColors.errorLight, width: 1.5),
        ),
      ),
    );
  }

  /// Dark Theme for Lumani
  static ThemeData get darkTheme {
    const colorScheme = ColorScheme(
      brightness: Brightness.dark,
      primary: AppColors.primaryDark,
      onPrimary: AppColors.onPrimaryDark,
      secondary: AppColors.secondaryDark,
      onSecondary: AppColors.onSecondaryDark,
      tertiary: AppColors.tertiaryDark,
      onTertiary: AppColors.onTertiaryDark,
      error: AppColors.errorDark,
      onError: AppColors.onErrorDark,
      surface: AppColors.surfaceDark,
      onSurface: AppColors.onSurfaceDark,
      onSurfaceVariant: AppColors.textSecondaryDark,
      outline: AppColors.outlineDark,
      shadow: Colors.black,
      scrim: Colors.black54,
    );

    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      fontFamily: AppTextStyles.fontFamily,
      scaffoldBackgroundColor: AppColors.backgroundDark,
      colorScheme: colorScheme,

      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.backgroundDark,
        foregroundColor: AppColors.textPrimaryDark,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: true,
        titleTextStyle: AppTextStyles.titleLarge,
        iconTheme: IconThemeData(
          color: AppColors.textPrimaryDark,
          size: AppDimensions.iconLg,
        ),
        systemOverlayStyle: SystemUiOverlayStyle(
          statusBarColor: Colors.transparent,
          statusBarIconBrightness: Brightness.light,
          statusBarBrightness: Brightness.dark,
        ),
      ),

      textTheme: _textTheme(
        AppColors.textPrimaryDark,
        AppColors.textSecondaryDark,
      ),

      cardTheme: const CardThemeData(
        color: AppColors.surfaceDark,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: cardRadius,
          side: BorderSide(color: AppColors.outlineDark, width: 1.0),
        ),
      ),

      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.primaryDark,
          foregroundColor: AppColors.onPrimaryDark,
          disabledBackgroundColor: AppColors.outlineDark,
          disabledForegroundColor: AppColors.textSecondaryDark,
          elevation: 0,
          minimumSize: const Size(
            AppDimensions.minTouchTarget,
            AppDimensions.buttonHeight,
          ),
          padding: const EdgeInsets.symmetric(
            horizontal: AppDimensions.space20,
            vertical: AppDimensions.space12,
          ),
          textStyle: AppTextStyles.labelLarge,
          shape: const RoundedRectangleBorder(borderRadius: buttonRadius),
        ),
      ),

      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.textPrimaryDark,
          disabledForegroundColor: AppColors.textSecondaryDark,
          elevation: 0,
          minimumSize: const Size(
            AppDimensions.minTouchTarget,
            AppDimensions.buttonHeight,
          ),
          side: const BorderSide(color: AppColors.outlineDark, width: 1.0),
          padding: const EdgeInsets.symmetric(
            horizontal: AppDimensions.space20,
            vertical: AppDimensions.space12,
          ),
          textStyle: AppTextStyles.labelLarge,
          shape: const RoundedRectangleBorder(borderRadius: buttonRadius),
        ),
      ),

      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.primaryDark,
          disabledForegroundColor: AppColors.textSecondaryDark,
          minimumSize: const Size(
            AppDimensions.minTouchTarget,
            AppDimensions.buttonHeight,
          ),
          padding: const EdgeInsets.symmetric(
            horizontal: AppDimensions.space16,
            vertical: AppDimensions.space8,
          ),
          textStyle: AppTextStyles.labelLarge,
          shape: const RoundedRectangleBorder(borderRadius: buttonRadius),
        ),
      ),

      inputDecorationTheme: const InputDecorationTheme(
        filled: true,
        fillColor: AppColors.surfaceDark,
        hintStyle: TextStyle(
          fontFamily: AppTextStyles.fontFamily,
          fontSize: 14.0,
          fontWeight: FontWeight.w400,
          color: AppColors.textSecondaryDark,
        ),
        labelStyle: TextStyle(
          fontFamily: AppTextStyles.fontFamily,
          fontSize: 14.0,
          fontWeight: FontWeight.w400,
          color: AppColors.textSecondaryDark,
        ),
        contentPadding: EdgeInsets.symmetric(
          horizontal: AppDimensions.space16,
          vertical: AppDimensions.space16,
        ),
        border: OutlineInputBorder(
          borderRadius: inputRadius,
          borderSide: BorderSide(color: AppColors.outlineDark, width: 1.0),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: inputRadius,
          borderSide: BorderSide(color: AppColors.outlineDark, width: 1.0),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: inputRadius,
          borderSide: BorderSide(color: AppColors.primaryDark, width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: inputRadius,
          borderSide: BorderSide(color: AppColors.errorDark, width: 1.0),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: inputRadius,
          borderSide: BorderSide(color: AppColors.errorDark, width: 1.5),
        ),
      ),
    );
  }

  static TextTheme _textTheme(Color primaryColor, Color secondaryColor) {
    return TextTheme(
      displayLarge: AppTextStyles.displayLarge.copyWith(color: primaryColor),
      displayMedium: AppTextStyles.displayMedium.copyWith(color: primaryColor),
      headlineLarge: AppTextStyles.headlineLarge.copyWith(color: primaryColor),
      headlineMedium: AppTextStyles.headlineMedium.copyWith(
        color: primaryColor,
      ),
      titleLarge: AppTextStyles.titleLarge.copyWith(color: primaryColor),
      titleMedium: AppTextStyles.titleMedium.copyWith(color: primaryColor),
      titleSmall: AppTextStyles.titleSmall.copyWith(color: primaryColor),
      bodyLarge: AppTextStyles.bodyLarge.copyWith(color: primaryColor),
      bodyMedium: AppTextStyles.bodyMedium.copyWith(color: secondaryColor),
      bodySmall: AppTextStyles.bodySmall.copyWith(color: secondaryColor),
      labelLarge: AppTextStyles.labelLarge.copyWith(color: primaryColor),
      labelMedium: AppTextStyles.labelMedium.copyWith(color: primaryColor),
      labelSmall: AppTextStyles.labelSmall.copyWith(color: secondaryColor),
    );
  }
}
