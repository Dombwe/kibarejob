import 'package:flutter/material.dart';

class VerificationBadge extends StatelessWidget {
  const VerificationBadge({
    super.key,
    required this.isVerified,
    required this.score,
  });

  final bool isVerified;
  final int score;

  @override
  Widget build(BuildContext context) {
    final color = isVerified ? Colors.green : Colors.orange;
    final label = isVerified ? 'Verifie' : 'A verifier';

    return Chip(
      visualDensity: VisualDensity.compact,
      avatar: Icon(
        isVerified ? Icons.verified : Icons.pending,
        color: color,
        size: 18,
      ),
      label: Text('$label ($score%)'),
    );
  }
}
