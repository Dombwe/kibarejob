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
      return const Center(child: Text('Aucune offre disponible pour le moment'));
    }

    final visibleJobs = widget.jobs.take(3).toList().reversed.toList();

    return Stack(
      alignment: Alignment.center,
      children: [
        for (var index = 0; index < visibleJobs.length; index++)
          _buildPositionedCard(
            context,
            visibleJobs[index],
            index,
            visibleJobs.length - 1,
          ),
      ],
    );
  }

  Widget _buildPositionedCard(
    BuildContext context,
    JobModel job,
    int index,
    int topIndex,
  ) {
    final isTop = index == topIndex;
    final scale = 1 - ((topIndex - index) * 0.04);
    final yOffset = (topIndex - index) * 14.0;
    final angle = isTop ? _dragOffset.dx / 900 : 0.0;

    return Transform.translate(
      offset: isTop ? _dragOffset : Offset(0, yOffset),
      child: Transform.rotate(
        angle: angle,
        child: Transform.scale(
          scale: scale,
          child: SizedBox(
            width: MediaQuery.of(context).size.width * 0.9,
            height: MediaQuery.of(context).size.height * 0.66,
            child: isTop
                ? GestureDetector(
                    onPanUpdate: (details) {
                      setState(() => _dragOffset += details.delta);
                    },
                    onPanEnd: (_) => _handlePanEnd(job),
                    child: JobCard(job: job),
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
