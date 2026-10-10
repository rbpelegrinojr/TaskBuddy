import 'package:flutter/foundation.dart';

import '../core/api/api_exception.dart';
import '../models/session.dart';
import '../models/user.dart';
import '../repositories/auth_repository.dart';

class AuthProvider extends ChangeNotifier {
  AuthProvider({required AuthRepository repository}) : _repository = repository;

  final AuthRepository _repository;

  bool _bootstrapping = true;
  bool _busy = false;
  String? _errorMessage;
  Session? _session;

  bool get isBootstrapping => _bootstrapping;
  bool get isBusy => _busy;
  String? get errorMessage => _errorMessage;
  Session? get session => _session;
  User? get user => _session?.user;
  bool get isAuthenticated => _session != null;
  bool get isClient => user?.role == 'client';
  bool get isProvider => user?.role == 'provider';

  Future<void> restoreSession() async {
    _bootstrapping = true;
    _errorMessage = null;
    notifyListeners();
    try {
      final stored = await _repository.restoreSession();
      if (stored == null) {
        _session = null;
      } else {
        final currentUser = await _repository.me();
        _session = stored.copyWith(user: currentUser);
        await _repository.persistSession(_session!);
      }
    } on ApiException catch (error) {
      if (error.isUnauthorized) {
        await _repository.clearSession();
        _session = null;
      } else {
        _session = await _repository.restoreSession();
        _errorMessage = error.message;
      }
    } finally {
      _bootstrapping = false;
      notifyListeners();
    }
  }

  Future<bool> login({required String email, required String password}) async {
    return _run(() async {
      _session = await _repository.login(email: email, password: password);
    });
  }

  Future<bool> register({
    required String email,
    required String password,
    required String fullName,
    required String role,
    String? phone,
  }) async {
    return _run(() async {
      _session = await _repository.register(
        email: email,
        password: password,
        fullName: fullName,
        role: role,
        phone: phone,
      );
    });
  }

  Future<void> logout() async {
    try {
      if (_session != null) {
        await _repository.logout();
      }
    } catch (_) {
    } finally {
      await _repository.clearSession();
      _session = null;
      _errorMessage = null;
      notifyListeners();
    }
  }

  Future<void> handleUnauthorized() async {
    if (_session == null) {
      return;
    }
    await _repository.clearSession();
    _session = null;
    _errorMessage = 'Your session expired. Please sign in again.';
    notifyListeners();
  }

  Future<bool> _run(Future<void> Function() action) async {
    _busy = true;
    _errorMessage = null;
    notifyListeners();
    try {
      await action();
      return true;
    } on ApiException catch (error) {
      _errorMessage = error.message;
      return false;
    } finally {
      _busy = false;
      notifyListeners();
    }
  }
}
