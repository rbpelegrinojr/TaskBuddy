import 'package:flutter/foundation.dart';

import '../core/api/api_exception.dart';
import '../models/service_request.dart';
import '../repositories/requests_repository.dart';
import 'async_state.dart';

class OpportunitiesProvider extends ChangeNotifier {
  OpportunitiesProvider({required RequestsRepository repository}) : _repository = repository;

  final RequestsRepository _repository;

  AsyncState<List<ServiceRequest>> state = const AsyncState();

  Future<void> load() async {
    state = AsyncState.loading(state.data);
    notifyListeners();
    try {
      final result = await _repository.getProviderOpportunities();
      state = result.items.isEmpty
          ? AsyncState.empty(result.items)
          : AsyncState.success(result.items);
    } on ApiException catch (error) {
      state = AsyncState.error(error.message, state.data);
    }
    notifyListeners();
  }
}
