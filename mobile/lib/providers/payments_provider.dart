import 'package:flutter/foundation.dart';

import '../core/api/api_exception.dart';
import '../models/payment.dart';
import '../repositories/payments_repository.dart';
import 'async_state.dart';

class PaymentsProvider extends ChangeNotifier {
  PaymentsProvider({required PaymentsRepository repository}) : _repository = repository;

  final PaymentsRepository _repository;

  AsyncState<List<Payment>> state = const AsyncState();
  AsyncState<Payment> detailState = const AsyncState();
  Map<String, dynamic> summary = const {};

  Future<void> load({String? stateFilter}) async {
    state = AsyncState.loading(state.data);
    notifyListeners();
    try {
      final result = await _repository.list(state: stateFilter);
      summary = result.summary;
      state = result.items.isEmpty
          ? AsyncState.empty(result.items)
          : AsyncState.success(result.items);
    } on ApiException catch (error) {
      state = AsyncState.error(error.message, state.data);
    }
    notifyListeners();
  }

  Future<void> loadDetail(int id) async {
    detailState = AsyncState.loading(detailState.data);
    notifyListeners();
    try {
      detailState = AsyncState.success(await _repository.get(id));
    } on ApiException catch (error) {
      detailState = AsyncState.error(error.message, detailState.data);
    }
    notifyListeners();
  }
}
