import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:taskbuddy_mobile/models/session.dart';
import 'package:taskbuddy_mobile/models/user.dart';
import 'package:taskbuddy_mobile/providers/auth_provider.dart';
import 'package:taskbuddy_mobile/repositories/auth_repository.dart';
import 'package:taskbuddy_mobile/screens/auth/login_screen.dart';

class FakeAuthRepository implements AuthRepository {
  @override
  Future<void> clearSession() async {}

  @override
  Future<void> logout() async {}

  @override
  Future<User> me() async => _user;

  @override
  Future<void> persistSession(Session session) async {}

  @override
  Future<Session?> restoreSession() async => null;

  @override
  Future<Session> login({required String email, required String password}) async {
    return Session(
      user: _user,
      token: 'token',
      tokenType: 'Bearer',
      expiresAt: DateTime.utc(2030, 1, 7),
    );
  }

  @override
  Future<Session> register({required String email, required String password, required String fullName, required String role, String? phone}) {
    throw UnimplementedError();
  }

  static final _user = User(
    id: 1,
    email: 'user@example.com',
    fullName: 'Test User',
    role: 'client',
    status: 'active',
    phone: null,
    createdAt: DateTime.utc(2030, 1, 1),
  );
}

void main() {
  testWidgets('login screen validates email and password', (tester) async {
    final authProvider = AuthProvider(repository: FakeAuthRepository());

    await tester.pumpWidget(
      MaterialApp(
        home: ChangeNotifierProvider<AuthProvider>.value(
          value: authProvider,
          child: const LoginScreen(),
        ),
      ),
    );

    await tester.enterText(find.byType(TextFormField).first, 'invalid');
    await tester.enterText(find.byType(TextFormField).last, 'short');
    await tester.tap(find.text('Sign in'));
    await tester.pump();

    expect(find.text('Enter a valid email address.'), findsOneWidget);
    expect(find.text('Password must be 8-72 characters and include a letter and a digit.'), findsOneWidget);
  });
}
