import '../models/user_model.dart';
import 'api_service.dart';
import 'storage_service.dart';

class AuthResult {
  const AuthResult({required this.token, required this.user});

  final String token;
  final UserModel user;
}

class AuthService {
  const AuthService(this._api, this._storage);

  final ApiService _api;
  final StorageService _storage;

  UserModel? get cachedUser {
    final user = _storage.cachedUser;
    return user == null ? null : UserModel.fromJson(user);
  }

  bool get isLoggedIn => _storage.token != null;

  Future<AuthResult> login(String email, String password) async {
    final data = await _api.postJson(
      '/api/auth/login',
      data: {'email': email, 'password': password},
    );
    return _persistAuth(data);
  }

  Future<AuthResult> register({
    required String email,
    required String password,
    required String firstName,
    required String lastName,
    required String city,
    String? phone,
  }) async {
    final data = await _api.postJson(
      '/api/auth/register',
      data: {
        'accountType': 'candidat',
        'email': email,
        'phone': phone,
        'password': password,
        'firstName': firstName,
        'lastName': lastName,
        'city': city,
      },
    );
    return _persistAuth(data);
  }

  Future<void> logout() => _storage.clearAuth();

  Future<AuthResult> _persistAuth(Map<String, dynamic> data) async {
    final token = data['token']?.toString() ?? '';
    final userJson = Map<String, dynamic>.from(data['user'] as Map);
    final user = UserModel.fromJson(userJson);
    await _storage.saveToken(token);
    await _storage.saveUser(user.toJson());
    return AuthResult(token: token, user: user);
  }
}
