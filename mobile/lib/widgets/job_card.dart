import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../models/job_model.dart';
import '../theme/app_theme.dart';
import 'kibare_logo.dart';

class JobCard extends StatelessWidget {
  const JobCard({
    super.key,
    required this.job,
    this.dragOffset = Offset.zero,
  });

  final JobModel job;
  final Offset dragOffset;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final borderColor = isDark
        ? Colors.white.withValues(alpha: 0.14)
        : AppColors.primary.withValues(alpha: 0.08);

    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(30),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.36 : 0.13),
            blurRadius: 30,
            offset: const Offset(0, 18),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(30),
        child: Stack(
          fit: StackFit.expand,
          children: [
            _HeroVisual(job: job),
            _ReadabilityOverlay(isDark: isDark),
            Positioned.fill(
              child: DecoratedBox(
                decoration: BoxDecoration(
                  border: Border.all(color: borderColor),
                  borderRadius: BorderRadius.circular(30),
                ),
              ),
            ),
            Positioned(
              left: 18,
              top: 18,
              right: 18,
              child: _TopMeta(job: job),
            ),
            if (dragOffset.dx.abs() > 18)
              Positioned(
                top: 92,
                left: dragOffset.dx > 0 ? 22 : null,
                right: dragOffset.dx < 0 ? 22 : null,
                child: _SwipeStamp(
                  label: dragOffset.dx > 0 ? 'POSTULER' : 'PASSER',
                  icon: dragOffset.dx > 0
                      ? Icons.favorite_rounded
                      : Icons.close_rounded,
                  color: dragOffset.dx > 0
                      ? AppColors.accent
                      : const Color(0xFFE7EDF3),
                ),
              ),
            Positioned(
              left: 22,
              right: 22,
              bottom: 24,
              child: _Content(job: job),
            ),
          ],
        ),
      ),
    );
  }
}

class _HeroVisual extends StatelessWidget {
  const _HeroVisual({required this.job});

  final JobModel job;

  @override
  Widget build(BuildContext context) {
    final hasLogo =
        job.companyLogoUrl != null && job.companyLogoUrl!.isNotEmpty;

    if (hasLogo) {
      return CachedNetworkImage(
        imageUrl: job.companyLogoUrl!,
        fit: BoxFit.cover,
        placeholder: (context, url) => const _FallbackVisual(),
        errorWidget: (context, url, error) => const _FallbackVisual(),
      );
    }

    return const _FallbackVisual();
  }
}

class _FallbackVisual extends StatelessWidget {
  const _FallbackVisual();

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: isDark
              ? const [Color(0xFF182434), Color(0xFF263849), Color(0xFF3F5669)]
              : const [Color(0xFFEAF0F5), Color(0xFFDCE6EE), Color(0xFFB8C7B4)],
        ),
      ),
      child: Center(
        child: Container(
          width: 138,
          height: 138,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: isDark ? 0.10 : 0.40),
            shape: BoxShape.circle,
            border: Border.all(color: Colors.white.withValues(alpha: 0.24)),
          ),
          child: const Center(child: KibareLogo(size: 86)),
        ),
      ),
    );
  }
}

class _ReadabilityOverlay extends StatelessWidget {
  const _ReadabilityOverlay({required this.isDark});

  final bool isDark;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [
            Colors.black.withValues(alpha: isDark ? 0.30 : 0.14),
            Colors.transparent,
            Colors.black.withValues(alpha: 0.34),
            Colors.black.withValues(alpha: 0.88),
          ],
          stops: const [0, 0.34, 0.62, 1],
        ),
      ),
    );
  }
}

class _TopMeta extends StatelessWidget {
  const _TopMeta({required this.job});

