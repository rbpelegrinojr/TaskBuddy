import 'package:flutter/foundation.dart';

import '../core/api/api_exception.dart';
import '../models/message.dart';
import '../repositories/conversation_repository.dart';
import 'async_state.dart';

class ChatProvider extends ChangeNotifier {
  ChatProvider({required ConversationRepository repository}) : _repository = repository;

  final ConversationRepository _repository;

  AsyncState<List<Message>> state = const AsyncState();
  int? _conversationId;
  int? _latestId;
  bool sending = false;

  int? get conversationId => _conversationId;

  Future<void> openConversation(int conversationId) async {
    _conversationId = conversationId;
    state = AsyncState.loading(state.data);
    notifyListeners();
    try {
      final page = await _repository.listMessages(conversationId);
      final items = page.items;
      _latestId = items.isEmpty ? null : items.last.id;
      state = items.isEmpty ? AsyncState.empty(items) : AsyncState.success(items);
      await _repository.markRead(conversationId);
    } on ApiException catch (error) {
      state = AsyncState.error(error.message, state.data);
    }
    notifyListeners();
  }

  Future<void> poll() async {
    final conversationId = _conversationId;
    if (conversationId == null) {
      return;
    }
    try {
      final page = await _repository.listMessages(
        conversationId,
        afterId: _latestId,
        limit: 30,
      );
      if (page.items.isNotEmpty) {
        final merged = [...?state.data, ...page.items];
        _latestId = merged.last.id;
        state = AsyncState.success(merged);
        notifyListeners();
      }
      await _repository.markRead(conversationId);
    } on ApiException catch (_) {
    }
  }

  Future<bool> send(String body) async {
    final conversationId = _conversationId;
    if (conversationId == null) {
      return false;
    }
    sending = true;
    notifyListeners();
    try {
      final message = await _repository.sendMessage(conversationId, body);
      final merged = [...?state.data, message];
      _latestId = message.id;
      state = AsyncState.success(merged);
      return true;
    } on ApiException catch (error) {
      state = AsyncState.error(error.message, state.data);
      return false;
    } finally {
      sending = false;
      notifyListeners();
    }
  }

  void clear() {
    _conversationId = null;
    _latestId = null;
    state = const AsyncState();
    notifyListeners();
  }
}
