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
    required this.city,
    required this.educationLevel,
    required this.skills,
    required this.languages,
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
  final String city;
  final String educationLevel;
  final String? educationField;
  final List<String> skills;
  final List<Map<String, dynamic>> languages;
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
      availability: 'Immediate',
    );
  }

  factory CandidateProfileModel.fromJson(Map<String, dynamic> json) {
    return CandidateProfileModel(
      userId: json['userId']?.toString() ?? '',
      firstName: json['firstName']?.toString() ?? '',
      lastName: json['lastName']?.toString() ?? '',
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
      availability: json['availability']?.toString() ?? 'Immediate',
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
        'city': city,
        'educationLevel': educationLevel,
        'educationField': educationField,
        'skills': skills,
        'languages': languages,
        'availability': availability,
        'salaryExpectation': salaryExpectation,
        'cvOriginalUrl': cvOriginalUrl,
        'cvGeneratedUrl': cvGeneratedUrl,
        'profileCompletedPercent': profileCompletedPercent,
      };
}
