class UserModel {
  const UserModel({
    required this.id,
    required this.email,
    required this.roles,
    this.phone,
    this.profileCompletedPercent = 0,
    this.subscriptionTier = 'free',
  });

  final String id;
  final String email;
  final String? phone;
  final List<String> roles;
  final int profileCompletedPercent;
  final String subscriptionTier;

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['id']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      phone: json['phone']?.toString(),
      roles: (json['roles'] as List<dynamic>? ?? const [])
          .map((role) => role.toString())
          .toList(),
      profileCompletedPercent:
          int.tryParse(json['profileCompletedPercent']?.toString() ?? '') ?? 0,
      subscriptionTier: json['subscriptionTier']?.toString() ?? 'free',
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'email': email,
        'phone': phone,
        'roles': roles,
        'profileCompletedPercent': profileCompletedPercent,
        'subscriptionTier': subscriptionTier,
      };
}

class CandidateProfileModel {
  const CandidateProfileModel({
    required this.userId,
    required this.firstName,
    required this.lastName,
    this.birthDate,
    required this.city,
    required this.educationLevel,
    required this.skills,
    required this.languages,
    required this.experiences,
    required this.interests,
    required this.references,
    required this.availability,
    this.educationField,
    this.salaryExpectation,
    this.cvOriginalUrl,
    this.cvGeneratedUrl,
    this.profileCompletedPercent = 0,
  });

  final String userId;
  final String firstName;
  final String lastName;
  final String? birthDate;
  final String city;
  final String educationLevel;
  final String? educationField;
  final List<String> skills;
  final List<Map<String, dynamic>> languages;
  final List<String> experiences;
  final List<String> interests;
  final List<String> references;
  final String availability;
  final int? salaryExpectation;
  final String? cvOriginalUrl;
  final String? cvGeneratedUrl;
  final int profileCompletedPercent;

  factory CandidateProfileModel.empty() {
    return const CandidateProfileModel(
      userId: '',
      firstName: '',
      lastName: '',
      city: 'Ouagadougou',
      educationLevel: 'Aucun',
      skills: [],
      languages: [],
      experiences: [],
      interests: [],
      references: [],
      availability: 'Immédiate',
    );
  }

  factory CandidateProfileModel.fromJson(Map<String, dynamic> json) {
    return CandidateProfileModel(
      userId: json['userId']?.toString() ?? '',
      firstName: json['firstName']?.toString() ?? '',
      lastName: json['lastName']?.toString() ?? '',
      birthDate: json['birthDate']?.toString(),
      city: json['city']?.toString() ?? '',
      educationLevel: json['educationLevel']?.toString() ?? 'Aucun',
      educationField: json['educationField']?.toString(),
      skills: (json['skills'] as List<dynamic>? ?? const [])
          .map((skill) => skill.toString())
          .toList(),
      languages: (json['languages'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map((language) => Map<String, dynamic>.from(language))
          .toList(),
      experiences: (json['experiences'] as List<dynamic>? ?? const [])
          .map((item) => item.toString())
          .toList(),
      interests: (json['interests'] as List<dynamic>? ?? const [])
          .map((item) => item.toString())
          .toList(),
      references: (json['references'] as List<dynamic>? ?? const [])
          .map((item) => item.toString())
          .toList(),
      availability: json['availability']?.toString() ?? 'Immédiate',
      salaryExpectation:
          int.tryParse(json['salaryExpectation']?.toString() ?? ''),
      cvOriginalUrl: json['cvOriginalUrl']?.toString(),
      cvGeneratedUrl: json['cvGeneratedUrl']?.toString(),
      profileCompletedPercent:
          int.tryParse(json['profileCompletedPercent']?.toString() ?? '') ?? 0,
    );
  }

  Map<String, dynamic> toJson() => {
        'userId': userId,
        'firstName': firstName,
        'lastName': lastName,
        'birthDate': birthDate,
        'city': city,
        'educationLevel': educationLevel,
        'educationField': educationField,
        'skills': skills,
        'languages': languages,
        'experiences': experiences,
        'interests': interests,
        'references': references,
        'availability': availability,
        'salaryExpectation': salaryExpectation,
        'cvOriginalUrl': cvOriginalUrl,
        'cvGeneratedUrl': cvGeneratedUrl,
        'profileCompletedPercent': profileCompletedPercent,
      };
}
