import 'package:file_picker/file_picker.dart';

import '../core/api/api_client.dart';
import '../core/config/api_config.dart';
import '../models/paginated_result.dart';
import '../models/task.dart';

abstract class TasksRepository {
  Future<PaginatedResult<TaskItem>> list({String? status});
  Future<TaskItem> get(int taskId);
  Future<TaskItem> startReview(int taskId);
  Future<TaskItem> approve(int taskId);
  Future<TaskItem> requestRevision(int taskId, String reason);
  Future<TaskItem> dispute(int taskId, String reason);
  Future<TaskItem> submitWork({
    required int taskId,
    required List<PlatformFile> files,
    String? note,
  });
  String resolveDownloadUrl(String path);
}

class NetworkTasksRepository implements TasksRepository {
  NetworkTasksRepository(this._client, this._config);

  final ApiClient _client;
  final ApiConfig _config;

  @override
  Future<PaginatedResult<TaskItem>> list({String? status}) async {
    final envelope = await _client.getEnvelope('tasks', queryParameters: {
      if (status != null && status.isNotEmpty) 'status': status,
    });
    final data = envelope.data as List<dynamic>;
    return PaginatedResult(
      items: data
          .map((item) => TaskItem.fromJson(item as Map<String, dynamic>))
          .toList(),
      meta: envelope.meta,
    );
  }

  @override
  Future<TaskItem> get(int taskId) async {
    final data = await _client.get('tasks/$taskId') as Map<String, dynamic>;
    return TaskItem.fromJson(data);
  }

  @override
  Future<TaskItem> startReview(int taskId) async {
    final data = await _client.post('tasks/$taskId/start-review') as Map<String, dynamic>;
    return TaskItem.fromJson(data);
  }

  @override
  Future<TaskItem> approve(int taskId) async {
    final data = await _client.post('tasks/$taskId/approve') as Map<String, dynamic>;
    return TaskItem.fromJson(data);
  }

  @override
  Future<TaskItem> requestRevision(int taskId, String reason) async {
    final data = await _client.post(
      'tasks/$taskId/revision',
      body: {'reason': reason.trim()},
    ) as Map<String, dynamic>;
    return TaskItem.fromJson(data);
  }

  @override
  Future<TaskItem> dispute(int taskId, String reason) async {
    final data = await _client.post(
      'tasks/$taskId/dispute',
      body: {'reason': reason.trim()},
    ) as Map<String, dynamic>;
    return TaskItem.fromJson(data);
  }

  @override
  Future<TaskItem> submitWork({
    required int taskId,
    required List<PlatformFile> files,
    String? note,
  }) async {
    final uploadFiles = files
        .where((file) => file.path != null)
        .map((file) => UploadFile(path: file.path!, fileName: file.name))
        .toList();
    final data = await _client.upload(
      'tasks/$taskId/submit',
      files: uploadFiles,
      fields: {
        if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
      },
      fieldName: 'files[]',
    ) as Map<String, dynamic>;
    return TaskItem.fromJson(data['task'] as Map<String, dynamic>);
  }

  @override
  String resolveDownloadUrl(String path) => _config.resolveDownloadUrl(path);
}
