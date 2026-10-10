import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart';

import '../core/api/api_exception.dart';
import '../models/task.dart';
import '../repositories/tasks_repository.dart';
import 'async_state.dart';

class TasksProvider extends ChangeNotifier {
  TasksProvider({required TasksRepository repository}) : _repository = repository;

  final TasksRepository _repository;

  AsyncState<List<TaskItem>> state = const AsyncState();
  AsyncState<TaskItem> detailState = const AsyncState();
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

  Future<void> loadDetail(int taskId) async {
    detailState = AsyncState.loading(detailState.data);
    notifyListeners();
    try {
      detailState = AsyncState.success(await _repository.get(taskId));
    } on ApiException catch (error) {
      detailState = AsyncState.error(error.message, detailState.data);
    }
    notifyListeners();
  }

  Future<bool> approve(int taskId) => _mutate(taskId, () => _repository.approve(taskId));
  Future<bool> startReview(int taskId) => _mutate(taskId, () => _repository.startReview(taskId));
  Future<bool> requestRevision(int taskId, String reason) =>
      _mutate(taskId, () => _repository.requestRevision(taskId, reason));
  Future<bool> dispute(int taskId, String reason) =>
      _mutate(taskId, () => _repository.dispute(taskId, reason));

  Future<bool> submitWork({
    required int taskId,
    required List<PlatformFile> files,
    String? note,
  }) => _mutate(
        taskId,
        () => _repository.submitWork(taskId: taskId, files: files, note: note),
      );

  Future<bool> _mutate(int taskId, Future<TaskItem> Function() action) async {
    updating = true;
    notifyListeners();
    try {
      detailState = AsyncState.success(await action());
      await load();
      return true;
    } on ApiException catch (error) {
      detailState = AsyncState.error(error.message, detailState.data);
      return false;
    } finally {
      updating = false;
      notifyListeners();
    }
  }
}
