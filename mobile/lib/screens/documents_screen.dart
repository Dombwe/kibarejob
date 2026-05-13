import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../providers/document_provider.dart';
import '../widgets/document_card.dart';
import '../widgets/loading_widget.dart';
import '../widgets/state_message.dart';

class DocumentsScreen extends ConsumerWidget {
  const DocumentsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final documentsState = ref.watch(documentProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Mes documents'),
        actions: [
          PopupMenuButton<_DocumentMenuAction>(
            tooltip: 'Actions documents',
            icon: const Icon(Icons.more_vert_rounded),
            onSelected: (action) {
              switch (action) {
                case _DocumentMenuAction.generateCv:
                  context.push('/documents/generate-cv');
                  return;
                case _DocumentMenuAction.upload:
                  context.push('/documents/upload');
                  return;
              }
            },
            itemBuilder: (context) => const [
              PopupMenuItem(
                value: _DocumentMenuAction.generateCv,
                child: ListTile(
                  leading: Icon(Icons.auto_awesome_outlined),
                  title: Text('Generer un CV'),
                ),
              ),
              PopupMenuItem(
                value: _DocumentMenuAction.upload,
                child: ListTile(
                  leading: Icon(Icons.upload_file),
                  title: Text('Ajouter un document'),
                ),
              ),
            ],
          ),
        ],
      ),
      body: documentsState.when(
        loading: () => const LoadingWidget(),
        error: (error, _) => StateMessage(
          icon: Icons.wifi_off_rounded,
          title: 'Documents indisponibles',
          message:
              'Vos documents apparaitront ici des qu ils auront ete charges au moins une fois avec internet.',
          actionLabel: 'Reessayer',
          onAction: () => ref.invalidate(documentProvider),
        ),
        data: (documents) {
          if (documents.isEmpty) {
            return StateMessage(
              icon: Icons.description_outlined,
              title: 'Aucun document ajouté',
              message:
                  'Ajoutez votre CV, vos attestations ou générez un CV prêt à envoyer.',
              actionLabel: 'Générer mon CV',
              onAction: () => context.push('/documents/generate-cv'),
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

enum _DocumentMenuAction { generateCv, upload }
