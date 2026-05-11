import '../models/notification_model.dart';
import 'api_service.dart';

class NotificationService {
  const NotificationService(this._api);

  final ApiService _api;

  Future<List<NotificationModel>> fetchNotifications() async {
    final data = await _api.getJson('/api/notifications');
    return (data['notifications'] as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) =>
            NotificationModel.fromJson(Map<String, dynamic>.from(item)))
        .toList();
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
}
