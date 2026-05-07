import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/job_model.dart';
import '../providers/job_provider.dart';
import '../widgets/card_stack.dart';
import '../widgets/loading_widget.dart';
import '../widgets/swipe_buttons.dart';

class SwipeScreen extends ConsumerWidget {
  const SwipeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final feedState = ref.watch(feedProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Offres'),
        actions: [
          IconButton(
            tooltip: 'Candidatures',
            onPressed: () => context.go('/matches'),
            icon: const Icon(Icons.favorite_outline),
          ),
          IconButton(
            tooltip: 'Notifications',
            onPressed: () => context.go('/notifications'),
            icon: const Icon(Icons.notifications_outlined),
          ),
          IconButton(
            tooltip: 'Profil',
            onPressed: () => context.go('/profile'),
            icon: const Icon(Icons.person_outline),
          ),
        ],
      ),
      body: feedState.when(
        loading: () => const LoadingWidget(message: 'Chargement des offres...'),
        error: (error, _) => Center(child: Text(error.toString())),
        data: (jobs) => Column(
          children: [
            Expanded(
              child: CardStack(
                jobs: jobs,
                onSwipe: (job, direction) => _swipe(ref, job, direction),
              ),
            ),
            const SizedBox(height: 12),
            SwipeButtons(
              onDislike: jobs.isEmpty ? () {} : () => _swipe(ref, jobs.first, 'dislike'),
              onLike: jobs.isEmpty ? () {} : () => _swipe(ref, jobs.first, 'like'),
              onSuperlike: jobs.isEmpty ? () {} : () => _swipe(ref, jobs.first, 'superlike'),
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  Future<void> _swipe(WidgetRef ref, JobModel job, String direction) {
    return ref.read(feedProvider.notifier).swipe(job, direction);
  }
}
