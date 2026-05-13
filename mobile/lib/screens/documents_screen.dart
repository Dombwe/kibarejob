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
      floatingActionButton: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          FloatingActionButton.extended(
            heroTag: 'generate-cv',
            onPressed: () => context.push('/documents/generate-cv'),
            icon: const Icon(Icons.auto_awesome_outlined),
            label: const Text('Générer un CV'),
          ),
          const SizedBox(height: 10),
          FloatingActionButton.extended(
            heroTag: 'upload-document',
            onPressed: () => context.push('/documents/upload'),
            icon: const Icon(Icons.upload_file),
            label: const Text('Ajouter'),
          ),
        ],
      ),
      body: documentsState.when(
        loading: () => const LoadingWidget(),
        error: (error, _) => Center(child: Text(error.toString())),
        data: (documents) {
          if (documents.isEmpty) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.description_outlined, size: 52),
                    const SizedBox(height: 12),
                    Text(
                      'Aucun document ajouté',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Vous pouvez générer un CV guidé ou ajouter vos documents existants.',
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 18),
                    FilledButton.icon(
                      onPressed: () => context.push('/documents/generate-cv'),
                      icon: const Icon(Icons.auto_awesome_outlined),
                      label: const Text('Générer mon CV'),
                    ),
                  ],
                ),
              ),
            );
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
