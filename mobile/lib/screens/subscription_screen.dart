import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers/auth_provider.dart';
import '../widgets/loading_widget.dart';

class SubscriptionScreen extends ConsumerStatefulWidget {
  const SubscriptionScreen({super.key});

  @override
  ConsumerState<SubscriptionScreen> createState() => _SubscriptionScreenState();
}

class _SubscriptionScreenState extends ConsumerState<SubscriptionScreen> {
  late Future<Map<String, dynamic>> _statusFuture;
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _statusFuture = _fetchStatus();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Abonnement')),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _statusFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const LoadingWidget();
          }

          final status = snapshot.data ?? const <String, dynamic>{};
          final candidate = status['candidate'] is Map<String, dynamic>
              ? status['candidate'] as Map<String, dynamic>
              : const <String, dynamic>{};
          final tier = candidate['tier']?.toString() ?? 'free';

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Card(
                child: ListTile(
                  leading: const Icon(Icons.workspace_premium_outlined),
                  title: Text('Plan actuel: $tier'),
                  subtitle: Text(
                    status['mode'] == 'mock'
                        ? 'Phase 1 gratuite: droits Premium actifs.'
                        : 'Gerez votre abonnement candidat.',
                  ),
                ),
              ),
              const SizedBox(height: 16),
              _PlanCard(
                title: 'Gratuit',
                price: '0 FCFA',
                features: const [
                  '10 swipes par jour',
                  '10 documents',
                  'CV standard',
                ],
                onPressed: null,
              ),
              const SizedBox(height: 12),
              _PlanCard(
                title: 'Premium',
                price: '2 000 FCFA / mois',
                features: const [
                  'Swipes illimites',
                  '50 documents',
                  'CV designer pro',
                  'Super swipe',
                  'Statistiques personnelles',
                ],
                onPressed: _isSubmitting ? null : () => _subscribe('candidate_premium'),
              ),
              const SizedBox(height: 20),
              OutlinedButton.icon(
                onPressed: _isSubmitting ? null : _cancel,
                icon: const Icon(Icons.cancel_outlined),
                label: const Text('Resilier'),
              ),
            ],
          );
        },
      ),
    );
  }

  Future<Map<String, dynamic>> _fetchStatus() {
    return ref.read(apiServiceProvider).getJson('/api/subscription/status');
  }

  Future<void> _subscribe(String planCode) async {
    setState(() => _isSubmitting = true);
    try {
      await ref.read(apiServiceProvider).postJson(
        '/api/subscription/subscribe',
        data: {'planCode': planCode, 'method': 'none'},
      );
      setState(() => _statusFuture = _fetchStatus());
    } finally {
      if (mounted) {
        setState(() => _isSubmitting = false);
      }
    }
  }

  Future<void> _cancel() async {
    setState(() => _isSubmitting = true);
    try {
      await ref.read(apiServiceProvider).postJson('/api/subscription/cancel');
      setState(() => _statusFuture = _fetchStatus());
    } finally {
      if (mounted) {
        setState(() => _isSubmitting = false);
      }
    }
  }
}

class _PlanCard extends StatelessWidget {
  const _PlanCard({
    required this.title,
    required this.price,
    required this.features,
    required this.onPressed,
  });

  final String title;
  final String price;
  final List<String> features;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 6),
            Text(price, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            ...features.map(
              (feature) => Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Row(
                  children: [
                    const Icon(Icons.check_circle_outline, size: 18),
                    const SizedBox(width: 8),
                    Expanded(child: Text(feature)),
                  ],
                ),
              ),
            ),
            if (onPressed != null) ...[
              const SizedBox(height: 12),
              FilledButton(
                onPressed: onPressed,
                child: const Text('Activer'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
