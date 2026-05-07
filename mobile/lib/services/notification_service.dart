import 'package:dio/dio.dart';

import '../models/notification_model.dart';
import 'api_service.dart';

class NotificationService {
  const NotificationService(this._api);

  final ApiService _api;

  Future<List<NotificationModel>> fetchNotifications() async {
    try {
      final data = await _api.getJson('/api/notifications');
      return (data['notifications'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map((item) => NotificationModel.fromJson(Map<String, dynamic>.from(item)))
          .toList();
    } on DioException catch (error) {
      if (error.response?.statusCode == 404) {
        return const [];
      }
      rethrow;
    }
  }
}
