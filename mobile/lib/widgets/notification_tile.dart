import 'package:flutter/material.dart';

import '../core/utils/formatters.dart';
import '../models/notification_item.dart';

class NotificationTile extends StatelessWidget {
  const NotificationTile({
    super.key,
    required this.item,
    required this.onTap,
  });

  final NotificationItem item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      color: item.isRead ? null : Theme.of(context).colorScheme.secondaryContainer,
      child: ListTile(
        onTap: onTap,
        title: Text(item.title),
        subtitle: Text(
          '${item.body}
${AppFormatters.formatUtcToLocalDateTime(item.createdAt.toUtc().toIso8601String())}',
        ),
        isThreeLine: true,
      ),
    );
  }
}
