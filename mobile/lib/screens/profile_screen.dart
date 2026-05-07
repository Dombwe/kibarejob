import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../providers/auth_provider.dart';
import '../providers/profile_provider.dart';
import '../widgets/loading_widget.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final profileState = ref.watch(profileProvider);
    final user = ref.watch(authControllerProvider).valueOrNull;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Mon profil'),
        actions: [
          IconButton(
            tooltip: 'Offres',
            onPressed: () => context.go('/swipe'),
            icon: const Icon(Icons.view_carousel_outlined),
          ),
          IconButton(
            tooltip: 'Abonnement',
            onPressed: () => context.go('/subscription'),
            icon: const Icon(Icons.workspace_premium_outlined),
          ),
          IconButton(
            tooltip: 'Documents',
            onPressed: () => context.go('/documents'),
            icon: const Icon(Icons.folder_outlined),
          ),
          IconButton(
            tooltip: 'Deconnexion',
            onPressed: () async {
              await ref.read(authControllerProvider.notifier).logout();
              if (context.mounted) {
                context.go('/login');
              }
            },
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: profileState.when(
        loading: () => const LoadingWidget(),
        error: (error, _) => Center(child: Text(error.toString())),
        data: (profile) {
          if (profile == null) {
            return const Center(child: Text('Profil introuvable'));
          }
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text(
                '${profile.firstName} ${profile.lastName}',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 8),
              Text(user?.email ?? ''),
              const SizedBox(height: 16),
              LinearProgressIndicator(
                value: profile.profileCompletedPercent / 100,
              ),
              const SizedBox(height: 8),
              Text('Profil complete a ${profile.profileCompletedPercent}%'),
              const SizedBox(height: 24),
              _InfoTile(label: 'Ville', value: profile.city),
              _InfoTile(label: 'Niveau', value: profile.educationLevel),
              _InfoTile(label: 'Disponibilite', value: profile.availability),
              _InfoTile(
                label: 'Competences',
                value: profile.skills.isEmpty ? 'Aucune' : profile.skills.join(', '),
              ),
              const SizedBox(height: 20),
              FilledButton.icon(
                onPressed: () => context.go('/documents'),
                icon: const Icon(Icons.description_outlined),
                label: const Text('Gerer mes documents'),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _InfoTile extends StatelessWidget {
  const _InfoTile({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(label),
      subtitle: Text(value),
    );
  }
}
