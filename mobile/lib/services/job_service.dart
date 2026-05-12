import '../models/job_model.dart';
import 'api_service.dart';

class SwipeResult {
  const SwipeResult({
    required this.accepted,
    this.swipeId,
    this.direction,
    this.queued = false,
  });

  final bool accepted;
  final String? swipeId;
  final String? direction;
  final bool queued;
}

class ProfileCompletionRequiredException implements Exception {
  const ProfileCompletionRequiredException({
    required this.message,
    this.missingProfileItems = const [],
    this.missingDocuments = const [],
    this.requiredDocuments = const [],
  });

  final String message;
  final List<String> missingProfileItems;
  final List<String> missingDocuments;
  final List<String> requiredDocuments;

  @override
  String toString() => message;
}

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

  Future<SwipeResult> swipe({
    required String offerId,
    required String direction,
  }) async {
    try {
      final data = await _api.postJson(
        '/api/jobs/$offerId/swipe',
        data: {'direction': direction},
      );

      return SwipeResult(
        accepted: data['accepted'] == true,
        swipeId: data['swipeId']?.toString(),
        direction: data['direction']?.toString(),
        queued: data['queued'] == true,
      );
    } on ApiException catch (error) {
      final data = error.data;
      if (data?['code'] == 'profile_completion_required') {
        throw ProfileCompletionRequiredException(
          message: data?['message']?.toString() ?? error.message,
          missingProfileItems: _stringList(data?['missingProfileItems']),
          missingDocuments: _stringList(data?['missingDocuments']),
          requiredDocuments: _stringList(data?['requiredDocuments']),
        );
      }

      rethrow;
    }
  }

  static List<String> _stringList(Object? value) {
    if (value is! List) {
      return const [];
    }

    return value
        .map((item) => item.toString())
        .where((item) => item.trim().isNotEmpty)
        .toList();
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
