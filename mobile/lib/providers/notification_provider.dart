import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/notification_model.dart';
import '../services/notification_service.dart';
import 'auth_provider.dart';

final notificationServiceProvider = Provider<NotificationService>((ref) {
  return NotificationService(ref.watch(apiServiceProvider));
});

final notificationProvider = FutureProvider<List<NotificationModel>>((ref) {
  return ref.watch(notificationServiceProvider).fetchNotifications();
});
