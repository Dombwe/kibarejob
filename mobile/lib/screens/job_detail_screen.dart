import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../models/job_model.dart';
import '../providers/profile_provider.dart';
import '../theme/app_theme.dart';
import '../theme/responsive.dart';
import '../widgets/app_bottom_navigation.dart';
import '../widgets/kibare_logo.dart';
import '../widgets/kibare_tab_app_bar.dart';

class JobDetailScreen extends ConsumerWidget {
  const JobDetailScreen({super.key, required this.job});

  final JobModel job;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final profile = ref.watch(profileProvider).valueOrNull;
    final profileIncomplete =
        profile == null || profile.profileCompletedPercent < 100;
    final horizontalPadding = Responsive.horizontalPadding(context);

    return Scaffold(
      appBar: const KibareTabAppBar(),
      bottomNavigationBar:
          const AppBottomNavigation(currentTab: AppTab.discover),
      body: DecoratedBox(
        decoration: BoxDecoration(
          color: isDark ? AppColors.darkBackground : AppColors.lightBackground,
        ),
        child: Responsive.centeredContent(
          context: context,
          child: ListView(
            padding: EdgeInsets.fromLTRB(
              horizontalPadding,
              12,
              horizontalPadding,
              28,
            ),
            children: [
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: theme.cardColor,
                  borderRadius: BorderRadius.circular(26),
                  border: Border.all(
                    color: isDark
                        ? Colors.white.withValues(alpha: 0.12)
                        : const Color(0xFFE2E8F0),
                  ),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Container(
                          width: 62,
                          height: 62,
                          decoration: BoxDecoration(
                            color: theme.colorScheme.primary
                                .withValues(alpha: 0.08),
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: const Center(child: KibareLogo(size: 38)),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                job.companyName ?? 'Entreprise',
                                style: theme.textTheme.bodyLarge?.copyWith(
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                job.location.isEmpty
                                    ? 'Lieu non précisé'
                                    : job.location,
                                style: theme.textTheme.bodyMedium,
                              ),
                            ],
                          ),
                        ),
                        if (job.matchScore != null)
                          Container(
                            width: 58,
                            height: 58,
                            decoration: BoxDecoration(
                              color: _matchTone(job.matchScore!).background,
                              shape: BoxShape.circle,
                              border: Border.all(
                                color: _matchTone(job.matchScore!).border,
                              ),
                            ),
                            child: Center(
                              child: Text(
                                '${job.matchScore}%',
                                style: TextStyle(
                                  color: _matchTone(job.matchScore!).foreground,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 22),
                    Text(job.title, style: theme.textTheme.headlineMedium),
                    const SizedBox(height: 18),
                    Wrap(
                      spacing: 10,
                      runSpacing: 10,
                      children: [
                        _DetailPill(
                          icon: Icons.business_center_outlined,
                          label: job.contractType,
                        ),
                        _DetailPill(
                          icon: Icons.groups_outlined,
                          label:
                              '${job.positions ?? 1} poste${(job.positions ?? 1) > 1 ? 's' : ''}',
                        ),
                        _DetailPill(
                          icon: Icons.event_available_outlined,
                          label: job.deadline == null
                              ? 'Date limite non précisée'
                              : 'Expire le ${_formatDate(job.deadline!)}',
                        ),
                        _DetailPill(
                          icon: Icons.payments_outlined,
                          label: _salaryLabel(job) ?? 'Salaire à négocier',
                        ),
                        if (job.isRemoteAllowed)
                          const _DetailPill(
                            icon: Icons.public_rounded,
                            label: 'Télétravail possible',
                          ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 18),
              _Section(
                title: 'Description du poste',
                child: Text(job.description, style: theme.textTheme.bodyLarge),
              ),
              const SizedBox(height: 18),
              _Section(
                title: 'Profil recherché',
                child: Column(
                  children: [
                    _InfoRow(
                      icon: Icons.school_outlined,
                      label: 'Niveau minimum',
                      value:
                          _emptyFallback(job.requiredEducation, 'Non précisé'),
                    ),
                    _InfoRow(
                      icon: Icons.menu_book_outlined,
                      label: "Domaine d'étude",
                      value: _emptyFallback(job.educationField, 'Non précisé'),
                    ),
                    _InfoRow(
                      icon: Icons.timeline_rounded,
                      label: 'Expérience',
                      value: (job.requiredExperienceYears ?? 0) <= 0
                          ? 'Débutant accepté'
                          : '${job.requiredExperienceYears} an${job.requiredExperienceYears! > 1 ? 's' : ''} minimum',
                    ),
                  ],
                ),
              ),
              if (job.requiredSkills.isNotEmpty) ...[
                const SizedBox(height: 18),
                _Section(
                  title: 'Compétences recherchées',
                  child: Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: job.requiredSkills
                        .map((skill) => Chip(label: Text(skill)))
                        .toList(),
                  ),
                ),
              ],
              const SizedBox(height: 18),
              _Section(
                title: 'Documents à fournir',
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (job.requiredDocuments.isEmpty &&
                        job.recommendedDocuments.isEmpty)
                      Text(
                        'Aucun document spécifique n’a été indiqué pour cette offre.',
                        style: theme.textTheme.bodyMedium,
                      ),
                    if (job.requiredDocuments.isNotEmpty) ...[
                      Text('Obligatoires', style: theme.textTheme.labelLarge),
                      const SizedBox(height: 8),
                      _DocumentWrap(
                        documents: job.requiredDocuments,
                        required: true,
                      ),
                    ],
                    if (job.recommendedDocuments.isNotEmpty) ...[
                      const SizedBox(height: 14),
                      Text('Optionnels', style: theme.textTheme.labelLarge),
                      const SizedBox(height: 8),
                      _DocumentWrap(
                        documents: job.recommendedDocuments,
                        required: false,
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 18),
              _Section(
                title: 'Candidature',
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'Vérifiez votre profil et vos documents avant de postuler.',
                      style: theme.textTheme.bodyMedium,
                    ),
                    if (profileIncomplete) ...[
                      const SizedBox(height: 14),
                      OutlinedButton.icon(
                        onPressed: () => context.push('/profile'),
                        icon: const Icon(Icons.person_add_alt_rounded),
                        label: const Text('Compléter mon profil'),
                      ),
                    ],
                    if (job.applicationEmail != null &&
                        job.applicationEmail!.isNotEmpty) ...[
                      const SizedBox(height: 14),
                      OutlinedButton.icon(
                        onPressed: () => _openExternal(
                          context,
                          'mailto:${job.applicationEmail}?subject=Candidature - ${Uri.encodeComponent(job.title)}',
                        ),
                        icon: const Icon(Icons.mail_outline_rounded),
                        label: const Text('Postuler par email'),
                      ),
                    ],
                  ],
                ),
              ),
              if (job.externalUrl != null && job.externalUrl!.isNotEmpty) ...[
                const SizedBox(height: 18),
                FilledButton.icon(
                  onPressed: () => _openExternal(context, job.externalUrl!),
                  icon: const Icon(Icons.open_in_new_rounded),
                  label: const Text('Postuler sur le site source'),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 20, color: Theme.of(context).colorScheme.primary),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: Theme.of(context).textTheme.labelLarge),
                const SizedBox(height: 2),
                Text(value, style: Theme.of(context).textTheme.bodyMedium),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DocumentWrap extends StatelessWidget {
  const _DocumentWrap({
    required this.documents,
    required this.required,
  });

  final List<String> documents;
  final bool required;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: documents.map((document) {
        return Chip(
          avatar: Icon(
            required ? Icons.check_circle_outline : Icons.add_circle_outline,
            size: 18,
          ),
          label: Text(document),
        );
      }).toList(),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({
    required this.title,
    required this.child,
  });

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(
          color: isDark
              ? Colors.white.withValues(alpha: 0.12)
              : const Color(0xFFE2E8F0),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _DetailPill extends StatelessWidget {
  const _DetailPill({
    required this.icon,
    required this.label,
  });

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 17, color: Theme.of(context).colorScheme.primary),
          const SizedBox(width: 7),
          Text(label, style: Theme.of(context).textTheme.labelLarge),
        ],
      ),
    );
  }
}

String _formatDate(DateTime date) {
  final day = date.day.toString().padLeft(2, '0');
  final month = date.month.toString().padLeft(2, '0');

  return '$day/$month/${date.year}';
}

String? _salaryLabel(JobModel job) {
  final min = job.salaryMin;
  final max = job.salaryMax;

  if (min == null && max == null) {
    return null;
  }

  if (min != null && max != null && max > min) {
    return '$min - $max FCFA';
  }

  return '${min ?? max} FCFA';
}

String _emptyFallback(String? value, String fallback) {
  if (value == null || value.trim().isEmpty) {
    return fallback;
  }

  return value;
}

Future<void> _openExternal(BuildContext context, String rawUrl) async {
  final uri = Uri.tryParse(rawUrl);
  if (uri == null) {
    return;
  }

  final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
  if (!opened && context.mounted) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text(
            'Impossible d’ouvrir ce lien. Vérifiez votre connexion internet.'),
      ),
    );
  }
}

_MatchTone _matchTone(int score) {
  if (score >= 75) {
    return const _MatchTone(
      background: Color(0xFFE7F3EC),
      border: Color(0xFF9FCDB2),
      foreground: Color(0xFF2F6F5E),
    );
  }

  if (score >= 50) {
    return const _MatchTone(
      background: Color(0xFFFFF4D8),
      border: Color(0xFFE6C46E),
      foreground: Color(0xFF8A6516),
    );
  }

  return const _MatchTone(
    background: Color(0xFFFFE8E3),
    border: Color(0xFFE7A99C),
    foreground: Color(0xFF9A3E31),
  );
}

class _MatchTone {
  const _MatchTone({
    required this.background,
    required this.border,
    required this.foreground,
  });

  final Color background;
  final Color border;
  final Color foreground;
}
