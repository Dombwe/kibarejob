import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../theme/responsive.dart';

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
    final compact = Responsive.compact(context);

    return Padding(
      padding: EdgeInsets.symmetric(horizontal: compact ? 16 : 24),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          _ActionButton(
            tooltip: 'Passer',
            label: 'Passer',
            icon: Icons.close_rounded,
            color: const Color(0xFF9AA5B1),
            foregroundColor: const Color(0xFF45515F),
            size: compact ? 52 : 58,
            onPressed: onDislike,
          ),
          SizedBox(width: compact ? 12 : 18),
          _ActionButton(
            tooltip: 'Mettre en favori',
            label: 'Favori',
            icon: Icons.star_rounded,
            color: const Color(0xFFC8A24A),
            foregroundColor:
                isDark ? const Color(0xFFFFE6A3) : const Color(0xFF7A5A12),
            size: compact ? 46 : 50,
            onPressed: onSuperlike,
          ),
          SizedBox(width: compact ? 12 : 18),
          _ActionButton(
            tooltip: 'Postuler',
            label: 'Postuler',
            icon: Icons.favorite_rounded,
            color: isDark ? AppColors.accent : const Color(0xFF2F6F5E),
            foregroundColor: Colors.white,
            size: compact ? 52 : 58,
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
    required this.label,
    required this.icon,
    required this.color,
    required this.size,
    required this.onPressed,
    this.foregroundColor,
  });

  final String tooltip;
  final String label;
  final IconData icon;
  final Color color;
  final Color? foregroundColor;
  final double size;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bgColor = foregroundColor == null
        ? color.withValues(alpha: isDark ? 0.20 : 0.14)
        : color;

    return Tooltip(
      message: tooltip,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          DecoratedBox(
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
          const SizedBox(height: 7),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: color,
                  fontWeight: FontWeight.w900,
                ),
          ),
        ],
      ),
    );
  }
}
