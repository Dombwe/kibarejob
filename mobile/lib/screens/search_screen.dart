import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/job_model.dart';
import '../providers/job_provider.dart';
import '../theme/app_theme.dart';
import '../theme/responsive.dart';
import '../widgets/app_bottom_navigation.dart';
import '../widgets/kibare_tab_app_bar.dart';
import '../widgets/loading_widget.dart';

class SearchScreen extends ConsumerStatefulWidget {
  const SearchScreen({super.key});

  @override
  ConsumerState<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends ConsumerState<SearchScreen> {
  final _searchController = TextEditingController();
  late Future<List<JobModel>> _jobsFuture;
  String _query = '';

  @override
  void initState() {
    super.initState();
    _jobsFuture = _loadJobs();
    _searchController.addListener(() {
      setState(() => _query = _searchController.text.trim().toLowerCase());
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<List<JobModel>> _loadJobs() async {
    final result = await ref.read(jobServiceProvider).fetchFeed(limit: 30);
    return result.jobs;
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: const KibareTabAppBar(),
      bottomNavigationBar: const AppBottomNavigation(currentTab: AppTab.search),
      body: FutureBuilder<List<JobModel>>(
        future: _jobsFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const LoadingWidget(message: 'Recherche des offres...');
          }

          if (snapshot.hasError) {
            return _SearchStateMessage(
              icon: Icons.wifi_off_rounded,
              title: 'Recherche indisponible',
              message: snapshot.error.toString(),
              actionLabel: 'Réessayer',
              onAction: () => setState(() => _jobsFuture = _loadJobs()),
            );
          }

          final jobs = _filteredJobs(snapshot.data ?? const <JobModel>[]);

          return RefreshIndicator(
            onRefresh: () async {
              setState(() => _jobsFuture = _loadJobs());
              await _jobsFuture;
            },
            child: Responsive.centeredContent(
              context: context,
              child: ListView(
                padding: EdgeInsets.fromLTRB(
                  Responsive.horizontalPadding(context),
                  10,
                  Responsive.horizontalPadding(context),
                  24,
                ),
                children: [
                  TextField(
                    controller: _searchController,
                    textInputAction: TextInputAction.search,
                    decoration: const InputDecoration(
                      prefixIcon: Icon(Icons.search_rounded),
                      labelText: 'Rechercher une offre',
                      hintText: 'Titre, entreprise, ville, compétence...',
                    ),
                  ),
                  const SizedBox(height: 18),
                  Text(
                    _query.isEmpty
                        ? 'Offres récentes'
                        : '${jobs.length} résultat${jobs.length > 1 ? 's' : ''}',
                    style: theme.textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 12),
                  if (jobs.isEmpty)
                    const _SearchStateMessage(
                      icon: Icons.search_off_rounded,
                      title: 'Aucune offre trouvée',
                      message:
                          'Essayez avec un autre titre, une ville, une entreprise ou une compétence.',
                    )
                  else
                    ...jobs.map(
                      (job) => _SearchJobTile(
                        job: job,
                        onTap: () =>
                            context.push('/jobs/${job.id}', extra: job),
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

  List<JobModel> _filteredJobs(List<JobModel> jobs) {
    if (_query.isEmpty) {
      return jobs;
    }

    return jobs.where((job) {
      final haystack = [
        job.title,
        job.companyName ?? '',
        job.location,
        job.contractType,
        job.description,
        ...job.requiredSkills,
      ].join(' ').toLowerCase();

      return haystack.contains(_query);
    }).toList();
  }
}

class _SearchJobTile extends StatelessWidget {
  const _SearchJobTile({
    required this.job,
    required this.onTap,
  });

  final JobModel job;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(24),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color:
                      AppColors.accent.withValues(alpha: isDark ? 0.20 : 0.30),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: const Icon(Icons.business_center_outlined),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      job.title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodyLarge?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      job.companyName ?? 'Entreprise',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _MetaChip(
                          icon: Icons.location_on_outlined,
                          label: job.location.isEmpty
                              ? 'Non précisé'
                              : job.location,
                        ),
                        _MetaChip(
                          icon: Icons.work_outline_rounded,
                          label: job.contractType.isEmpty
                              ? 'Contrat'
                              : job.contractType.replaceAll('_', ' '),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              const Icon(Icons.chevron_right_rounded),
            ],
          ),
        ),
      ),
    );
  }
}

class _MetaChip extends StatelessWidget {
  const _MetaChip({
    required this.icon,
    required this.label,
  });

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return ConstrainedBox(
      constraints: BoxConstraints(
        maxWidth: MediaQuery.sizeOf(context).width * 0.58,
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 15, color: Theme.of(context).colorScheme.primary),
          const SizedBox(width: 4),
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.labelSmall,
            ),
          ),
        ],
      ),
    );
  }
}

class _SearchStateMessage extends StatelessWidget {
  const _SearchStateMessage({
    required this.icon,
    required this.title,
    required this.message,
    this.actionLabel,
    this.onAction,
  });

  final IconData icon;
  final String title;
  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 44, horizontal: 12),
      child: Column(
        children: [
          Icon(icon, size: 46, color: Theme.of(context).colorScheme.primary),
          const SizedBox(height: 14),
          Text(
            title,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 8),
          Text(
            message,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          if (actionLabel != null && onAction != null) ...[
            const SizedBox(height: 18),
            FilledButton(
              onPressed: onAction,
              child: Text(actionLabel!),
            ),
          ],
        ],
      ),
    );
  }
}
