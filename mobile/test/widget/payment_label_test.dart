import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:taskbuddy_mobile/models/payment.dart';
import 'package:taskbuddy_mobile/providers/payments_provider.dart';
import 'package:taskbuddy_mobile/repositories/payments_repository.dart';
import 'package:taskbuddy_mobile/screens/shared/payments_screen.dart';

class FakePaymentsRepository implements PaymentsRepository {
  @override
  Future<Payment> get(int id) async => _payment;

  @override
  Future<PaymentsResult> list({String? state}) async {
    return PaymentsResult(
      items: [_payment],
      meta: const {
        'summary': {
          'label': 'SIMULATED / TEST TRANSACTION',
          'is_simulated': true,
          'pending_total': '500.00',
          'completed_total': '0.00',
          'on_hold_total': '0.00',
          'cancelled_total': '0.00',
        },
      },
    );
  }

  static final _payment = Payment(
    id: 1,
    reference: 'SIM-20261010-AAAA1111',
    label: 'SIMULATED / TEST TRANSACTION',
    isSimulated: true,
    taskId: 10,
    taskTitle: 'Logo refresh',
    client: PaymentParty(id: 1, fullName: 'Client One'),
    provider: PaymentParty(id: 2, fullName: 'Provider One'),
    amount: '500.00',
    currencyNote: 'Simulated amount; no real funds are moved.',
    state: 'PENDING',
    createdAt: DateTime.utc(2030, 1, 7, 9),
    completedAt: null,
  );
}

void main() {
  testWidgets('payments screen shows simulated label from fake repository', (tester) async {
    final provider = PaymentsProvider(repository: FakePaymentsRepository());

    await tester.pumpWidget(
      MaterialApp(
        home: ChangeNotifierProvider<PaymentsProvider>.value(
          value: provider,
          child: const PaymentsScreen(),
        ),
      ),
    );

    await tester.pump();
    await tester.pump();

    expect(find.text('SIMULATED / TEST TRANSACTION'), findsOneWidget);
    expect(find.text('Logo refresh'), findsOneWidget);
  });
}
