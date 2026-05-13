import 'package:flutter/material.dart';

import '../models/job_model.dart';
import '../theme/app_theme.dart';
import '../theme/responsive.dart';
import 'job_card.dart';

class CardStack extends StatefulWidget {
  const CardStack({
    super.key,
    required this.jobs,
    required this.onSwipe,
    required this.onOpenDetails,
  });

  final List<JobModel> jobs;
  final Future<void> Function(JobModel job, String direction) onSwipe;
  final void Function(JobModel job) onOpenDetails;

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
        final compact = Responsive.compact(context);
        final cardWidth = constraints.maxWidth.clamp(280.0, 560.0);
        final cardHeight = constraints.maxHeight.clamp(
          compact ? 300.0 : 340.0,
          compact ? 620.0 : 760.0,
        );

        return Padding(
          padding: EdgeInsets.symmetric(vertical: compact ? 4 : 8),
          child: Container(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(36),
              boxShadow: [
                if (Theme.of(context).brightness == Brightness.dark)
                  BoxShadow(
                    color: AppColors.accent.withValues(alpha: 0.16),
                    blurRadius: 28,
                    spreadRadius: 2,
                  ),
              ],
            ),
            child: DecoratedBox(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(34),
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: Theme.of(context).brightness == Brightness.dark
                      ? const [Color(0xFF050A12), Color(0xFF182435)]
                      : const [Color(0xFFE2E8F0), Color(0xFFF8FAFC)],
                ),
                border: Border.all(
                  color: Theme.of(context).brightness == Brightness.dark
                      ? AppColors.accent.withValues(alpha: 0.34)
                      : const Color(0xFFCBD5E1),
                  width:
                      Theme.of(context).brightness == Brightness.dark ? 1.4 : 1,
                ),
              ),
              child: Stack(
                alignment: Alignment.center,
                children: [
                  const Positioned(
                    left: 12,
                    top: 16,
                    child: _SwipeSideHint(
                      icon: Icons.keyboard_arrow_left_rounded,
                      label: 'Passer',
                      alignment: CrossAxisAlignment.start,
                    ),
                  ),
                  const Positioned(
                    right: 12,
                    top: 16,
                    child: _SwipeSideHint(
                      icon: Icons.keyboard_arrow_right_rounded,
                      label: 'Postuler',
                      alignment: CrossAxisAlignment.end,
                    ),
                  ),
                  for (var index = 0; index < visibleJobs.length; index++)
                    _buildPositionedCard(
                      context,
                      visibleJobs[index],
                      index,
                      visibleJobs.length - 1,
                      cardWidth - (compact ? 10 : 18),
                      cardHeight - (compact ? 8 : 14),
                    ),
                ],
              ),
            ),
          ),
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
                    onTap: () => widget.onOpenDetails(job),
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

class _SwipeSideHint extends StatelessWidget {
  const _SwipeSideHint({
    required this.icon,
    required this.label,
    required this.alignment,
  });

  final IconData icon;
  final String label;
  final CrossAxisAlignment alignment;

  @override
  Widget build(BuildContext context) {
    final isRight = alignment == CrossAxisAlignment.end;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final color = isRight
        ? (isDark ? AppColors.accent : AppColors.primary)
        : (isDark ? const Color(0xFFCBD5E1) : const Color(0xFF64748B));

    return Column(
      crossAxisAlignment: alignment,
      children: [
        Icon(icon, size: 24, color: color.withValues(alpha: 0.70)),
        Text(
          label,
          style: TextStyle(
            color: color.withValues(alpha: 0.78),
            fontSize: 12,
            fontWeight: FontWeight.w900,
          ),
        ),
      ],
    );
  }
}
