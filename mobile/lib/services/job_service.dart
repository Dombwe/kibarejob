import '../models/job_model.dart';
import 'api_service.dart';

class JobService {
  const JobService(this._api);

  final ApiService _api;

  Future<FeedResult> fetchFeed({int cursor = 0, int limit = 6}) async {
    final data =
        await _api.getJson('/api/jobs/feed?cursor=$cursor&limit=$limit');
    final jobs = (data['items'] as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => JobModel.fromJson(Map<String, dynamic>.from(item)))
        .toList();

    return FeedResult(
      jobs: jobs,
      nextCursor: int.tryParse(data['nextCursor']?.toString() ?? ''),
      hasMore: data['hasMore'] == true,
    );
  }

  Future<void> swipe({
    required String offerId,
    required String direction,
  }) async {
    await _api.postJson(
      '/api/jobs/$offerId/swipe',
      data: {'direction': direction},
    );
  }
}

class FeedResult {
  const FeedResult({
    required this.jobs,
    required this.nextCursor,
    required this.hasMore,
  });

  final List<JobModel> jobs;
  final int? nextCursor;
  final bool hasMore;
}
