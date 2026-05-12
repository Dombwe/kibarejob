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
    this.requiredEducation,
    this.educationField,
    this.requiredExperienceYears,
    this.positions,
    this.isRemoteAllowed = false,
    this.requiredDocuments = const [],
    this.recommendedDocuments = const [],
    this.externalUrl,
    this.applicationEmail,
    this.externalSourceName,
    this.viewsCount = 0,
    this.applicationsCount = 0,
    this.likesCount = 0,
    this.createdAt,
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
  final String? requiredEducation;
  final String? educationField;
  final int? requiredExperienceYears;
  final int? positions;
  final bool isRemoteAllowed;
  final List<String> requiredDocuments;
  final List<String> recommendedDocuments;
  final String? externalUrl;
  final String? applicationEmail;
  final String? externalSourceName;
  final int viewsCount;
  final int applicationsCount;
  final int likesCount;
  final DateTime? createdAt;
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
      description: _cleanDescription(offer['description']?.toString() ?? ''),
      location: offer['location']?.toString() ?? '',
      contractType: offer['contractType']?.toString() ?? '',
      requiredSkills: (offer['requiredSkills'] as List<dynamic>? ?? const [])
          .map((skill) => skill.toString())
          .toList(),
      deadline: DateTime.tryParse(offer['deadline']?.toString() ?? ''),
      companyName:
          company?['name']?.toString() ?? offer['companyName']?.toString(),
      companyLogoUrl: company?['logoUrl']?.toString(),
      requiredEducation: offer['requiredEducation']?.toString(),
      educationField: offer['educationField']?.toString(),
      requiredExperienceYears:
          int.tryParse(offer['requiredExperienceYears']?.toString() ?? ''),
      positions: int.tryParse(offer['positions']?.toString() ?? ''),
      isRemoteAllowed: offer['isRemoteAllowed'] == true,
      requiredDocuments: _stringList(offer['requiredDocuments']),
      recommendedDocuments: _stringList(offer['recommendedDocuments']),
      externalUrl: offer['externalUrl']?.toString(),
      applicationEmail: offer['applicationEmail']?.toString(),
      externalSourceName: offer['externalSourceName']?.toString(),
      viewsCount: int.tryParse(offer['viewsCount']?.toString() ?? '') ?? 0,
      applicationsCount:
          int.tryParse(offer['applicationsCount']?.toString() ?? '') ?? 0,
      likesCount: int.tryParse(offer['likesCount']?.toString() ?? '') ?? 0,
      createdAt: DateTime.tryParse(offer['createdAt']?.toString() ?? ''),
      salaryMin: int.tryParse(offer['salaryMin']?.toString() ?? ''),
      salaryMax: int.tryParse(offer['salaryMax']?.toString() ?? ''),
      matchScore: int.tryParse(json['matchScore']?.toString() ?? ''),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'matchScore': matchScore,
      'offer': {
        'id': id,
        'title': title,
        'description': description,
        'location': location,
        'contractType': contractType,
        'requiredSkills': requiredSkills,
        'deadline': deadline?.toIso8601String(),
        'requiredEducation': requiredEducation,
        'educationField': educationField,
        'requiredExperienceYears': requiredExperienceYears,
        'positions': positions,
        'isRemoteAllowed': isRemoteAllowed,
        'requiredDocuments': requiredDocuments,
        'recommendedDocuments': recommendedDocuments,
        'externalUrl': externalUrl,
        'applicationEmail': applicationEmail,
        'externalSourceName': externalSourceName,
        'viewsCount': viewsCount,
        'applicationsCount': applicationsCount,
        'likesCount': likesCount,
        'createdAt': createdAt?.toIso8601String(),
        'salaryMin': salaryMin,
        'salaryMax': salaryMax,
        'company': {
          'name': companyName,
          'logoUrl': companyLogoUrl,
        },
      },
    };
  }

  static List<String> _stringList(Object? value) {
    if (value is! List) {
      return const [];
    }

    return value
        .map((item) {
          if (item is Map) {
            return (item['label'] ??
                    item['name'] ??
                    item['title'] ??
                    'Document')
                .toString();
          }

          return item.toString();
        })
        .where((item) => item.trim().isNotEmpty)
        .toList();
  }

  static String _cleanDescription(String value) {
    var text = value;

    text = text
        .replaceAll(r'\r\n', '\n')
        .replaceAll(r'\n', '\n')
        .replaceAll(r'\t', ' ')
        .replaceAll(RegExp(r'<\s*br\s*/?\s*>', caseSensitive: false), '\n')
        .replaceAll(RegExp(r'</\s*p\s*>', caseSensitive: false), '\n')
        .replaceAll(RegExp(r'</\s*li\s*>', caseSensitive: false), '\n')
        .replaceAll(RegExp(r'<\s*li[^>]*>', caseSensitive: false), '• ')
        .replaceAll(RegExp(r'<[^>]+>'), ' ')
        .replaceAll('&nbsp;', ' ')
        .replaceAll('&amp;', '&')
        .replaceAll('&quot;', '"')
        .replaceAll('&#39;', "'")
        .replaceAll('&rsquo;', "'")
        .replaceAll('&lsquo;', "'")
        .replaceAll('&eacute;', 'é')
        .replaceAll('&egrave;', 'è')
        .replaceAll('&ecirc;', 'ê')
        .replaceAll('&agrave;', 'à')
        .replaceAll('&ccedil;', 'ç')
        .replaceAll('â€™', "'")
        .replaceAll('â€œ', '"')
        .replaceAll('â€', '"')
        .replaceAll('â€“', '-')
        .replaceAll('â€”', '-')
        .replaceAll('Â', '');

    text = text
        .split('\n')
        .map((line) => line.replaceAll(RegExp(r'[ \t]+'), ' ').trim())
        .where((line) => line.isNotEmpty)
        .join('\n\n');

    return text.isEmpty ? 'Description non précisée.' : text;
  }
}
