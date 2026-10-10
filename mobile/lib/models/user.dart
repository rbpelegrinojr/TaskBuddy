class User {
  const User({
    required this.id,
    required this.email,
    required this.fullName,
    required this.role,
    required this.status,
    this.phone,
    required this.createdAt,
  });

  final int id;
  final String email;
  final String fullName;
  final String role;
  final String status;
  final String? phone;
  final DateTime createdAt;

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'] as int,
      email: json['email'] as String,
      fullName: json['full_name'] as String,
      role: json['role'] as String,
      status: json['status'] as String,
      phone: json['phone'] as String?,
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'email': email,
        'full_name': fullName,
        'role': role,
        'status': status,
        'phone': phone,
        'created_at': createdAt.toUtc().toIso8601String(),
      };
}
