import 'package:flutter/material.dart';

class KibareLogo extends StatelessWidget {
  const KibareLogo({
    super.key,
    this.size = 56,
    this.borderRadius,
  });

  final double size;
  final double? borderRadius;

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(borderRadius ?? size * 0.28),
      child: Image.asset(
        'assets/branding/logo_kj.png',
        height: size,
        width: size,
        fit: BoxFit.contain,
      ),
    );
  }
}
