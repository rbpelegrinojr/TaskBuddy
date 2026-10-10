class Message {
  const Message({
    required this.id,
    required this.senderId,
    required this.body,
    required this.isRead,
    this.readAt,
    required this.createdAt,
  });

  final int id;
  final int senderId;
  final String body;
  final bool isRead;
  final DateTime? readAt;
  final DateTime createdAt;

  factory Message.fromJson(Map<String, dynamic> json) {
    return Message(
      id: json['id'] as int,
      senderId: json['sender_id'] as int,
      body: json['body'] as String,
      isRead: json['is_read'] as bool? ?? false,
      readAt: json['read_at'] == null
          ? null
          : DateTime.parse(json['read_at'] as String),
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }
}
