import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/user_model.dart';
import '../providers/auth_provider.dart';
import '../providers/profile_provider.dart';
import '../theme/responsive.dart';
import '../widgets/app_bottom_navigation.dart';
import '../widgets/kibare_tab_app_bar.dart';
import '../widgets/loading_widget.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final profileState = ref.watch(profileProvider);
    final user = ref.watch(authControllerProvider).valueOrNull;

    return Scaffold(
      appBar: const KibareTabAppBar(),
      bottomNavigationBar:
          const AppBottomNavigation(currentTab: AppTab.profile),
      body: profileState.when(
        loading: () => const LoadingWidget(),
        error: (error, _) => Center(child: Text(error.toString())),
        data: (profile) {
          if (profile == null) {
            return const Center(child: Text('Profil introuvable'));
          }

          return Responsive.centeredContent(
            context: context,
            child: RefreshIndicator(
              onRefresh: () =>
                  ref.read(profileProvider.notifier).fetchProfile(),
              child: ListView(
                padding: EdgeInsets.all(Responsive.horizontalPadding(context)),
                children: [
                  _ProfileHeader(profile: profile, email: user?.email ?? ''),
                  const SizedBox(height: 18),
                  _InfoCard(profile: profile),
                  const SizedBox(height: 14),
                  _EditableListSection(
                    title: 'Compétences',
                    emptyText: 'Ajoutez vos compétences clés.',
                    items: profile.skills,
                    onChanged: (items) =>
                        _updateList(context, ref, profile, 'skills', items),
                  ),
                  const SizedBox(height: 14),
                  _EditableListSection(
                    title: 'Expériences',
                    emptyText: 'Ajoutez vos expériences professionnelles.',
                    items: profile.experiences,
                    multiline: true,
                    onChanged: (items) => _updateList(
                        context, ref, profile, 'experiences', items),
                  ),
                  const SizedBox(height: 14),
                  _EditableListSection(
                    title: 'Centres d’intérêt',
                    emptyText: 'Ajoutez vos centres d’intérêt.',
                    items: profile.interests,
                    onChanged: (items) =>
                        _updateList(context, ref, profile, 'interests', items),
                  ),
                  const SizedBox(height: 14),
                  _EditableListSection(
                    title: 'Références',
                    emptyText: 'Ajoutez vos personnes de référence.',
                    items: profile.references,
                    multiline: true,
                    onChanged: (items) => _updateList(
                        context, ref, profile, 'references', items),
                  ),
                  const SizedBox(height: 20),
                  FilledButton.icon(
                    onPressed: () => context.push('/documents'),
                    icon: const Icon(Icons.description_outlined),
                    label: const Text('Consulter mes documents'),
                  ),
                  const SizedBox(height: 12),
                  OutlinedButton.icon(
                    onPressed: () => context.push('/subscription'),
                    icon: const Icon(Icons.workspace_premium_outlined),
                    label: const Text('Gérer mon abonnement'),
                  ),
                  const SizedBox(height: 12),
                  OutlinedButton.icon(
                    onPressed: () async {
                      await ref.read(authControllerProvider.notifier).logout();
                      if (context.mounted) {
                        context.go('/login');
                      }
                    },
                    icon: const Icon(Icons.logout_rounded),
                    label: const Text('Déconnexion'),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  static Future<void> _updateList(
    BuildContext context,
    WidgetRef ref,
    CandidateProfileModel profile,
    String field,
    List<String> items,
  ) {
    final payload = profile.toJson();
    payload[field] = items;

    return ref.read(profileProvider.notifier).updateProfile(payload).catchError(
      (Object error) {
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Mise à jour impossible : $error')),
          );
        }
      },
    );
  }
}

class _ProfileHeader extends StatelessWidget {
  const _ProfileHeader({required this.profile, required this.email});

  final CandidateProfileModel profile;
  final String email;

  @override
  Widget build(BuildContext context) {
    final percent = profile.profileCompletedPercent.clamp(0, 100);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '${profile.firstName} ${profile.lastName}'.trim().isEmpty
                  ? 'Mon profil'
                  : '${profile.firstName} ${profile.lastName}',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            if (email.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(email, style: Theme.of(context).textTheme.bodyMedium),
            ],
            const SizedBox(height: 16),
            ClipRRect(
              borderRadius: BorderRadius.circular(999),
              child: LinearProgressIndicator(
                minHeight: 10,
                value: percent / 100,
              ),
            ),
            const SizedBox(height: 8),
            Text('Profil complété à $percent%'),
          ],
        ),
      ),
    );
  }
}

class _InfoCard extends StatelessWidget {
  const _InfoCard({required this.profile});

  final CandidateProfileModel profile;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          children: [
            _InfoTile(label: 'Ville', value: profile.city),
            _InfoTile(label: 'Niveau', value: profile.educationLevel),
            _InfoTile(
              label: 'Domaine',
              value: profile.educationField?.isNotEmpty == true
                  ? profile.educationField!
                  : 'Non renseigné',
            ),
            _InfoTile(label: 'Disponibilité', value: profile.availability),
          ],
        ),
      ),
    );
  }
}

class _EditableListSection extends StatelessWidget {
  const _EditableListSection({
    required this.title,
    required this.emptyText,
    required this.items,
    required this.onChanged,
    this.multiline = false,
  });

  final String title;
  final String emptyText;
  final List<String> items;
  final ValueChanged<List<String>> onChanged;
  final bool multiline;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    title,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w900,
                        ),
                  ),
                ),
                IconButton(
                  tooltip: 'Ajouter',
                  onPressed: () => _openEditor(context),
                  icon: const Icon(Icons.add_circle_outline),
                ),
              ],
            ),
            const SizedBox(height: 8),
            if (items.isEmpty)
              Text(emptyText, style: Theme.of(context).textTheme.bodyMedium)
            else
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (var index = 0; index < items.length; index++)
                    InputChip(
                      label: Text(items[index]),
                      onPressed: () => _openEditor(context, index: index),
                      onDeleted: () {
                        final next = [...items]..removeAt(index);
                        onChanged(next);
                      },
                    ),
                ],
              ),
          ],
        ),
      ),
    );
  }

  Future<void> _openEditor(BuildContext context, {int? index}) async {
    final value = await showDialog<String>(
      context: context,
      builder: (context) => _ListItemDialog(
        title: index == null ? 'Ajouter' : 'Modifier',
        label: title,
        initialValue: index == null ? '' : items[index],
        multiline: multiline,
      ),
    );

    if (value == null || value.isEmpty) {
      return;
    }

    final next = [...items];
    if (index == null) {
      next.add(value);
    } else {
      next[index] = value;
    }
    onChanged(next);
  }
}

class _ListItemDialog extends StatefulWidget {
  const _ListItemDialog({
    required this.title,
    required this.label,
    required this.initialValue,
    required this.multiline,
  });

  final String title;
  final String label;
  final String initialValue;
  final bool multiline;

  @override
  State<_ListItemDialog> createState() => _ListItemDialogState();
}

class _ListItemDialogState extends State<_ListItemDialog> {
  late final TextEditingController _controller;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.initialValue);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.title),
      content: TextField(
        controller: _controller,
        minLines: widget.multiline ? 3 : 1,
        maxLines: widget.multiline ? 5 : 1,
        autofocus: true,
        decoration: InputDecoration(labelText: widget.label),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Annuler'),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(context, _controller.text.trim()),
          child: const Text('Enregistrer'),
        ),
      ],
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
      dense: true,
      contentPadding: EdgeInsets.zero,
      title: Text(label),
      subtitle: Text(value.isEmpty ? 'Non renseigné' : value),
    );
  }
}
