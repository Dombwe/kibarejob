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
}
