import 'job_service.dart';

class MatchingService {
  const MatchingService(this._jobService);

  final JobService _jobService;

  Future<int> getMatchScore(String offerId) async {
    final feed = await _jobService.fetchFeed(limit: 30);
    for (final job in feed.jobs) {
      if (job.id == offerId) {
        return job.matchScore ?? 0;
      }
    }
    return 0;
  }
}
