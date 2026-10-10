import '../core/api/api_client.dart';
import '../models/notification_item.dart';
import '../models/paginated_result.dart';

abstract class NotificationsRepository {
  Future<PaginatedResult<NotificationItem>> list({bool unreadOnly = false});
  Future<void> markRead(int id);
  Future<void> markAllRead();
}

class NetworkNotificationsRepository implements NotificationsRepository {
  NetworkNotificationsRepository(this._client);

  final ApiClient _client;

  @override
  Future<PaginatedResult<NotificationItem>> list({bool unreadOnly = false}) async {
    final envelope = await _client.getEnvelope('notifications', queryParameters: {
      if (unreadOnly) 'unread': 1,
    });
    final data = envelope.data as List<dynamic>;
    return PaginatedResult(
      items: data
          .map((item) => NotificationItem.fromJson(item as Map<String, dynamic>))
          .toList(),
      meta: envelope.meta,
    );
  }

  @override
  Future<void> markRead(int id) async {
    await _client.post('notifications/$id/read');
  }

  @override
  Future<void> markAllRead() async {
    await _client.post('notifications/read-all');
  }
}
