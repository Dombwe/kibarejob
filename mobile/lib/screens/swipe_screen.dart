import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/job_model.dart';
import '../providers/job_provider.dart';
import '../theme/app_theme.dart';
import '../widgets/card_stack.dart';
import '../widgets/loading_widget.dart';
import '../widgets/swipe_buttons.dart';

class SwipeScreen extends ConsumerWidget {
  const SwipeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final feedState = ref.watch(feedProvider);
    final theme = Theme.of(context);

    return Scaffold(
      extendBodyBehindAppBar: true,
      appBar: AppBar(
        title: Row(
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: theme.colorScheme.primary.withValues(alpha: 0.13),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Center(
                child: Text(
                  'KJ',
                  style: TextStyle(
                    color: theme.colorScheme.primary,
                    fontSize: 15,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ),
            const SizedBox(width: 10),
            const Text('Offres'),
          ],
        ),
        actions: [
          _HeaderIcon(
            tooltip: 'Candidatures',
            icon: Icons.favorite_outline_rounded,
            onPressed: () => context.go('/matches'),
          ),
          _HeaderIcon(
            tooltip: 'Notifications',
            icon: Icons.notifications_outlined,
            onPressed: () => context.go('/notifications'),
          ),
          _HeaderIcon(
            tooltip: 'Profil',
            icon: Icons.person_outline_rounded,
            onPressed: () => context.go('/profile'),
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: feedState.when(
        loading: () => const LoadingWidget(message: 'Chargement des offres...'),
        error: (error, _) => _ErrorState(
          message: error.toString(),
          onRetry: () => ref.invalidate(feedProvider),
        ),
        data: (jobs) => _SwipeBody(
          jobs: jobs,
          onSwipe: (job, direction) => _swipe(ref, job, direction),
        ),
      ),
    );
  }

  Future<void> _swipe(WidgetRef ref, JobModel job, String direction) {
    return ref.read(feedProvider.notifier).swipe(job, direction);
  }
}

class _SwipeBody extends StatelessWidget {
  const _SwipeBody({
    required this.jobs,
    required this.onSwipe,
  });

  final List<JobModel> jobs;
  final Future<void> Function(JobModel job, String direction) onSwipe;

  @override
  Widget build(BuildContext context) {
    final topPadding = MediaQuery.paddingOf(context).top + kToolbarHeight + 8;

    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: Theme.of(context).brightness == Brightness.dark
              ? const [Color(0xFF111827), Color(0xFF162033), Color(0xFF0F172A)]
              : const [Color(0xFFF8FAFC), Color(0xFFEFF4F8), Color(0xFFF8FAFC)],
        ),
      ),
      child: SafeArea(
        top: false,
        child: Column(
          children: [
            SizedBox(height: topPadding),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 14),
                child: CardStack(jobs: jobs, onSwipe: onSwipe),
              ),
            ),
            const SizedBox(height: 16),
            SwipeButtons(
              onDislike:
                  jobs.isEmpty ? () {} : () => onSwipe(jobs.first, 'dislike'),
              onLike: jobs.isEmpty ? () {} : () => onSwipe(jobs.first, 'like'),
              onSuperlike:
                  jobs.isEmpty ? () {} : () => onSwipe(jobs.first, 'superlike'),
            ),
            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }
}

class _HeaderIcon extends StatelessWidget {
  const _HeaderIcon({
    required this.tooltip,
    required this.icon,
    required this.onPressed,
  });

  final String tooltip;
  final IconData icon;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(left: 4),
      child: Material(
        color: Theme.of(context).colorScheme.surface.withValues(alpha: 0.62),
        shape: const CircleBorder(),
        child: IconButton(
          tooltip: tooltip,
          onPressed: onPressed,
          icon: Icon(icon, size: 21),
        ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({
    required this.message,
    required this.onRetry,
  });

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 78,
              height: 78,
              decoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.10),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.wifi_off_rounded,
                color: AppColors.primary,
                size: 34,
              ),
            ),
            const SizedBox(height: 18),
            Text(
              'Chargement impossible',
              style: Theme.of(context).textTheme.headlineSmall,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 8),
            Text(
              message,
              style: Theme.of(context).textTheme.bodyMedium,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: onRetry,
              child: const Text('Reessayer'),
            ),
          ],
        ),
      ),
    );
  }
}
