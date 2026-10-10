import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/utils/validators.dart';
import '../../models/availability_slot.dart';
import '../../models/category.dart';
import '../../models/skill.dart';
import '../../providers/auth_provider.dart';
import '../../providers/catalog_provider.dart';
import '../../providers/profile_provider.dart';
import '../../widgets/app_text_field.dart';
import '../../widgets/async_state_view.dart';
import '../../widgets/availability_editor.dart';
import '../provider/invitations_screen.dart';
import 'notifications_screen.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  final _fullNameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _bioController = TextEditingController();
  final _locationController = TextEditingController();
  final _headlineController = TextEditingController();
  final _experienceController = TextEditingController();
  final _yearsController = TextEditingController();
  final _minRateController = TextEditingController();
  bool _hydrated = false;
  final Set<int> _selectedCategoryIds = {};
  final Set<int> _selectedSkillIds = {};
  List<AvailabilitySlot> _availability = const [];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ProfileProvider>().loadProfile();
      context.read<CatalogProvider>().loadCategories();
      context.read<CatalogProvider>().loadSkills();
    });
  }

  @override
  void dispose() {
    _fullNameController.dispose();
    _phoneController.dispose();
    _bioController.dispose();
    _locationController.dispose();
    _headlineController.dispose();
    _experienceController.dispose();
    _yearsController.dispose();
    _minRateController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final profileProvider = context.watch<ProfileProvider>();
    final catalogProvider = context.watch<CatalogProvider>();
    final bundle = profileProvider.state.data;
    if (bundle != null && !_hydrated) {
      _hydrated = true;
      _fullNameController.text = bundle.user.fullName;
      _phoneController.text = bundle.user.phone ?? '';
      _bioController.text = bundle.profile.bio ?? '';
      _locationController.text = bundle.profile.location ?? '';
      _headlineController.text = bundle.profile.headline ?? '';
      _experienceController.text = bundle.profile.experienceSummary ?? '';
      _yearsController.text = bundle.profile.yearsExperience?.toString() ?? '';
      _minRateController.text = bundle.profile.minRate ?? '';
      _selectedCategoryIds
        ..clear()
        ..addAll(bundle.providerDetails?.categories.map((item) => item.id) ?? const []);
      _selectedSkillIds
        ..clear()
        ..addAll(bundle.providerDetails?.skills.map((item) => item.id) ?? const []);
      _availability = [...?bundle.providerDetails?.availability];
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Account'),
        actions: [
          IconButton(
            tooltip: 'Notifications',
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute<void>(builder: (_) => const NotificationsScreen()),
            ),
            icon: const Icon(Icons.notifications_outlined),
          ),
          if (auth.isProvider)
            IconButton(
              tooltip: 'Invitations',
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(builder: (_) => const InvitationsScreen()),
              ),
              icon: const Icon(Icons.mail_outline),
            ),
          IconButton(
            tooltip: 'Log out',
            onPressed: () => context.read<AuthProvider>().logout(),
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: AsyncStateView(
        status: profileProvider.state.status,
        errorMessage: profileProvider.state.errorMessage,
        onRetry: () => profileProvider.loadProfile(),
        child: bundle == null
            ? const SizedBox.shrink()
            : Form(
                key: _formKey,
                child: ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    AppTextField(
                      controller: _fullNameController,
                      label: 'Full name',
                      validator: (value) => AppValidators.requiredText(value, label: 'Full name'),
                    ),
                    const SizedBox(height: 12),
                    AppTextField(
                      controller: _phoneController,
                      label: 'Phone',
                      keyboardType: TextInputType.phone,
                    ),
                    const SizedBox(height: 12),
                    AppTextField(
                      controller: _bioController,
                      label: 'Bio',
                      minLines: 3,
                      maxLines: 5,
                    ),
                    const SizedBox(height: 12),
                    AppTextField(
                      controller: _locationController,
                      label: 'Location',
                    ),
                    if (auth.isProvider) ...[
                      const SizedBox(height: 12),
                      AppTextField(
                        controller: _headlineController,
                        label: 'Headline',
                      ),
                      const SizedBox(height: 12),
                      AppTextField(
                        controller: _experienceController,
                        label: 'Experience summary',
                        minLines: 4,
                        maxLines: 6,
                      ),
                      const SizedBox(height: 12),
                      AppTextField(
                        controller: _yearsController,
                        label: 'Years of experience',
                        keyboardType: TextInputType.number,
                      ),
                      const SizedBox(height: 12),
                      AppTextField(
                        controller: _minRateController,
                        label: 'Minimum rate',
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        validator: (value) => value == null || value.isEmpty
                            ? null
                            : AppValidators.positiveMoney(value, label: 'Minimum rate'),
                      ),
                      const SizedBox(height: 16),
                      Text('Categories', style: Theme.of(context).textTheme.titleMedium),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: (catalogProvider.categoriesState.data ?? const <Category>[]).map((category) {
                          return FilterChip(
                            selected: _selectedCategoryIds.contains(category.id),
                            label: Text(category.name),
                            onSelected: (value) {
                              setState(() {
                                if (value) {
                                  _selectedCategoryIds.add(category.id);
                                } else {
                                  _selectedCategoryIds.remove(category.id);
                                }
                              });
                            },
                          );
                        }).toList(),
                      ),
                      const SizedBox(height: 16),
                      Text('Skills', style: Theme.of(context).textTheme.titleMedium),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: (catalogProvider.skillsStateFor(null).data ?? const <Skill>[]).map((skill) {
                          return FilterChip(
                            selected: _selectedSkillIds.contains(skill.id),
                            label: Text(skill.name),
                            onSelected: (value) {
                              setState(() {
                                if (value) {
                                  _selectedSkillIds.add(skill.id);
                                } else {
                                  _selectedSkillIds.remove(skill.id);
                                }
                              });
                            },
                          );
                        }).toList(),
                      ),
                      const SizedBox(height: 16),
                      AvailabilityEditor(
                        slots: _availability,
                        onChanged: (slots) => setState(() => _availability = slots),
                      ),
                      if (bundle.providerDetails != null) ...[
                        const SizedBox(height: 12),
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(12),
                            child: Text(
                              bundle.providerDetails!.profileComplete == true
                                  ? 'Your provider profile is complete.'
                                  : 'Missing for matching eligibility: ${bundle.providerDetails!.missingFields.join(', ')}',
                            ),
                          ),
                        ),
                      ],
                    ],
                    const SizedBox(height: 16),
                    FilledButton(
                      onPressed: profileProvider.saving ? null : () => _save(auth.isProvider),
                      child: profileProvider.saving
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Text('Save profile'),
                    ),
                  ],
                ),
              ),
      ),
    );
  }

  Future<void> _save(bool isProvider) async {
    if (!_formKey.currentState!.validate()) {
      return;
    }
    final profileProvider = context.read<ProfileProvider>();
    final ok = await profileProvider.updateProfile({
      'full_name': _fullNameController.text,
      'phone': _phoneController.text,
      'bio': _bioController.text,
      'location': _locationController.text,
      if (isProvider) ...{
        'headline': _headlineController.text,
        'experience_summary': _experienceController.text,
        'years_experience': int.tryParse(_yearsController.text),
        'min_rate': _minRateController.text.isEmpty ? null : _minRateController.text,
      },
    });
    if (!ok || !mounted || !isProvider) {
      return;
    }
    await profileProvider.updateProviderExtras(
      categoryIds: _selectedCategoryIds.toList(),
      skillIds: _selectedSkillIds.toList(),
      slots: _availability,
    );
  }
}
