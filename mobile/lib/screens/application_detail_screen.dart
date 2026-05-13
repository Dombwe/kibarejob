import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/document_model.dart';
import '../models/swipe_model.dart';
import '../providers/matches_provider.dart';
import '../theme/responsive.dart';
import '../widgets/app_bottom_navigation.dart';
import '../widgets/kibare_tab_app_bar.dart';

class ApplicationDetailScreen extends ConsumerWidget {
  const ApplicationDetailScreen({super.key, required this.application});

  final SwipeModel application;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detailState = ref.watch(matchDetailProvider(application.id));

    return Scaffold(
      appBar: const KibareTabAppBar(showBackButton: true),
      bottomNavigationBar:
          const AppBottomNavigation(currentTab: AppTab.applications),
      body: detailState.when(
        loading: () => _ApplicationBody(
          application: application,
          onResend: () => _resend(context, ref, application.id),
        ),
        error: (error, _) => Center(child: Text(error.toString())),
        data: (freshApplication) => _ApplicationBody(
          application: freshApplication,
          onResend: () => _resend(context, ref, freshApplication.id),
        ),
      ),
    );
  }

  Future<void> _resend(BuildContext context, WidgetRef ref, String id) async {
    _showBlockingLoader(context, 'Renvoi de votre candidature...');
    try {
      await ref.read(applicationServiceProvider).resendMatch(id);
      ref.invalidate(matchDetailProvider(id));
      ref.invalidate(matchesProvider);
      if (context.mounted) {
        Navigator.of(context, rootNavigator: true).pop();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Candidature renvoyée.')),
        );
      }
    } catch (error) {
      if (context.mounted) {
        Navigator.of(context, rootNavigator: true).pop();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Renvoi impossible : $error')),
        );
      }
    }
  }
}

class _ApplicationBody extends StatelessWidget {
  const _ApplicationBody({
    required this.application,
    required this.onResend,
  });

  final SwipeModel application;
  final Future<void> Function() onResend;

