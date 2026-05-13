import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/swipe_model.dart';
import '../providers/matches_provider.dart';
import '../theme/app_theme.dart';
import '../theme/responsive.dart';
import '../widgets/app_bottom_navigation.dart';
import '../widgets/kibare_tab_app_bar.dart';
import '../widgets/loading_widget.dart';
import '../widgets/state_message.dart';

class MatchesScreen extends ConsumerStatefulWidget {
  const MatchesScreen({super.key});

  @override
  ConsumerState<MatchesScreen> createState() => _MatchesScreenState();
}

class _MatchesScreenState extends ConsumerState<MatchesScreen> {
  String _filter = 'all';

  @override
  Widget build(BuildContext context) {
    final matchesState = ref.watch(matchesProvider);

    return Scaffold(
      appBar: const KibareTabAppBar(),
      bottomNavigationBar:
          const AppBottomNavigation(currentTab: AppTab.applications),
      body: matchesState.when(
        loading: () => const LoadingWidget(),
        error: (error, _) => StateMessage(
          icon: Icons.favorite_border_rounded,
          title: 'Candidatures indisponibles',
          message:
              'Vos candidatures apparaitront ici apres une premiere synchronisation avec internet.',
          actionLabel: 'Reessayer',
          onAction: () => ref.invalidate(matchesProvider),
        ),
        data: (matches) {
          final visible = _filtered(matches);

          return RefreshIndicator(
            onRefresh: () => ref.refresh(matchesProvider.future),
            child: Responsive.centeredContent(
              context: context,
              child: CustomScrollView(
                slivers: [
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: EdgeInsets.fromLTRB(
                        Responsive.horizontalPadding(context),
                        18,
                        Responsive.horizontalPadding(context),
                        8,
                      ),
                      child: Text(
                        'Mes Candidatures',
                        style: Theme.of(context).textTheme.headlineMedium,
                      ),
                    ),
                  ),
                  SliverToBoxAdapter(
                    child: _FilterBar(
                      current: _filter,
                      matches: matches,
                      onChanged: (value) => setState(() => _filter = value),
                    ),
                  ),
                  if (visible.isEmpty)
                    const SliverFillRemaining(
                      hasScrollBody: false,
                      child: StateMessage(
                        icon: Icons.inbox_outlined,
                        title: 'Rien ici pour le moment',
                        message:
                            'Changez de filtre ou postulez a une offre pour la voir apparaitre ici.',
                      ),
                    )
                  else
                    SliverPadding(
                      padding: EdgeInsets.fromLTRB(
                        Responsive.horizontalPadding(context),
                        14,
                        Responsive.horizontalPadding(context),
                        20,
                      ),
                      sliver: SliverList.builder(
                        itemCount: visible.length,
                        itemBuilder: (context, index) => _ApplicationCard(
                          application: visible[index],
                          onOpen: () => context.push(
                            '/matches/${visible[index].id}',
                            extra: visible[index],
                          ),
                          onDelete: () => _deleteApplication(visible[index]),
                        ),
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

  List<SwipeModel> _filtered(List<SwipeModel> matches) {
    if (_filter == 'all') {
      return matches;
    }
    if (_filter == 'sent') {
      return matches.where((item) => item.email.sent).toList();
    }
    return matches.where((item) => item.status == _filter).toList();
  }

  Future<void> _deleteApplication(SwipeModel application) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Supprimer la candidature ?'),
        content: Text(
          'La candidature pour "${application.offerTitle ?? 'cette offre'}" sera retirée de votre liste.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Supprimer'),
          ),
        ],
      ),
    );

    if (confirmed != true) {
      return;
    }

    try {
      await ref.read(applicationServiceProvider).deleteMatch(application.id);
      ref.invalidate(matchesProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Candidature supprimée.')),
        );
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Suppression impossible : $error')),
        );
      }
    }
  }
}

class _FilterBar extends StatelessWidget {
  const _FilterBar({
    required this.current,
    required this.matches,
    required this.onChanged,
  });

  final String current;
  final List<SwipeModel> matches;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final filters = [
      _FilterItem('all', 'Toutes', matches.length),
      _FilterItem(
          'sent', 'Envoyées', matches.where((m) => m.email.sent).length),
      _FilterItem('viewed', 'Consultées',
          matches.where((m) => m.status == 'viewed').length),
      _FilterItem('interview', 'Entretiens',
          matches.where((m) => m.status == 'interview').length),
      _FilterItem('hired', 'Retenues',
          matches.where((m) => m.status == 'hired').length),
      _FilterItem('rejected', 'Refusées',
          matches.where((m) => m.status == 'rejected').length),
    ];

