import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../providers/profile_provider.dart';
import '../theme/app_theme.dart';
import '../theme/responsive.dart';
import 'kibare_logo.dart';
import 'theme_mode_toggle.dart';

class KibareTabAppBar extends ConsumerWidget implements PreferredSizeWidget {
  const KibareTabAppBar({super.key, this.showBackButton = false});

  final bool showBackButton;

  @override
  Size get preferredSize => const Size.fromHeight(108);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final profile = ref.watch(profileProvider).valueOrNull;
    final candidateName =
        '${profile?.firstName.trim() ?? ''} ${profile?.lastName.trim() ?? ''}'
            .trim();
    final headerTitle = candidateName.isEmpty ? 'KIBARE-JOB' : candidateName;

    final background = isDark ? AppColors.darkSurface : Colors.white;
    final borderColor =
        isDark ? Colors.white.withValues(alpha: 0.10) : const Color(0xFFE2E8F0);
    final mutedText =
        isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B);
    final compact = Responsive.compact(context);

    return PreferredSize(
      preferredSize: preferredSize,
      child: Material(
        color: background,
        elevation: isDark ? 0 : 8,
        shadowColor: Colors.black.withValues(alpha: 0.08),
        child: Container(
          height: preferredSize.height,
          decoration: BoxDecoration(
            border: Border(bottom: BorderSide(color: borderColor)),
          ),
          child: SafeArea(
            bottom: false,
            child: Stack(
              children: [
                Padding(
                  padding: EdgeInsets.fromLTRB(
                    Responsive.horizontalPadding(context),
                    compact ? 6 : 10,
                    Responsive.horizontalPadding(context) - 4,
                    compact ? 10 : 14,
                  ),
                  child: Row(
                    children: [
                      if (showBackButton)
                        _HeaderAction(
                          tooltip: 'Retour',
                          icon: Icons.arrow_back_rounded,
                          onPressed: () {
                            if (context.canPop()) {
                              context.pop();
                            } else {
                              context.go('/swipe');
                            }
                          },
                        )
                      else
                        _BrandMark(isDark: isDark),
                      SizedBox(width: compact ? 10 : 12),
                      Expanded(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          mainAxisSize: MainAxisSize.min,
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              headerTitle,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                color: isDark
                                    ? AppColors.lightBackground
                                    : AppColors.primary,
                                fontSize: compact ? 15 : 17,
                                fontWeight: FontWeight.w900,
                                letterSpacing: 0,
                              ),
                            ),
                            Text(
                              'Espace candidat',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                color: mutedText,
                                fontSize: compact ? 10.5 : 11.5,
                                fontWeight: FontWeight.w800,
                                height: 1.05,
                                letterSpacing: 0,
                              ),
                            ),
                          ],
                        ),
                      ),
                      _HeaderAction(
                        tooltip: 'Notifications',
                        icon: Icons.notifications_outlined,
                        onPressed: () => context.push('/notifications'),
                      ),
                      SizedBox(width: compact ? 4 : 6),
                      const ThemeModeToggle(compact: true),
                    ],
                  ),
                ),
                Positioned(
                  left: 0,
                  right: 0,
                  bottom: 0,
                  child: Container(
                    height: 2,
                    decoration: const BoxDecoration(
                      gradient: LinearGradient(
                        colors: [
                          Color(0xFFB8C7B4),
                          Color(0xFF5F7F99),
                          Color(0xFF263849),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _BrandMark extends StatelessWidget {
  const _BrandMark({required this.isDark});

  final bool isDark;

  @override
  Widget build(BuildContext context) {
    final compact = Responsive.compact(context);

    return Container(
      width: compact ? 36 : 42,
      height: compact ? 36 : 42,
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: isDark
              ? Colors.white.withValues(alpha: 0.12)
              : const Color(0xFFE2E8F0),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.18 : 0.05),
            blurRadius: 14,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: KibareLogo(
        size: compact ? 36 : 42,
        borderRadius: 14,
      ),
    );
  }
}

class _HeaderAction extends StatelessWidget {
  const _HeaderAction({
    required this.tooltip,
    required this.icon,
    required this.onPressed,
  });

  final String tooltip;
  final IconData icon;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Material(
      color: isDark
          ? Colors.white.withValues(alpha: 0.06)
          : const Color(0xFFF1F5F9),
      shape: const CircleBorder(),
      child: IconButton(
        tooltip: tooltip,
        onPressed: onPressed,
        icon: Icon(icon, size: 21),
        color: isDark ? AppColors.lightBackground : AppColors.primary,
      ),
    );
  }
}
