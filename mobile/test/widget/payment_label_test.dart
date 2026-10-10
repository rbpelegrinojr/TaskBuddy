import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:taskbuddy_mobile/widgets/payment_label_banner.dart';

void main() {
  testWidgets('payment banner shows simulated label', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: PaymentLabelBanner(),
        ),
      ),
    );

    expect(find.text('SIMULATED / TEST TRANSACTION'), findsOneWidget);
    expect(find.bySemanticsLabel('Simulated or test transaction label'), findsOneWidget);
  });
}
