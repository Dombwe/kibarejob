import 'package:hive_flutter/hive_flutter.dart';

class StorageService {
  static const String authBoxName = 'auth';
  static const String profileBoxName = 'profile';
  static const String offlineBoxName = 'offline';

  static Future<void> init() async {
    await Hive.initFlutter();
    await Hive.openBox<dynamic>(authBoxName);
    await Hive.openBox<dynamic>(profileBoxName);
    await Hive.openBox<dynamic>(offlineBoxName);
  }

  final Box<dynamic> _authBox = Hive.box<dynamic>(authBoxName);
  final Box<dynamic> _profileBox = Hive.box<dynamic>(profileBoxName);
  final Box<dynamic> _offlineBox = Hive.box<dynamic>(offlineBoxName);

  String? get token => _authBox.get('token') as String?;

  Future<void> saveToken(String token) => _authBox.put('token', token);

  Future<void> clearAuth() => _authBox.clear();

  Map<String, dynamic>? get cachedUser {
    final data = _authBox.get('user');
    return data is Map ? Map<String, dynamic>.from(data) : null;
  }

  Future<void> saveUser(Map<String, dynamic> user) =>
      _authBox.put('user', user);

  Map<String, dynamic>? get cachedProfile {
    final data = _profileBox.get('candidate_profile');
    return data is Map ? Map<String, dynamic>.from(data) : null;
  }

  Future<void> saveProfile(Map<String, dynamic> profile) {
    return _profileBox.put('candidate_profile', profile);
  }

  String? get themeMode => _profileBox.get('theme_mode') as String?;

  Future<void> saveThemeMode(String themeMode) {
    return _profileBox.put('theme_mode', themeMode);
  }

  String? get apiBaseUrl => _profileBox.get('api_base_url') as String?;

  Future<void> saveApiBaseUrl(String apiBaseUrl) {
    return _profileBox.put('api_base_url', apiBaseUrl);
  }

  bool get hasSeenOnboarding =>
      _profileBox.get('has_seen_onboarding', defaultValue: false) == true;

  Future<void> markOnboardingSeen() {
    return _profileBox.put('has_seen_onboarding', true);
  }

  List<Map<String, dynamic>> get queuedSwipes {
    final items = _offlineBox.get('swipe_queue', defaultValue: <dynamic>[]);
    return (items as List<dynamic>)
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  Future<void> saveQueuedSwipes(List<Map<String, dynamic>> swipes) {
    return _offlineBox.put('swipe_queue', swipes);
  }
}
