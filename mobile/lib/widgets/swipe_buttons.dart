import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

class SwipeButtons extends StatelessWidget {
  const SwipeButtons({
    super.key,
    required this.onDislike,
    required this.onLike,
    required this.onSuperlike,
  });

  final VoidCallback onDislike;
  final VoidCallback onLike;
  final VoidCallback onSuperlike;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        _RoundButton(
          tooltip: 'Passer',
          icon: Icons.close,
          color: const Color(0xFF7A8694),
          onPressed: onDislike,
        ),
        const SizedBox(width: 18),
        _RoundButton(
          tooltip: 'Super like',
          icon: Icons.star,
          color: AppColors.secondary,
          onPressed: onSuperlike,
        ),
        const SizedBox(width: 18),
        _RoundButton(
          tooltip: 'Postuler',
          icon: Icons.favorite,
          color: AppColors.primary,
          onPressed: onLike,
        ),
      ],
    );
  }
}

class _RoundButton extends StatelessWidget {
  const _RoundButton({
    required this.tooltip,
    required this.icon,
    required this.color,
    required this.onPressed,
  });

  final String tooltip;
  final IconData icon;
  final Color color;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: tooltip,
      child: Material(
        color: color.withValues(alpha: 0.12),
        shape: const CircleBorder(),
        child: IconButton(
          iconSize: 32,
          color: color,
          onPressed: onPressed,
          icon: Icon(icon),
        ),
      ),
    );
  }
}
