import 'package:flutter/foundation.dart';

import '../core/api/api_exception.dart';
import '../models/invitation.dart';
import '../repositories/invitations_repository.dart';
import 'async_state.dart';

class InvitationsProvider extends ChangeNotifier {
  InvitationsProvider({required InvitationsRepository repository}) : _repository = repository;

  final InvitationsRepository _repository;

  AsyncState<List<Invitation>> state = const AsyncState();
  bool updating = false;

  Future<void> load({String? status}) async {
    state = AsyncState.loading(state.data);
    notifyListeners();
    try {
      final result = await _repository.list(status: status);
      state = result.items.isEmpty
          ? AsyncState.empty(result.items)
          : AsyncState.success(result.items);
    } on ApiException catch (error) {
      state = AsyncState.error(error.message, state.data);
    }
    notifyListeners();
  }

  Future<bool> accept(int id) async {
    return _mutate(() => _repository.accept(id));
  }

  Future<bool> decline(int id) async {
    return _mutate(() => _repository.decline(id));
  }

  Future<bool> _mutate(Future<Invitation> Function() action) async {
    updating = true;
    notifyListeners();
    try {
      await action();
      await load();
      return true;
    } on ApiException catch (error) {
      state = AsyncState.error(error.message, state.data);
      return false;
    } finally {
      updating = false;
      notifyListeners();
    }
  }
}
