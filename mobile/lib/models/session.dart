import 'user.dart';

class Session {
  const Session({
    required this.user,
    required this.token,
    required this.tokenType,
    required this.expiresAt,
  });

  final User user;
  final String token;
  final String tokenType;
  final DateTime expiresAt;

  factory Session.fromJson(Map<String, dynamic> json) {
    return Session(
      user: User.fromJson(json['user'] as Map<String, dynamic>),
      token: json['token'] as String,
      tokenType: json['token_type'] as String,
      expiresAt: DateTime.parse(json['expires_at'] as String),
    );
  }

  Map<String, dynamic> toJson() => {
        'user': user.toJson(),
        'token': token,
        'token_type': tokenType,
        'expires_at': expiresAt.toUtc().toIso8601String(),
      };

  Session copyWith({User? user, String? token, String? tokenType, DateTime? expiresAt}) {
    return Session(
      user: user ?? this.user,
      token: token ?? this.token,
      tokenType: tokenType ?? this.tokenType,
      expiresAt: expiresAt ?? this.expiresAt,
    );
  }
}
