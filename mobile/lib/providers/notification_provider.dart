import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/notification_model.dart';
import '../services/notification_service.dart';
import 'auth_provider.dart';

final notificationServiceProvider = Provider<NotificationService>((ref) {
  return NotificationService(
    ref.watch(apiServiceProvider),
    ref.watch(storageServiceProvider),
  );
});

final notificationProvider =
    AsyncNotifierProvider<NotificationNotifier, List<NotificationModel>>(
  NotificationNotifier.new,
);

class NotificationNotifier extends AsyncNotifier<List<NotificationModel>> {
  @override
  Future<List<NotificationModel>> build() {
    return ref.watch(notificationServiceProvider).fetchNotifications();
  }

  Future<void> refresh() async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(
      () => ref.read(notificationServiceProvider).fetchNotifications(),
    );
  }

  Future<void> markAsRead(String id) async {
    final current = state.valueOrNull ?? const <NotificationModel>[];
    final optimistic = current
        .map((notification) => notification.id == id
            ? notification.copyWith(isRead: true)
            : notification)
        .toList();
    state = AsyncValue.data(optimistic);
    await ref.read(storageServiceProvider).saveCachedNotifications(
          optimistic.map((notification) => notification.toJson()).toList(),
        );

    try {
      final updated =
          await ref.read(notificationServiceProvider).markAsRead(id);
      final next = (state.valueOrNull ?? current)
          .map((notification) => notification.id == id ? updated : notification)
          .toList();
      state = AsyncValue.data(next);
      await ref.read(storageServiceProvider).saveCachedNotifications(
            next.map((notification) => notification.toJson()).toList(),
          );
    } catch (_) {
      state = AsyncValue.data(optimistic);
    }
  }

  Future<void> markAllAsRead() async {
    final current = state.valueOrNull ?? const <NotificationModel>[];
    final optimistic = current
        .map((notification) => notification.copyWith(isRead: true))
        .toList();
    state = AsyncValue.data(optimistic);
    await ref.read(storageServiceProvider).saveCachedNotifications(
          optimistic.map((notification) => notification.toJson()).toList(),
        );

    try {
      await ref.read(notificationServiceProvider).markAllAsRead();
    } catch (_) {
      state = AsyncValue.data(optimistic);
    }
  }
}
