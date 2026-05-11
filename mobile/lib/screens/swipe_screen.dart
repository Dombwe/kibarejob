import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/job_model.dart';
import '../providers/job_provider.dart';
import '../theme/app_theme.dart';
import '../theme/responsive.dart';
import '../widgets/app_bottom_navigation.dart';
import '../widgets/card_stack.dart';
import '../widgets/kibare_tab_app_bar.dart';
import '../widgets/loading_widget.dart';
import '../widgets/swipe_buttons.dart';

class SwipeScreen extends ConsumerWidget {
  const SwipeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final feedState = ref.watch(feedProvider);

    return Scaffold(
      extendBodyBehindAppBar: true,
      bottomNavigationBar:
          const AppBottomNavigation(currentTab: AppTab.discover),
      appBar: const KibareTabAppBar(),
      body: feedState.when(
        loading: () => const LoadingWidget(message: 'Chargement des offres...'),
        error: (error, _) => _ErrorState(
          message: error.toString(),
          onRetry: () => ref.invalidate(feedProvider),
        ),
        data: (jobs) => _SwipeBody(
          jobs: jobs,
          isOffline: ref.read(feedProvider.notifier).isOfflineMode,
          onSwipe: (job, direction) => _swipe(context, ref, job, direction),
          onOpenDetails: (job) => context.push('/jobs/${job.id}', extra: job),
        ),
      ),
    );
  }

  Future<void> _swipe(
    BuildContext context,
    WidgetRef ref,
    JobModel job,
    String direction,
  ) async {
    final notifier = ref.read(feedProvider.notifier);
    if (notifier.isOfflineMode) {
      _showOfflineSwipeMessage(context);
      return;
    }

    final swipeFuture = notifier.swipe(job, direction);
    if (context.mounted && direction == 'like') {
      await _showApplicationSentDialog(context, job);
    }

    try {
      await swipeFuture;
    } catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(content: Text(error.toString())));
      }
    }
  }
}

class _SwipeBody extends StatelessWidget {
  const _SwipeBody({
    required this.jobs,
    required this.isOffline,
    required this.onSwipe,
    required this.onOpenDetails,
  });

  final List<JobModel> jobs;
  final bool isOffline;
  final Future<void> Function(JobModel job, String direction) onSwipe;
  final void Function(JobModel job) onOpenDetails;

  @override
  Widget build(BuildContext context) {
    final compact = Responsive.compact(context);
    final topPadding =
        const KibareTabAppBar().preferredSize.height + (compact ? 6 : 10);

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
            if (isOffline)
              Container(
                margin: const EdgeInsets.fromLTRB(18, 0, 18, 12),
                padding:
                    const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                decoration: BoxDecoration(
                  color: AppColors.accent.withValues(alpha: 0.22),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(
                    color: AppColors.accent.withValues(alpha: 0.40),
                  ),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.cloud_off_rounded, size: 20),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'Mode hors connexion : vous pouvez consulter les offres sauvegardées, mais pas swiper.',
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                    ),
                  ],
                ),
              ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 14),
                child: CardStack(
                  jobs: jobs,
                  onSwipe: onSwipe,
                  onOpenDetails: onOpenDetails,
                ),
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
            SizedBox(height: compact ? 12 : 20),
          ],
        ),
      ),
    );
  }
}

void _showOfflineSwipeMessage(BuildContext context) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(
      const SnackBar(
        content: Text(
          'Le swipe nécessite une connexion. Vous pouvez seulement consulter les offres sauvegardées.',
        ),
      ),
    );
}

Future<void> _showApplicationSentDialog(BuildContext context, JobModel job) {
  return showDialog<void>(
    context: context,
    barrierDismissible: true,
    builder: (context) {
      return Dialog(
        insetPadding: const EdgeInsets.symmetric(horizontal: 22),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(30)),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(28, 30, 28, 28),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 100,
                height: 100,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: LinearGradient(
                    colors: [Color(0xFFB8C7B4), Color(0xFF2F6F5E)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                ),
                child: const Icon(
                  Icons.favorite_border_rounded,
                  color: Colors.white,
                  size: 50,
                ),
              ),
              const SizedBox(height: 24),
              Text(
                'Candidature envoyée !',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
              ),
              const SizedBox(height: 12),
              Text(
                'Votre profil et vos documents ont été préparés et envoyés à :',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyLarge,
              ),
              const SizedBox(height: 12),
              Text(
                job.companyName ?? 'Entreprise',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: const Color(0xFF2F6F5E),
                      fontWeight: FontWeight.w900,
                    ),
              ),
              const SizedBox(height: 18),
              Text(
                'pour le poste de ${job.title}',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: 28),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: () => Navigator.of(context).pop(),
                  style: FilledButton.styleFrom(
                    backgroundColor: const Color(0xFF2F6F5E),
                  ),
                  child: const Text('Continuer à swiper'),
                ),
              ),
            ],
          ),
        ),
      );
    },
  );
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
