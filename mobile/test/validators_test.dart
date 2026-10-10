import 'package:flutter_test/flutter_test.dart';
import 'package:taskbuddy_mobile/core/utils/validators.dart';

void main() {
  test('password validator enforces letter digit and length', () {
    expect(AppValidators.password('short1'), isNotNull);
    expect(AppValidators.password('alllettersonly'), isNotNull);
    expect(AppValidators.password('12345678'), isNotNull);
    expect(AppValidators.password('ValidPass123'), isNull);
  });

  test('deadline validator requires future date', () {
    expect(AppValidators.futureDeadline(DateTime.now().subtract(const Duration(minutes: 1))), isNotNull);
    expect(AppValidators.futureDeadline(DateTime.now().add(const Duration(hours: 1))), isNull);
  });

  test('compensation validator requires value greater than zero', () {
    expect(AppValidators.positiveMoney('0'), isNotNull);
    expect(AppValidators.positiveMoney('-1'), isNotNull);
    expect(AppValidators.positiveMoney('100.50'), isNull);
  });

  test('dispute reason validator requires ten characters', () {
    expect(AppValidators.disputeReason('too short'), isNotNull);
    expect(AppValidators.disputeReason('Valid enough reason'), isNull);
  });
}
