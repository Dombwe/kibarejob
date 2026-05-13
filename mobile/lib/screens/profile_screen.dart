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
import '../widgets/state_message.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final profileState = ref.watch(profileProvider);
    final user = ref.watch(authControllerProvider).valueOrNull;

    return Scaffold(
      appBar: KibareTabAppBar(
        actions: [
          PopupMenuButton<_ProfileMenuAction>(
            tooltip: 'Menu du profil',
            icon: const Icon(Icons.more_vert_rounded),
            onSelected: (action) => _handleProfileMenu(context, ref, action),
            itemBuilder: (context) => const [
              PopupMenuItem(
                value: _ProfileMenuAction.documents,
                child: ListTile(
                  leading: Icon(Icons.description_outlined),
                  title: Text('Consulter mes documents'),
                ),
              ),
              PopupMenuItem(
                value: _ProfileMenuAction.subscription,
                child: ListTile(
                  leading: Icon(Icons.workspace_premium_outlined),
                  title: Text('Gerer mon abonnement'),
                ),
              ),
              PopupMenuDivider(),
              PopupMenuItem(
                value: _ProfileMenuAction.logout,
                child: ListTile(
                  leading: Icon(Icons.logout_rounded),
                  title: Text('Deconnexion'),
                ),
              ),
            ],
          ),
        ],
      ),
      bottomNavigationBar:
          const AppBottomNavigation(currentTab: AppTab.profile),
      body: profileState.when(
        loading: () => const LoadingWidget(),
        error: (error, _) => StateMessage(
          icon: Icons.person_off_outlined,
          title: 'Profil indisponible',
          message:
              'Votre profil sera disponible hors ligne apres une premiere ouverture avec internet.',
          actionLabel: 'Reessayer',
          onAction: () => ref.invalidate(profileProvider),
        ),
        data: (profile) {
          if (profile == null) {
            return const StateMessage(
              icon: Icons.person_outline_rounded,
              title: 'Profil a completer',
              message:
                  'Ajoutez vos informations pour recevoir de meilleures offres.',
            );
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
                  _InfoCard(
                    profile: profile,
                    onEdit: () => _editPersonalInfo(context, ref, profile),
                  ),
                  const SizedBox(height: 14),
                  _AvailabilitySection(
                    availability: profile.availability,
                    onChanged: (value) =>
                        _updateAvailability(context, ref, profile, value),
                  ),
                  const SizedBox(height: 14),
                  _EditableListSection(
                    title: 'Compétences',
                    emptyText: 'Ajoutez vos compétences clés.',
                    items: profile.skills,
                    onChanged: (items) =>
                        _updateList(context, ref, profile, 'skills', items),
                    onClear: () => _confirmClearList(
                      context,
                      ref,
                      profile,
                      'skills',
                      'toutes les competences',
                    ),
                  ),
                  const SizedBox(height: 14),
                  _EditableListSection(
                    title: 'Expériences',
                    emptyText: 'Ajoutez vos expériences professionnelles.',
                    items: profile.experiences,
                    multiline: true,
                    onChanged: (items) => _updateList(
                        context, ref, profile, 'experiences', items),
                    onClear: () => _confirmClearList(
                      context,
                      ref,
                      profile,
                      'experiences',
                      'toutes les experiences',
                    ),
                  ),
                  const SizedBox(height: 14),
                  _EditableListSection(
                    title: 'Centres d’intérêt',
                    emptyText: 'Ajoutez vos centres d’intérêt.',
                    items: profile.interests,
                    onChanged: (items) =>
                        _updateList(context, ref, profile, 'interests', items),
                    onClear: () => _confirmClearList(
                      context,
                      ref,
                      profile,
                      'interests',
                      'tous les centres d interet',
                    ),
                  ),
                  const SizedBox(height: 14),
                  _EditableListSection(
                    title: 'Références',
                    emptyText: 'Ajoutez vos personnes de référence.',
                    items: profile.references,
                    multiline: true,
                    onChanged: (items) =>
                        _updateList(context, ref, profile, 'references', items),
                    onClear: () => _confirmClearList(
                      context,
                      ref,
                      profile,
                      'references',
                      'toutes les references',
                    ),
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

  static Future<void> _confirmClearList(
    BuildContext context,
    WidgetRef ref,
    CandidateProfileModel profile,
    String field,
    String label,
  ) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Confirmer la suppression'),
        content: Text('Voulez-vous vraiment vider $label ?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Annuler'),
          ),
          FilledButton.icon(
            onPressed: () => Navigator.pop(context, true),
            icon: const Icon(Icons.delete_sweep_outlined),
            label: const Text('Vider'),
          ),
        ],
      ),
    );

    if (confirmed != true || !context.mounted) {
      return;
    }

    await _updateList(context, ref, profile, field, const []);
  }

  static Future<void> _handleProfileMenu(
    BuildContext context,
    WidgetRef ref,
    _ProfileMenuAction action,
  ) async {
    switch (action) {
      case _ProfileMenuAction.documents:
        context.push('/documents');
        return;
      case _ProfileMenuAction.subscription:
        context.push('/subscription');
        return;
      case _ProfileMenuAction.logout:
        await ref.read(authControllerProvider.notifier).logout();
        if (context.mounted) {
          context.go('/login');
        }
        return;
    }
  }

  static Future<void> _updateAvailability(
    BuildContext context,
    WidgetRef ref,
    CandidateProfileModel profile,
    String availability,
  ) {
    final payload = profile.toJson();
    payload['availability'] = availability;

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

  static Future<void> _editPersonalInfo(
    BuildContext context,
    WidgetRef ref,
    CandidateProfileModel profile,
  ) async {
    final values = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (context) => _ProfileDetailsDialog(profile: profile),
    );
    if (values == null) {
      return;
    }

    final payload = profile.toJson()..addAll(values);
    try {
      await ref.read(profileProvider.notifier).updateProfile(payload);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Profil mis à jour.')),
        );
      }
    } catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Mise à jour impossible : $error')),
        );
      }
    }
  }
}

