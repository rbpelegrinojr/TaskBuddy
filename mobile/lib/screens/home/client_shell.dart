import 'package:flutter/material.dart';

import '../client/client_dashboard_screen.dart';
import '../client/client_requests_screen.dart';
import '../shared/conversations_screen.dart';
import '../shared/payments_screen.dart';
import '../shared/profile_screen.dart';

class ClientShell extends StatefulWidget {
  const ClientShell({super.key});

  @override
  State<ClientShell> createState() => _ClientShellState();
}

class _ClientShellState extends State<ClientShell> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    final screens = [
      const ClientDashboardScreen(),
      const ClientRequestsScreen(),
      const ConversationsScreen(),
      const PaymentsScreen(),
      const ProfileScreen(),
    ];

    return Scaffold(
      body: IndexedStack(index: _index, children: screens),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (value) => setState(() => _index = value),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.home_outlined), label: 'Home'),
          NavigationDestination(icon: Icon(Icons.assignment_outlined), label: 'Requests'),
          NavigationDestination(icon: Icon(Icons.chat_bubble_outline), label: 'Messages'),
          NavigationDestination(icon: Icon(Icons.payments_outlined), label: 'Payments'),
          NavigationDestination(icon: Icon(Icons.person_outline), label: 'Account'),
        ],
      ),
    );
  }
}
