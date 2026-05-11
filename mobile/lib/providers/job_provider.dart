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

class OfflineSwipeException implements Exception {
  const OfflineSwipeException();

  @override
  String toString() {
    return 'Vous pouvez consulter les offres sauvegardées hors connexion, mais cette action nécessite une connexion internet.';
  }
}

class FeedNotifier extends AsyncNotifier<List<JobModel>> {
  int _cursor = 0;
  bool _hasMore = true;
  bool _offlineMode = false;

  bool get isOfflineMode => _offlineMode;

  @override
  Future<List<JobModel>> build() async {
    ref.read(offlineSyncWorkerProvider).start();
    return refresh();
  }

  Future<List<JobModel>> refresh() async {
    _cursor = 0;
    _hasMore = true;
    try {
      final result = await ref.read(jobServiceProvider).fetchFeed();
      _offlineMode = false;
      _cursor = result.nextCursor ?? 0;
      _hasMore = result.hasMore;
      await ref.read(offlineServiceProvider).cacheJobs(result.jobs);
      return result.jobs;
    } catch (_) {
      final cachedJobs = ref.read(offlineServiceProvider).cachedJobs;
      if (cachedJobs.isEmpty) {
        rethrow;
      }

      _offlineMode = true;
      _hasMore = false;
      _cursor = cachedJobs.length;
      return cachedJobs;
    }
  }

  Future<void> swipe(JobModel job, String direction) async {
    if (_offlineMode) {
      throw const OfflineSwipeException();
    }

    final currentJobs = state.valueOrNull ?? const <JobModel>[];
    state = AsyncValue.data(
      currentJobs.where((item) => item.id != job.id).toList(),
    );

    try {
      await ref.read(jobServiceProvider).swipe(
            offerId: job.id,
            direction: direction,
          );
    } catch (error) {
      state = AsyncValue.data(currentJobs);
      throw Exception(_readableError(error));
    }

    if ((state.valueOrNull?.length ?? 0) <= 3 && _hasMore) {
      await loadMore();
    }
  }

  Future<void> loadMore() async {
    if (!_hasMore) {
      return;
    }
    final result =
        await ref.read(jobServiceProvider).fetchFeed(cursor: _cursor);
    _offlineMode = false;
    _cursor = result.nextCursor ?? _cursor;
    _hasMore = result.hasMore;
    final jobs = [
      ...state.valueOrNull ?? const <JobModel>[],
      ...result.jobs,
    ];
    state = AsyncValue.data(jobs);
    await ref.read(offlineServiceProvider).cacheJobs(jobs);
  }

  String _readableError(Object error) {
    final message = error.toString().replaceFirst('Exception: ', '').trim();
    if (message.isEmpty) {
      return 'Le swipe n’a pas pu être enregistré. Veuillez réessayer.';
    }

    return message;
  }
}
