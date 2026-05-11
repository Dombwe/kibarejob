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
    final surface = isDark ? AppColors.darkSurface : Colors.white;
    final borderColor =
        isDark ? Colors.white.withValues(alpha: 0.12) : const Color(0xFFE2E8F0);

    return DecoratedBox(
      decoration: BoxDecoration(
        color: surface,
        borderRadius: BorderRadius.circular(30),
        border: Border.all(color: borderColor),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.30 : 0.10),
            blurRadius: 34,
            offset: const Offset(0, 18),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(30),
        child: Stack(
          children: [
            Positioned.fill(
              child: DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: isDark
                        ? const [Color(0xFF1E293B), Color(0xFF111827)]
                        : const [Color(0xFFFFFFFF), Color(0xFFF8FAFC)],
                  ),
                ),
              ),
            ),
            if (dragOffset.dx.abs() > 18)
              Positioned(
                top: 24,
                left: dragOffset.dx > 0 ? 24 : null,
                right: dragOffset.dx < 0 ? 24 : null,
                child: _SwipeStamp(
                  label: dragOffset.dx > 0 ? 'POSTULER' : 'PASSER',
                  icon: dragOffset.dx > 0
                      ? Icons.favorite_rounded
                      : Icons.close_rounded,
                  color: dragOffset.dx > 0
                      ? AppColors.primary
                      : const Color(0xFF7A8694),
                ),
              ),
            LayoutBuilder(
              builder: (context, constraints) {
                final compact = constraints.maxHeight < 560;
                final dense = constraints.maxHeight < 500;
                final tight = constraints.maxHeight < 460;
                final gap = dense ? 6.0 : (compact ? 10.0 : 18.0);

                return Padding(
                  padding: EdgeInsets.fromLTRB(
                    dense ? 12 : (compact ? 16 : 22),
                    dense ? 12 : (compact ? 16 : 22),
                    dense ? 12 : (compact ? 16 : 22),
                    dense ? 10 : (compact ? 14 : 18),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _CompanyHeader(job: job, compact: compact, dense: dense),
                      SizedBox(height: dense ? 8 : (compact ? 12 : 22)),
                      Text(
                        job.title,
                        maxLines: dense ? 2 : (compact ? 2 : 3),
                        overflow: TextOverflow.ellipsis,
                        style: (compact
                                ? theme.textTheme.headlineSmall
                                : theme.textTheme.headlineMedium)
                            ?.copyWith(
                          height: 1.08,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      SizedBox(height: dense ? 4 : (compact ? 6 : 10)),
                      Text(
                        job.companyName ?? 'Entreprise',
                        maxLines: dense ? 1 : 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodyLarge?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      SizedBox(height: dense ? 6 : (compact ? 8 : 12)),
                      _DescriptionExcerpt(job: job, dense: dense),
                      SizedBox(height: dense ? 6 : (compact ? 10 : 18)),
                      _InfoGrid(job: job, compact: compact, dense: dense),
                      if (job.requiredSkills.isNotEmpty &&
                          !tight &&
                          !dense) ...[
                        SizedBox(height: gap),
                        _SkillStrip(
                            skills: job.requiredSkills, compact: compact),
                      ],
                      const Spacer(),
                      SizedBox(height: dense ? 6 : (compact ? 8 : 12)),
                      _Footer(job: job),
                    ],
                  ),
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _DescriptionExcerpt extends StatelessWidget {
  const _DescriptionExcerpt({
    required this.job,
    required this.dense,
  });

  final JobModel job;
  final bool dense;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      width: double.infinity,
      padding: EdgeInsets.symmetric(
        horizontal: dense ? 10 : 12,
        vertical: dense ? 8 : 10,
      ),
      decoration: BoxDecoration(
        color: theme.colorScheme.primary.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Text(
        job.description,
        maxLines: dense ? 2 : 3,
        overflow: TextOverflow.ellipsis,
        style: theme.textTheme.bodyMedium?.copyWith(
          height: 1.34,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _CompanyHeader extends StatelessWidget {
  const _CompanyHeader({
    required this.job,
    required this.compact,
    required this.dense,
  });

  final JobModel job;
  final bool compact;
  final bool dense;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        _CompanyLogo(job: job, compact: compact, dense: dense),
        SizedBox(width: dense ? 8 : (compact ? 10 : 12)),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Offre recommandee',
                style: Theme.of(context).textTheme.labelLarge,
              ),
              SizedBox(height: dense ? 1 : (compact ? 2 : 4)),
              Text(
                _formatContract(job.contractType),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
            ],
          ),
        ),
        if (job.matchScore != null)
          Container(
            padding: EdgeInsets.symmetric(
              horizontal: dense ? 8 : (compact ? 10 : 12),
              vertical: dense ? 6 : (compact ? 8 : 10),
            ),
            decoration: BoxDecoration(
              color: _matchTone(job.matchScore!).background,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: _matchTone(job.matchScore!).border),
            ),
            child: Column(
              children: [
                Text(
                  '${job.matchScore}%',
                  style: TextStyle(
                    color: _matchTone(job.matchScore!).foreground,
                    fontSize: dense ? 15 : 18,
                    fontWeight: FontWeight.w900,
                    height: 1,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  'match',
                  style: TextStyle(
                    color: _matchTone(job.matchScore!).foreground,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class _CompanyLogo extends StatelessWidget {
  const _CompanyLogo({
    required this.job,
    required this.compact,
    required this.dense,
  });

  final JobModel job;
  final bool compact;
  final bool dense;

  @override
  Widget build(BuildContext context) {
    final hasLogo =
        job.companyLogoUrl != null && job.companyLogoUrl!.isNotEmpty;

    return Container(
      width: dense ? 42 : (compact ? 48 : 58),
      height: dense ? 42 : (compact ? 48 : 58),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(20),
      ),
      clipBehavior: Clip.antiAlias,
      child: hasLogo
          ? CachedNetworkImage(
              imageUrl: job.companyLogoUrl!,
              fit: BoxFit.cover,
              errorWidget: (_, __, ___) => Center(
                  child: KibareLogo(size: dense ? 24 : (compact ? 28 : 34))),
            )
          : Center(child: KibareLogo(size: dense ? 24 : (compact ? 28 : 34))),
    );
  }
}

class _InfoGrid extends StatelessWidget {
  const _InfoGrid({
    required this.job,
    required this.compact,
    required this.dense,
  });

  final JobModel job;
  final bool compact;
  final bool dense;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Row(
          children: [
            Expanded(
              child: _InfoTile(
                icon: Icons.location_on_outlined,
                label: 'Lieu',
                value: job.location.isEmpty ? 'Non precise' : job.location,
                compact: compact,
              ),
            ),
            SizedBox(width: compact ? 8 : 10),
            Expanded(
              child: _InfoTile(
                icon: Icons.payments_outlined,
                label: 'Salaire',
                value: _salaryLabel(job) ?? 'A negocier',
                compact: compact,
              ),
            ),
          ],
        ),
        if (!dense) ...[
          SizedBox(height: compact ? 8 : 10),
          Row(
            children: [
              Expanded(
                child: _InfoTile(
                  icon: Icons.schedule_rounded,
                  label: 'Limite',
                  value: job.deadline == null
                      ? 'Non precisee'
                      : _formatDate(job.deadline!),
                  compact: compact,
                ),
              ),
              SizedBox(width: compact ? 8 : 10),
              Expanded(
                child: _InfoTile(
                  icon: Icons.business_center_outlined,
                  label: 'Contrat',
                  value: _formatContract(job.contractType),
                  compact: compact,
                ),
              ),
            ],
          ),
        ],
      ],
    );
  }
}

class _InfoTile extends StatelessWidget {
  const _InfoTile({
    required this.icon,
    required this.label,
    required this.value,
    required this.compact,
  });

  final IconData icon;
  final String label;
  final String value;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      padding: EdgeInsets.all(compact ? 8 : 12),
      decoration: BoxDecoration(
        color: isDark
            ? Colors.white.withValues(alpha: 0.06)
            : const Color(0xFFF1F5F9),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        children: [
          Icon(
            icon,
            size: compact ? 16 : 18,
            color: Theme.of(context).colorScheme.primary,
          ),
          SizedBox(width: compact ? 6 : 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: Theme.of(context).textTheme.labelSmall),
                SizedBox(height: compact ? 1 : 2),
                Text(
                  value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SkillStrip extends StatelessWidget {
  const _SkillStrip({
    required this.skills,
    required this.compact,
  });

  final List<String> skills;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: compact ? 6 : 8,
      runSpacing: compact ? 6 : 8,
      children: skills.take(compact ? 3 : 5).map((skill) {
        return Container(
          padding: EdgeInsets.symmetric(
            horizontal: compact ? 9 : 11,
            vertical: compact ? 5 : 7,
          ),
          decoration: BoxDecoration(
            color: AppColors.secondary.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(999),
          ),
          child: Text(
            skill,
            style: TextStyle(
              color: Theme.of(context).brightness == Brightness.dark
                  ? const Color(0xFFE2E8F0)
                  : AppColors.primary,
              fontSize: 12,
              fontWeight: FontWeight.w800,
            ),
          ),
        );
      }).toList(),
    );
  }
}

class _Footer extends StatelessWidget {
  const _Footer({required this.job});

  final JobModel job;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(
          Icons.touch_app_outlined,
          size: 18,
          color: Theme.of(context).colorScheme.primary,
        ),
        const SizedBox(width: 7),
        Expanded(
          child: Text(
            'Touchez la carte pour voir les details',
            style: Theme.of(context).textTheme.labelLarge,
          ),
        ),
        const Icon(Icons.arrow_forward_ios_rounded, size: 15),
      ],
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
      angle: label == 'POSTULER' ? -0.12 : 0.12,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.10),
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: color, width: 2),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: color, size: 22),
            const SizedBox(width: 8),
            Text(
              label,
              style: TextStyle(
                color: color,
                fontSize: 18,
                fontWeight: FontWeight.w900,
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

  return '$day/$month/${date.year}';
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
    return '${millions.toStringAsFixed(millions >= 10 ? 0 : 1)}M';
  }

  if (value >= 1000) {
    final thousands = value / 1000;
    return '${thousands.toStringAsFixed(thousands >= 10 ? 0 : 1)}k';
  }

  return '$value';
}

_MatchTone _matchTone(int score) {
  if (score >= 75) {
    return const _MatchTone(
      background: Color(0xFFE7F3EC),
      border: Color(0xFF9FCDB2),
      foreground: Color(0xFF2F6F5E),
    );
  }

  if (score >= 50) {
    return const _MatchTone(
      background: Color(0xFFFFF4D8),
      border: Color(0xFFE6C46E),
      foreground: Color(0xFF8A6516),
    );
  }

  return const _MatchTone(
    background: Color(0xFFFFE8E3),
    border: Color(0xFFE7A99C),
    foreground: Color(0xFF9A3E31),
  );
}

class _MatchTone {
  const _MatchTone({
    required this.background,
    required this.border,
    required this.foreground,
  });

  final Color background;
  final Color border;
  final Color foreground;
}
