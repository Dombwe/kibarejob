import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/document_model.dart';
import 'auth_provider.dart';

final documentProvider =
    AsyncNotifierProvider<DocumentNotifier, List<DocumentModel>>(
  DocumentNotifier.new,
);

class DocumentNotifier extends AsyncNotifier<List<DocumentModel>> {
  @override
  Future<List<DocumentModel>> build() => fetchDocuments();

  Future<List<DocumentModel>> fetchDocuments() async {
    final api = ref.read(apiServiceProvider);
    final data = await api.getJson('/api/documents');
    return (data['documents'] as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((json) => DocumentModel.fromJson(Map<String, dynamic>.from(json)))
        .toList();
  }

  Future<void> uploadDocument({
    required File file,
    required String title,
    required String type,
    String? description,
  }) async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(() async {
      final api = ref.read(apiServiceProvider);
      final formData = FormData.fromMap({
        'file': await MultipartFile.fromFile(file.path),
        'title': title,
        'type': type,
        'description': description,
      });
      await api.dio.post('/api/documents/upload', data: formData);
      return fetchDocuments();
    });
  }

  Future<void> deleteDocument(String id) async {
    final api = ref.read(apiServiceProvider);
    await api.dio.delete('/api/documents/$id');
    state = AsyncValue.data(await fetchDocuments());
  }
}
