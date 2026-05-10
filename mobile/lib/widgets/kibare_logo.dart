import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

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
      child: SvgPicture.asset(
        'assets/branding/kibare_job_logo.svg',
        height: size,
        width: size,
        fit: BoxFit.contain,
      ),
    );
  }
}