  final JobModel job;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        _GlassPill(
          icon: Icons.business_center_outlined,
          label: _formatContract(job.contractType),
        ),
        const Spacer(),
        if (job.matchScore != null)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.18),
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: Colors.white.withValues(alpha: 0.22)),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(
                  Icons.auto_awesome_rounded,
                  color: AppColors.accent,
                  size: 17,
                ),
                const SizedBox(width: 6),
                Text(
                  '${job.matchScore}% match',
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w900,
                    fontSize: 13,
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class _Content extends StatelessWidget {
  const _Content({required this.job});

  final JobModel job;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          job.title,
          maxLines: 3,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 30,
            fontWeight: FontWeight.w900,
            height: 1.03,
          ),
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            Expanded(
              child: Text(
                job.companyName ?? 'Entreprise',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.86),
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            if (_salaryLabel(job) != null) ...[
              const SizedBox(width: 10),
              Text(
                _salaryLabel(job)!,
                style: const TextStyle(
                  color: AppColors.accent,
                  fontWeight: FontWeight.w900,
                  fontSize: 13,
                ),
              ),
            ],
          ],
        ),
        const SizedBox(height: 12),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            _GlassPill(
              icon: Icons.location_on_outlined,
              label: job.location.isEmpty ? 'Lieu non precise' : job.location,
            ),
            if (job.deadline != null)
              _GlassPill(
                icon: Icons.event_available_outlined,
                label: 'Avant ${_formatDate(job.deadline!)}',
              ),
          ],
        ),
        const SizedBox(height: 12),
        if (job.requiredSkills.isNotEmpty)
          SizedBox(
            height: 34,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              physics: const BouncingScrollPhysics(),
              itemCount: job.requiredSkills.take(6).length,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (context, index) {
                final skill = job.requiredSkills[index];
                return Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 8,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.14),
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(
                      color: Colors.white.withValues(alpha: 0.18),
                    ),
                  ),
                  child: Text(
                    skill,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                );
              },
            ),
          ),
        const SizedBox(height: 14),
        Text(
          job.description,
          maxLines: 3,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.76),
            fontSize: 13.5,
            fontWeight: FontWeight.w600,
            height: 1.4,
          ),
        ),
      ],
    );
  }
}

class _GlassPill extends StatelessWidget {
  const _GlassPill({
    required this.icon,
    required this.label,
  });

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 210),
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white.withValues(alpha: 0.20)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 15, color: Colors.white),
          const SizedBox(width: 6),
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 12,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SwipeStamp extends StatelessWidget {
  const _SwipeStamp({
    required this.label,
    required this.icon,
    required this.color,
  });

  final String label;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Transform.rotate(
      angle: label == 'POSTULER' ? -0.16 : 0.16,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.16),
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: color, width: 2.4),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: color, size: 24),
            const SizedBox(width: 8),
            Text(
              label,
              style: TextStyle(
                color: color,
                fontSize: 20,
                fontWeight: FontWeight.w900,
                letterSpacing: 0,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

String _formatContract(String value) {
  if (value.isEmpty) {
    return 'Contrat';
  }

  return value.replaceAll('_', ' ').toUpperCase();
}

String _formatDate(DateTime date) {
  final day = date.day.toString().padLeft(2, '0');
  final month = date.month.toString().padLeft(2, '0');

  return '$day/$month';
}

String? _salaryLabel(JobModel job) {
  final min = job.salaryMin;
  final max = job.salaryMax;

  if (min == null && max == null) {
    return null;
  }

  if (min != null && max != null && max > min) {
    return '${_compactMoney(min)} - ${_compactMoney(max)}';
  }

  return _compactMoney(min ?? max!);
}

String _compactMoney(int value) {
  if (value >= 1000000) {
    final millions = value / 1000000;
    return '${millions.toStringAsFixed(millions >= 10 ? 0 : 1)}M FCFA';
  }

  if (value >= 1000) {
    final thousands = value / 1000;
    return '${thousands.toStringAsFixed(thousands >= 10 ? 0 : 1)}k FCFA';
  }

  return '$value FCFA';
}
