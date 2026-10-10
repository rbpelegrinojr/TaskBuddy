import '../core/api/api_client.dart';
import '../models/conversation.dart';
import '../models/message.dart';

class MessagesPage {
  const MessagesPage({required this.items, required this.meta});

  final List<Message> items;
  final Map<String, dynamic> meta;
}

abstract class ConversationRepository {
  Future<List<Conversation>> listConversations();
  Future<MessagesPage> listMessages(
    int conversationId, {
    int? afterId,
    int? beforeId,
    int limit = 30,
  });
  Future<Message> sendMessage(int conversationId, String body);
  Future<void> markRead(int conversationId);
}

class NetworkConversationRepository implements ConversationRepository {
  NetworkConversationRepository(this._client);

  final ApiClient _client;

  @override
  Future<List<Conversation>> listConversations() async {
    final data = await _client.get('conversations') as List<dynamic>;
    return data
        .map((item) => Conversation.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  @override
  Future<MessagesPage> listMessages(
    int conversationId, {
    int? afterId,
    int? beforeId,
    int limit = 30,
  }) async {
    final envelope = await _client.getEnvelope(
      'conversations/$conversationId/messages',
      queryParameters: {
        'limit': limit,
        if (afterId != null) 'after_id': afterId,
        if (beforeId != null) 'before_id': beforeId,
      },
    );
    final data = envelope.data as List<dynamic>;
    return MessagesPage(
      items: data
          .map((item) => Message.fromJson(item as Map<String, dynamic>))
          .toList(),
      meta: envelope.meta,
    );
  }

  @override
  Future<Message> sendMessage(int conversationId, String body) async {
    final data = await _client.post(
      'conversations/$conversationId/messages',
      body: {'body': body.trim()},
    ) as Map<String, dynamic>;
    return Message.fromJson(data);
  }

  @override
  Future<void> markRead(int conversationId) async {
    await _client.post('conversations/$conversationId/read');
  }
}
