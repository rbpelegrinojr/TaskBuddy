import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/utils/formatters.dart';
import '../../providers/payments_provider.dart';
import '../../widgets/async_state_view.dart';
import '../../widgets/payment_label_banner.dart';
import 'payment_detail_screen.dart';

class PaymentsScreen extends StatefulWidget {
  const PaymentsScreen({super.key});

  @override
  State<PaymentsScreen> createState() => _PaymentsScreenState();
}

class _PaymentsScreenState extends State<PaymentsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<PaymentsProvider>().load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<PaymentsProvider>();
    return Scaffold(
      appBar: AppBar(title: const Text('Payments')),
      body: Column(
        children: [
          const Padding(
            padding: EdgeInsets.all(16),
            child: PaymentLabelBanner(),
          ),
          Expanded(
            child: AsyncStateView(
              status: provider.state.status,
              errorMessage: provider.state.errorMessage,
              emptyMessage: 'No payments recorded yet.',
              onRetry: () => provider.load(),
              child: ListView.builder(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: provider.state.data?.length ?? 0,
                itemBuilder: (context, index) {
                  final payment = provider.state.data![index];
                  return Card(
                    child: ListTile(
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) => PaymentDetailScreen(paymentId: payment.id),
                        ),
                      ),
                      title: Text(payment.taskTitle ?? payment.reference),
                      subtitle: Text('${payment.state} • ${AppFormatters.formatMoney(payment.amount)}'),
                      trailing: const Icon(Icons.chevron_right),
                    ),
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }
}
