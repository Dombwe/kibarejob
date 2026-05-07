class JobModel {
  const JobModel({
    required this.id,
    required this.title,
    required this.description,
    required this.location,
    required this.contractType,
    required this.requiredSkills,
    required this.deadline,
    this.companyName,
    this.companyLogoUrl,
    this.salaryMin,
    this.salaryMax,
    this.matchScore,
  });

  final String id;
  final String title;
  final String description;
  final String location;
  final String contractType;
  final List<String> requiredSkills;
  final DateTime? deadline;
  final String? companyName;
  final String? companyLogoUrl;
  final int? salaryMin;
  final int? salaryMax;
  final int? matchScore;

  factory JobModel.fromJson(Map<String, dynamic> json) {
    final offer = json['offer'] is Map<String, dynamic>
        ? json['offer'] as Map<String, dynamic>
        : json;
    final company = offer['company'] is Map<String, dynamic>
        ? offer['company'] as Map<String, dynamic>
        : null;

    return JobModel(
      id: offer['id']?.toString() ?? '',
      title: offer['title']?.toString() ?? '',
      description: offer['description']?.toString() ?? '',
      location: offer['location']?.toString() ?? '',
      contractType: offer['contractType']?.toString() ?? '',
      requiredSkills: (offer['requiredSkills'] as List<dynamic>? ?? const [])
          .map((skill) => skill.toString())
          .toList(),
      deadline: DateTime.tryParse(offer['deadline']?.toString() ?? ''),
      companyName: company?['name']?.toString() ?? offer['companyName']?.toString(),
      companyLogoUrl: company?['logoUrl']?.toString(),
      salaryMin: int.tryParse(offer['salaryMin']?.toString() ?? ''),
      salaryMax: int.tryParse(offer['salaryMax']?.toString() ?? ''),
      matchScore: int.tryParse(json['matchScore']?.toString() ?? ''),
    );
  }
}