  @override
  Widget build(BuildContext context) {
    return Responsive.centeredContent(
      context: context,
      child: ListView(
        padding: EdgeInsets.all(Responsive.horizontalPadding(context)),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    application.offerTitle ?? 'Candidature',
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    [
                      if (application.offerCompany?.isNotEmpty == true)
                        application.offerCompany!,
                      if (application.offerLocation?.isNotEmpty == true)
                        application.offerLocation!,
                    ].join(' • '),
                  ),
                  const SizedBox(height: 16),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      Chip(label: Text(_statusLabel(application.status))),
                      Chip(
                          label: Text('Score ${application.matchScore ?? 0}%')),
                      if (application.email.sent)
                        const Chip(label: Text('Email envoyé')),
                    ],
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 14),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    application.email.sent ? 'Email envoyé' : 'Suivi email',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 8),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: (application.email.sent
                              ? const Color(0xFF047857)
                              : const Color(0xFFB45309))
                          .withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Text(
                      application.email.sent
                          ? 'Envoi confirmé par le serveur KIBARE-JOB.'
                          : 'Envoi non confirmé. Vérifiez le message d’erreur ou réessayez plus tard.',
                      style: TextStyle(
                        color: application.email.sent
                            ? const Color(0xFF047857)
                            : const Color(0xFFB45309),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  _InfoLine(
                    label: 'Destinataire',
                    value: application.email.recipient ??
                        application.offerApplicationEmail ??
                        'Non disponible',
                  ),
                  _InfoLine(
                    label: 'Expéditeur technique',
                    value: application.email.sender ?? 'Non disponible',
                  ),
                  _InfoLine(
                    label: 'Réponse au candidat',
                    value: application.email.replyTo ?? 'Non disponible',
                  ),
                  _InfoLine(
                    label: 'Objet',
                    value: application.email.subject ??
                        application.offerTitle ??
                        'Candidature',
                  ),
                  _InfoLine(
                    label: 'Date',
                    value: application.email.sentAt == null
                        ? (application.email.sent
                            ? 'Envoyé'
                            : 'En attente de confirmation')
                        : _formatDate(application.email.sentAt!),
                  ),
                  if (application.email.error?.isNotEmpty == true) ...[
                    const SizedBox(height: 10),
                    Text(
                      application.email.error!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                  if (!application.email.sent) ...[
                    const SizedBox(height: 14),
                    FilledButton.icon(
                      onPressed: onResend,
                      icon: const Icon(Icons.refresh_rounded),
                      label: const Text('Renvoyer ma candidature'),
                    ),
                  ],
                  const SizedBox(height: 16),
                  Text(
                    application.email.body ??
                        application.motivationLetterText ??
                        'Le contenu du mail sera disponible après le traitement de la candidature.',
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 14),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Pièces jointes',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 10),
                  if (application.attachments.isEmpty &&
                      application.generatedAttachments.isEmpty)
                    const Text('Aucun document joint pour le moment.')
                  else
                    for (final attachment in application.generatedAttachments)
                      if (attachment.content?.isNotEmpty == true)
                        ExpansionTile(
                          tilePadding: EdgeInsets.zero,
                          leading: const Icon(Icons.auto_awesome_outlined),
                          title: Text(attachment.title),
                          subtitle:
                              const Text('Document généré automatiquement'),
                          trailing: attachment.fileUrl.isEmpty
                              ? null
                              : IconButton(
                                  tooltip: 'Afficher',
                                  icon: const Icon(Icons.visibility_outlined),
                                  onPressed: () => context.push(
                                    '/documents/generated-${attachment.type}',
                                    extra: DocumentModel(
                                      id: '',
                                      type: attachment.type,
                                      title: attachment.title,
                                      fileUrl: attachment.fileUrl,
                                      isVerified: true,
                                      confidenceScore: 100,
                                    ),
                                  ),
                                ),
                          children: [
                            Align(
                              alignment: Alignment.centerLeft,
                              child: Padding(
                                padding:
                                    const EdgeInsets.fromLTRB(48, 0, 0, 14),
                                child: Text(attachment.content!),
                              ),
                            ),
                          ],
                        )
                      else
                        ListTile(
                          contentPadding: EdgeInsets.zero,
                          leading: const Icon(Icons.auto_awesome_outlined),
                          title: Text(attachment.title),
                          subtitle:
                              const Text('Document généré automatiquement'),
                          trailing: attachment.fileUrl.isEmpty
                              ? null
                              : const Icon(Icons.visibility_outlined),
                          onTap: attachment.fileUrl.isEmpty
                              ? null
                              : () => context.push(
                                    '/documents/generated-${attachment.type}',
                                    extra: DocumentModel(
                                      id: '',
                                      type: attachment.type,
                                      title: attachment.title,
                                      fileUrl: attachment.fileUrl,
                                      isVerified: true,
                                      confidenceScore: 100,
                                    ),
                                  ),
                        ),
                  for (final attachment in application.attachments)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.picture_as_pdf_outlined),
                      title: Text(attachment.title),
                      subtitle: Text(attachment.type),
                      trailing: const Icon(Icons.visibility_outlined),
                      onTap: () => context.push(
                        '/documents/${attachment.id}',
                        extra: DocumentModel(
                          id: attachment.id,
                          type: attachment.type,
                          title: attachment.title,
                          fileUrl: attachment.fileUrl,
                          isVerified: true,
                          confidenceScore: 100,
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  String _statusLabel(String status) {
    return switch (status) {
      'viewed' => 'Vue par le recruteur',
      'interview' => 'Entretien programmé',
      'rejected' => 'Refusée',
      'hired' => 'Retenue',
      _ => 'Envoyée',
    };
  }

  String _formatDate(DateTime date) {
    final local = date.toLocal();
    return '${local.day.toString().padLeft(2, '0')}/'
        '${local.month.toString().padLeft(2, '0')}/'
        '${local.year} à ${local.hour.toString().padLeft(2, '0')}:'
        '${local.minute.toString().padLeft(2, '0')}';
  }
}

void _showBlockingLoader(BuildContext context, String message) {
  showDialog<void>(
    context: context,
    barrierDismissible: false,
    builder: (context) => PopScope(
      canPop: false,
      child: Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const CircularProgressIndicator(),
              const SizedBox(height: 18),
              Text(
                message,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyLarge,
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

class _InfoLine extends StatelessWidget {
  const _InfoLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 132,
            child: Text(
              label,
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
          ),
          Expanded(child: Text(value)),
        ],
      ),
    );
  }
}
