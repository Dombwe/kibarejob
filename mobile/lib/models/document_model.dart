class DocumentModel {
  const DocumentModel({
    required this.id,
    required this.type,
    required this.title,
    required this.fileUrl,
    required this.isVerified,
    required this.confidenceScore,
    this.description,
    this.issuingOrganization,
    this.uploadedAt,
    this.tags = const [],
  });

  final String id;
  final String type;
  final String title;
  final String? description;
  final String? issuingOrganization;
  final String fileUrl;
  final bool isVerified;
  final int confidenceScore;
  final DateTime? uploadedAt;
  final List<String> tags;

  factory DocumentModel.fromJson(Map<String, dynamic> json) {
    return DocumentModel(
      id: json['id']?.toString() ?? '',
      type: json['type']?.toString() ?? 'other',
      title: json['title']?.toString() ?? '',
      description: json['description']?.toString(),
      issuingOrganization: json['issuingOrganization']?.toString(),
      fileUrl: json['fileUrl']?.toString() ?? '',
      isVerified: json['isVerified'] == true,
      confidenceScore:
          int.tryParse(json['confidenceScore']?.toString() ?? '') ?? 0,
      uploadedAt: DateTime.tryParse(json['uploadedAt']?.toString() ?? ''),
      tags: (json['tags'] as List<dynamic>? ?? const [])
          .map((tag) => tag.toString())
          .toList(),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'type': type,
        'title': title,
        'description': description,
        'issuingOrganization': issuingOrganization,
        'fileUrl': fileUrl,
        'isVerified': isVerified,
        'confidenceScore': confidenceScore,
        'uploadedAt': uploadedAt?.toIso8601String(),
        'tags': tags,
      };
}