enum _ProfileMenuAction { documents, subscription, logout }

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
  const _InfoCard({required this.profile, required this.onEdit});

  final CandidateProfileModel profile;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Informations personnelles',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w900,
                        ),
                  ),
                ),
                IconButton(
                  tooltip: 'Modifier',
                  onPressed: onEdit,
                  icon: const Icon(Icons.edit_outlined),
                ),
              ],
            ),
            _InfoTile(label: 'Nom', value: profile.lastName),
            _InfoTile(label: 'Prénom(s)', value: profile.firstName),
            _InfoTile(
              label: 'Date de naissance',
              value: profile.birthDate?.isNotEmpty == true
                  ? profile.birthDate!
                  : 'Non renseigné',
            ),
            _InfoTile(label: 'Ville', value: profile.city),
            _InfoTile(label: 'Niveau', value: profile.educationLevel),
            _InfoTile(
              label: 'Domaine',
              value: profile.educationField?.isNotEmpty == true
                  ? profile.educationField!
                  : 'Non renseigné',
            ),
            _InfoTile(
              label: 'Prétention salariale',
              value: profile.salaryExpectation == null
                  ? 'Non renseigné'
                  : '${profile.salaryExpectation} FCFA',
            ),
          ],
        ),
      ),
    );
  }
}

class _ProfileDetailsDialog extends StatefulWidget {
  const _ProfileDetailsDialog({required this.profile});

  final CandidateProfileModel profile;

  @override
  State<_ProfileDetailsDialog> createState() => _ProfileDetailsDialogState();
}

class _ProfileDetailsDialogState extends State<_ProfileDetailsDialog> {
  late final TextEditingController _firstNameController;
  late final TextEditingController _lastNameController;
  late final TextEditingController _birthDateController;
  late final TextEditingController _cityController;
  late final TextEditingController _educationLevelController;
  late final TextEditingController _educationFieldController;
  late final TextEditingController _salaryController;

  @override
  void initState() {
    super.initState();
    final profile = widget.profile;
    _firstNameController = TextEditingController(text: profile.firstName);
    _lastNameController = TextEditingController(text: profile.lastName);
    _birthDateController = TextEditingController(text: profile.birthDate ?? '');
    _cityController = TextEditingController(text: profile.city);
    _educationLevelController =
        TextEditingController(text: profile.educationLevel);
    _educationFieldController =
        TextEditingController(text: profile.educationField ?? '');
    _salaryController = TextEditingController(
        text: profile.salaryExpectation?.toString() ?? '');
  }

