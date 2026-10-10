import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SecureTokenStorage {
  static const _sessionKey = 'taskbuddy.session';

  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  Future<void> writeSessionJson(String value) => _storage.write(key: _sessionKey, value: value);

  Future<String?> readSessionJson() => _storage.read(key: _sessionKey);

  Future<String?> readToken() async {
    final sessionJson = await readSessionJson();
    if (sessionJson == null || sessionJson.isEmpty) {
      return null;
    }
    final decoded = jsonDecode(sessionJson);
    if (decoded is Map<String, dynamic>) {
      final token = decoded['token'];
      return token?.toString();
    }
    return null;
  }

  Future<void> deleteSession() => _storage.delete(key: _sessionKey);
}
