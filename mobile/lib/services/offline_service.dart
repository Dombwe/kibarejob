import 'api_service.dart';
import 'storage_service.dart';

class OfflineService {
  const OfflineService(this._api, this._storage);

  final ApiService _api;
  final StorageService _storage;

  Future<void> cacheProfile(Map<String, dynamic> profile) {
    return _storage.saveProfile(profile);
  }

  Map<String, dynamic>? get cachedProfile => _storage.cachedProfile;

  Future<void> queueSwipe({
    required String offerId,
    required String direction,
  }) async {
    final queue = _storage.queuedSwipes;
    queue.add({
      'offerId': offerId,
      'direction': direction,
      'queuedAt': DateTime.now().toIso8601String(),
    });
    await _storage.saveQueuedSwipes(queue);
  }

  Future<int> flushSwipeQueue() async {
    final queue = _storage.queuedSwipes;
    final remaining = <Map<String, dynamic>>[];
    var sent = 0;

    for (final item in queue) {
      try {
        await _api.postJson(
          '/api/jobs/${item['offerId']}/swipe',
          data: {'direction': item['direction']},
        );
        sent++;
      } catch (_) {
        remaining.add(item);
      }
    }

    await _storage.saveQueuedSwipes(remaining);
    return sent;
  }
}
