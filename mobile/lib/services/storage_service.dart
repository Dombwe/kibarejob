import 'package:hive_flutter/hive_flutter.dart';

class StorageService {
  static const String authBoxName = 'auth';
  static const String profileBoxName = 'profile';
  static const String offlineBoxName = 'offline';
  static const int _maxCachedJobs = 80;
  static const int _maxCachedMatches = 120;
  static const int _maxCachedDocuments = 120;
  static const int _maxCachedNotifications = 100;

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

  List<Map<String, dynamic>> get cachedJobs {
    final items = _offlineBox.get('cached_jobs', defaultValue: <dynamic>[]);
    return (items as List<dynamic>)
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  Future<void> saveCachedJobs(List<Map<String, dynamic>> jobs) {
    return _offlineBox.put('cached_jobs', jobs.take(_maxCachedJobs).toList());
  }

  List<Map<String, dynamic>> get cachedMatches {
    final items = _offlineBox.get('cached_matches', defaultValue: <dynamic>[]);
    return (items as List<dynamic>)
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  Future<void> saveCachedMatches(List<Map<String, dynamic>> matches) {
    return _offlineBox.put(
      'cached_matches',
      matches.take(_maxCachedMatches).toList(),
    );
  }

  List<Map<String, dynamic>> get cachedDocuments {
    final items =
        _offlineBox.get('cached_documents', defaultValue: <dynamic>[]);
    return (items as List<dynamic>)
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  Future<void> saveCachedDocuments(List<Map<String, dynamic>> documents) {
    return _offlineBox.put(
      'cached_documents',
      documents.take(_maxCachedDocuments).toList(),
    );
  }

  List<Map<String, dynamic>> get cachedNotifications {
    final items =
        _offlineBox.get('cached_notifications', defaultValue: <dynamic>[]);
    return (items as List<dynamic>)
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  Future<void> saveCachedNotifications(
    List<Map<String, dynamic>> notifications,
  ) {
    return _offlineBox.put(
      'cached_notifications',
      notifications.take(_maxCachedNotifications).toList(),
    );
  }

  Map<String, dynamic>? get cachedSubscriptionStatus {
    final data = _offlineBox.get('cached_subscription_status');
    return data is Map ? Map<String, dynamic>.from(data) : null;
  }

  Future<void> saveCachedSubscriptionStatus(Map<String, dynamic> status) {
    return _offlineBox.put('cached_subscription_status', status);
  }

  List<int>? cachedDocumentBytes(String key) {
    final data = _offlineBox.get('document_bytes_$key');
    if (data is List<int>) {
      return data;
    }
    if (data is List) {
      return data.whereType<int>().toList();
    }

    return null;
  }

  Future<void> saveDocumentBytes(String key, List<int> bytes) {
    return _offlineBox.put('document_bytes_$key', bytes);
  }

  String? cachedDocumentPreview(String key) {
    return _offlineBox.get('document_preview_$key') as String?;
  }

  Future<void> saveDocumentPreview(String key, String text) {
    return _offlineBox.put('document_preview_$key', text);
  }
}
