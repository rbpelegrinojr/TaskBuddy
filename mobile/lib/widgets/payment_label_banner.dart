import 'package:flutter/material.dart';

class PaymentLabelBanner extends StatelessWidget {
  const PaymentLabelBanner({super.key, this.compact = false});

  final bool compact;

  @override
  Widget build(BuildContext context) {
    final child = Text(
      'SIMULATED / TEST TRANSACTION',
      style: Theme.of(context).textTheme.labelLarge?.copyWith(
            color: Theme.of(context).colorScheme.onErrorContainer,
            fontWeight: FontWeight.bold,
          ),
    );

    return Semantics(
      label: 'Simulated or test transaction label',
      child: Container(
        width: double.infinity,
        padding: EdgeInsets.symmetric(
          horizontal: 12,
          vertical: compact ? 8 : 12,
        ),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.errorContainer,
          borderRadius: BorderRadius.circular(12),
        ),
        child: child,
      ),
    );
  }
}