    return SizedBox(
      height: 66,
      child: ListView.separated(
        padding: EdgeInsets.symmetric(
          horizontal: Responsive.horizontalPadding(context),
          vertical: 10,
        ),
        scrollDirection: Axis.horizontal,
        itemCount: filters.length,
        separatorBuilder: (_, __) => const SizedBox(width: 10),
        itemBuilder: (context, index) {
          final filter = filters[index];
          final selected = current == filter.key;
          final isDark = Theme.of(context).brightness == Brightness.dark;

          return ChoiceChip(
            selected: selected,
            label: Text('${filter.label} (${filter.count})'),
            onSelected: (_) => onChanged(filter.key),
            selectedColor: AppColors.primary,
            backgroundColor:
                isDark ? const Color(0xFF1E293B) : const Color(0xFFEFF4F8),
            labelStyle: TextStyle(
              color: selected
                  ? Colors.white
                  : (isDark ? const Color(0xFFE2E8F0) : AppColors.primary),
              fontWeight: FontWeight.w900,
            ),
            side: BorderSide(
              color: selected
                  ? AppColors.primary
                  : (isDark
                      ? Colors.white.withValues(alpha: 0.10)
                      : const Color(0xFFD8E1EA)),
            ),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(22),
            ),
          );
        },
      ),
    );
  }
}

class _ApplicationCard extends StatelessWidget {
  const _ApplicationCard({
    required this.application,
    required this.onOpen,
    required this.onDelete,
  });

