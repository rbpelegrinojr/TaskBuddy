import 'package:flutter_test/flutter_test.dart';
import 'package:taskbuddy_mobile/core/api/api_exception.dart';

void main() {
  test('maps structured API error envelope', () {
    final exception = ApiException.fromResponse(422, {
      'success': false,
      'error': {
        'code': 'VALIDATION_FAILED',
        'message': 'One or more fields are invalid.',
        'details': {
          'fields': {
            'deadline': 'Must be in the future.',
          },
        },
      },
    });

    expect(exception.statusCode, 422);
    expect(exception.code, 'VALIDATION_FAILED');
    expect(exception.fieldMessage('deadline'), 'Must be in the future.');
    expect(exception.isUnauthorized, isFalse);
  });

  test('flags unauthorized responses', () {
    final exception = ApiException.fromResponse(401, {
      'success': false,
      'error': {
        'code': 'UNAUTHENTICATED',
        'message': 'Authentication required.',
      },
    });

    expect(exception.isUnauthorized, isTrue);
  });
}
