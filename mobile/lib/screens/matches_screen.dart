import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../providers/matches_provider.dart';
import '../theme/responsive.dart';
import '../widgets/app_bottom_navigation.dart';
import '../widgets/kibare_tab_app_bar.dart';
import '../widgets/loading_widget.dart';

class MatchesScreen extends ConsumerWidget {
  const MatchesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final matchesState = ref.watch(matchesProvider);

    return Scaffold(
      appBar: const KibareTabAppBar(),
      bottomNavigationBar:
          const AppBottomNavigation(currentTab: AppTab.applications),
      body: matchesState.when(
        loading: () => const LoadingWidget(),
        error: (error, _) => Center(child: Text(error.toString())),
        data: (matches) {
          if (matches.isEmpty) {
            return const Center(child: Text('Aucune candidature envoyee'));
          }
          return RefreshIndicator(
            onRefresh: () => ref.refresh(matchesProvider.future),
            child: Responsive.centeredContent(
              context: context,
              child: ListView.builder(
                padding: EdgeInsets.symmetric(
                  horizontal: Responsive.horizontalPadding(context),
                  vertical: 10,
                ),
                itemCount: matches.length,
                itemBuilder: (context, index) {
                  final match = matches[index];
                  return Card(
                    child: ListTile(
                      leading: CircleAvatar(
                          child: Text('${match.matchScore ?? 0}%')),
                      title: Text(match.offerTitle ?? 'Offre'),
                      subtitle: Text(match.status),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () =>
                          context.push('/matches/${match.id}', extra: match),
                    ),
                  );
                },
              ),
            ),
          );
        },
      ),
    );
  }
}
