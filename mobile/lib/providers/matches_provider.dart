import 'package:flutter/foundation.dart';

import '../core/api/api_exception.dart';
import '../models/match_item.dart';
import '../repositories/requests_repository.dart';
import 'async_state.dart';

class MatchesProvider extends ChangeNotifier {
  MatchesProvider({required RequestsRepository repository}) : _repository = repository;

  final RequestsRepository _repository;

  AsyncState<List<MatchItem>> state = const AsyncState();
  String? emptyMessage;
  bool inviting = false;

  Future<void> loadMatches(int requestId) async {
    state = AsyncState.loading(state.data);
    emptyMessage = null;
    notifyListeners();
    try {
      final result = await _repository.getMatches(requestId);
      if (result.items.isEmpty) {
        state = AsyncState.empty(result.items);
        emptyMessage = result.meta['message']?.toString() ??
            'No providers currently qualify for this request.';
      } else {
        state = AsyncState.success(result.items);
      }
    } on ApiException catch (error) {
      state = AsyncState.error(error.message, state.data);
    }
    notifyListeners();
  }

  Future<bool> inviteProvider({
    required int requestId,
    required int providerId,
    String? message,
    int? expiresInHours,
  }) async {
    inviting = true;
    notifyListeners();
    try {
      await _repository.inviteProvider(
        requestId: requestId,
        providerId: providerId,
        message: message,
        expiresInHours: expiresInHours,
      );
      return true;
    } on ApiException catch (error) {
      state = AsyncState.error(error.message, state.data);
      return false;
    } finally {
      inviting = false;
      notifyListeners();
    }
  }
}
