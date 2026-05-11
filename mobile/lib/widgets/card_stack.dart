import 'package:flutter/material.dart';

import '../models/job_model.dart';
import 'job_card.dart';

class CardStack extends StatefulWidget {
  const CardStack({
    super.key,
    required this.jobs,
    required this.onSwipe,
  });

  final List<JobModel> jobs;
  final Future<void> Function(JobModel job, String direction) onSwipe;

  @override
  State<CardStack> createState() => _CardStackState();
}

class _CardStackState extends State<CardStack> {
  Offset _dragOffset = Offset.zero;

  @override
  Widget build(BuildContext context) {
    if (widget.jobs.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(28),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 86,
                height: 86,
                decoration: BoxDecoration(
                  color: Theme.of(context)
                      .colorScheme
                      .primary
                      .withValues(alpha: 0.10),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  Icons.work_outline_rounded,
                  color: Theme.of(context).colorScheme.primary,
                  size: 38,
                ),
              ),
              const SizedBox(height: 18),
              Text(
                'Aucune offre disponible',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 8),
              Text(
                'De nouvelles opportunites apparaitront ici des qu elles seront publiees.',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
            ],
          ),
        ),
      );
    }

    final visibleJobs = widget.jobs.take(3).toList().reversed.toList();

    return LayoutBuilder(
      builder: (context, constraints) {
        final cardWidth = constraints.maxWidth.clamp(280.0, 560.0);
        final cardHeight = constraints.maxHeight.clamp(420.0, 760.0);

        return Stack(
          alignment: Alignment.center,
          children: [
            for (var index = 0; index < visibleJobs.length; index++)
              _buildPositionedCard(
                context,
                visibleJobs[index],
                index,
                visibleJobs.length - 1,
                cardWidth,
                cardHeight,
              ),
          ],
        );
      },
    );
  }

  Widget _buildPositionedCard(
    BuildContext context,
    JobModel job,
    int index,
    int topIndex,
    double cardWidth,
    double cardHeight,
  ) {
    final isTop = index == topIndex;
    final depth = topIndex - index;
    final scale = 1 - (depth * 0.045);
    final yOffset = depth * 16.0;
    final angle = isTop ? _dragOffset.dx / 780 : 0.0;

    return Transform.translate(
      offset: isTop ? _dragOffset : Offset(0, yOffset),
      child: Transform.rotate(
        angle: angle,
        child: Transform.scale(
          scale: scale,
          child: SizedBox(
            width: cardWidth,
            height: cardHeight,
            child: isTop
                ? GestureDetector(
                    onPanUpdate: (details) {
                      setState(() => _dragOffset += details.delta);
                    },
                    onPanEnd: (_) => _handlePanEnd(job),
                    child: JobCard(job: job, dragOffset: _dragOffset),
                  )
                : JobCard(job: job),
          ),
        ),
      ),
    );
  }

  void _handlePanEnd(JobModel job) {
    final width = MediaQuery.of(context).size.width;
    final shouldSwipe = _dragOffset.dx.abs() > width * 0.24;

    if (!shouldSwipe) {
      setState(() => _dragOffset = Offset.zero);
      return;
    }

    final direction = _dragOffset.dx > 0 ? 'like' : 'dislike';
    setState(() => _dragOffset = Offset.zero);
    widget.onSwipe(job, direction);
  }
}
