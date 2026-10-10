import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../providers/tasks_provider.dart';
import '../../widgets/async_state_view.dart';
import '../../widgets/task_card.dart';
import 'task_detail_screen.dart';

class TasksScreen extends StatefulWidget {
  const TasksScreen({super.key});

  @override
  State<TasksScreen> createState() => _TasksScreenState();
}

class _TasksScreenState extends State<TasksScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<TasksProvider>().load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<TasksProvider>();
    return Scaffold(
      appBar: AppBar(title: const Text('Tasks')),
      body: AsyncStateView(
        status: provider.state.status,
        errorMessage: provider.state.errorMessage,
        emptyMessage: 'No tasks yet.',
        onRetry: () => provider.load(),
        child: RefreshIndicator(
          onRefresh: () => provider.load(),
          child: ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: provider.state.data?.length ?? 0,
            itemBuilder: (context, index) {
              final task = provider.state.data![index];
              return TaskCard(
                task: task,
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => TaskDetailScreen(taskId: task.id),
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}
