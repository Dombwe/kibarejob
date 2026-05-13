import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/document_model.dart';
import 'auth_provider.dart';
import 'profile_provider.dart';

final documentProvider =
    AsyncNotifierProvider<DocumentNotifier, List<DocumentModel>>(
  DocumentNotifier.new,
);

class DocumentNotifier extends AsyncNotifier<List<DocumentModel>> {
  @override
  Future<List<DocumentModel>> build() => fetchDocuments();

  Future<List<DocumentModel>> fetchDocuments() async {
    final storage = ref.read(storageServiceProvider);

    try {
      final api = ref.read(apiServiceProvider);
      final rawDocuments =
          ((await api.getJson('/api/documents?limit=100'))['documents']
                      as List<dynamic>? ??
                  const [])
              .whereType<Map>()
              .map((json) => Map<String, dynamic>.from(json))
              .toList();
      await storage.saveCachedDocuments(rawDocuments);

      return rawDocuments.map(DocumentModel.fromJson).toList();
    } catch (error) {
      final cached = storage.cachedDocuments;
      if (cached.isEmpty || !_shouldUseCache(error)) {
        rethrow;
      }

      return cached.map(DocumentModel.fromJson).toList();
    }
  }

  Future<void> uploadDocument({
    required File file,
    required String title,
    required String type,
    String? description,
  }) async {
    state = const AsyncValue.loading();
    try {
      final api = ref.read(apiServiceProvider);
      final data = await api.postFormData(
        '/api/documents/upload',
        dataBuilder: () async => FormData.fromMap({
          'file': await MultipartFile.fromFile(file.path),
          'title': title,
          'type': type,
          'description': description,
        }),
      );
      await _syncReturnedProfile(data);
      state = AsyncValue.data(await fetchDocuments());
      ref.invalidate(profileProvider);
    } catch (error, stackTrace) {
      state = AsyncValue.error(error, stackTrace);
      rethrow;
    }
  }

  Future<DocumentModel> generateCv(Map<String, dynamic> payload) async {
    state = const AsyncValue.loading();
    try {
      final api = ref.read(apiServiceProvider);
      final data =
          await api.postJson('/api/documents/generate-cv', data: payload);
      final documentJson = data['document'] as Map<String, dynamic>;
      final document = DocumentModel.fromJson(documentJson);
      await _syncReturnedProfile(data);
      state = AsyncValue.data(await fetchDocuments());
      ref.invalidate(profileProvider);
      return document;
    } catch (error, stackTrace) {
      state = AsyncValue.error(error, stackTrace);
      rethrow;
    }
  }

  Future<void> deleteDocument(String id) async {
    final api = ref.read(apiServiceProvider);
    await api.delete('/api/documents/$id');
    state = AsyncValue.data(await fetchDocuments());
    ref.invalidate(profileProvider);
  }

  bool _shouldUseCache(Object error) {
    final message = error.toString().toLowerCase();
    return message.contains('connection') ||
        message.contains('inaccessible') ||
        message.contains('socket') ||
        message.contains('timeout') ||
        message.contains('network') ||
        message.contains('reseau') ||
        message.contains('réseau');
  }

  Future<void> _syncReturnedProfile(Map<String, dynamic> data) async {
    final profile = data['profile'];
    if (profile is Map) {
      await ref
          .read(storageServiceProvider)
          .saveProfile(Map<String, dynamic>.from(profile));
    }
  }
}
