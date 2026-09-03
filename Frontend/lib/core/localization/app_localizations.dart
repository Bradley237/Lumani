import 'dart:ui' as ui;

import 'package:flutter/widgets.dart';

import 'app_en.dart';
import 'app_fr.dart';

/// Abstract base class for Lumani entry-experience localizations.
///
/// Designed as a clean foundation for:
/// - Splash
/// - Onboarding
/// - Authentication
/// - Subsystem selection
/// - Level selection
abstract class AppLocalizations {
  const AppLocalizations();

  /// Supported locales for the Lumani entry experience.
  static const List<Locale> supportedLocales = [Locale('en'), Locale('fr')];

  /// Standard Flutter [LocalizationsDelegate] for [AppLocalizations].
  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// Resolves the appropriate [AppLocalizations] instance from the given [context].
  ///
  /// Lookup resolution:
  /// 1. [Localizations.of<AppLocalizations>] from the widget hierarchy.
  /// 2. [Localizations.maybeLocaleOf] from the current [BuildContext].
  /// 3. Device platform locale from [View.of(context).platformDispatcher.locale].
  /// 4. Fallback to English if none of the above match or if language is unsupported.
  static AppLocalizations of(BuildContext context) {
    final localized = Localizations.of<AppLocalizations>(
      context,
      AppLocalizations,
    );
    if (localized != null) return localized;

    final locale =
        Localizations.maybeLocaleOf(context) ??
        View.of(context).platformDispatcher.locale;
    return fromLocale(locale);
  }

  /// Resolves [AppLocalizations] for a specified [Locale].
  ///
  /// - English ('en') -> [AppLocalizationsEn]
  /// - French ('fr') -> [AppLocalizationsFr]
  /// - Any other locale -> Falls back to [AppLocalizationsEn]
  static AppLocalizations fromLocale(Locale? locale) {
    final languageCode = locale?.languageCode.toLowerCase() ?? '';
    if (languageCode.startsWith('fr')) {
      return const AppLocalizationsFr();
    }
    return const AppLocalizationsEn();
  }

  /// Resolves [AppLocalizations] directly from the host device locale.
  static AppLocalizations fromDeviceLocale([
    ui.PlatformDispatcher? dispatcher,
  ]) {
    final d = dispatcher ?? ui.PlatformDispatcher.instance;
    return fromLocale(d.locale);
  }

  // ===========================================================================
  // Splash Screen Strings
  // ===========================================================================

  /// Minimal loading text displayed beneath the logo during initialization.
  String get splashPreparingLearningExperience;

  // ===========================================================================
  // Onboarding Screen Strings (4-Act Narrative + Controls)
  // ===========================================================================

  /// Act 1: Problem Awareness
  String get onboardingAct1Title;
  String get onboardingAct1Body;

  /// Act 2: Solution & Curriculum Alignment
  String get onboardingAct2Title;
  String get onboardingAct2Body;

  /// Act 3: AI Tutor Capability
  String get onboardingAct3Title;
  String get onboardingAct3Body;

  /// Act 4: Future Vision & Mastery
  String get onboardingAct4Title;
  String get onboardingAct4Body;

  /// Common Controls
  String get onboardingSkip;
  String get onboardingNext;
  String get onboardingGetStarted;
}

/// Simple, dependency-free [LocalizationsDelegate] for [AppLocalizations].
class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  bool isSupported(Locale locale) {
    return ['en', 'fr'].contains(locale.languageCode.toLowerCase());
  }

  @override
  Future<AppLocalizations> load(Locale locale) {
    return Future.value(AppLocalizations.fromLocale(locale));
  }

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}
