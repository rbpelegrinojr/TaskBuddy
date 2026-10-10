import 'availability_slot.dart';
import 'category.dart';
import 'skill.dart';

class ProviderProfile {
  const ProviderProfile({
    required this.id,
    required this.fullName,
    this.headline,
    this.bio,
    this.location,
    this.experienceSummary,
    this.yearsExperience,
    this.minRate,
    this.categories = const [],
    this.skills = const [],
    this.availability = const [],
    this.completedTasks,
    this.profileComplete,
    this.missingFields = const [],
    this.hasAvailability,
  });

  final int id;
  final String fullName;
  final String? headline;
  final String? bio;
  final String? location;
  final String? experienceSummary;
  final int? yearsExperience;
  final String? minRate;
  final List<Category> categories;
  final List<Skill> skills;
  final List<AvailabilitySlot> availability;
  final int? completedTasks;
  final bool? profileComplete;
  final List<String> missingFields;
  final bool? hasAvailability;

  factory ProviderProfile.fromPublicJson(Map<String, dynamic> json) {
    return ProviderProfile(
      id: json['id'] as int,
      fullName: json['full_name'] as String,
      headline: json['headline'] as String?,
      bio: json['bio'] as String?,
      location: json['location'] as String?,
      experienceSummary: json['experience_summary'] as String?,
      yearsExperience: json['years_experience'] as int?,
      minRate: json['min_rate'] as String?,
      categories: (json['categories'] as List<dynamic>? ?? const [])
          .map((item) => Category.fromJson(item as Map<String, dynamic>))
          .toList(),
      skills: (json['skills'] as List<dynamic>? ?? const [])
          .map((item) => Skill.fromJson(item as Map<String, dynamic>))
          .toList(),
      availability: (json['availability'] as List<dynamic>? ?? const [])
          .map((item) => AvailabilitySlot.fromJson(item as Map<String, dynamic>))
          .toList(),
      completedTasks: json['completed_tasks'] as int?,
    );
  }

  factory ProviderProfile.fromProviderDetailsJson(Map<String, dynamic> json) {
    return ProviderProfile(
      id: 0,
      fullName: '',
      categories: (json['categories'] as List<dynamic>? ?? const [])
          .map((item) => Category.fromJson(item as Map<String, dynamic>))
          .toList(),
      skills: (json['skills'] as List<dynamic>? ?? const [])
          .map((item) => Skill.fromJson(item as Map<String, dynamic>))
          .toList(),
      availability: (json['availability'] as List<dynamic>? ?? const [])
          .map((item) => AvailabilitySlot.fromJson(item as Map<String, dynamic>))
          .toList(),
      profileComplete: json['profile_complete'] as bool?,
      missingFields: (json['missing_fields'] as List<dynamic>? ?? const [])
          .map((item) => item.toString())
          .toList(),
      hasAvailability: json['has_availability'] as bool?,
    );
  }
}
