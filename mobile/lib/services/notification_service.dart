import '../models/notification_model.dart';
import 'api_service.dart';
import 'storage_service.dart';

class NotificationService {
  const NotificationService(this._api, this._storage);

  final ApiService _api;
  final StorageService _storage;

  Future<List<NotificationModel>> fetchNotifications() async {
    try {
      final data = await _api.getJson('/api/notifications?limit=100');
      final rawNotifications =
          (data['notifications'] as List<dynamic>? ?? const [])
              .whereType<Map>()
              .map((item) => Map<String, dynamic>.from(item))
              .toList();
      await _storage.saveCachedNotifications(rawNotifications);

      return rawNotifications.map(NotificationModel.fromJson).toList();
    } catch (error) {
      final cached = _storage.cachedNotifications;
      if (cached.isEmpty || !_shouldUseCache(error)) {
        rethrow;
      }

      return cached.map(NotificationModel.fromJson).toList();
    }
  }

  Future<NotificationModel> markAsRead(String id) async {
    final data = await _api.postJson('/api/notifications/$id/read');
    return NotificationModel.fromJson(
      Map<String, dynamic>.from(data['notification'] as Map),
    );
  }

  Future<void> markAllAsRead() async {
    await _api.postJson('/api/notifications/read-all');
  }

  bool _shouldUseCache(Object error) {
    if (error is ApiException) {
      return error.statusCode == null;
    }

    final message = error.toString().toLowerCase();
    return message.contains('connection') ||
        message.contains('inaccessible') ||
        message.contains('socket') ||
        message.contains('timeout') ||
        message.contains('network') ||
        message.contains('reseau') ||
        message.contains('réseau');
  }
}
