import 'package:flutter/material.dart';

import '../core/utils/formatters.dart';
import '../models/task.dart';

class TaskCard extends StatelessWidget {
  const TaskCard({super.key, required this.task, required this.onTap});

  final TaskItem task;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        onTap: onTap,
        title: Text(task.title),
        subtitle: Text(
          '${task.status} • ${task.category.name}
${AppFormatters.formatUtcToLocalDateTime(task.deadline.toUtc().toIso8601String())}',
        ),
        isThreeLine: true,
        trailing: Text(AppFormatters.formatMoney(task.compensation)),
      ),
    );
  }
}
