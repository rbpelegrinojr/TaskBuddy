class ApiException implements Exception {
  ApiException({
    required this.statusCode,
    required this.code,
    required this.message,
    this.details,
  });

  factory ApiException.fromResponse(
    int statusCode,
    Map<String, dynamic> body,
  ) {
    final error = body['error'];
    if (error is Map<String, dynamic>) {
      return ApiException(
        statusCode: statusCode,
        code: error['code']?.toString() ?? 'UNKNOWN_ERROR',
        message: error['message']?.toString() ?? 'Request failed.',
        details: error['details'] is Map<String, dynamic>
            ? error['details'] as Map<String, dynamic>
            : null,
      );
    }

    return ApiException(
      statusCode: statusCode,
      code: 'INVALID_RESPONSE',
      message: 'The server returned an unexpected error payload.',
    );
  }

  factory ApiException.network(Object error) {
    return ApiException(
      statusCode: null,
      code: 'NETWORK_ERROR',
      message: 'Could not reach the server. Please check your connection and try again.',
      details: {'source': error.runtimeType.toString()},
    );
  }

  final int? statusCode;
  final String code;
  final String message;
  final Map<String, dynamic>? details;

  bool get isUnauthorized => statusCode == 401 || code == 'UNAUTHENTICATED';

  String? fieldMessage(String field) {
    final fields = details?['fields'];
    if (fields is Map<String, dynamic>) {
      return fields[field]?.toString();
    }
    return null;
  }

  @override
  String toString() => 'ApiException($code, $statusCode): $message';
}
