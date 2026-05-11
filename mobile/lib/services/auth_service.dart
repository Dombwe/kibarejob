import 'package:google_sign_in/google_sign_in.dart';

import '../models/user_model.dart';
import 'api_service.dart';
import 'storage_service.dart';

class AuthResult {
  const AuthResult({
    required this.user,
    this.token,
    this.message,
    this.emailVerificationRequired = false,
  });

  final String? token;
  final UserModel user;
  final String? message;
  final bool emailVerificationRequired;
}

class AuthService {
  const AuthService(this._api, this._storage);

  static const _defaultGoogleWebClientId =
      '380349066467-s2fjkk9hcr55nr3fukimm43sj2fo9p16.apps.googleusercontent.com';
  static bool _googleInitialized = false;

  final ApiService _api;
  final StorageService _storage;

  UserModel? get cachedUser {
    if (!isLoggedIn) {
      return null;
    }

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

  Future<AuthResult> loginWithGoogle() async {
    await _initializeGoogleSignIn();

    if (!GoogleSignIn.instance.supportsAuthenticate()) {
      throw Exception(
        'La connexion Google n\'est pas disponible sur cette plateforme.',
      );
    }

    final GoogleSignInAccount account;
    try {
      account = await GoogleSignIn.instance.authenticate();
    } on GoogleSignInException catch (error) {
      throw Exception(_googleErrorMessage(error));
    }

    final idToken = account.authentication.idToken;

    if (idToken == null || idToken.isEmpty) {
      throw Exception(
        'Google n\'a pas fourni de jeton de connexion. Verifiez la configuration OAuth dans Google Cloud.',
      );
    }

    final data = await _api.postJson(
      '/api/auth/google/mobile',
      data: {'idToken': idToken},
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
    final userJson = Map<String, dynamic>.from(data['user'] as Map);
    return AuthResult(
      user: UserModel.fromJson(userJson),
      message: data['message']?.toString(),
      emailVerificationRequired: data['emailVerificationRequired'] == true,
    );
  }

  Future<String> resendVerification(String email) async {
    final data = await _api.postJson(
      '/api/auth/resend-verification',
      data: {'email': email},
    );

    return data['message']?.toString() ??
        'Si ce compte existe, un lien de confirmation a ete envoye.';
  }

  Future<void> logout() => _storage.clearAuth();

  Future<void> _initializeGoogleSignIn() async {
    if (_googleInitialized) {
      return;
    }

    const webClientId = String.fromEnvironment(
      'GOOGLE_WEB_CLIENT_ID',
      defaultValue: _defaultGoogleWebClientId,
    );
    const androidClientId = String.fromEnvironment('GOOGLE_ANDROID_CLIENT_ID');

    await GoogleSignIn.instance.initialize(
      clientId: androidClientId.isEmpty ? null : androidClientId,
      serverClientId: webClientId.isEmpty ? null : webClientId,
    );
    _googleInitialized = true;
  }

  String _googleErrorMessage(GoogleSignInException error) {
    if (error.code == GoogleSignInExceptionCode.canceled) {
      return 'Google a interrompu la connexion apres le choix du compte. Verifiez dans Google Cloud le package Android, le SHA-1 debug et le client ID Web.';
    }

    if (error.code == GoogleSignInExceptionCode.uiUnavailable) {
      return 'Google Play Services n\'est pas disponible sur cet appareil.';
    }

    if (error.code == GoogleSignInExceptionCode.clientConfigurationError) {
      return 'Configuration Google incorrecte. Verifiez le package Android, le SHA-1 et les identifiants OAuth.';
    }

    return error.description ??
        'Connexion Google impossible. Verifiez la configuration OAuth.';
  }

  Future<AuthResult> _persistAuth(Map<String, dynamic> data) async {
    final token = data['token']?.toString() ?? '';
    if (token.isEmpty) {
      throw Exception('Le backend n\'a pas renvoye de jeton de connexion.');
    }

    final userJson = Map<String, dynamic>.from(data['user'] as Map);
    final user = UserModel.fromJson(userJson);
    await _storage.saveToken(token);
    await _storage.saveUser(user.toJson());
    return AuthResult(token: token, user: user);
  }
}
