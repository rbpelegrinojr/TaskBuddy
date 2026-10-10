import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart';

import '../core/api/api_exception.dart';
import '../models/service_request.dart';
import '../repositories/requests_repository.dart';
import 'async_state.dart';

class RequestsProvider extends ChangeNotifier {
  RequestsProvider({required RequestsRepository repository}) : _repository = repository;

  final RequestsRepository _repository;

  AsyncState<List<ServiceRequest>> state = const AsyncState();
  AsyncState<ServiceRequest> detailState = const AsyncState();
  RequestMatchingStatus? lastMatchingStatus;
  bool saving = false;

  Future<void> loadRequests({String? status}) async {
    state = AsyncState.loading(state.data);
    notifyListeners();
    try {
      final result = await _repository.getClientRequests(status: status);
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
      detailState = AsyncState.success(await _repository.getRequest(id));
    } on ApiException catch (error) {
      detailState = AsyncState.error(error.message, detailState.data);
    }
    notifyListeners();
  }

  Future<bool> createRequest(
    RequestUpsertPayload payload, {
    PlatformFile? attachment,
  }) async {
    saving = true;
    notifyListeners();
    try {
      final result = await _repository.createRequest(payload, attachment: attachment);
      lastMatchingStatus = result.matching;
      detailState = AsyncState.success(result.request);
      await loadRequests();
      return true;
    } on ApiException catch (error) {
      detailState = AsyncState.error(error.message, detailState.data);
      return false;
    } finally {
      saving = false;
      notifyListeners();
    }
  }

  Future<bool> cancelRequest(int id) async {
    saving = true;
    notifyListeners();
    try {
      detailState = AsyncState.success(await _repository.cancelRequest(id));
      await loadRequests();
      return true;
    } on ApiException catch (error) {
      detailState = AsyncState.error(error.message, detailState.data);
      return false;
    } finally {
      saving = false;
      notifyListeners();
    }
  }

  Future<bool> addAttachment(int requestId, PlatformFile file) async {
    saving = true;
    notifyListeners();
    try {
      await _repository.addAttachment(requestId, file);
      await loadDetail(requestId);
      return true;
    } on ApiException catch (error) {
      detailState = AsyncState.error(error.message, detailState.data);
      return false;
    } finally {
      saving = false;
      notifyListeners();
    }
  }

  Future<bool> rematch(int requestId) async {
    saving = true;
    notifyListeners();
    try {
      final resultId = await _repository.retryMatching(requestId);
      lastMatchingStatus = RequestMatchingStatus(
        status: 'COMPLETED',
        resultId: resultId,
      );
      return true;
    } on ApiException catch (error) {
      detailState = AsyncState.error(error.message, detailState.data);
      return false;
    } finally {
      saving = false;
      notifyListeners();
    }
  }
}
