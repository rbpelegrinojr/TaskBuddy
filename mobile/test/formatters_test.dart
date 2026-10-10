import 'package:flutter_test/flutter_test.dart';
import 'package:taskbuddy_mobile/core/config/api_config.dart';
import 'package:taskbuddy_mobile/core/utils/formatters.dart';

void main() {
  test('formats money consistently', () {
    expect(AppFormatters.formatMoney('500'), r'$500.00');
  });

  test('converts local datetime to UTC ISO string', () {
    final local = DateTime(2030, 1, 7, 17, 30);
    final iso = AppFormatters.toUtcIsoString(local);
    expect(iso, contains('2030-01-07'));
  });

  test('resolves relative download url against api origin', () {
    const config = ApiConfig(baseUrl: 'https://example.com/api');
    expect(
      AppFormatters.fullDownloadUrl(config, '/api/attachments/4/download'),
      'https://example.com/api/attachments/4/download',
    );
  });
}
