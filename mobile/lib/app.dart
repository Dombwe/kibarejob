import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'providers/theme_provider.dart';
import 'routes/app_router.dart';
import 'theme/app_theme.dart';
import 'theme/responsive.dart';

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
      routerConfig: appRouter,
      builder: (context, child) {
        return MediaQuery(
          data: MediaQuery.of(context).copyWith(
            textScaler: Responsive.textScaler(context),
          ),
          child: child ?? const SizedBox.shrink(),
        );
      },
    );
  }
}
