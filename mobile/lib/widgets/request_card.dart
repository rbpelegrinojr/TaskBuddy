import 'package:flutter/material.dart';

import '../core/utils/formatters.dart';
import '../models/service_request.dart';

class RequestCard extends StatelessWidget {
  const RequestCard({
    super.key,
    required this.request,
    required this.onTap,
    this.showMatchSummary = false,
  });

  final ServiceRequest request;
  final VoidCallback onTap;
  final bool showMatchSummary;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(request.title, style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              Text(request.category.name),
              const SizedBox(height: 4),
              Text('Deadline: ${AppFormatters.formatUtcToLocalDateTime(request.deadline.toUtc().toIso8601String())}'),
              Text('Compensation: ${AppFormatters.formatMoney(request.compensation)}'),
              Text('Status: ${request.status}'),
              if (showMatchSummary && request.myMatch != null) ...[
                const SizedBox(height: 8),
                Chip(label: Text(AppFormatters.budgetStatusLabel(request.myMatch!.budgetStatus))),
                Text('Match score: ${request.myMatch!.finalScore.toStringAsFixed(2)}'),
                if ((request.myMatch?.summary ?? '').isNotEmpty)
                  Text(request.myMatch!.summary!),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