  @override
  void dispose() {
    _firstNameController.dispose();
    _lastNameController.dispose();
    _birthDateController.dispose();
    _cityController.dispose();
    _educationLevelController.dispose();
    _educationFieldController.dispose();
    _salaryController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Modifier mon profil'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: _lastNameController,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(labelText: 'Nom'),
            ),
            TextField(
              controller: _firstNameController,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(labelText: 'Prénom(s)'),
            ),
            TextField(
              controller: _birthDateController,
              readOnly: true,
              decoration: const InputDecoration(
                labelText: 'Date de naissance',
                suffixIcon: Icon(Icons.calendar_today_outlined),
              ),
              onTap: _pickBirthDate,
            ),
            TextField(
              controller: _cityController,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(labelText: 'Ville'),
            ),
            TextField(
              controller: _educationLevelController,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(labelText: 'Niveau d’études'),
            ),
            TextField(
              controller: _educationFieldController,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(labelText: 'Domaine d’étude'),
            ),
            TextField(
              controller: _salaryController,
              keyboardType: TextInputType.number,
              decoration:
                  const InputDecoration(labelText: 'Prétention salariale'),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Annuler'),
        ),
        FilledButton(
          onPressed: () {
            Navigator.pop(context, {
              'firstName': _firstNameController.text.trim(),
              'lastName': _lastNameController.text.trim(),
              'birthDate': _birthDateController.text.trim(),
              'city': _cityController.text.trim(),
              'educationLevel': _educationLevelController.text.trim(),
              'educationField': _educationFieldController.text.trim(),
              'salaryExpectation': int.tryParse(_salaryController.text.trim()),
            });
          },
          child: const Text('Enregistrer'),
        ),
      ],
    );
  }

  Future<void> _pickBirthDate() async {
    final initialDate =
        DateTime.tryParse(_birthDateController.text) ?? DateTime(2000);
    final picked = await showDatePicker(
      context: context,
      initialDate: initialDate,
      firstDate: DateTime(1940),
      lastDate: DateTime.now(),
    );
    if (picked == null) {
      return;
    }

    _birthDateController.text = '${picked.year.toString().padLeft(4, '0')}-'
        '${picked.month.toString().padLeft(2, '0')}-'
        '${picked.day.toString().padLeft(2, '0')}';
  }
}

class _AvailabilitySection extends StatelessWidget {
  const _AvailabilitySection({
    required this.availability,
    required this.onChanged,
  });

  final String availability;
  final ValueChanged<String> onChanged;

  static const _options = [
    'Immédiate',
    'Sous 1 semaine',
    'Sous 2 semaines',
    'Sous 1 mois',
    'Préavis en cours',
    'Non disponible',
  ];

  @override
  Widget build(BuildContext context) {
    final current = _normalizeLegacyAvailability(availability);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: DropdownButtonFormField<String>(
          value: _options.contains(current) ? current : _options.first,
          decoration: const InputDecoration(
            labelText: 'Disponibilité',
            prefixIcon: Icon(Icons.event_available_outlined),
          ),
          items: [
            for (final option in _options)
              DropdownMenuItem(value: option, child: Text(option)),
          ],
          onChanged: (value) {
            if (value != null) {
              onChanged(value);
            }
          },
        ),
      ),
    );
  }

  String _normalizeLegacyAvailability(String value) {
    final normalized = value.trim().toLowerCase();
    if (normalized == 'immediate' || normalized == 'immédiate') {
      return 'Immédiate';
    }

    return value;
  }
}

class _EditableListSection extends StatelessWidget {
  const _EditableListSection({
    required this.title,
    required this.emptyText,
    required this.items,
    required this.onChanged,
    required this.onClear,
    this.multiline = false,
  });

  final String title;
  final String emptyText;
  final List<String> items;
  final ValueChanged<List<String>> onChanged;
  final VoidCallback onClear;
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
                IconButton(
                  tooltip: 'Tout vider',
                  onPressed: items.isEmpty ? null : onClear,
                  icon: const Icon(Icons.delete_sweep_outlined),
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
