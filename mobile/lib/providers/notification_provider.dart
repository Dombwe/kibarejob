import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/notification_model.dart';
import '../services/notification_service.dart';
import 'auth_provider.dart';

final notificationServiceProvider = Provider<NotificationService>((ref) {
  return NotificationService(ref.watch(apiServiceProvider));
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
    state = AsyncValue.data(
      current
          .map((notification) => notification.id == id
              ? notification.copyWith(isRead: true)
              : notification)
          .toList(),
    );

    try {
      final updated =
          await ref.read(notificationServiceProvider).markAsRead(id);
      state = AsyncValue.data(
        (state.valueOrNull ?? current)
            .map((notification) =>
                notification.id == id ? updated : notification)
            .toList(),
      );
    } catch (error, stackTrace) {
      state = AsyncValue.error(error, stackTrace);
    }
  }

  Future<void> markAllAsRead() async {
    final current = state.valueOrNull ?? const <NotificationModel>[];
    state = AsyncValue.data(
      current
          .map((notification) => notification.copyWith(isRead: true))
          .toList(),
    );

    try {
      await ref.read(notificationServiceProvider).markAllAsRead();
    } catch (error, stackTrace) {
      state = AsyncValue.error(error, stackTrace);
    }
  }
}
