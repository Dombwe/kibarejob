import '../models/swipe_model.dart';
import 'api_service.dart';
import 'storage_service.dart';

class ApplicationService {
  const ApplicationService(this._api, this._storage);

  final ApiService _api;
  final StorageService _storage;

  Future<List<SwipeModel>> fetchMatches() async {
    try {
      final data = await _api.getJson('/api/matches?limit=100');
      final rawMatches = (data['matches'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList();
      await _storage.saveCachedMatches(rawMatches);

      return rawMatches.map(SwipeModel.fromJson).toList();
    } catch (error) {
      if (!_shouldUseCache(error)) {
        rethrow;
      }

      final cached = _storage.cachedMatches;
      if (cached.isEmpty) {
        rethrow;
      }

      return cached.map(SwipeModel.fromJson).toList();
    }
  }

  Future<SwipeModel> fetchMatch(String id) async {
    try {
      final data = await _api.getJson('/api/matches/$id');
      return SwipeModel.fromJson(
        Map<String, dynamic>.from(data['match'] as Map),
      );
    } catch (error) {
      if (!_shouldUseCache(error)) {
        rethrow;
      }

      final cached = _storage.cachedMatches
          .map(SwipeModel.fromJson)
          .where((match) => match.id == id)
          .firstOrNull;
      if (cached == null) {
        rethrow;
      }

      return cached;
    }
  }

  Future<void> deleteMatch(String id) {
    return _api.delete('/api/matches/$id');
  }

  Future<SwipeModel> resendMatch(String id) async {
    final data = await _api.postJson('/api/matches/$id/resend');
    return SwipeModel.fromJson(
      Map<String, dynamic>.from(data['match'] as Map),
    );
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
