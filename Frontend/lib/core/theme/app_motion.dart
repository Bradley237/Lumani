import 'package:flutter/animation.dart';

abstract final class AppMotion {

  static const Duration instant = Duration(milliseconds: 100);

  static const Duration fast = Duration(milliseconds: 200);

  static const Duration normal = Duration(milliseconds: 300);

  static const Duration slow = Duration(milliseconds: 500);

  static const Curve standard = Curves.easeInOut;

  static const Curve decelerate = Curves.easeOutCubic;

  static const Curve accelerate = Curves.easeInCubic;

  static const Curve emphasized = Curves.fastOutSlowIn;
}
