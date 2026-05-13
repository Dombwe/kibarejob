import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:syncfusion_flutter_pdfviewer/pdfviewer.dart';

import '../models/document_model.dart';
import '../providers/auth_provider.dart';
import '../widgets/loading_widget.dart';

class DocumentViewerScreen extends ConsumerWidget {
  const DocumentViewerScreen({super.key, required this.document});

  final DocumentModel document;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final fileUrl = document.fileUrl.toLowerCase();
    final isImage = fileUrl.endsWith('.jpg') ||
        fileUrl.endsWith('.jpeg') ||
        fileUrl.endsWith('.png') ||
        fileUrl.endsWith('.webp');
    final isPdf = fileUrl.endsWith('.pdf');

    return Scaffold(
      appBar: AppBar(title: Text(document.title)),
      body: isImage
          ? _ImagePreview(document: document)
          : isPdf
              ? _PdfPreview(document: document)
              : _TextPreview(document: document),
    );
  }
}

class _ImagePreview extends ConsumerWidget {
  const _ImagePreview({required this.document});

  final DocumentModel document;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return FutureBuilder<List<int>>(
      future: _loadDocumentBytes(ref, document),
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const LoadingWidget(message: 'Chargement du document...');
        }
        if (snapshot.hasError || snapshot.data == null) {
          return const _PreviewError();
        }

        return InteractiveViewer(
          minScale: 0.6,
          maxScale: 4,
          child: Center(
            child: Image.memory(Uint8List.fromList(snapshot.data!)),
          ),
        );
      },
    );
  }
}

class _PdfPreview extends ConsumerWidget {
  const _PdfPreview({required this.document});

  final DocumentModel document;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return FutureBuilder<List<int>>(
      future: _loadDocumentBytes(ref, document),
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const LoadingWidget(message: 'Chargement du document...');
        }
        if (snapshot.hasError || snapshot.data == null) {
          return const _PreviewError();
        }

        return SfPdfViewer.memory(Uint8List.fromList(snapshot.data!));
      },
    );
  }
}

String _documentFilePath(DocumentModel document) {
  if (document.id.isEmpty && document.fileUrl.isNotEmpty) {
    return document.fileUrl;
  }

  return '/api/documents/${document.id}/file';
}

class _TextPreview extends ConsumerWidget {
  const _TextPreview({required this.document});

  final DocumentModel document;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _loadDocumentPreview(ref, document),
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const LoadingWidget(message: 'Préparation de l’aperçu...');
        }
        if (snapshot.hasError) {
          return const _PreviewError();
        }

        final text = snapshot.data?['text']?.toString().trim() ?? '';
        if (text.isEmpty) {
          return const _PreviewError(
            message:
                'Aucun aperçu lisible n’est disponible pour ce document. Le fichier est bien conservé dans votre profil.',
          );
        }

        return ListView(
          padding: const EdgeInsets.all(18),
          children: [
            Text(
              document.title,
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: 12),
            SelectableText(
              text,
              style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                    height: 1.55,
                  ),
            ),
          ],
        );
      },
    );
  }
}

Future<List<int>> _loadDocumentBytes(
    WidgetRef ref, DocumentModel document) async {
  final storage = ref.read(storageServiceProvider);
  final key = _documentCacheKey(document);

  try {
    final bytes = await ref.read(apiServiceProvider).getBytes(
          _documentFilePath(document),
        );
    if (bytes.isNotEmpty) {
      await storage.saveDocumentBytes(key, bytes);
    }

    return bytes;
  } catch (error) {
    final cached = storage.cachedDocumentBytes(key);
    if (cached != null && cached.isNotEmpty) {
      return cached;
    }

    rethrow;
  }
}

Future<Map<String, dynamic>> _loadDocumentPreview(
  WidgetRef ref,
  DocumentModel document,
) async {
  final storage = ref.read(storageServiceProvider);
  final key = _documentCacheKey(document);

  try {
    final data = await ref.read(apiServiceProvider).getJson(
          '/api/documents/${document.id}/preview',
        );
    final text = data['text']?.toString();
    if (text != null && text.trim().isNotEmpty) {
      await storage.saveDocumentPreview(key, text);
    }

    return data;
  } catch (error) {
    final cached = storage.cachedDocumentPreview(key);
    if (cached != null && cached.trim().isNotEmpty) {
      return {'text': cached, '_offline': true};
    }

    rethrow;
  }
}

String _documentCacheKey(DocumentModel document) {
  final raw = document.id.isNotEmpty ? document.id : document.fileUrl;

  return raw.replaceAll(RegExp(r'[^a-zA-Z0-9_-]+'), '_');
}

class _PreviewError extends StatelessWidget {
  const _PreviewError({
    this.message =
        'Impossible d’afficher ce document pour le moment. Réessayez plus tard.',
  });

  final String message;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Text(
          message,
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.bodyLarge,
        ),
      ),
    );
  }
}
