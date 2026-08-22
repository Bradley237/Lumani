import 'package:flutter/material.dart';

abstract final class AppRadius {
  AppRadius._();

  static const double r4 = 4.0;
  static const double r8 = 8.0;
  static const double r12 = 12.0;
  static const double r16 = 16.0;
  static const double r24 = 24.0;
  static const double r999 = 999.0;

  static const Radius radius4 = Radius.circular(r4);
  static const Radius radius8 = Radius.circular(r8);
  static const Radius radius12 = Radius.circular(r12);
  static const Radius radius16 = Radius.circular(r16);
  static const Radius radius24 = Radius.circular(r24);
  static const Radius radius999 = Radius.circular(r999);

  static const BorderRadius borderRadius4 = BorderRadius.all(radius4);
  static const BorderRadius borderRadius8 = BorderRadius.all(radius8);
  static const BorderRadius borderRadius12 = BorderRadius.all(radius12);
  static const BorderRadius borderRadius16 = BorderRadius.all(radius16);
  static const BorderRadius borderRadius24 = BorderRadius.all(radius24);
  static const BorderRadius borderRadius999 = BorderRadius.all(radius999);
}

