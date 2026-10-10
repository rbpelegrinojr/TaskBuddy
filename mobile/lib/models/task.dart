import 'attachment.dart';
import 'category.dart';
import 'payment.dart';
import 'skill.dart';

class TaskItem {
  const TaskItem({
    required this.id,
    required this.title,
    required this.status,
    required this.category,
    required this.deadline,
    required this.compensation,
    required this.client,
    required this.provider,
    required this.assignmentId,
    this.conversationId,
    required this.assignedAt,
    this.completedAt,
    required this.updatedAt,
    this.description,
    this.requirements,
    this.skills = const [],
    this.attachments = const [],
    this.submissions = const [],
    this.revisions = const [],
    this.dispute,
    this.payment,
    this.allowedActions = const [],
  });

  final int id;
  final String title;
  final String status;
  final Category category;
  final DateTime deadline;
  final String compensation;
  final TaskParty client;
  final TaskParty provider;
  final int assignmentId;
  final int? conversationId;
  final DateTime assignedAt;
  final DateTime? completedAt;
  final DateTime updatedAt;
  final String? description;
  final String? requirements;
  final List<Skill> skills;
  final List<Attachment> attachments;
  final List<Submission> submissions;
  final List<RevisionRecord> revisions;
  final TaskDispute? dispute;
  final Payment? payment;
  final List<String> allowedActions;

  factory TaskItem.fromJson(Map<String, dynamic> json) {
    return TaskItem(
      id: json['id'] as int,
      title: json['title'] as String,
      status: json['status'] as String,
      category: Category.fromJson(json['category'] as Map<String, dynamic>),
      deadline: DateTime.parse(json['deadline'] as String),
      compensation: json['compensation'] as String,
      client: TaskParty.fromJson(json['client'] as Map<String, dynamic>),
      provider: TaskParty.fromJson(json['provider'] as Map<String, dynamic>),
      assignmentId: json['assignment_id'] as int,
      conversationId: json['conversation_id'] as int?,
      assignedAt: DateTime.parse(json['assigned_at'] as String),
      completedAt: json['completed_at'] == null
          ? null
          : DateTime.parse(json['completed_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
      description: json['description'] as String?,
      requirements: json['requirements'] as String?,
      skills: (json['skills'] as List<dynamic>? ?? const [])
          .map((item) => Skill.fromJson(item as Map<String, dynamic>))
          .toList(),
      attachments: (json['attachments'] as List<dynamic>? ?? const [])
          .map((item) => Attachment.fromJson(item as Map<String, dynamic>))
          .toList(),
      submissions: (json['submissions'] as List<dynamic>? ?? const [])
          .map((item) => Submission.fromJson(item as Map<String, dynamic>))
          .toList(),
      revisions: (json['revisions'] as List<dynamic>? ?? const [])
          .map((item) => RevisionRecord.fromJson(item as Map<String, dynamic>))
          .toList(),
      dispute: json['dispute'] is Map<String, dynamic>
          ? TaskDispute.fromJson(json['dispute'] as Map<String, dynamic>)
          : null,
      payment: json['payment'] is Map<String, dynamic>
          ? Payment.fromJson(json['payment'] as Map<String, dynamic>)
          : null,
      allowedActions: (json['allowed_actions'] as List<dynamic>? ?? const [])
          .map((item) => item.toString())
          .toList(),
    );
  }
}

class TaskParty {
  const TaskParty({required this.id, required this.fullName});

  final int id;
  final String fullName;

  factory TaskParty.fromJson(Map<String, dynamic> json) {
    return TaskParty(
      id: json['id'] as int,
      fullName: json['full_name'] as String,
    );
  }
}

class Submission {
  const Submission({
    required this.id,
    this.note,
    required this.createdAt,
    required this.files,
  });

  final int id;
  final String? note;
  final DateTime createdAt;
  final List<Attachment> files;

  factory Submission.fromJson(Map<String, dynamic> json) {
    return Submission(
      id: json['id'] as int,
      note: json['note'] as String?,
      createdAt: DateTime.parse(json['created_at'] as String),
      files: (json['files'] as List<dynamic>? ?? const [])
          .map((item) => Attachment.fromJson(item as Map<String, dynamic>))
          .toList(),
    );
  }
}

class RevisionRecord {
  const RevisionRecord({
    required this.id,
    required this.reason,
    required this.submissionId,
    required this.createdAt,
  });

  final int id;
  final String reason;
  final int submissionId;
  final DateTime createdAt;

  factory RevisionRecord.fromJson(Map<String, dynamic> json) {
    return RevisionRecord(
      id: json['id'] as int,
      reason: json['reason'] as String,
      submissionId: json['submission_id'] as int,
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }
}

class TaskDispute {
  const TaskDispute({
    required this.id,
    required this.reason,
    required this.status,
    this.resolution,
    this.resolutionNote,
    required this.createdAt,
    this.resolvedAt,
  });

  final int id;
  final String reason;
  final String status;
  final String? resolution;
  final String? resolutionNote;
  final DateTime createdAt;
  final DateTime? resolvedAt;

  factory TaskDispute.fromJson(Map<String, dynamic> json) {
    return TaskDispute(
      id: json['id'] as int,
      reason: json['reason'] as String,
      status: json['status'] as String,
      resolution: json['resolution'] as String?,
      resolutionNote: json['resolution_note'] as String?,
      createdAt: DateTime.parse(json['created_at'] as String),
      resolvedAt: json['resolved_at'] == null
          ? null
          : DateTime.parse(json['resolved_at'] as String),
    );
  }
}
