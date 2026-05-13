import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/job_model.dart';
import '../providers/auth_provider.dart';
import '../providers/job_provider.dart';
import '../services/job_service.dart';
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
          onConfigureServer: () => _configureServer(context, ref),
        ),
        data: (jobs) => _SwipeBody(
          jobs: jobs,
          isOffline: ref.read(feedProvider.notifier).isOfflineMode,
          onRetryOnline: () => ref.read(feedProvider.notifier).retryOnline(),
          onConfigureServer: () => _configureServer(context, ref),
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

    if (direction == 'like') {
      _showBlockingLoader(context, 'Envoi de votre candidature...');
    }

    try {
      final result = await notifier.swipe(job, direction);
      if (context.mounted && direction == 'like') {
        Navigator.of(context, rootNavigator: true).pop();
      }
      if (context.mounted && direction == 'like' && result.accepted) {
        await _showApplicationSentDialog(context, job, result);
      }
    } on ProfileCompletionRequiredException catch (error) {
      if (context.mounted && direction == 'like') {
        Navigator.of(context, rootNavigator: true).pop();
      }
      if (context.mounted) {
        await _showProfileCompletionDialog(context, error);
      }
    } catch (error) {
      if (context.mounted && direction == 'like') {
        Navigator.of(context, rootNavigator: true).pop();
      }
      if (context.mounted) {
        if (direction == 'like') {
          await _showApplicationErrorDialog(context, error.toString());
        } else {
          ScaffoldMessenger.of(context)
            ..hideCurrentSnackBar()
            ..showSnackBar(SnackBar(content: Text(error.toString())));
        }
      }
    }
  }

  Future<void> _configureServer(BuildContext context, WidgetRef ref) async {
    final storage = ref.read(storageServiceProvider);
    final controller = TextEditingController(
      text: storage.apiBaseUrl ?? 'https://192.168.11.100:8000',
    );

    final value = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Adresse du backend'),
        content: TextField(
          controller: controller,
          keyboardType: TextInputType.url,
          decoration: const InputDecoration(
            labelText: 'URL serveur',
            hintText: 'https://192.168.11.100:8000',
            prefixIcon: Icon(Icons.dns_outlined),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, controller.text.trim()),
            child: const Text('Enregistrer'),
          ),
        ],
      ),
    );

    controller.dispose();

    if (value == null || value.isEmpty) {
      return;
    }

    await storage.saveApiBaseUrl(value.replaceAll(RegExp(r'/+$'), ''));
    ref.invalidate(feedProvider);
  }
}

Future<void> _showApplicationErrorDialog(
  BuildContext context,
  String message,
) {
  return showDialog<void>(
    context: context,
    builder: (context) => AlertDialog(
      icon: Icon(
        Icons.error_outline_rounded,
        color: Theme.of(context).colorScheme.error,
      ),
      title: const Text('Candidature non envoyée'),
      content: Text(message.replaceFirst('Exception: ', '')),
      actions: [
        FilledButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Compris'),
        ),
      ],
    ),
  );
}

