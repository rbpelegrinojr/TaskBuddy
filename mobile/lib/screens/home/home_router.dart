import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../providers/auth_provider.dart';
import '../splash_screen.dart';
import '../welcome_screen.dart';
import 'client_shell.dart';
import 'provider_shell.dart';

class HomeRouter extends StatelessWidget {
  const HomeRouter({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    if (auth.isBootstrapping) {
      return const SplashScreen();
    }
    if (!auth.isAuthenticated) {
      return const WelcomeScreen();
    }
    return auth.isClient ? const ClientShell() : const ProviderShell();
  }
}
