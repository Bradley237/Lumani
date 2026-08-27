import 'package:flutter/material.dart';

abstract final class AppRadius {
  static const double radius4 = 4.0;

  static const double radius8 = 8.0;

  static const double radius12 = 12.0;

  static const double radius16 = 16.0;

  static const double radius24 = 24.0;

  static const double radiusPill = 999.0;

  static const Radius r4 = Radius.circular(radius4);
  static const Radius r8 = Radius.circular(radius8);
  static const Radius r12 = Radius.circular(radius12);
  static const Radius r16 = Radius.circular(radius16);
  static const Radius r24 = Radius.circular(radius24);
  static const Radius rPill = Radius.circular(radiusPill);

  static const BorderRadius all4 = BorderRadius.all(r4);
  static const BorderRadius all8 = BorderRadius.all(r8);
  static const BorderRadius all12 = BorderRadius.all(r12);
  static const BorderRadius all16 = BorderRadius.all(r16);
  static const BorderRadius all24 = BorderRadius.all(r24);
  static const BorderRadius pill = BorderRadius.all(rPill);

  static const BorderRadius top16 = BorderRadius.vertical(top: r16);
  static const BorderRadius top24 = BorderRadius.vertical(top: r24);
  static const BorderRadius bottom16 = BorderRadius.vertical(bottom: r16);
}
