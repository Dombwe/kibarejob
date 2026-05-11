import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../theme/app_theme.dart';
import '../theme/responsive.dart';

enum AppTab {
  discover,
  applications,
  search,
  profile,
}

class AppBottomNavigation extends StatelessWidget {
  const AppBottomNavigation({
    super.key,
    required this.currentTab,
  });

  final AppTab currentTab;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final compact = Responsive.compact(context);

    return Container(
      decoration: BoxDecoration(
        color: isDark ? AppColors.darkSurface : Colors.white,
        border: Border(
          top: BorderSide(
            color: isDark
                ? Colors.white.withValues(alpha: 0.10)
                : const Color(0xFFE2E8F0),
          ),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.22 : 0.08),
            blurRadius: 22,
            offset: const Offset(0, -8),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: NavigationBar(
          selectedIndex: currentTab.index,
          height: compact ? 64 : 72,
          backgroundColor: Colors.transparent,
          elevation: 0,
          indicatorColor:
              AppColors.accent.withValues(alpha: isDark ? 0.28 : 0.34),
          labelBehavior: compact
              ? NavigationDestinationLabelBehavior.onlyShowSelected
              : NavigationDestinationLabelBehavior.alwaysShow,
          onDestinationSelected: (index) =>
              _goToTab(context, AppTab.values[index]),
          destinations: const [
            NavigationDestination(
              selectedIcon: Icon(Icons.home_rounded),
              icon: Icon(Icons.home_outlined),
              label: 'Découvrir',
            ),
            NavigationDestination(
              selectedIcon: Icon(Icons.favorite_rounded),
              icon: Icon(Icons.favorite_border_rounded),
              label: 'Candidatures',
            ),
            NavigationDestination(
              selectedIcon: Icon(Icons.search_rounded),
              icon: Icon(Icons.search_rounded),
              label: 'Recherche',
            ),
            NavigationDestination(
              selectedIcon: Icon(Icons.person_rounded),
              icon: Icon(Icons.person_outline_rounded),
              label: 'Profil',
            ),
          ],
        ),
      ),
    );
  }

  void _goToTab(BuildContext context, AppTab tab) {
    switch (tab) {
      case AppTab.discover:
        context.go('/swipe');
      case AppTab.applications:
        context.go('/matches');
      case AppTab.search:
        context.go('/search');
      case AppTab.profile:
        context.go('/profile');
    }
  }
}
