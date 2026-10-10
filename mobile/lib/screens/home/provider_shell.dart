import 'package:flutter/material.dart';

import '../provider/opportunities_screen.dart';
import '../provider/provider_dashboard_screen.dart';
import '../shared/conversations_screen.dart';
import '../shared/profile_screen.dart';
import '../shared/tasks_screen.dart';

class ProviderShell extends StatefulWidget {
  const ProviderShell({super.key});

  @override
  State<ProviderShell> createState() => _ProviderShellState();
}

class _ProviderShellState extends State<ProviderShell> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    final screens = [
      const ProviderDashboardScreen(),
      const OpportunitiesScreen(),
      const TasksScreen(),
      const ConversationsScreen(),
      const ProfileScreen(),
    ];

    return Scaffold(
      body: IndexedStack(index: _index, children: screens),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (value) => setState(() => _index = value),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.home_outlined), label: 'Home'),
          NavigationDestination(icon: Icon(Icons.search_outlined), label: 'Opportunities'),
          NavigationDestination(icon: Icon(Icons.task_outlined), label: 'Tasks'),
          NavigationDestination(icon: Icon(Icons.chat_bubble_outline), label: 'Messages'),
          NavigationDestination(icon: Icon(Icons.person_outline), label: 'Account'),
        ],
      ),
    );
  }
}
