import 'package:flutter/material.dart';

import '../core/utils/formatters.dart';
import '../models/match_item.dart';

class MatchCard extends StatelessWidget {
  const MatchCard({
    super.key,
    required this.match,
    required this.onViewProfile,
    required this.onInvite,
  });

  final MatchItem match;
  final VoidCallback onViewProfile;
  final VoidCallback onInvite;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    '${match.rank}. ${match.provider.fullName}',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ),
                Chip(label: Text(match.budgetStatus)),
              ],
            ),
            if ((match.provider.headline ?? '').isNotEmpty) Text(match.provider.headline!),
            const SizedBox(height: 8),
            Text('Final score: ${match.finalScore.toStringAsFixed(2)}'),
            Text('Category: ${match.scores.category.toStringAsFixed(2)}'),
            Text('Skills: ${match.scores.skills.toStringAsFixed(2)}'),
            Text('Availability: ${match.scores.availability.toStringAsFixed(2)}'),
            Text('Budget: ${match.scores.budget.toStringAsFixed(2)}'),
            const SizedBox(height: 8),
            Text(match.explanation.summary),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                OutlinedButton(
                  onPressed: onViewProfile,
                  child: const Text('View profile'),
                ),
                FilledButton(
                  onPressed: onInvite,
                  child: const Text('Invite provider'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
