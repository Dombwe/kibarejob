import 'dart:io';

import 'package:dio/dio.dart';
import 'package:dio/io.dart';

import 'storage_service.dart';

class ApiService {
  ApiService(this._storage)
      : _primaryBaseUrl = const String.fromEnvironment(
          'API_BASE_URL',
          defaultValue: 'https://127.0.0.1:8000',
        ),
        dio = Dio(
          BaseOptions(
            connectTimeout: const Duration(seconds: 20),
            receiveTimeout: const Duration(seconds: 20),
            headers: {'Accept': 'application/json'},
          ),
        ) {
    dio.options.baseUrl = _primaryBaseUrl;
    (dio.httpClientAdapter as IOHttpClientAdapter).createHttpClient = () {
      final client = HttpClient();
      client.badCertificateCallback = (certificate, host, port) {
        return host == '10.0.2.2' ||
            host == '127.0.0.1' ||
            host == 'localhost' ||
            host.startsWith('192.168.') ||
            host.startsWith('10.') ||
            RegExp(r'^172\.(1[6-9]|2\d|3[0-1])\.').hasMatch(host);
      };
      return client;
    };

    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          final token = _storage.token;
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
      ),
    );
  }

  final StorageService _storage;
  final String _primaryBaseUrl;
  final Dio dio;
  bool _configurationLoaded = false;

  Future<Map<String, dynamic>> getJson(String path) async {
    await _loadRemoteConfigurationIfNeeded(path);
    final response = await _requestWithFallback(
      path,
      (requestPath) => dio.get<Map<String, dynamic>>(requestPath),
    );
    return response.data ?? <String, dynamic>{};
  }

  Future<Map<String, dynamic>> postJson(
    String path, {
    Map<String, dynamic>? data,
  }) async {
    await _loadRemoteConfigurationIfNeeded(path);
    final response = await _requestWithFallback(
      path,
      (requestPath) => dio.post<Map<String, dynamic>>(
        requestPath,
        data: data,
      ),
    );
    return response.data ?? <String, dynamic>{};
  }

  Future<Map<String, dynamic>> putJson(
    String path, {
    Map<String, dynamic>? data,
  }) async {
    await _loadRemoteConfigurationIfNeeded(path);
    final response = await _requestWithFallback(
      path,
      (requestPath) => dio.put<Map<String, dynamic>>(
        requestPath,
        data: data,
      ),
    );
    return response.data ?? <String, dynamic>{};
  }

  Future<Response<Map<String, dynamic>>> _requestWithFallback(
    String path,
    Future<Response<Map<String, dynamic>>> Function(String path) request,
  ) async {
    DioException? lastConnectionError;

    for (final baseUrl in _baseUrlCandidates) {
      dio.options.baseUrl = baseUrl;
      try {
        return await request(path);
      } on DioException catch (error) {
        if (!_canTryNextBaseUrl(error)) {
          throw Exception(_messageFromDioError(error));
        }
        lastConnectionError = error;
      }
    }

    throw Exception(_messageFromDioError(lastConnectionError));
  }

  List<String> get _baseUrlCandidates {
    return {
      if (_storage.apiBaseUrl != null && _storage.apiBaseUrl!.isNotEmpty)
        _storage.apiBaseUrl!,
      _primaryBaseUrl,
      'https://127.0.0.1:8000',
      'http://127.0.0.1:8000',
      'https://192.168.11.105:8000',
      'http://192.168.11.105:8000',
      'https://10.0.2.2:8000',
      'http://10.0.2.2:8000',
    }.toList(growable: false);
  }

  bool _canTryNextBaseUrl(DioException error) {
    return error.type == DioExceptionType.connectionError ||
        error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.receiveTimeout;
  }

  Future<void> _loadRemoteConfigurationIfNeeded(String path) async {
    if (_configurationLoaded || path.startsWith('/api/app-config')) {
      return;
    }

    _configurationLoaded = true;

    try {
      final response = await _requestWithFallback(
        '/api/app-config',
        (requestPath) => dio.get<Map<String, dynamic>>(requestPath),
      );
      final apiBaseUrl = response.data?['apiBaseUrl']?.toString().trim();
      if (apiBaseUrl != null && apiBaseUrl.isNotEmpty) {
        await _storage
            .saveApiBaseUrl(apiBaseUrl.replaceAll(RegExp(r'/+$'), ''));
      }
    } catch (_) {
      // La configuration distante est une optimisation. Les appels API gardent les fallbacks locaux.
    }
  }

  String _messageFromDioError(DioException? error) {
    final data = error?.response?.data;
    if (data is Map<String, dynamic>) {
      final message = data['message']?.toString();
      if (message != null && message.isNotEmpty) {
        return message;
      }

      final errors = data['errors'];
      if (errors is Map && errors.isNotEmpty) {
        return errors.values.first.toString();
      }
    }

    if (error?.type == DioExceptionType.connectionError) {
      return 'Connexion internet requise. Vérifiez votre Wi-Fi ou vos données mobiles, puis réessayez.';
    }

    if (error?.type == DioExceptionType.connectionTimeout ||
        error?.type == DioExceptionType.receiveTimeout) {
      return 'Connexion internet instable. Le serveur met trop de temps à répondre.';
    }

    return error?.message ?? 'Une erreur réseau est survenue.';
  }
}
