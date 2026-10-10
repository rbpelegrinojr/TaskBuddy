import 'attachment.dart';
import 'category.dart';
import 'skill.dart';

class ServiceRequest {
  const ServiceRequest({
    required this.id,
    required this.clientId,
    required this.clientName,
    required this.category,
    required this.title,
    required this.description,
    this.requirements,
    required this.deadline,
    required this.compensation,
    this.estimatedHours,
    required this.requiresExclusive,
    required this.status,
    required this.skills,
    required this.createdAt,
    required this.updatedAt,
    this.attachments = const [],
    this.pendingInvitation,
    this.assignment,
    this.myMatch,
  });

  final int id;
  final int clientId;
  final String clientName;
  final Category category;
  final String title;
  final String description;
  final String? requirements;
  final DateTime deadline;
  final String compensation;
  final double? estimatedHours;
  final bool requiresExclusive;
  final String status;
  final List<Skill> skills;
  final DateTime createdAt;
  final DateTime updatedAt;
  final List<Attachment> attachments;
  final PendingInvitation? pendingInvitation;
  final RequestAssignment? assignment;
  final ProviderMatchSummary? myMatch;

  factory ServiceRequest.fromJson(Map<String, dynamic> json) {
    return ServiceRequest(
      id: json['id'] as int,
      clientId: (json['client'] as Map<String, dynamic>)['id'] as int,
      clientName: (json['client'] as Map<String, dynamic>)['full_name'] as String,
      category: Category.fromJson(json['category'] as Map<String, dynamic>),
      title: json['title'] as String,
      description: json['description'] as String,
      requirements: json['requirements'] as String?,
      deadline: DateTime.parse(json['deadline'] as String),
      compensation: json['compensation'] as String,
      estimatedHours: (json['estimated_hours'] as num?)?.toDouble(),
      requiresExclusive: json['requires_exclusive'] as bool? ?? false,
      status: json['status'] as String,
      skills: (json['skills'] as List<dynamic>? ?? const [])
          .map((item) => Skill.fromJson(item as Map<String, dynamic>))
          .toList(),
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
      attachments: (json['attachments'] as List<dynamic>? ?? const [])
          .map((item) => Attachment.fromJson(item as Map<String, dynamic>))
          .toList(),
      pendingInvitation: json['pending_invitation'] is Map<String, dynamic>
          ? PendingInvitation.fromJson(
              json['pending_invitation'] as Map<String, dynamic>,
            )
          : null,
      assignment: json['assignment'] is Map<String, dynamic>
          ? RequestAssignment.fromJson(json['assignment'] as Map<String, dynamic>)
          : null,
      myMatch: json['my_match'] is Map<String, dynamic>
          ? ProviderMatchSummary.fromJson(json['my_match'] as Map<String, dynamic>)
          : null,
    );
  }
}

class PendingInvitation {
  const PendingInvitation({
    required this.id,
    required this.providerId,
    required this.status,
    required this.expiresAt,
  });

  final int id;
  final int providerId;
  final String status;
  final DateTime expiresAt;

  factory PendingInvitation.fromJson(Map<String, dynamic> json) {
    return PendingInvitation(
      id: json['id'] as int,
      providerId: json['provider_id'] as int,
      status: json['status'] as String,
      expiresAt: DateTime.parse(json['expires_at'] as String),
    );
  }
}

class RequestAssignment {
  const RequestAssignment({
    required this.id,
    required this.providerId,
    required this.providerName,
    required this.assignedAt,
  });

  final int id;
  final int providerId;
  final String providerName;
  final DateTime assignedAt;

  factory RequestAssignment.fromJson(Map<String, dynamic> json) {
    final provider = json['provider'] as Map<String, dynamic>;
    return RequestAssignment(
      id: json['id'] as int,
      providerId: provider['id'] as int,
      providerName: provider['full_name'] as String,
      assignedAt: DateTime.parse(json['assigned_at'] as String),
    );
  }
}

class ProviderMatchSummary {
  const ProviderMatchSummary({
    required this.finalScore,
    required this.budgetStatus,
    this.summary,
  });

  final double finalScore;
  final String budgetStatus;
  final String? summary;

  factory ProviderMatchSummary.fromJson(Map<String, dynamic> json) {
    return ProviderMatchSummary(
      finalScore: (json['final_score'] as num).toDouble(),
      budgetStatus: json['budget_status'] as String,
      summary: json['summary'] as String?,
    );
  }
}
