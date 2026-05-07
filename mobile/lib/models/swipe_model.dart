class SwipeModel {
  const SwipeModel({
    required this.id,
    required this.direction,
    required this.status,
    this.matchScore,
    this.sentAt,
    this.offerTitle,
  });

  final String id;
  final String direction;
  final String status;
  final int? matchScore;
  final DateTime? sentAt;
  final String? offerTitle;

  factory SwipeModel.fromJson(Map<String, dynamic> json) {
    final offer = json['offer'] is Map<String, dynamic>
        ? json['offer'] as Map<String, dynamic>
        : null;

    return SwipeModel(
      id: json['id']?.toString() ?? json['swipeId']?.toString() ?? '',
      direction: json['direction']?.toString() ?? 'like',
      status: json['status']?.toString() ?? 'sent',
      matchScore: int.tryParse(json['matchScore']?.toString() ?? ''),
      sentAt: DateTime.tryParse(json['sentAt']?.toString() ?? ''),
      offerTitle: offer?['title']?.toString(),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'direction': direction,
        'status': status,
        'matchScore': matchScore,
        'sentAt': sentAt?.toIso8601String(),
        'offerTitle': offerTitle,
      };
}
