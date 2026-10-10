import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/config/api_config.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/validators.dart';
import '../../providers/auth_provider.dart';
import '../../providers/tasks_provider.dart';
import '../../widgets/async_state_view.dart';
import '../../widgets/download_link_tile.dart';
import '../../widgets/payment_label_banner.dart';
import 'chat_screen.dart';
import 'submit_work_screen.dart';

class TaskDetailScreen extends StatefulWidget {
  const TaskDetailScreen({super.key, required this.taskId});

  final int taskId;

  @override
  State<TaskDetailScreen> createState() => _TaskDetailScreenState();
}

class _TaskDetailScreenState extends State<TaskDetailScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<TasksProvider>().loadDetail(widget.taskId);
    });
  }

  @override
  Widget build(BuildContext context) {
    final tasks = context.watch<TasksProvider>();
    final task = tasks.detailState.data;
    final isClient = context.watch<AuthProvider>().isClient;
    final config = context.read<ApiConfig>();
    return Scaffold(
      appBar: AppBar(title: const Text('Task details')),
      body: AsyncStateView(
        status: tasks.detailState.status,
        errorMessage: tasks.detailState.errorMessage,
        onRetry: () => tasks.loadDetail(widget.taskId),
        child: task == null
            ? const SizedBox.shrink()
            : ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Text(task.title, style: Theme.of(context).textTheme.headlineSmall),
                  const SizedBox(height: 8),
                  Text('Status: ${task.status}'),
                  Text('Deadline: ${AppFormatters.formatUtcToLocalDateTime(task.deadline.toUtc().toIso8601String())}'),
                  Text('Compensation: ${AppFormatters.formatMoney(task.compensation)}'),
                  const SizedBox(height: 12),
                  if ((task.description ?? '').isNotEmpty) Text(task.description!),
                  if ((task.requirements ?? '').isNotEmpty) ...[
                    const SizedBox(height: 12),
                    Text('Requirements', style: Theme.of(context).textTheme.titleMedium),
                    Text(task.requirements!),
                  ],
                  const SizedBox(height: 12),
                  Text('Request attachments', style: Theme.of(context).textTheme.titleMedium),
                  if (task.attachments.isEmpty)
                    const Text('No request attachments.')
                  else
                    ...task.attachments.map(
                      (attachment) => DownloadLinkTile(
                        title: attachment.originalName,
                        subtitle: config.resolveDownloadUrl(attachment.downloadUrl),
                      ),
                    ),
                  const SizedBox(height: 12),
                  Text('Submissions', style: Theme.of(context).textTheme.titleMedium),
                  if (task.submissions.isEmpty)
                    const Text('No submissions yet.')
                  else
                    ...task.submissions.map(
                      (submission) => Card(
                        child: Padding(
                          padding: const EdgeInsets.all(12),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Submitted ${AppFormatters.formatUtcToLocalDateTime(submission.createdAt.toUtc().toIso8601String())}',
                              ),
                              if ((submission.note ?? '').isNotEmpty) Text(submission.note!),
                              const SizedBox(height: 8),
                              ...submission.files.map(
                                (file) => DownloadLinkTile(
                                  title: file.originalName,
                                  subtitle: config.resolveDownloadUrl(file.downloadUrl),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  const SizedBox(height: 12),
                  Text('Revision requests', style: Theme.of(context).textTheme.titleMedium),
                  if (task.revisions.isEmpty)
                    const Text('No revision requests.')
                  else
                    ...task.revisions.map(
                      (revision) => ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(revision.reason),
                        subtitle: Text(
                          AppFormatters.formatUtcToLocalDateTime(revision.createdAt.toUtc().toIso8601String()),
                        ),
                      ),
                    ),
                  if (task.dispute != null) ...[
                    const SizedBox(height: 12),
                    Text('Dispute', style: Theme.of(context).textTheme.titleMedium),
                    Card(
                      child: ListTile(
                        title: Text(task.dispute!.status),
                        subtitle: Text(task.dispute!.reason),
                      ),
                    ),
                  ],
                  if (task.payment != null) ...[
                    const SizedBox(height: 12),
                    const PaymentLabelBanner(),
                    const SizedBox(height: 8),
                    Text('Payment state: ${task.payment!.state}'),
                    Text(task.payment!.currencyNote),
                  ],
                  const SizedBox(height: 16),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      if (task.conversationId != null)
                        OutlinedButton(
                          onPressed: () => Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => ChatScreen(
                                conversationId: task.conversationId!,
                                title: task.title,
                              ),
                            ),
                          ),
                          child: const Text('Open messages'),
                        ),
                      if (!isClient && task.allowedActions.contains('submit'))
                        FilledButton(
                          onPressed: () => Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => SubmitWorkScreen(taskId: task.id, title: task.title),
                            ),
                          ),
                          child: const Text('Submit work'),
                        ),
                      if (isClient && task.allowedActions.contains('start_review'))
                        OutlinedButton(
                          onPressed: () => context.read<TasksProvider>().startReview(task.id),
                          child: const Text('Start review'),
                        ),
                      if (isClient && task.allowedActions.contains('approve'))
                        FilledButton(
                          onPressed: () => context.read<TasksProvider>().approve(task.id),
                          child: const Text('Approve work'),
                        ),
                      if (isClient && task.allowedActions.contains('revision'))
                        OutlinedButton(
                          onPressed: () => _requestRevision(),
                          child: const Text('Request revision'),
                        ),
                      if (task.allowedActions.contains('dispute'))
                        OutlinedButton(
                          onPressed: () => _openDispute(),
                          child: const Text('Open dispute'),
                        ),
                    ],
                  ),
                ],
              ),
      ),
    );
  }

  Future<void> _requestRevision() async {
    final controller = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Request revision'),
        content: TextField(
          controller: controller,
          minLines: 3,
          maxLines: 5,
          decoration: const InputDecoration(
            labelText: 'Revision reason',
            border: OutlineInputBorder(),
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Submit')),
        ],
      ),
    );
    if (ok == true && mounted) {
      if (controller.text.trim().length < 3) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Revision reason must be at least 3 characters.')),
        );
        return;
      }
      await context.read<TasksProvider>().requestRevision(widget.taskId, controller.text);
    }
  }

  Future<void> _openDispute() async {
    final controller = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Open dispute'),
        content: TextField(
          controller: controller,
          minLines: 3,
          maxLines: 5,
          decoration: const InputDecoration(
            labelText: 'Dispute reason (min 10 characters)',
            border: OutlineInputBorder(),
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Open dispute')),
        ],
      ),
    );
    if (ok == true && mounted) {
      final validation = AppValidators.disputeReason(controller.text);
      if (validation != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(validation)),
        );
        return;
      }
      await context.read<TasksProvider>().dispute(widget.taskId, controller.text);
    }
  }
}
