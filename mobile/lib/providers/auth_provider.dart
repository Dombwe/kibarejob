import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/user_model.dart';
import '../services/api_service.dart';
import '../services/auth_service.dart';
import '../services/storage_service.dart';

final storageServiceProvider = Provider<StorageService>((ref) {
  return StorageService();
});

final apiServiceProvider = Provider<ApiService>((ref) {
  return ApiService(ref.watch(storageServiceProvider));
});

final authServiceProvider = Provider<AuthService>((ref) {
  return AuthService(
    ref.watch(apiServiceProvider),
    ref.watch(storageServiceProvider),
  );
});

final authControllerProvider =
    StateNotifierProvider<AuthController, AsyncValue<UserModel?>>((ref) {
  return AuthController(ref.watch(authServiceProvider));
});

class AuthController extends StateNotifier<AsyncValue<UserModel?>> {
  AuthController(this._authService)
      : super(AsyncValue.data(_authService.cachedUser));

  final AuthService _authService;

  Future<void> login(String email, String password) async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(() async {
      final result = await _authService.login(email, password);
      return result.user;
    });
  }

  Future<void> loginWithGoogle() async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(() async {
      final result = await _authService.loginWithGoogle();
      return result.user;
    });
  }

  Future<void> register({
    required String email,
    required String password,
    required String firstName,
    required String lastName,
    required String city,
    String? phone,
  }) async {
    state = const AsyncValue.loading();
    final result = await AsyncValue.guard(() async {
      final result = await _authService.register(
        email: email,
        password: password,
        firstName: firstName,
        lastName: lastName,
        city: city,
        phone: phone,
      );
      return result.user;
    });

    if (result.hasError) {
      state = result;
      return;
    }

    state = const AsyncValue.data(null);
  }

  Future<String> resendVerification(String email) {
    return _authService.resendVerification(email);
  }

  Future<void> logout() async {
    await _authService.logout();
    state = const AsyncValue.data(null);
  }
}
