import 'package:flutter/foundation.dart';

import '../core/api/api_exception.dart';
import '../models/availability_slot.dart';
import '../models/provider_profile.dart';
import '../repositories/profile_repository.dart';
import 'async_state.dart';

class ProfileProvider extends ChangeNotifier {
  ProfileProvider({required ProfileRepository repository}) : _repository = repository;

  final ProfileRepository _repository;

  AsyncState<ProfileBundle> state = const AsyncState();
  AsyncState<ProviderProfile> publicProviderState = const AsyncState();
  bool saving = false;

  Future<void> loadProfile() async {
    state = AsyncState.loading(state.data);
    notifyListeners();
    try {
      final bundle = await _repository.getProfile();
      state = AsyncState.success(bundle);
    } on ApiException catch (error) {
      state = AsyncState.error(error.message, state.data);
    }
    notifyListeners();
  }

  Future<bool> updateProfile(Map<String, dynamic> payload) async {
    saving = true;
    notifyListeners();
    try {
      state = AsyncState.success(await _repository.updateProfile(payload));
      return true;
    } on ApiException catch (error) {
      state = AsyncState.error(error.message, state.data);
      return false;
    } finally {
      saving = false;
      notifyListeners();
    }
  }

  Future<bool> updateProviderExtras({
    required List<int> categoryIds,
    required List<int> skillIds,
    required List<AvailabilitySlot> slots,
  }) async {
    saving = true;
    notifyListeners();
    try {
      final categories = await _repository.updateProviderCategories(categoryIds);
      final skills = await _repository.updateProviderSkills(skillIds);
      final availability = await _repository.updateProviderAvailability(slots);
      final current = state.data;
      if (current != null) {
        state = AsyncState.success(ProfileBundle(
          user: current.user,
          profile: current.profile,
          providerDetails: ProviderProfile(
            id: current.user.id,
            fullName: current.user.fullName,
            headline: current.profile.headline,
            bio: current.profile.bio,
            location: current.profile.location,
            experienceSummary: current.profile.experienceSummary,
            yearsExperience: current.profile.yearsExperience,
            minRate: current.profile.minRate,
            categories: categories.categories,
            skills: skills.skills,
            availability: availability.availability,
            profileComplete: availability.profileComplete,
            missingFields: availability.missingFields,
            hasAvailability: availability.hasAvailability,
          ),
        ));
      }
      return true;
    } on ApiException catch (error) {
      state = AsyncState.error(error.message, state.data);
      return false;
    } finally {
      saving = false;
      notifyListeners();
    }
  }

  Future<void> loadPublicProvider(int providerId) async {
    publicProviderState = AsyncState.loading(publicProviderState.data);
    notifyListeners();
    try {
      publicProviderState = AsyncState.success(
        await _repository.getPublicProvider(providerId),
      );
    } on ApiException catch (error) {
      publicProviderState = AsyncState.error(error.message, publicProviderState.data);
    }
    notifyListeners();
  }
}