  final SwipeModel application;
  final VoidCallback onOpen;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final tone = _statusTone(application.status, application.email.sent);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final surface = isDark ? const Color(0xFF172033) : const Color(0xFFFFFFFF);
    final outline =
        isDark ? Colors.white.withValues(alpha: 0.10) : const Color(0xFFE2E8F0);

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: surface,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: outline),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.24 : 0.07),
            blurRadius: 24,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(22),
          onTap: onOpen,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 10, 16),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Container(
                  width: 58,
                  height: 58,
                  decoration: BoxDecoration(
                    color: tone.color.withValues(alpha: isDark ? 0.18 : 0.10),
                    borderRadius: BorderRadius.circular(18),
                    border:
                        Border.all(color: tone.color.withValues(alpha: 0.18)),
                  ),
                  alignment: Alignment.center,
                  child: Text(
                    _initials(
                        application.offerCompany ?? application.offerTitle),
                    style: TextStyle(
                      fontWeight: FontWeight.w900,
                      color: tone.color,
                    ),
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Text(
                              application.offerTitle ?? 'Offre',
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(context)
                                  .textTheme
                                  .titleMedium
                                  ?.copyWith(fontWeight: FontWeight.w900),
                            ),
                          ),
                          const SizedBox(width: 8),
                          _StatusBadge(tone: tone),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 7,
                        runSpacing: 4,
                        children: [
                          _Meta(
                              icon: Icons.business_outlined,
                              text: application.offerCompany ?? 'Entreprise'),
                          _Meta(
                              icon: Icons.location_on_outlined,
                              text: application.offerLocation ??
                                  'Lieu non précisé'),
                          _Meta(
                              icon: application.email.sent
                                  ? Icons.mark_email_read_outlined
                                  : Icons.mark_email_unread_outlined,
                              text: application.email.sent
                                  ? 'Email transmis'
                                  : 'Email a renvoyer'),
                        ],
                      ),
                      const SizedBox(height: 10),
                      Row(
                        children: [
                          Icon(
                            Icons.schedule_outlined,
                            size: 15,
                            color:
                                Theme.of(context).textTheme.bodyMedium?.color,
                          ),
                          const SizedBox(width: 4),
                          Expanded(
                            child: Text(
                              _relativeDate(application.sentAt),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 5,
                            ),
                            decoration: BoxDecoration(
                              color: _matchColor(application.matchScore ?? 0)
                                  .withValues(alpha: 0.16),
                              borderRadius: BorderRadius.circular(999),
                            ),
                            child: Text(
                              '${application.matchScore ?? 0}% match',
                              style: TextStyle(
                                color: _matchColor(application.matchScore ?? 0),
                                fontWeight: FontWeight.w900,
                                fontSize: 12,
                              ),
                            ),
                          ),
                        ],
                      ),
                      if (application.status == 'interview') ...[
                        const SizedBox(height: 12),
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color:
                                const Color(0xFF8B5CF6).withValues(alpha: 0.10),
                            borderRadius: BorderRadius.circular(16),
                          ),
                          child: const Text(
                            'Entretien à confirmer avec le recruteur',
                            style: TextStyle(
                              color: Color(0xFF7C3AED),
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                Column(
                  children: [
                    IconButton(
                      tooltip: 'Supprimer',
                      onPressed: onDelete,
                      icon: const Icon(Icons.delete_outline_rounded),
                    ),
                    const Icon(Icons.chevron_right_rounded),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.tone});

  final _StatusTone tone;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: tone.background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(tone.icon, size: 14, color: tone.color),
          const SizedBox(width: 4),
          Text(
            tone.label,
            style: TextStyle(
              color: tone.color,
              fontWeight: FontWeight.w900,
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }
}

class _Meta extends StatelessWidget {
  const _Meta({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 178),
      child: Row(
        mainAxisSize: MainAxisSize.max,
        children: [
          Icon(icon, size: 15, color: Theme.of(context).colorScheme.primary),
          const SizedBox(width: 4),
          Expanded(
            child: Text(
              text,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ),
        ],
      ),
    );
  }
}

class _FilterItem {
  const _FilterItem(this.key, this.label, this.count);

  final String key;
  final String label;
  final int count;
}

class _StatusTone {
  const _StatusTone({
    required this.label,
    required this.color,
    required this.background,
    required this.icon,
  });

  final String label;
  final Color color;
  final Color background;
  final IconData icon;
}

_StatusTone _statusTone(String status, bool emailSent) {
  if (status == 'interview') {
    return const _StatusTone(
      label: 'Entretien',
      color: Color(0xFF7C3AED),
      background: Color(0xFFF3E8FF),
      icon: Icons.calendar_month_outlined,
    );
  }
  if (status == 'viewed') {
    return const _StatusTone(
      label: 'Consultée',
      color: Color(0xFFB45309),
      background: Color(0xFFFEF3C7),
      icon: Icons.visibility_outlined,
    );
  }
  if (status == 'hired') {
    return const _StatusTone(
      label: 'Retenue',
      color: Color(0xFF047857),
      background: Color(0xFFD1FAE5),
      icon: Icons.check_circle_outline,
    );
  }
  if (status == 'rejected') {
    return const _StatusTone(
      label: 'Refusée',
      color: Color(0xFFB91C1C),
      background: Color(0xFFFEE2E2),
      icon: Icons.cancel_outlined,
    );
  }

  return _StatusTone(
    label: emailSent ? 'Envoyée' : 'Préparée',
    color: const Color(0xFF2563EB),
    background: const Color(0xFFDBEAFE),
    icon: emailSent ? Icons.send_outlined : Icons.pending_actions_outlined,
  );
}

Color _matchColor(int score) {
  if (score >= 75) {
    return const Color(0xFF047857);
  }
  if (score >= 50) {
    return const Color(0xFFB45309);
  }
  return const Color(0xFFB91C1C);
}

String _initials(String? value) {
  final words = (value ?? 'KJ')
      .trim()
      .split(RegExp(r'\s+'))
      .where((word) => word.isNotEmpty)
      .toList();
  if (words.isEmpty) {
    return 'KJ';
  }
  if (words.length == 1) {
    return words.first
        .substring(0, words.first.length >= 2 ? 2 : 1)
        .toUpperCase();
  }
  return '${words.first[0]}${words[1][0]}'.toUpperCase();
}

String _relativeDate(DateTime? date) {
  if (date == null) {
    return 'Date non disponible';
  }

  final diff = DateTime.now().difference(date.toLocal());
  if (diff.inMinutes < 1) {
    return 'À l’instant';
  }
  if (diff.inHours < 1) {
    return 'Il y a ${diff.inMinutes} min';
  }
  if (diff.inDays < 1) {
    return 'Il y a ${diff.inHours} h';
  }
  return 'Il y a ${diff.inDays} jour${diff.inDays > 1 ? 's' : ''}';
}
