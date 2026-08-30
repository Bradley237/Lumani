import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:shared_preferences/shared_preferences.dart';

class LocaleCubit extends Cubit<Locale> {
  static const String _localeKey = 'user_preferred_locale';

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

  Future<void> setLocale(String languageCode) async {
    if (languageCode != 'en' && languageCode != 'fr') return;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_localeKey, languageCode);
    emit(Locale(languageCode));
  }
}
