import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers/theme_provider.dart';

class ThemeModeToggle extends ConsumerWidget {
  const ThemeModeToggle({
    super.key,
    this.compact = false,
  });

  final bool compact;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final mode = ref.watch(themeControllerProvider);
    final isDark = mode == ThemeMode.dark ||
        (mode == ThemeMode.system &&
            MediaQuery.platformBrightnessOf(context) == Brightness.dark);

    return Material(
      color: Theme.of(context).colorScheme.surface.withValues(alpha: 0.92),
      elevation: 8,
      shadowColor: Colors.black.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        borderRadius: BorderRadius.circular(999),
        onTap: () => ref.read(themeControllerProvider.notifier).toggle(),
        child: Padding(
          padding: EdgeInsets.symmetric(
            horizontal: compact ? 10 : 12,
            vertical: compact ? 10 : 8,
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                isDark ? Icons.dark_mode_rounded : Icons.light_mode_rounded,
                size: 18,
                color: Theme.of(context).colorScheme.primary,
              ),
              if (!compact) ...[
                const SizedBox(width: 8),
                Text(
                  isDark ? 'Dark' : 'Light',
                  style: Theme.of(context).textTheme.labelLarge,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
