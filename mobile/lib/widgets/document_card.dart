import 'package:flutter/material.dart';

import '../models/document_model.dart';
import 'verification_badge.dart';

class DocumentCard extends StatelessWidget {
  const DocumentCard({
    super.key,
    required this.document,
    this.onOpen,
    this.onDelete,
  });

  final DocumentModel document;
  final VoidCallback? onOpen;
  final VoidCallback? onDelete;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        leading: const Icon(Icons.description_outlined),
        title: Text(document.title),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(document.type),
            const SizedBox(height: 6),
            VerificationBadge(
              isVerified: document.isVerified,
              score: document.confidenceScore,
            ),
          ],
        ),
        trailing: onDelete == null
            ? null
            : Wrap(
                spacing: 2,
                children: [
                  IconButton(
                    tooltip: 'Afficher',
                    icon: const Icon(Icons.visibility_outlined),
                    onPressed: onOpen,
                  ),
                  IconButton(
                    tooltip: 'Supprimer',
                    icon: const Icon(Icons.delete_outline),
                    onPressed: onDelete,
                  ),
                ],
              ),
      ),
    );
  }
}
