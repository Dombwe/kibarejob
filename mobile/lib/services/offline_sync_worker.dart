import 'dart:async';

import 'offline_service.dart';

class OfflineSyncWorker {
  OfflineSyncWorker(this._offlineService);

  final OfflineService _offlineService;
  Timer? _timer;

  void start({Duration interval = const Duration(minutes: 5)}) {
    _timer?.cancel();
    _timer = Timer.periodic(interval, (_) => syncNow());
    unawaited(syncNow());
  }

  Future<int> syncNow() {
    return _offlineService.flushSwipeQueue();
  }

  void stop() {
    _timer?.cancel();
    _timer = null;
  }
}
