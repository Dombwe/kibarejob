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
        return host == '10.0.2.2' || host == '127.0.0.1' || host == 'localhost';
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

  Future<Map<String, dynamic>> getJson(String path) async {
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
      _primaryBaseUrl,
      'https://127.0.0.1:8000',
      'http://127.0.0.1:8000',
      'https://10.0.2.2:8000',
      'http://10.0.2.2:8000',
    }.toList(growable: false);
  }

  bool _canTryNextBaseUrl(DioException error) {
    return error.type == DioExceptionType.connectionError ||
        error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.receiveTimeout;
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
      return 'Connexion au backend impossible. Verifiez que Symfony tourne sur https://127.0.0.1:8000 et que adb reverse tcp:8000 tcp:8000 est actif si vous utilisez un telephone physique.';
    }

    if (error?.type == DioExceptionType.connectionTimeout ||
        error?.type == DioExceptionType.receiveTimeout) {
      return 'Le serveur met trop de temps a repondre.';
    }

    return error?.message ?? 'Une erreur reseau est survenue.';
  }
}
