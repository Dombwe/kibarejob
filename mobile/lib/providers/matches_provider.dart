import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/swipe_model.dart';
import '../services/application_service.dart';
import 'auth_provider.dart';

final applicationServiceProvider = Provider<ApplicationService>((ref) {
  return ApplicationService(ref.watch(apiServiceProvider));
});

final matchesProvider = FutureProvider<List<SwipeModel>>((ref) {
  return ref.watch(applicationServiceProvider).fetchMatches();
});
