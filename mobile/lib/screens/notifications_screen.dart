import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../providers/notification_provider.dart';
import '../theme/responsive.dart';
import '../widgets/loading_widget.dart';
import '../widgets/state_message.dart';

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final notificationsState = ref.watch(notificationProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          TextButton(
            onPressed: () =>
                ref.read(notificationProvider.notifier).markAllAsRead(),
            child: const Text('Tout lire'),
          ),
        ],
      ),
      body: notificationsState.when(
        loading: () => const LoadingWidget(),
        error: (error, _) => StateMessage(
          icon: Icons.notifications_off_outlined,
          title: 'Notifications indisponibles',
          message:
              'Vos notifications apparaitront ici apres une premiere synchronisation avec internet.',
          actionLabel: 'Reessayer',
          onAction: () => ref.invalidate(notificationProvider),
        ),
        data: (notifications) {
          if (notifications.isEmpty) {
            return const StateMessage(
              icon: Icons.notifications_none_rounded,
              title: 'Aucune notification',
              message:
                  'Les nouvelles informations importantes apparaitront ici.',
            );
          }
          return RefreshIndicator(
            onRefresh: () => ref.read(notificationProvider.notifier).refresh(),
            child: Responsive.centeredContent(
              context: context,
              child: ListView.builder(
                padding: EdgeInsets.symmetric(
                  horizontal: Responsive.horizontalPadding(context),
                  vertical: 10,
                ),
                itemCount: notifications.length,
                itemBuilder: (context, index) {
                  final notification = notifications[index];
                  return Card(
                    child: ListTile(
                      leading: Icon(
                        notification.isRead
                            ? Icons.notifications_none
                            : Icons.notifications_active,
                      ),
                      title: Text(notification.title),
                      subtitle: Text(notification.message),
                      trailing: notification.isRead
                          ? null
                          : const Icon(Icons.circle, size: 10),
                      onTap: () async {
                        await ref
                            .read(notificationProvider.notifier)
                            .markAsRead(notification.id);
                        final route = notification.data['route']?.toString();
                        if (context.mounted &&
                            route != null &&
                            route.isNotEmpty) {
                          context.push(route);
                        }
                      },
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
