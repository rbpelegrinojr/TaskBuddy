import 'package:flutter_test/flutter_test.dart';
import 'package:taskbuddy_mobile/models/match_item.dart';
import 'package:taskbuddy_mobile/models/payment.dart';

void main() {
  test('parses match item explanation details', () {
    final item = MatchItem.fromJson({
      'rank': 1,
      'provider': {
        'id': 8,
        'full_name': 'Ava Cruz',
        'headline': 'Data analyst',
        'min_rate': '450.00',
        'years_experience': 4,
      },
      'final_score': 88.5,
      'scores': {
        'category': 100,
        'skills': 75,
        'availability': 100,
        'budget': 50,
      },
      'budget_status': 'WITHIN_BUDGET',
      'explanation': {
        'algorithm_version': 'v1.0.0',
        'final_score': 88.5,
        'weights': {
          'category': 20,
          'skills': 35,
          'availability': 25,
          'budget': 20,
        },
        'category': {'matched': true, 'score': 100},
        'skills': {
          'score': 75,
          'required_total': 4,
          'matched_count': 3,
          'matched_skills': [
            {'id': 1, 'name': 'Excel'},
            {'id': 2, 'name': 'SQL'},
          ],
          'missing_nonmandatory_skills': [
            {'id': 3, 'name': 'Tableau'},
          ],
        },
        'availability': {
          'available': true,
          'score': 100,
          'available_hours_before_deadline': 16.5,
        },
        'budget': {
          'status': 'WITHIN_BUDGET',
          'score': 50,
          'provider_min_rate': '450.00',
          'proposed_compensation': '500.00',
        },
        'summary': 'Matched service category and 3 of 4 requested skills.',
      },
    });

    expect(item.provider.fullName, 'Ava Cruz');
    expect(item.explanation.skills.matchedSkills.length, 2);
    expect(item.explanation.skills.missingNonmandatorySkills.single.name, 'Tableau');
    expect(item.explanation.availability.availableHoursBeforeDeadline, 16.5);
    expect(item.explanation.summary, contains('Matched service category'));
  });

  test('parses payment model with simulated label', () {
    final payment = Payment.fromJson({
      'id': 9,
      'reference': 'SIM-20261010-ABCD1234',
      'label': 'SIMULATED / TEST TRANSACTION',
      'is_simulated': true,
      'task_id': 14,
      'task_title': 'Create brochure',
      'client': {'id': 1, 'full_name': 'Client One'},
      'provider': {'id': 2, 'full_name': 'Provider One'},
      'amount': '1200.00',
      'currency_note': 'Simulated amount; no real funds are moved.',
      'state': 'PENDING',
      'created_at': '2030-01-07T09:00:00Z',
      'completed_at': null,
    });

    expect(payment.label, 'SIMULATED / TEST TRANSACTION');
    expect(payment.isSimulated, isTrue);
    expect(payment.client.fullName, 'Client One');
  });
}