void _showBlockingLoader(BuildContext context, String message) {
  showDialog<void>(
    context: context,
    barrierDismissible: false,
    builder: (context) => PopScope(
      canPop: false,
      child: Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const CircularProgressIndicator(),
              const SizedBox(height: 18),
              Text(
                message,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyLarge,
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

Future<void> _showProfileCompletionDialog(
  BuildContext context,
  ProfileCompletionRequiredException error,
) {
  final missingProfile = error.missingProfileItems;
  final missingDocuments = error.missingDocuments;

  return showDialog<void>(
    context: context,
    barrierDismissible: true,
    builder: (context) {
      return AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        icon: const Icon(Icons.assignment_ind_outlined),
        title: const Text('Profil à compléter'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                error.message,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              if (missingProfile.isNotEmpty) ...[
                const SizedBox(height: 14),
                Text(
                  'Informations manquantes',
                  style: Theme.of(context).textTheme.titleSmall,
                ),
                const SizedBox(height: 6),
                ...missingProfile.map((item) => _MissingItem(label: item)),
              ],
              if (missingDocuments.isNotEmpty) ...[
                const SizedBox(height: 14),
                Text(
                  'Documents à ajouter',
                  style: Theme.of(context).textTheme.titleSmall,
                ),
                const SizedBox(height: 6),
                ...missingDocuments.map((item) => _MissingItem(label: item)),
              ],
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Plus tard'),
          ),
          FilledButton(
            onPressed: () {
              Navigator.of(context).pop();
              context.push(
                missingDocuments.isNotEmpty ? '/documents' : '/profile',
              );
            },
            child: Text(
              missingDocuments.isNotEmpty
                  ? 'Ajouter les documents'
                  : 'Compléter mon profil',
            ),
          ),
        ],
      );
    },
  );
}

class _MissingItem extends StatelessWidget {
  const _MissingItem({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 5),
      child: Row(
        children: [
          Icon(
            Icons.check_circle_outline,
            size: 17,
            color: Theme.of(context).colorScheme.primary,
          ),
          const SizedBox(width: 7),
          Expanded(child: Text(label)),
        ],
      ),
    );
  }
}

class _SwipeBody extends StatelessWidget {
  const _SwipeBody({
    required this.jobs,
    required this.isOffline,
    required this.onRetryOnline,
    required this.onConfigureServer,
    required this.onSwipe,
    required this.onOpenDetails,
  });

  final List<JobModel> jobs;
  final bool isOffline;
  final VoidCallback onRetryOnline;
  final VoidCallback onConfigureServer;
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
                    const SizedBox(width: 8),
                    TextButton(
                      onPressed: onRetryOnline,
                      child: const Text('Revenir en ligne'),
                    ),
                    IconButton(
                      tooltip: 'Configurer le serveur',
                      onPressed: onConfigureServer,
                      icon: const Icon(Icons.dns_outlined),
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

Future<void> _showApplicationSentDialog(
  BuildContext context,
  JobModel job,
  SwipeResult result,
) {
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
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: LinearGradient(
                    colors: result.emailSent
                        ? const [Color(0xFFB8C7B4), Color(0xFF2F6F5E)]
                        : const [Color(0xFFFDE68A), Color(0xFFB45309)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                ),
                child: Icon(
                  result.emailSent
                      ? Icons.mark_email_read_outlined
                      : Icons.mark_email_unread_outlined,
                  color: Colors.white,
                  size: 50,
                ),
              ),
              const SizedBox(height: 24),
              Text(
                result.emailSent
                    ? 'Candidature envoyée !'
                    : 'Candidature non envoyée',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
              ),
              const SizedBox(height: 12),
              Text(
                result.emailSent
                    ? 'L’email de candidature a bien été transmis. Une copie a été envoyée à votre adresse mail et la preuve d’envoi est disponible dans l’onglet Candidatures.'
                    : (result.hasEmailError
                        ? result.emailError!
                        : 'Votre candidature est enregistrée, mais l’email n’a pas été confirmé. Vous pourrez la renvoyer depuis l’onglet Candidatures.'),
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyLarge,
              ),
              const SizedBox(height: 12),
              Text(
                result.emailRecipient ?? job.companyName ?? 'Entreprise',
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
                  onPressed: () {
                    Navigator.of(context).pop();
                    if (!result.emailSent) {
                      context.push('/matches');
                    }
                  },
                  style: FilledButton.styleFrom(
                    backgroundColor: const Color(0xFF2F6F5E),
                  ),
                  child: Text(
                    result.emailSent
                        ? 'Continuer à swiper'
                        : 'Voir mes candidatures',
                  ),
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
    required this.onConfigureServer,
  });

  final String message;
  final VoidCallback onRetry;
  final VoidCallback onConfigureServer;

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
              message.toLowerCase().contains('inaccessible') ||
                      message.toLowerCase().contains('connexion') ||
                      message.toLowerCase().contains('network')
                  ? 'Connexion au backend impossible'
                  : 'Erreur de chargement',
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
            Wrap(
              alignment: WrapAlignment.center,
              spacing: 10,
              runSpacing: 10,
              children: [
                OutlinedButton.icon(
                  onPressed: onConfigureServer,
                  icon: const Icon(Icons.dns_outlined),
                  label: const Text('Configurer'),
                ),
                FilledButton(
                  onPressed: onRetry,
                  child: const Text('Réessayer'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
