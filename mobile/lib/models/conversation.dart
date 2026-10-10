class Conversation {
  const Conversation({
    required this.id,
    required this.taskId,
    required this.taskTitle,
    required this.taskStatus,
    required this.otherParty,
    this.lastMessage,
    required this.unreadCount,
  });

  final int id;
  final int taskId;
  final String taskTitle;
  final String taskStatus;
  final ConversationParty otherParty;
  final ConversationMessagePreview? lastMessage;
  final int unreadCount;

  factory Conversation.fromJson(Map<String, dynamic> json) {
    return Conversation(
      id: json['id'] as int,
      taskId: json['task_id'] as int,
      taskTitle: json['task_title'] as String,
      taskStatus: json['task_status'] as String,
      otherParty: ConversationParty.fromJson(
        json['other_party'] as Map<String, dynamic>,
      ),
      lastMessage: json['last_message'] is Map<String, dynamic>
          ? ConversationMessagePreview.fromJson(
              json['last_message'] as Map<String, dynamic>,
            )
          : null,
      unreadCount: json['unread_count'] as int? ?? 0,
    );
  }
}

class ConversationParty {
  const ConversationParty({required this.id, required this.fullName});

  final int id;
  final String fullName;

  factory ConversationParty.fromJson(Map<String, dynamic> json) {
    return ConversationParty(
      id: json['id'] as int,
      fullName: json['full_name'] as String,
    );
  }
}

class ConversationMessagePreview {
  const ConversationMessagePreview({
    required this.id,
    required this.senderId,
    required this.body,
    required this.createdAt,
  });

  final int id;
  final int senderId;
  final String body;
  final DateTime createdAt;

  factory ConversationMessagePreview.fromJson(Map<String, dynamic> json) {
    return ConversationMessagePreview(
      id: json['id'] as int,
      senderId: json['sender_id'] as int,
      body: json['body'] as String,
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }
}
