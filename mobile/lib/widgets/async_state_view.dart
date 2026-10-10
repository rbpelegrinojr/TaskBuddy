import 'package:flutter/material.dart';

import '../providers/async_state.dart';

class AsyncStateView extends StatelessWidget {
  const AsyncStateView({
    super.key,
    required this.status,
    required this.child,
    this.errorMessage,
    this.emptyMessage = 'Nothing to show here yet.',
    this.onRetry,
  });

  final LoadStatus status;
  final Widget child;
  final String? errorMessage;
  final String emptyMessage;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    switch (status) {
      case LoadStatus.initial:
      case LoadStatus.loading:
        return const Center(child: CircularProgressIndicator());
      case LoadStatus.error:
        return Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(errorMessage ?? 'Something went wrong.'),
                const SizedBox(height: 12),
                FilledButton(
                  onPressed: onRetry,
                  child: const Text('Retry'),
                ),
              ],
            ),
          ),
        );
      case LoadStatus.empty:
        return Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Text(
              emptyMessage,
              textAlign: TextAlign.center,
            ),
          ),
        );
      case LoadStatus.success:
        return child;
    }
  }
}
