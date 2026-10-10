import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../providers/matches_provider.dart';
import '../../widgets/async_state_view.dart';
import '../../widgets/match_card.dart';
import 'provider_public_profile_screen.dart';

class RequestMatchesScreen extends StatefulWidget {
  const RequestMatchesScreen({super.key, required this.requestId});

  final int requestId;

  @override
  State<RequestMatchesScreen> createState() => _RequestMatchesScreenState();
}

class _RequestMatchesScreenState extends State<RequestMatchesScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<MatchesProvider>().loadMatches(widget.requestId);
    });
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<MatchesProvider>();
    return Scaffold(
      appBar: AppBar(title: const Text('Recommended providers')),
      body: AsyncStateView(
        status: provider.state.status,
        errorMessage: provider.state.errorMessage,
        emptyMessage: provider.emptyMessage ?? 'No providers currently qualify for this request.',
        onRetry: () => provider.loadMatches(widget.requestId),
        child: ListView.builder(
          padding: const EdgeInsets.all(16),
          itemCount: provider.state.data?.length ?? 0,
          itemBuilder: (context, index) {
            final match = provider.state.data![index];
            return MatchCard(
              match: match,
              onViewProfile: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => ProviderPublicProfileScreen(providerId: match.provider.id),
                ),
              ),
              onInvite: () => _showInviteDialog(match.provider.id),
            );
          },
        ),
      ),
    );
  }

  Future<void> _showInviteDialog(int providerId) async {
    final messageController = TextEditingController();
    final hoursController = TextEditingController(text: '72');
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Invite provider'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: messageController,
              decoration: const InputDecoration(labelText: 'Message (optional)'),
              minLines: 2,
              maxLines: 4,
            ),
            const SizedBox(height: 12),
            TextField(
              controller: hoursController,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Expires in hours'),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Send invite')),
        ],
      ),
    );
    if (ok != true || !mounted) {
      return;
    }
    final sent = await context.read<MatchesProvider>().inviteProvider(
          requestId: widget.requestId,
          providerId: providerId,
          message: messageController.text,
          expiresInHours: int.tryParse(hoursController.text),
        );
    if (sent && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Invitation sent.')),
      );
    }
  }
}
