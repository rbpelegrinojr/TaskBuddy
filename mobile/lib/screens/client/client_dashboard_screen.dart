import 'package:flutter/material.dart';

import '../shared/notifications_screen.dart';
import 'client_requests_screen.dart';
import 'create_request_screen.dart';

class ClientDashboardScreen extends StatelessWidget {
  const ClientDashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Client dashboard'),
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
          const Text('Manage requests, review ranked recommendations, and track simulated payments.'),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute<void>(builder: (_) => const CreateRequestScreen()),
            ),
            icon: const Icon(Icons.add),
            label: const Text('Create service request'),
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute<void>(builder: (_) => const ClientRequestsScreen(standalone: true)),
            ),
            icon: const Icon(Icons.assignment_outlined),
            label: const Text('View my requests'),
          ),
          const SizedBox(height: 16),
          const Card(
            child: Padding(
              padding: EdgeInsets.all(16),
              child: Text(
                'Recommendations show the final score, category, skills, availability, budget sub-scores, and a clear within-budget or over-budget badge.',
              ),
            ),
          ),
        ],
      ),
    );
  }
}
