import 'package:file_picker/file_picker.dart';

import '../core/api/api_client.dart';
import '../core/config/api_config.dart';
import '../core/utils/formatters.dart';
import '../models/match_item.dart';
import '../models/paginated_result.dart';
import '../models/service_request.dart';

class RequestUpsertPayload {
  const RequestUpsertPayload({
    required this.title,
    required this.description,
    required this.categoryId,
    required this.skills,
    this.requirements,
    required this.deadline,
    required this.compensation,
    this.estimatedHours,
    required this.requiresExclusive,
  });

  final String title;
  final String description;
  final int categoryId;
  final List<RequestSkillSelection> skills;
  final String? requirements;
  final DateTime deadline;
  final String compensation;
  final String? estimatedHours;
  final bool requiresExclusive;

  Map<String, dynamic> toJson() => {
        'title': title.trim(),
        'description': description.trim(),
        'category_id': categoryId,
        'skills': skills.map((item) => item.toJson()).toList(),
        if (requirements != null && requirements!.trim().isNotEmpty)
          'requirements': requirements!.trim(),
        'deadline': AppFormatters.toUtcIsoString(deadline),
        'compensation': compensation.trim(),
        if (estimatedHours != null && estimatedHours!.trim().isNotEmpty)
          'estimated_hours': estimatedHours!.trim(),
        'requires_exclusive': requiresExclusive,
      };
}

class RequestSkillSelection {
  const RequestSkillSelection({required this.skillId, required this.isMandatory});

  final int skillId;
  final bool isMandatory;

  Map<String, dynamic> toJson() => {
        'skill_id': skillId,
        'is_mandatory': isMandatory,
      };
}

class RequestCreateResult {
  const RequestCreateResult({required this.request, required this.matching});

  final ServiceRequest request;
  final RequestMatchingStatus matching;
}

class RequestMatchingStatus {
  const RequestMatchingStatus({
    required this.status,
    this.resultId,
    this.eligibleCount,
    this.noMatch,
    this.message,
  });

  final String status;
  final int? resultId;
  final int? eligibleCount;
  final bool? noMatch;
  final String? message;

  factory RequestMatchingStatus.fromJson(Map<String, dynamic> json) {
    return RequestMatchingStatus(
      status: json['status'] as String,
      resultId: json['result_id'] as int?,
      eligibleCount: json['eligible_count'] as int?,
      noMatch: json['no_match'] as bool?,
      message: json['message'] as String?,
    );
  }
}

abstract class RequestsRepository {
  Future<RequestCreateResult> createRequest(
    RequestUpsertPayload payload, {
    PlatformFile? attachment,
  });
  Future<PaginatedResult<ServiceRequest>> getClientRequests({String? status});
  Future<PaginatedResult<ServiceRequest>> getProviderOpportunities();
  Future<ServiceRequest> getRequest(int id);
  Future<ServiceRequest> cancelRequest(int id);
  Future<int?> retryMatching(int requestId);
  Future<PaginatedResult<MatchItem>> getMatches(int requestId, {int page = 1});
  Future<Map<String, dynamic>> inviteProvider({
    required int requestId,
    required int providerId,
    String? message,
    int? expiresInHours,
  });
  Future<void> addAttachment(int requestId, PlatformFile file);
}

class NetworkRequestsRepository implements RequestsRepository {
  NetworkRequestsRepository(this._client, this._config);

  final ApiClient _client;
  final ApiConfig _config;

  @override
  Future<RequestCreateResult> createRequest(
    RequestUpsertPayload payload, {
    PlatformFile? attachment,
  }) async {
    final data = await _client.post('requests', body: payload.toJson()) as Map<String, dynamic>;
    final result = RequestCreateResult(
      request: ServiceRequest.fromJson(data['request'] as Map<String, dynamic>),
      matching: RequestMatchingStatus.fromJson(data['matching'] as Map<String, dynamic>),
    );
    if (attachment != null && attachment.path != null) {
      await addAttachment(result.request.id, attachment);
    }
    return result;
  }

  @override
  Future<PaginatedResult<ServiceRequest>> getClientRequests({String? status}) async {
    final envelope = await _client.getEnvelope('requests', queryParameters: {
      if (status != null && status.isNotEmpty) 'status': status,
    });
    final data = envelope.data as List<dynamic>;
    return PaginatedResult(
      items: data
          .map((item) => ServiceRequest.fromJson(item as Map<String, dynamic>))
          .toList(),
      meta: envelope.meta,
    );
  }

  @override
  Future<PaginatedResult<ServiceRequest>> getProviderOpportunities() async {
    final envelope = await _client.getEnvelope('requests');
    final data = envelope.data as List<dynamic>;
    return PaginatedResult(
      items: data
          .map((item) => ServiceRequest.fromJson(item as Map<String, dynamic>))
          .toList(),
      meta: envelope.meta,
    );
  }

  @override
  Future<ServiceRequest> getRequest(int id) async {
    final data = await _client.get('requests/$id') as Map<String, dynamic>;
    return ServiceRequest.fromJson(data);
  }

  @override
  Future<ServiceRequest> cancelRequest(int id) async {
    final data = await _client.post('requests/$id/cancel') as Map<String, dynamic>;
    return ServiceRequest.fromJson(data);
  }

  @override
  Future<int?> retryMatching(int requestId) async {
    final data = await _client.post('requests/$requestId/matches') as Map<String, dynamic>;
    return data['result_id'] as int?;
  }

  @override
  Future<PaginatedResult<MatchItem>> getMatches(int requestId, {int page = 1}) async {
    final envelope = await _client.getEnvelope(
      'requests/$requestId/matches',
      queryParameters: {'page': page},
    );
    final response = envelope.data;
    if (response is! List<dynamic>) {
      return PaginatedResult(items: const [], meta: envelope.meta);
    }
    return PaginatedResult(
      items: response
          .map((item) => MatchItem.fromJson(item as Map<String, dynamic>))
          .toList(),
      meta: envelope.meta,
    );
  }

  @override
  Future<Map<String, dynamic>> inviteProvider({
    required int requestId,
    required int providerId,
    String? message,
    int? expiresInHours,
  }) async {
    final data = await _client.post(
      'requests/$requestId/invite',
      body: {
        'provider_id': providerId,
        if (message != null && message.trim().isNotEmpty) 'message': message.trim(),
        if (expiresInHours != null) 'expires_in_hours': expiresInHours,
      },
    ) as Map<String, dynamic>;
    return data;
  }

  @override
  Future<void> addAttachment(int requestId, PlatformFile file) async {
    if (file.path == null) {
      return;
    }
    await _client.upload(
      'requests/$requestId/attachments',
      files: [UploadFile(path: file.path!, fileName: file.name)],
    );
  }

  String resolveAttachmentUrl(String relativePath) {
    return AppFormatters.fullDownloadUrl(_config, relativePath);
  }
}
