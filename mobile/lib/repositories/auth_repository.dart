import 'dart:convert';

import '../core/api/api_client.dart';
import '../core/storage/secure_token_storage.dart';
import '../models/session.dart';
import '../models/user.dart';

abstract class AuthRepository {
  Future<Session> login({required String email, required String password});
  Future<Session> register({
    required String email,
    required String password,
    required String fullName,
    required String role,
    String? phone,
  });
  Future<User> me();
  Future<void> logout();
  Future<void> persistSession(Session session);
  Future<Session?> restoreSession();
  Future<void> clearSession();
}

class NetworkAuthRepository implements AuthRepository {
  NetworkAuthRepository(this._client, this._storage);

  final ApiClient _client;
  final SecureTokenStorage _storage;

  @override
  Future<Session> login({required String email, required String password}) async {
    final data = await _client.post(
      'auth/login',
      body: {'email': email.trim(), 'password': password},
      requiresAuth: false,
    ) as Map<String, dynamic>;
    final session = Session.fromJson(data);
    await persistSession(session);
    return session;
  }

  @override
  Future<Session> register({
    required String email,
    required String password,
    required String fullName,
    required String role,
    String? phone,
  }) async {
    final data = await _client.post(
      'auth/register',
      body: {
        'email': email.trim(),
        'password': password,
        'full_name': fullName.trim(),
        'role': role,
        if (phone != null && phone.trim().isNotEmpty) 'phone': phone.trim(),
      },
      requiresAuth: false,
    ) as Map<String, dynamic>;
    final session = Session.fromJson(data);
    await persistSession(session);
    return session;
  }

  @override
  Future<User> me() async {
    final data = await _client.get('auth/me') as Map<String, dynamic>;
    return User.fromJson(data['user'] as Map<String, dynamic>);
  }

  @override
  Future<void> logout() async {
    await _client.post('auth/logout');
  }

  @override
  Future<void> persistSession(Session session) {
    return _storage.writeSessionJson(jsonEncode(session.toJson()));
  }

  @override
  Future<Session?> restoreSession() async {
    final raw = await _storage.readSessionJson();
    if (raw == null || raw.isEmpty) {
      return null;
    }
    final decoded = jsonDecode(raw) as Map<String, dynamic>;
    return Session.fromJson(decoded);
  }

  @override
  Future<void> clearSession() => _storage.deleteSession();
}
