class SwipeModel {
  const SwipeModel({
    required this.id,
    required this.direction,
    required this.status,
    this.matchScore,
    this.sentAt,
    this.viewedAt,
    this.offerTitle,
    this.offerCompany,
    this.offerLocation,
    this.offerExternalUrl,
    this.offerApplicationEmail,
    this.cvUsedUrl,
    this.motivationLetterText,
    this.documentsSent = const [],
    this.attachments = const [],
    this.generatedAttachments = const [],
    this.email = const ApplicationEmailModel(),
  });

  final String id;
  final String direction;
  final String status;
  final int? matchScore;
  final DateTime? sentAt;
  final DateTime? viewedAt;
  final String? offerTitle;
  final String? offerCompany;
  final String? offerLocation;
  final String? offerExternalUrl;
  final String? offerApplicationEmail;
  final String? cvUsedUrl;
  final String? motivationLetterText;
  final List<String> documentsSent;
  final List<ApplicationAttachmentModel> attachments;
  final List<ApplicationAttachmentModel> generatedAttachments;
  final ApplicationEmailModel email;

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
      viewedAt: DateTime.tryParse(json['viewedAt']?.toString() ?? ''),
      offerTitle: offer?['title']?.toString(),
      offerCompany: offer?['companyName']?.toString(),
      offerLocation: offer?['location']?.toString(),
      offerExternalUrl: offer?['externalUrl']?.toString(),
      offerApplicationEmail: offer?['applicationEmail']?.toString(),
      cvUsedUrl: json['cvUsedUrl']?.toString(),
      motivationLetterText: json['motivationLetterText']?.toString(),
      documentsSent: (json['documentsSent'] as List<dynamic>? ?? const [])
          .map((item) => item.toString())
          .toList(),
      attachments: (json['attachments'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map((item) => ApplicationAttachmentModel.fromJson(
              Map<String, dynamic>.from(item)))
          .toList(),
      generatedAttachments:
          (json['generatedAttachments'] as List<dynamic>? ?? const [])
              .whereType<Map>()
              .map((item) => ApplicationAttachmentModel.fromJson(
                  Map<String, dynamic>.from(item)))
              .toList(),
      email: json['email'] is Map
          ? ApplicationEmailModel.fromJson(
              Map<String, dynamic>.from(json['email'] as Map))
          : const ApplicationEmailModel(),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'direction': direction,
        'status': status,
        'matchScore': matchScore,
        'sentAt': sentAt?.toIso8601String(),
        'viewedAt': viewedAt?.toIso8601String(),
        'offerTitle': offerTitle,
        'offerCompany': offerCompany,
        'offerLocation': offerLocation,
        'offerExternalUrl': offerExternalUrl,
        'offerApplicationEmail': offerApplicationEmail,
        'cvUsedUrl': cvUsedUrl,
        'motivationLetterText': motivationLetterText,
        'documentsSent': documentsSent,
        'attachments': attachments.map((item) => item.toJson()).toList(),
        'generatedAttachments':
            generatedAttachments.map((item) => item.toJson()).toList(),
        'email': email.toJson(),
      };
}

class ApplicationEmailModel {
  const ApplicationEmailModel({
    this.sent = false,
    this.recipient,
    this.sender,
    this.replyTo,
    this.subject,
    this.body,
    this.error,
    this.sentAt,
  });

  final bool sent;
  final String? recipient;
  final String? sender;
  final String? replyTo;
  final String? subject;
  final String? body;
  final String? error;
  final DateTime? sentAt;

  factory ApplicationEmailModel.fromJson(Map<String, dynamic> json) {
    return ApplicationEmailModel(
      sent: json['sent'] == true,
      recipient: json['recipient']?.toString(),
      sender: json['sender']?.toString(),
      replyTo: json['replyTo']?.toString(),
      subject: json['subject']?.toString(),
      body: json['body']?.toString(),
      error: json['error']?.toString(),
      sentAt: DateTime.tryParse(json['sentAt']?.toString() ?? ''),
    );
  }

  Map<String, dynamic> toJson() => {
        'sent': sent,
        'recipient': recipient,
        'sender': sender,
        'replyTo': replyTo,
        'subject': subject,
        'body': body,
        'error': error,
        'sentAt': sentAt?.toIso8601String(),
      };
}

class ApplicationAttachmentModel {
  const ApplicationAttachmentModel({
    this.id = '',
    required this.title,
    required this.type,
    this.fileUrl = '',
    this.content,
  });

  final String id;
  final String title;
  final String type;
  final String fileUrl;
  final String? content;

  factory ApplicationAttachmentModel.fromJson(Map<String, dynamic> json) {
    return ApplicationAttachmentModel(
      id: json['id']?.toString() ?? '',
      title: json['title']?.toString() ?? 'Document',
      type: json['type']?.toString() ?? 'other',
      fileUrl: json['fileUrl']?.toString() ?? '',
      content: json['content']?.toString(),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'title': title,
        'type': type,
        'fileUrl': fileUrl,
        'content': content,
      };
}
