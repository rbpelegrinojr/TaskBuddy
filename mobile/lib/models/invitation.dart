class Invitation {
  const Invitation({
    required this.id,
    required this.status,
    this.message,
    required this.expiresAt,
    this.respondedAt,
    required this.createdAt,
    required this.request,
    required this.client,
    required this.provider,
    this.assignmentId,
  });

  final int id;
  final String status;
  final String? message;
  final DateTime expiresAt;
  final DateTime? respondedAt;
  final DateTime createdAt;
  final InvitationRequest request;
  final InvitationParty client;
  final InvitationParty provider;
  final int? assignmentId;

  factory Invitation.fromJson(Map<String, dynamic> json) {
    return Invitation(
      id: json['id'] as int,
      status: json['status'] as String,
      message: json['message'] as String?,
      expiresAt: DateTime.parse(json['expires_at'] as String),
      respondedAt: json['responded_at'] == null
          ? null
          : DateTime.parse(json['responded_at'] as String),
      createdAt: DateTime.parse(json['created_at'] as String),
      request: InvitationRequest.fromJson(json['request'] as Map<String, dynamic>),
      client: InvitationParty.fromJson(json['client'] as Map<String, dynamic>),
      provider: InvitationParty.fromJson(json['provider'] as Map<String, dynamic>),
      assignmentId: json['assignment_id'] as int?,
    );
  }
}

class InvitationRequest {
  const InvitationRequest({
    required this.id,
    required this.title,
    required this.compensation,
    required this.deadline,
    required this.category,
    required this.status,
  });

  final int id;
  final String title;
  final String compensation;
  final DateTime deadline;
  final String category;
  final String status;

  factory InvitationRequest.fromJson(Map<String, dynamic> json) {
    return InvitationRequest(
      id: json['id'] as int,
      title: json['title'] as String,
      compensation: json['compensation'] as String,
      deadline: DateTime.parse(json['deadline'] as String),
      category: json['category'] as String,
      status: json['status'] as String,
    );
  }
}

class InvitationParty {
  const InvitationParty({required this.id, required this.fullName});

  final int id;
  final String fullName;

  factory InvitationParty.fromJson(Map<String, dynamic> json) {
    return InvitationParty(
      id: json['id'] as int,
      fullName: json['full_name'] as String,
    );
  }
}
