import 'dart:io';

import 'package:dio/dio.dart';
import 'package:dio/io.dart';

import 'storage_service.dart';

class ApiException implements Exception {
  const ApiException({
    required this.message,
    this.statusCode,
    this.data,
  });

  final String message;
  final int? statusCode;
  final Map<String, dynamic>? data;

  @override
  String toString() => message;
}

class ApiService {
  ApiService(this._storage)
      : _primaryBaseUrl = const String.fromEnvironment(
          'API_BASE_URL',
          defaultValue: 'http://10.0.2.2:8000',
        ),
        _lanBaseUrl = const String.fromEnvironment(
          'API_LAN_BASE_URL',
          defaultValue: '',
        ),
        dio = Dio(
          BaseOptions(
            connectTimeout: const Duration(seconds: 20),
            sendTimeout: const Duration(seconds: 60),
            receiveTimeout: const Duration(seconds: 60),
            followRedirects: false,
            headers: {'Accept': 'application/json'},
            validateStatus: (status) =>
                status != null && status >= 200 && status < 300,
          ),
        ) {
    dio.options.baseUrl = _primaryBaseUrl;
    (dio.httpClientAdapter as IOHttpClientAdapter).createHttpClient = () {
      final client = HttpClient();
      client.badCertificateCallback = (certificate, host, port) {
        return _isLocalHost(host);
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
  final String _lanBaseUrl;
  final Dio dio;
  bool _configurationLoaded = false;

  String resolveUrl(String url) {
    final value = url.trim();
    if (value.startsWith('http://') || value.startsWith('https://')) {
      return value;
    }

    final baseUrl = dio.options.baseUrl.replaceAll(RegExp(r'/+$'), '');
    final path = value.startsWith('/') ? value : '/$value';

    return '$baseUrl$path';
  }

  Future<Map<String, dynamic>> getJson(String path) async {
    await _loadRemoteConfigurationIfNeeded(path);
    final response = await _requestWithFallback(
      path,
      (requestPath) => dio.get<Map<String, dynamic>>(requestPath),
    );
    return response.data ?? <String, dynamic>{};
  }

  Future<List<int>> getBytes(String path) async {
    await _loadRemoteConfigurationIfNeeded(path);
    final response = await _requestWithFallbackBytes(
      path,
      (requestPath) => dio.get<List<int>>(
        requestPath,
        options: Options(responseType: ResponseType.bytes),
      ),
    );

    return response.data ?? const <int>[];
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

  Future<Map<String, dynamic>> postFormData(
    String path, {
    required Future<FormData> Function() dataBuilder,
  }) async {
    await _loadRemoteConfigurationIfNeeded(path);
    final response = await _requestWithFallback(
      path,
      (requestPath) async {
        final data = await dataBuilder();

        return dio.post<Map<String, dynamic>>(
          requestPath,
          data: data,
        );
      },
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

  Future<void> delete(String path) async {
    await _loadRemoteConfigurationIfNeeded(path);
    await _requestWithFallback(
      path,
      (requestPath) => dio.delete<Map<String, dynamic>>(requestPath),
    );
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
          throw ApiException(
            message: _messageFromDioError(error),
            statusCode: error.response?.statusCode,
            data: error.response?.data is Map
                ? Map<String, dynamic>.from(error.response!.data as Map)
                : null,
          );
        }
        lastConnectionError = error;
      }
    }

    throw ApiException(
      message: _messageFromDioError(lastConnectionError),
      statusCode: lastConnectionError?.response?.statusCode,
      data: lastConnectionError?.response?.data is Map
          ? Map<String, dynamic>.from(
              lastConnectionError!.response!.data as Map)
          : null,
    );
  }

  Future<Response<List<int>>> _requestWithFallbackBytes(
    String path,
    Future<Response<List<int>>> Function(String path) request,
  ) async {
    DioException? lastConnectionError;

    for (final baseUrl in _baseUrlCandidates) {
      dio.options.baseUrl = baseUrl;
      try {
        return await request(path);
      } on DioException catch (error) {
        if (!_canTryNextBaseUrl(error)) {
          throw ApiException(
            message: _messageFromDioError(error),
            statusCode: error.response?.statusCode,
            data: null,
          );
        }
        lastConnectionError = error;
      }
    }

    throw ApiException(
      message: _messageFromDioError(lastConnectionError),
      statusCode: lastConnectionError?.response?.statusCode,
      data: null,
    );
  }

  List<String> get _baseUrlCandidates {
    final candidates = <String>[
      if (_storage.apiBaseUrl != null && _storage.apiBaseUrl!.isNotEmpty)
        _storage.apiBaseUrl!,
      if (_lanBaseUrl.isNotEmpty) _lanBaseUrl,
      _primaryBaseUrl,
      'https://192.168.11.100:8000',
      'http://192.168.11.100:8000',
      'http://10.0.2.2:8000',
      'http://127.0.0.1:8000',
      'http://localhost:8000',
    ];

    return _normalizeBaseUrlCandidates(candidates);
  }

  bool _canTryNextBaseUrl(DioException error) {
    if (error.type == DioExceptionType.connectionError ||
        error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.receiveTimeout) {
      return true;
    }

    final statusCode = error.response?.statusCode;
    if (statusCode == 307 || statusCode == 308) {
      return true;
    }

    if (statusCode == 404 || statusCode == 405) {
      final failingBaseUrl = error.requestOptions.baseUrl;
      return failingBaseUrl != _primaryBaseUrl;
    }

    return false;
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
        await _storage.saveApiBaseUrl(_resolveReachableBaseUrl(apiBaseUrl));
      }
    } catch (_) {
      // La configuration distante est une optimisation. Les appels API gardent les fallbacks locaux.
    }
  }

  String _resolveReachableBaseUrl(String apiBaseUrl) {
    final cleanBaseUrl = apiBaseUrl.replaceAll(RegExp(r'/+$'), '');
    final configuredUri = Uri.tryParse(cleanBaseUrl);
    final currentUri = Uri.tryParse(dio.options.baseUrl);

    if (configuredUri == null || currentUri == null) {
      return cleanBaseUrl;
    }

    final host = configuredUri.host.toLowerCase();
    if (Platform.isAndroid && currentUri.host != host) {
      return currentUri
          .replace(path: '', query: '', fragment: '')
          .toString()
          .replaceAll(RegExp(r'/+$'), '');
    }

    return cleanBaseUrl;
  }

  List<String> _normalizeBaseUrlCandidates(List<String> candidates) {
    final normalized = <String>{};

    for (final candidate in candidates) {
      final cleanCandidate = candidate.trim().replaceAll(RegExp(r'/+$'), '');
      if (cleanCandidate.isEmpty) {
        continue;
      }

      final uri = Uri.tryParse(cleanCandidate);
      if (uri != null && _isLocalHost(uri.host)) {
        if (uri.scheme == 'https') {
          if (uri.host == '10.0.2.2') {
            normalized.add(uri.replace(scheme: 'http').toString());
          } else {
            normalized.add(cleanCandidate);
          }
          continue;
        }

        if (uri.scheme == 'http') {
          normalized.add(cleanCandidate);
          continue;
        }
      }

      normalized.add(cleanCandidate);
    }

    return normalized.toList(growable: false);
  }

  static bool _isLocalHost(String host) {
    final value = host.toLowerCase();
    return value == '10.0.2.2' ||
        value == '127.0.0.1' ||
        value == 'localhost' ||
        value.startsWith('192.168.') ||
        value.startsWith('10.') ||
        RegExp(r'^172\.(1[6-9]|2\d|3[0-1])\.').hasMatch(value);
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
      return 'Backend inaccessible. Sur émulateur Android, utilisez http://10.0.2.2:8000. Sur téléphone réel, utilisez l’adresse Wi-Fi du PC, par exemple https://192.168.11.100:8000.';
    }

    if (error?.type == DioExceptionType.connectionTimeout ||
        error?.type == DioExceptionType.sendTimeout ||
        error?.type == DioExceptionType.receiveTimeout) {
      return 'Le backend met trop de temps à répondre. Vérifiez que Symfony est lancé et accessible depuis le téléphone.';
    }

    final statusCode = error?.response?.statusCode;
    if (statusCode == 307 || statusCode == 308) {
      final location = error?.response?.headers.value('location');
      return 'Le backend redirige la requête vers ${location ?? 'une autre adresse'}. Vérifiez l’adresse API mobile dans l’admin ou utilisez directement https://10.0.2.2:8000 sur émulateur.';
    }

    if (statusCode == 405) {
      return 'Méthode API non autorisée pour ${error?.requestOptions.path}. Vérifiez que l’application mobile est à jour et que Symfony utilise les dernières routes API.';
    }

    if (statusCode != null) {
      return 'Erreur API $statusCode sur ${error?.requestOptions.path}. Adresse utilisée : ${error?.requestOptions.baseUrl}.';
    }

    if (error?.type == DioExceptionType.unknown) {
      final cause = error?.error?.toString().trim();
      if (cause != null && cause.isNotEmpty) {
        return 'Erreur réseau vers ${error?.requestOptions.baseUrl ?? 'adresse inconnue'} : $cause';
      }
    }

    final details = error?.message?.trim();
    if (details != null && details.isNotEmpty) {
      return details;
    }

    return 'Une erreur réseau est survenue. Type : ${error?.type.name ?? 'inconnu'}, adresse : ${error?.requestOptions.baseUrl ?? 'non disponible'}.';
  }
}
