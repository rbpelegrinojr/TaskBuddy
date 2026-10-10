import 'package:flutter/material.dart';

import '../shared/notifications_screen.dart';
import '../shared/payments_screen.dart';
import 'invitations_screen.dart';
import 'opportunities_screen.dart';

class ProviderDashboardScreen extends StatelessWidget {
  const ProviderDashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Provider dashboard'),
        actions: [
          IconButton(
            tooltip: 'Notifications',
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute<void>(builder: (_) => const NotificationsScreen()),
            ),
            icon: const Icon(Icons.notifications_outlined),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text('Complete your profile, manage invitations, and submit work from active tasks.'),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute<void>(builder: (_) => const OpportunitiesScreen(standalone: true)),
            ),
            icon: const Icon(Icons.search),
            label: const Text('Browse matching opportunities'),
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute<void>(builder: (_) => const InvitationsScreen()),
            ),
            icon: const Icon(Icons.mail_outline),
            label: const Text('View invitations'),
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute<void>(builder: (_) => const PaymentsScreen()),
            ),
            icon: const Icon(Icons.payments_outlined),
            label: const Text('View simulated earnings'),
          ),
          const SizedBox(height: 16),
          const Card(
            child: Padding(
              padding: EdgeInsets.all(16),
              child: Text(
                'Only providers with a complete profile and declared weekly availability are eligible for matching.',
              ),
            ),
          ),
        ],
      ),
    );
  }
}
