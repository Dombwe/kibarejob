import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'providers/theme_provider.dart';
import 'routes/app_router.dart';
import 'theme/app_theme.dart';
import 'widgets/theme_mode_toggle.dart';

class KibareJobApp extends ConsumerWidget {
  const KibareJobApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final themeMode = ref.watch(themeControllerProvider);

    return MaterialApp.router(
      title: 'KIBARE-JOB',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      darkTheme: AppTheme.dark(),
      themeMode: themeMode,
      builder: (context, child) {
        return Stack(
          children: [
            if (child != null) child,
            const Positioned(
              right: 16,
              bottom: 16,
              child: SafeArea(child: ThemeModeToggle()),
            ),
          ],
        );
      },
      routerConfig: appRouter,
    );
  }
}
