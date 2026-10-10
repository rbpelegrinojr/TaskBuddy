import '../core/api/api_client.dart';
import '../models/paginated_result.dart';
import '../models/payment.dart';

class PaymentsResult extends PaginatedResult<Payment> {
  const PaymentsResult({required super.items, required super.meta});

  Map<String, dynamic> get summary =>
      meta['summary'] is Map<String, dynamic> ? meta['summary'] as Map<String, dynamic> : const {};
}

abstract class PaymentsRepository {
  Future<PaymentsResult> list({String? state});
  Future<Payment> get(int id);
}

class NetworkPaymentsRepository implements PaymentsRepository {
  NetworkPaymentsRepository(this._client);

  final ApiClient _client;

  @override
  Future<PaymentsResult> list({String? state}) async {
    final envelope = await _client.getEnvelope('payments', queryParameters: {
      if (state != null && state.isNotEmpty) 'state': state,
    });
    final data = envelope.data as List<dynamic>;
    return PaymentsResult(
      items: data
          .map((item) => Payment.fromJson(item as Map<String, dynamic>))
          .toList(),
      meta: envelope.meta,
    );
  }

  @override
  Future<Payment> get(int id) async {
    final data = await _client.get('payments/$id') as Map<String, dynamic>;
    return Payment.fromJson(data);
  }
}
