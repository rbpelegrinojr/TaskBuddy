import 'package:flutter/foundation.dart';

import '../core/api/api_exception.dart';
import '../models/notification_item.dart';
import '../repositories/notifications_repository.dart';
import 'async_state.dart';

class NotificationsProvider extends ChangeNotifier {
  NotificationsProvider({required NotificationsRepository repository})
      : _repository = repository;

  final NotificationsRepository _repository;

  AsyncState<List<NotificationItem>> state = const AsyncState();
  bool updating = false;

  Future<void> load({bool unreadOnly = false}) async {
    state = AsyncState.loading(state.data);
    notifyListeners();
    try {
      final result = await _repository.list(unreadOnly: unreadOnly);
      state = result.items.isEmpty
          ? AsyncState.empty(result.items)
          : AsyncState.success(result.items);
    } on ApiException catch (error) {
      state = AsyncState.error(error.message, state.data);
    }
    notifyListeners();
  }

  Future<void> markRead(int id) async {
    updating = true;
    notifyListeners();
    try {
      await _repository.markRead(id);
      final items = state.data
              ?.map((item) => item.id == id
                  ? NotificationItem(
                      id: item.id,
                      type: item.type,
                      title: item.title,
                      body: item.body,
                      requestId: item.requestId,
                      isRead: true,
                      createdAt: item.createdAt,
                    )
                  : item)
              .toList() ??
          const <NotificationItem>[];
      state = items.isEmpty ? AsyncState.empty(items) : AsyncState.success(items);
    } on ApiException catch (error) {
      state = AsyncState.error(error.message, state.data);
    } finally {
      updating = false;
      notifyListeners();
    }
  }

  Future<void> markAllRead() async {
    updating = true;
    notifyListeners();
    try {
      await _repository.markAllRead();
      final items = state.data
              ?.map((item) => NotificationItem(
                    id: item.id,
                    type: item.type,
                    title: item.title,
                    body: item.body,
                    requestId: item.requestId,
                    isRead: true,
                    createdAt: item.createdAt,
                  ))
              .toList() ??
          const <NotificationItem>[];
      state = items.isEmpty ? AsyncState.empty(items) : AsyncState.success(items);
    } on ApiException catch (error) {
      state = AsyncState.error(error.message, state.data);
    } finally {
      updating = false;
      notifyListeners();
    }
  }
}
