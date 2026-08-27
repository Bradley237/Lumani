import 'package:flutter/material.dart';

abstract final class AppDimensions {
  static const double space4 = 4.0;

  static const double space8 = 8.0;

  static const double space12 = 12.0;

  static const double space16 = 16.0;

  static const double space20 = 20.0;

  static const double space24 = 24.0;

  static const double space32 = 32.0;

  static const double space40 = 40.0;

  static const double space48 = 48.0;

  static const double space64 = 64.0;

  static const double minTouchTarget = 48.0;

  static const double buttonHeight = 48.0;

  static const double buttonHeightLarge = 56.0;

  static const double buttonHeightSmall = 36.0;

  static const double inputHeight = 56.0;

  static const double appBarHeight = 56.0;

  static const double iconSm = 16.0;

  static const double iconMd = 20.0;

  static const double iconLg = 24.0;

  static const double iconXl = 32.0;

  static const double icon2Xl = 48.0;

  static const EdgeInsets padding4 = EdgeInsets.all(space4);
  static const EdgeInsets padding8 = EdgeInsets.all(space8);
  static const EdgeInsets padding12 = EdgeInsets.all(space12);
  static const EdgeInsets padding16 = EdgeInsets.all(space16);
  static const EdgeInsets padding20 = EdgeInsets.all(space20);
  static const EdgeInsets padding24 = EdgeInsets.all(space24);
  static const EdgeInsets padding32 = EdgeInsets.all(space32);

  static const EdgeInsets paddingH16 = EdgeInsets.symmetric(
    horizontal: space16,
  );
  static const EdgeInsets paddingH20 = EdgeInsets.symmetric(
    horizontal: space20,
  );
  static const EdgeInsets paddingH24 = EdgeInsets.symmetric(
    horizontal: space24,
  );

  static const EdgeInsets paddingV8 = EdgeInsets.symmetric(vertical: space8);
  static const EdgeInsets paddingV12 = EdgeInsets.symmetric(vertical: space12);
  static const EdgeInsets paddingV16 = EdgeInsets.symmetric(vertical: space16);
}
