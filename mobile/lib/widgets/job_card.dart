import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../models/job_model.dart';

class JobCard extends StatelessWidget {
  const JobCard({super.key, required this.job});

  final JobModel job;

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      elevation: 6,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            height: 150,
            width: double.infinity,
            child: job.companyLogoUrl == null || job.companyLogoUrl!.isEmpty
                ? Container(
                    color: Theme.of(context).colorScheme.primaryContainer,
                    child: const Icon(Icons.business, size: 72),
                  )
                : CachedNetworkImage(
                    imageUrl: job.companyLogoUrl!,
                    fit: BoxFit.cover,
                    placeholder: (context, url) => const Center(
                      child: CircularProgressIndicator(),
                    ),
                    errorWidget: (context, url, error) => const Icon(Icons.business),
                  ),
          ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          job.title,
                          style: Theme.of(context).textTheme.headlineSmall,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      if (job.matchScore != null)
                        CircleAvatar(
                          child: Text('${job.matchScore}%'),
                        ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(job.companyName ?? 'Entreprise'),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      const Icon(Icons.location_on_outlined, size: 18),
                      const SizedBox(width: 4),
                      Expanded(child: Text(job.location)),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: job.requiredSkills
                        .take(5)
                        .map((skill) => Chip(label: Text(skill)))
                        .toList(),
                  ),
                  const SizedBox(height: 10),
                  Expanded(
                    child: Text(
                      job.description,
                      maxLines: 5,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  Text(
                    job.contractType,
                    style: Theme.of(context).textTheme.labelLarge,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
