import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Manages the application locale with persistent storage and curriculum lock.
///
/// Resolution order on first launch:
/// 1. Persisted preference (from previous session or curriculum lock).
/// 2. Host device language (`fr_*` → French, everything else → English).
///
/// The curriculum lock overrides manual language switching:
/// - GCE subsystem → English.
/// - OBC subsystem → French.
class LocaleCubit extends Cubit<Locale> {
  static const String _localeKey = 'user_preferred_locale';
  static const String _curriculumLockedKey = 'curriculum_locale_locked';

  LocaleCubit() : super(const Locale('en')) {
    _initLocale();
  }

  Future<void> _initLocale() async {
    final prefs = await SharedPreferences.getInstance();
    final savedCode = prefs.getString(_localeKey);
    if (savedCode != null && (savedCode == 'en' || savedCode == 'fr')) {
      emit(Locale(savedCode));
    } else {
      final deviceLocale = ui.PlatformDispatcher.instance.locale.languageCode;
      if (deviceLocale == 'fr') {
        emit(const Locale('fr'));
      } else {
        emit(const Locale('en'));
      }
    }
  }

  /// Manually set the locale. Respects curriculum lock — if the subsystem has
  /// locked the language, this call is silently ignored.
  Future<void> setLocale(String languageCode) async {
    if (languageCode != 'en' && languageCode != 'fr') return;

    final prefs = await SharedPreferences.getInstance();
    final isLocked = prefs.getBool(_curriculumLockedKey) ?? false;
    if (isLocked) return;

    await prefs.setString(_localeKey, languageCode);
    emit(Locale(languageCode));
  }

  /// Enforces the curriculum language binding.
  ///
  /// Accepts the subsystem's API value string ('gce', 'obc', 'none') to
  /// avoid a circular import with `subsystem_cubit.dart`.
  ///
  /// - `'gce'` → locks to English.
  /// - `'obc'` → locks to French.
  /// - Any other value → unlocks (removes the lock flag).
  Future<void> lockToSubsystem(String subsystemApiValue) async {
    final prefs = await SharedPreferences.getInstance();

    switch (subsystemApiValue) {
      case 'gce':
        await prefs.setString(_localeKey, 'en');
        await prefs.setBool(_curriculumLockedKey, true);
        emit(const Locale('en'));
      case 'obc':
        await prefs.setString(_localeKey, 'fr');
        await prefs.setBool(_curriculumLockedKey, true);
        emit(const Locale('fr'));
      default:
        await prefs.setBool(_curriculumLockedKey, false);
    }
  }
}
