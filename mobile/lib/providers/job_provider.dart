import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/job_model.dart';
import '../services/job_service.dart';
import '../services/matching_service.dart';
import '../services/offline_service.dart';
import '../services/offline_sync_worker.dart';
import 'auth_provider.dart';

final jobServiceProvider = Provider<JobService>((ref) {
  return JobService(ref.watch(apiServiceProvider));
});

final matchingServiceProvider = Provider<MatchingService>((ref) {
  return MatchingService(ref.watch(jobServiceProvider));
});

final offlineServiceProvider = Provider<OfflineService>((ref) {
  return OfflineService(
    ref.watch(apiServiceProvider),
    ref.watch(storageServiceProvider),
  );
});

final offlineSyncWorkerProvider = Provider<OfflineSyncWorker>((ref) {
  final worker = OfflineSyncWorker(ref.watch(offlineServiceProvider));
  ref.onDispose(worker.stop);
  return worker;
});

final feedProvider = AsyncNotifierProvider<FeedNotifier, List<JobModel>>(
  FeedNotifier.new,
);

class FeedNotifier extends AsyncNotifier<List<JobModel>> {
  int _cursor = 0;
  bool _hasMore = true;

  @override
  Future<List<JobModel>> build() async {
    ref.read(offlineSyncWorkerProvider).start();
    return refresh();
  }

  Future<List<JobModel>> refresh() async {
    _cursor = 0;
    _hasMore = true;
    final result = await ref.read(jobServiceProvider).fetchFeed();
    _cursor = result.nextCursor ?? 0;
    _hasMore = result.hasMore;
    return result.jobs;
  }

  Future<void> swipe(JobModel job, String direction) async {
    final currentJobs = state.valueOrNull ?? const <JobModel>[];
    state = AsyncValue.data(
      currentJobs.where((item) => item.id != job.id).toList(),
    );

    try {
      await ref.read(jobServiceProvider).swipe(
            offerId: job.id,
            direction: direction,
          );
    } catch (_) {
      await ref.read(offlineServiceProvider).queueSwipe(
            offerId: job.id,
            direction: direction,
          );
    }

    if ((state.valueOrNull?.length ?? 0) <= 3 && _hasMore) {
      await loadMore();
    }
  }

  Future<void> loadMore() async {
    if (!_hasMore) {
      return;
    }
    final result = await ref.read(jobServiceProvider).fetchFeed(cursor: _cursor);
    _cursor = result.nextCursor ?? _cursor;
    _hasMore = result.hasMore;
    state = AsyncValue.data([
      ...state.valueOrNull ?? const <JobModel>[],
      ...result.jobs,
    ]);
  }
}
