import '../models/swipe_model.dart';
import 'api_service.dart';

class ApplicationService {
  const ApplicationService(this._api);

  final ApiService _api;

  Future<List<SwipeModel>> fetchMatches() async {
    final data = await _api.getJson('/api/matches');
    return (data['matches'] as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => SwipeModel.fromJson(Map<String, dynamic>.from(item)))
        .toList();
  }

  Future<SwipeModel> fetchMatch(String id) async {
    final data = await _api.getJson('/api/matches/$id');
    return SwipeModel.fromJson(
      Map<String, dynamic>.from(data['match'] as Map),
    );
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
}
