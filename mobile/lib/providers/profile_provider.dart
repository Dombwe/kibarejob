import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/user_model.dart';
import 'auth_provider.dart';

final profileProvider =
    AsyncNotifierProvider<ProfileNotifier, CandidateProfileModel?>(
  ProfileNotifier.new,
);

class ProfileNotifier extends AsyncNotifier<CandidateProfileModel?> {
  @override
  Future<CandidateProfileModel?> build() async {
    final storage = ref.watch(storageServiceProvider);
    final cached = storage.cachedProfile;

    try {
      return await fetchProfile();
    } catch (_) {
      if (cached == null) {
        rethrow;
      }

      return CandidateProfileModel.fromJson(cached);
    }
  }

  Future<CandidateProfileModel?> fetchProfile() async {
    final api = ref.read(apiServiceProvider);
    final data = await api.getJson('/api/profile');
    final profileJson = data['profile'] as Map<String, dynamic>?;
    if (profileJson == null) {
      return null;
    }
    await ref.read(storageServiceProvider).saveProfile(profileJson);
    return CandidateProfileModel.fromJson(profileJson);
  }

  Future<void> updateProfile(Map<String, dynamic> payload) async {
    final previous = state.valueOrNull;
    try {
      final api = ref.read(apiServiceProvider);
      final data = await api.putJson('/api/profile', data: payload);
      final profileJson = data['profile'] as Map<String, dynamic>;
      await ref.read(storageServiceProvider).saveProfile(profileJson);
      state = AsyncValue.data(CandidateProfileModel.fromJson(profileJson));
    } catch (error, stackTrace) {
      state = previous == null
          ? AsyncValue.error(error, stackTrace)
          : AsyncValue.data(previous);
      rethrow;
    }
  }
}
