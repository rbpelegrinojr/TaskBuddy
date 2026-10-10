import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/utils/formatters.dart';
import '../../providers/invitations_provider.dart';
import '../../widgets/async_state_view.dart';

class InvitationsScreen extends StatefulWidget {
  const InvitationsScreen({super.key});

  @override
  State<InvitationsScreen> createState() => _InvitationsScreenState();
}

class _InvitationsScreenState extends State<InvitationsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<InvitationsProvider>().load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<InvitationsProvider>();
    return Scaffold(
      appBar: AppBar(title: const Text('Invitations')),
      body: AsyncStateView(
        status: provider.state.status,
        errorMessage: provider.state.errorMessage,
        emptyMessage: 'No invitations found.',
        onRetry: () => provider.load(),
        child: ListView.builder(
          padding: const EdgeInsets.all(16),
          itemCount: provider.state.data?.length ?? 0,
          itemBuilder: (context, index) {
            final invitation = provider.state.data![index];
            final pending = invitation.status == 'PENDING';
            return Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(invitation.request.title, style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: 8),
                    Text('Client: ${invitation.client.fullName}'),
                    Text('Category: ${invitation.request.category}'),
                    Text('Deadline: ${AppFormatters.formatUtcToLocalDateTime(invitation.request.deadline.toUtc().toIso8601String())}'),
                    Text('Compensation: ${AppFormatters.formatMoney(invitation.request.compensation)}'),
                    Text('Status: ${invitation.status}'),
                    if ((invitation.message ?? '').isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Text(invitation.message!),
                    ],
                    const SizedBox(height: 12),
                    Wrap(
                      spacing: 8,
                      children: [
                        FilledButton(
                          onPressed: pending && !provider.updating ? () => provider.accept(invitation.id) : null,
                          child: const Text('Accept'),
                        ),
                        OutlinedButton(
                          onPressed: pending && !provider.updating ? () => provider.decline(invitation.id) : null,
                          child: const Text('Decline'),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}
