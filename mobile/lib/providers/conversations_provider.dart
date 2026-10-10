import 'package:flutter/foundation.dart';

import '../core/api/api_exception.dart';
import '../models/conversation.dart';
import '../repositories/conversation_repository.dart';
import 'async_state.dart';

class ConversationsProvider extends ChangeNotifier {
  ConversationsProvider({required ConversationRepository repository}) : _repository = repository;

  final ConversationRepository _repository;

  AsyncState<List<Conversation>> state = const AsyncState();

  Future<void> load() async {
    state = AsyncState.loading(state.data);
    notifyListeners();
    try {
      final items = await _repository.listConversations();
      state = items.isEmpty ? AsyncState.empty(items) : AsyncState.success(items);
    } on ApiException catch (error) {
      state = AsyncState.error(error.message, state.data);
    }
    notifyListeners();
  }
}
