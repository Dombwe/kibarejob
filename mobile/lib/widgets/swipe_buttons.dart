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
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          _ActionButton(
            tooltip: 'Passer',
            icon: Icons.close_rounded,
            color: const Color(0xFF7A8694),
            size: 58,
            onPressed: onDislike,
          ),
          const SizedBox(width: 18),
          _ActionButton(
            tooltip: 'Mettre en favori',
            icon: Icons.star_rounded,
            color: isDark ? AppColors.accent : AppColors.secondary,
            size: 50,
            onPressed: onSuperlike,
          ),
          const SizedBox(width: 18),
          _ActionButton(
            tooltip: 'Postuler',
            icon: Icons.favorite_rounded,
            color: AppColors.primary,
            foregroundColor: Colors.white,
            size: 66,
            onPressed: onLike,
          ),
        ],
      ),
    );
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({
    required this.tooltip,
    required this.icon,
    required this.color,
    required this.size,
    required this.onPressed,
    this.foregroundColor,
  });

  final String tooltip;
  final IconData icon;
  final Color color;
  final Color? foregroundColor;
  final double size;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bgColor = foregroundColor == null
        ? color.withValues(alpha: isDark ? 0.18 : 0.12)
        : color;

    return Tooltip(
      message: tooltip,
      child: DecoratedBox(
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          boxShadow: [
            BoxShadow(
              color: color.withValues(
                  alpha: foregroundColor == null ? 0.10 : 0.26),
              blurRadius: 22,
              offset: const Offset(0, 12),
            ),
          ],
        ),
        child: Material(
          color: bgColor,
          shape: const CircleBorder(),
          child: InkWell(
            customBorder: const CircleBorder(),
            onTap: onPressed,
            child: SizedBox(
              width: size,
              height: size,
              child: Icon(
                icon,
                size: size * 0.46,
                color: foregroundColor ?? color,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
