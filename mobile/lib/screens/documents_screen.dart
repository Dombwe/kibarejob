import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../providers/document_provider.dart';
import '../widgets/document_card.dart';
import '../widgets/loading_widget.dart';

class DocumentsScreen extends ConsumerWidget {
  const DocumentsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final documentsState = ref.watch(documentProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Mes documents')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.push('/documents/upload'),
        icon: const Icon(Icons.upload_file),
        label: const Text('Ajouter'),
      ),
      body: documentsState.when(
        loading: () => const LoadingWidget(),
        error: (error, _) => Center(child: Text(error.toString())),
        data: (documents) {
          if (documents.isEmpty) {
            return const Center(child: Text('Aucun document ajouté'));
          }

          return RefreshIndicator(
            onRefresh: () =>
                ref.read(documentProvider.notifier).fetchDocuments(),
            child: ListView.builder(
              padding: const EdgeInsets.all(12),
              itemCount: documents.length,
              itemBuilder: (context, index) {
                final document = documents[index];

                return DocumentCard(
                  document: document,
                  onOpen: () => context.push(
                    '/documents/${document.id}',
                    extra: document,
                  ),
                  onDelete: () async {
                    final confirmed = await showDialog<bool>(
                          context: context,
                          builder: (context) => AlertDialog(
                            title: const Text('Supprimer le document ?'),
                            content: Text(
                              'Voulez-vous vraiment supprimer "${document.title}" ?',
                            ),
                            actions: [
                              TextButton(
                                onPressed: () => Navigator.pop(context, false),
                                child: const Text('Annuler'),
                              ),
                              FilledButton(
                                onPressed: () => Navigator.pop(context, true),
                                child: const Text('Supprimer'),
                              ),
                            ],
                          ),
                        ) ??
                        false;

                    if (!confirmed) {
                      return;
                    }

                    await ref
                        .read(documentProvider.notifier)
                        .deleteDocument(document.id);
                  },
                );
              },
            ),
          );
        },
      ),
    );
  }
}
