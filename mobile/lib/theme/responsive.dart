import 'dart:math' as math;

import 'package:flutter/material.dart';

class Responsive {
  const Responsive._();

  static bool compact(BuildContext context) {
    final size = MediaQuery.sizeOf(context);
    return size.shortestSide < 360 || size.height < 700;
  }

  static bool roomy(BuildContext context) {
    return MediaQuery.sizeOf(context).shortestSide >= 600;
  }

  static double horizontalPadding(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    if (width >= 700) {
      return 28;
    }
    if (width <= 340) {
      return 12;
    }
    return 18;
  }

  static double maxContentWidth(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    if (width >= 900) {
      return 720;
    }
    if (width >= 600) {
      return 640;
    }
    return width;
  }

  static Widget centeredContent({
    required BuildContext context,
    required Widget child,
  }) {
    return Center(
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: maxContentWidth(context)),
        child: child,
      ),
    );
  }

  static TextScaler textScaler(BuildContext context) {
    final scaler = MediaQuery.textScalerOf(context);
    final width = MediaQuery.sizeOf(context).width;
    final maxScale = width <= 340 ? 1.05 : 1.18;

    return TextScaler.linear(math.min(scaler.scale(1), maxScale));
  }
}
