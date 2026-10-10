import 'package:flutter/material.dart';

import '../core/utils/formatters.dart';
import '../models/conversation.dart';

class ConversationTile extends StatelessWidget {
  const ConversationTile({
    super.key,
    required this.conversation,
    required this.onTap,
  });

  final Conversation conversation;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        onTap: onTap,
        title: Text(conversation.otherParty.fullName),
        subtitle: Text(
          conversation.lastMessage == null
              ? conversation.taskTitle
              : '${conversation.taskTitle}
${conversation.lastMessage!.body}',
        ),
        isThreeLine: conversation.lastMessage != null,
        trailing: conversation.unreadCount > 0
            ? CircleAvatar(
                radius: 12,
                child: Text(
                  conversation.unreadCount.toString(),
                  style: Theme.of(context).textTheme.labelSmall,
                ),
              )
            : Text(conversation.taskStatus),
      ),
    );
  }
}
