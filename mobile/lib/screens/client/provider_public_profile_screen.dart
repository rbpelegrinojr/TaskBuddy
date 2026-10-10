import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../providers/profile_provider.dart';
import '../../widgets/async_state_view.dart';

class ProviderPublicProfileScreen extends StatefulWidget {
  const ProviderPublicProfileScreen({super.key, required this.providerId});

  final int providerId;

  @override
  State<ProviderPublicProfileScreen> createState() => _ProviderPublicProfileScreenState();
}

class _ProviderPublicProfileScreenState extends State<ProviderPublicProfileScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ProfileProvider>().loadPublicProvider(widget.providerId);
    });
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<ProfileProvider>();
    final profile = provider.publicProviderState.data;
    return Scaffold(
      appBar: AppBar(title: const Text('Provider profile')),
      body: AsyncStateView(
        status: provider.publicProviderState.status,
        errorMessage: provider.publicProviderState.errorMessage,
        onRetry: () => provider.loadPublicProvider(widget.providerId),
        child: profile == null
            ? const SizedBox.shrink()
            : ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Text(profile.fullName, style: Theme.of(context).textTheme.headlineSmall),
                  if ((profile.headline ?? '').isNotEmpty) Text(profile.headline!),
                  const SizedBox(height: 8),
                  if ((profile.bio ?? '').isNotEmpty) Text(profile.bio!),
                  if ((profile.location ?? '').isNotEmpty) Text('Location: ${profile.location}'),
                  if ((profile.experienceSummary ?? '').isNotEmpty) ...[
                    const SizedBox(height: 12),
                    Text('Experience', style: Theme.of(context).textTheme.titleMedium),
                    Text(profile.experienceSummary!),
                  ],
                  if (profile.yearsExperience != null) Text('Years of experience: ${profile.yearsExperience}'),
                  if ((profile.minRate ?? '').isNotEmpty) Text('Minimum rate: ${profile.minRate}'),
                  const SizedBox(height: 12),
                  Text('Categories', style: Theme.of(context).textTheme.titleMedium),
                  Wrap(
                    spacing: 8,
                    children: profile.categories.map((item) => Chip(label: Text(item.name))).toList(),
                  ),
                  const SizedBox(height: 12),
                  Text('Skills', style: Theme.of(context).textTheme.titleMedium),
                  Wrap(
                    spacing: 8,
                    children: profile.skills.map((item) => Chip(label: Text(item.name))).toList(),
                  ),
                  const SizedBox(height: 12),
                  Text('Availability', style: Theme.of(context).textTheme.titleMedium),
                  ...profile.availability.map(
                    (slot) => ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text('Day ${slot.dayOfWeek}'),
                      subtitle: Text('${slot.startTime} - ${slot.endTime}'),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text('Completed tasks: ${profile.completedTasks ?? 0}'),
                ],
              ),
      ),
    );
  }
}
