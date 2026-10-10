import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/config/api_config.dart';
import '../../core/utils/formatters.dart';
import '../../providers/auth_provider.dart';
import '../../providers/requests_provider.dart';
import '../../widgets/async_state_view.dart';
import '../../widgets/download_link_tile.dart';
import 'request_matches_screen.dart';
import '../shared/task_detail_screen.dart';

class RequestDetailScreen extends StatefulWidget {
  const RequestDetailScreen({super.key, required this.requestId});

  final int requestId;

  @override
  State<RequestDetailScreen> createState() => _RequestDetailScreenState();
}

class _RequestDetailScreenState extends State<RequestDetailScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<RequestsProvider>().loadDetail(widget.requestId);
    });
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<RequestsProvider>();
    final isClient = context.watch<AuthProvider>().isClient;
    final config = context.read<ApiConfig>();
    final request = provider.detailState.data;
    return Scaffold(
      appBar: AppBar(title: const Text('Request details')),
      body: AsyncStateView(
        status: provider.detailState.status,
        errorMessage: provider.detailState.errorMessage,
        onRetry: () => provider.loadDetail(widget.requestId),
        child: request == null
            ? const SizedBox.shrink()
            : ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Text(request.title, style: Theme.of(context).textTheme.headlineSmall),
                  const SizedBox(height: 8),
                  Text(request.description),
                  const SizedBox(height: 12),
                  Text('Category: ${request.category.name}'),
                  Text('Deadline: ${AppFormatters.formatUtcToLocalDateTime(request.deadline.toUtc().toIso8601String())}'),
                  Text('Compensation: ${AppFormatters.formatMoney(request.compensation)}'),
                  if (request.estimatedHours != null) Text('Estimated hours: ${request.estimatedHours}'),
                  Text('Exclusive: ${request.requiresExclusive ? 'Yes' : 'No'}'),
                  Text('Status: ${request.status}'),
                  if ((request.requirements ?? '').isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Text('Requirements', style: Theme.of(context).textTheme.titleMedium),
                    Text(request.requirements!),
                  ],
                  const SizedBox(height: 12),
                  Text('Skills', style: Theme.of(context).textTheme.titleMedium),
                  Wrap(
                    spacing: 8,
                    children: request.skills
                        .map((skill) => Chip(label: Text(skill.isMandatory ? '${skill.name} *' : skill.name)))
                        .toList(),
                  ),
                  const SizedBox(height: 12),
                  Text('Attachments', style: Theme.of(context).textTheme.titleMedium),
                  if (request.attachments.isEmpty)
                    const Text('No attachments uploaded.')
                  else
                    ...request.attachments.map(
                      (file) => DownloadLinkTile(
                        title: file.originalName,
                        subtitle: config.resolveDownloadUrl(file.downloadUrl),
                      ),
                    ),
                  if (request.pendingInvitation != null) ...[
                    const SizedBox(height: 12),
                    Card(
                      child: ListTile(
                        title: const Text('Pending invitation'),
                        subtitle: Text(
                          'Provider #${request.pendingInvitation!.providerId} until ${AppFormatters.formatUtcToLocalDateTime(request.pendingInvitation!.expiresAt.toUtc().toIso8601String())}',
                        ),
                      ),
                    ),
                  ],
                  if (request.assignment != null) ...[
                    const SizedBox(height: 12),
                    Card(
                      child: ListTile(
                        title: const Text('Assigned provider'),
                        subtitle: Text(request.assignment!.providerName),
                      ),
                    ),
                    if (isClient)
                      Align(
                        alignment: Alignment.centerLeft,
                        child: OutlinedButton(
                          onPressed: () => Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => TaskDetailScreen(taskId: request.id),
                            ),
                          ),
                          child: const Text('Open task details'),
                        ),
                      ),
                  ],
                  if (!isClient && request.myMatch != null) ...[
                    const SizedBox(height: 12),
                    Chip(label: Text(AppFormatters.budgetStatusLabel(request.myMatch!.budgetStatus))),
                    Text('Your match score: ${request.myMatch!.finalScore.toStringAsFixed(2)}'),
                    if ((request.myMatch!.summary ?? '').isNotEmpty) Text(request.myMatch!.summary!),
                  ],
                  if (isClient) ...[
                    const SizedBox(height: 20),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        FilledButton(
                          onPressed: () => Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => RequestMatchesScreen(requestId: request.id),
                            ),
                          ),
                          child: const Text('View recommendations'),
                        ),
                        OutlinedButton(
                          onPressed: provider.saving
                              ? null
                              : () => context.read<RequestsProvider>().rematch(request.id),
                          child: const Text('Retry matching'),
                        ),
                        OutlinedButton(
                          onPressed: () => _attachFile(),
                          child: const Text('Add attachment'),
                        ),
                        OutlinedButton(
                          onPressed: request.status == 'OPEN' || request.status == 'PENDING_ACCEPTANCE'
                              ? () => context.read<RequestsProvider>().cancelRequest(request.id)
                              : null,
                          child: const Text('Cancel request'),
                        ),
                      ],
                    ),
                  ],
                ],
              ),
      ),
    );
  }

  Future<void> _attachFile() async {
    final result = await FilePicker.platform.pickFiles(withData: false);
    if (result == null || result.files.isEmpty || !mounted) {
      return;
    }
    await context.read<RequestsProvider>().addAttachment(widget.requestId, result.files.single);
  }
}
