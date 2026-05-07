import 'package:flutter/material.dart';

import '../models/swipe_model.dart';

class ApplicationDetailScreen extends StatelessWidget {
  const ApplicationDetailScreen({super.key, required this.application});

  final SwipeModel application;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Detail candidature')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            application.offerTitle ?? 'Offre',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 8),
          Text('Statut: ${application.status}'),
          Text('Swipe: ${application.direction}'),
          Text('Score: ${application.matchScore ?? 0}%'),
          const Divider(height: 32),
          Text('CV envoye', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          Text(application.cvUsedUrl ?? 'CV en cours de generation ou non disponible'),
          const SizedBox(height: 20),
          Text('Lettre de motivation', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          Text(application.motivationLetterText ?? 'Lettre en cours de generation ou non disponible'),
          const SizedBox(height: 20),
          Text('Documents joints', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          if (application.documentsSent.isEmpty)
            const Text('Aucun document joint pour le moment')
          else
            ...application.documentsSent.map((id) => ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.description_outlined),
                  title: Text(id),
                )),
        ],
      ),
    );
  }
}
