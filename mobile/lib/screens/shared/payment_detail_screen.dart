import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/utils/formatters.dart';
import '../../providers/payments_provider.dart';
import '../../widgets/async_state_view.dart';
import '../../widgets/payment_label_banner.dart';

class PaymentDetailScreen extends StatefulWidget {
  const PaymentDetailScreen({super.key, required this.paymentId});

  final int paymentId;

  @override
  State<PaymentDetailScreen> createState() => _PaymentDetailScreenState();
}

class _PaymentDetailScreenState extends State<PaymentDetailScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<PaymentsProvider>().loadDetail(widget.paymentId);
    });
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<PaymentsProvider>();
    final payment = provider.detailState.data;
    return Scaffold(
      appBar: AppBar(title: const Text('Payment details')),
      body: AsyncStateView(
        status: provider.detailState.status,
        errorMessage: provider.detailState.errorMessage,
        onRetry: () => provider.loadDetail(widget.paymentId),
        child: payment == null
            ? const SizedBox.shrink()
            : ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  const PaymentLabelBanner(),
                  const SizedBox(height: 16),
                  Text(payment.taskTitle ?? payment.reference, style: Theme.of(context).textTheme.headlineSmall),
                  const SizedBox(height: 8),
                  Text('Reference: ${payment.reference}'),
                  Text('State: ${payment.state}'),
                  Text('Amount: ${AppFormatters.formatMoney(payment.amount)}'),
                  Text('Client: ${payment.client.fullName ?? payment.client.id}'),
                  Text('Provider: ${payment.provider.fullName ?? payment.provider.id}'),
                  Text('Created: ${AppFormatters.formatUtcToLocalDateTime(payment.createdAt.toUtc().toIso8601String())}'),
                  if (payment.completedAt != null)
                    Text('Completed: ${AppFormatters.formatUtcToLocalDateTime(payment.completedAt!.toUtc().toIso8601String())}'),
                  const SizedBox(height: 12),
                  Text(payment.currencyNote),
                ],
              ),
      ),
    );
  }
}
