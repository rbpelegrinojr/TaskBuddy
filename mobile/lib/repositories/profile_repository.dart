import '../core/api/api_client.dart';
import '../models/availability_slot.dart';
import '../models/provider_profile.dart';
import '../models/user.dart';

class ProfileBundle {
  const ProfileBundle({
    required this.user,
    required this.profile,
    this.providerDetails,
  });

  final User user;
  final UserProfile profile;
  final ProviderProfile? providerDetails;
}

class UserProfile {
  const UserProfile({
    this.bio,
    this.location,
    this.headline,
    this.experienceSummary,
    this.yearsExperience,
    this.minRate,
  });

  final String? bio;
  final String? location;
  final String? headline;
  final String? experienceSummary;
  final int? yearsExperience;
  final String? minRate;

  factory UserProfile.fromJson(Map<String, dynamic> json) {
    return UserProfile(
      bio: json['bio'] as String?,
      location: json['location'] as String?,
      headline: json['headline'] as String?,
      experienceSummary: json['experience_summary'] as String?,
      yearsExperience: json['years_experience'] as int?,
      minRate: json['min_rate'] as String?,
    );
  }
}

abstract class ProfileRepository {
  Future<ProfileBundle> getProfile();
  Future<ProfileBundle> updateProfile(Map<String, dynamic> payload);
  Future<ProviderProfile> getPublicProvider(int id);
  Future<ProviderProfile> updateProviderSkills(List<int> skillIds);
  Future<ProviderProfile> updateProviderCategories(List<int> categoryIds);
  Future<ProviderProfile> updateProviderAvailability(List<AvailabilitySlot> slots);
}

class NetworkProfileRepository implements ProfileRepository {
  NetworkProfileRepository(this._client);

  final ApiClient _client;

  @override
  Future<ProfileBundle> getProfile() async {
    final data = await _client.get('profile') as Map<String, dynamic>;
    return _parseBundle(data);
  }

  @override
  Future<ProfileBundle> updateProfile(Map<String, dynamic> payload) async {
    final data = await _client.put('profile', body: payload) as Map<String, dynamic>;
    return _parseBundle(data);
  }

  @override
  Future<ProviderProfile> getPublicProvider(int id) async {
    final data = await _client.get('providers/$id') as Map<String, dynamic>;
    return ProviderProfile.fromPublicJson(data);
  }

  @override
  Future<ProviderProfile> updateProviderSkills(List<int> skillIds) async {
    final data = await _client.put(
      'provider/skills',
      body: {'skill_ids': skillIds},
    ) as Map<String, dynamic>;
    return ProviderProfile.fromProviderDetailsJson(data);
  }

  @override
  Future<ProviderProfile> updateProviderCategories(List<int> categoryIds) async {
    final data = await _client.put(
      'provider/categories',
      body: {'category_ids': categoryIds},
    ) as Map<String, dynamic>;
    return ProviderProfile.fromProviderDetailsJson(data);
  }

  @override
  Future<ProviderProfile> updateProviderAvailability(List<AvailabilitySlot> slots) async {
    final data = await _client.put(
      'provider/availability',
      body: {'slots': slots.map((slot) => slot.toJson()).toList()},
    ) as Map<String, dynamic>;
    return ProviderProfile.fromProviderDetailsJson(data);
  }

  ProfileBundle _parseBundle(Map<String, dynamic> data) {
    return ProfileBundle(
      user: User.fromJson(data['user'] as Map<String, dynamic>),
      profile: UserProfile.fromJson(data['profile'] as Map<String, dynamic>),
      providerDetails: data['provider'] is Map<String, dynamic>
          ? ProviderProfile.fromProviderDetailsJson(
              data['provider'] as Map<String, dynamic>,
            )
          : null,
    );
  }
}
