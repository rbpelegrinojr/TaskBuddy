import 'package:intl/intl.dart';

import '../config/api_config.dart';

class AppFormatters {
  static final DateFormat _dateTimeFormat = DateFormat.yMMMd().add_jm();
  static final DateFormat _dateFormat = DateFormat.yMMMd();
  static final NumberFormat _moneyFormat = NumberFormat.currency(
    symbol: '\$',
    decimalDigits: 2,
  );

  static String formatUtcToLocalDateTime(String isoUtc) {
    final local = DateTime.parse(isoUtc).toLocal();
    return _dateTimeFormat.format(local);
  }

  static String formatUtcToLocalDate(String isoUtc) {
    final local = DateTime.parse(isoUtc).toLocal();
    return _dateFormat.format(local);
  }

  static String formatMoney(String amount) {
    final value = double.tryParse(amount) ?? 0;
    return _moneyFormat.format(value);
  }

  static String deadlineInputText(DateTime dateTime) {
    return _dateTimeFormat.format(dateTime.toLocal());
  }

  static String toUtcIsoString(DateTime localDateTime) {
    return localDateTime.toUtc().toIso8601String();
  }

  static String budgetStatusLabel(String status) {
    return status == 'OVER_BUDGET' ? 'Over budget' : 'Within budget';
  }

  static String fullDownloadUrl(ApiConfig config, String path) {
    return config.resolveDownloadUrl(path);
  }
}
