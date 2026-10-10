<?php
declare(strict_types=1);

/**
 * Documented matching test dataset (see docs/matching-algorithm.md, "Reference dataset").
 * Weights: category 30 / skills 40 / availability 20 / budget 10.
 * 'now' is Monday 2030-01-07 09:00 UTC; the deadline is Friday 2030-01-11 17:00 UTC.
 */
$utc = new DateTimeZone('UTC');
$weekdays = array_map(static fn($d) => ['day_of_week' => $d, 'start_time' => '09:00', 'end_time' => '17:00'], [1, 2, 3, 4, 5]);
$provider = static fn(int $id, array $over = []) => $over + [
    'id' => $id, 'status' => 'active', 'profile_complete' => true, 'min_rate' => '400.00', 'category_ids' => [1],
    'skill_ids' => [10, 11, 12, 13], 'availability' => $weekdays, 'blocked_client_ids' => [], 'commitments' => [], 'restricted' => false,
];

return [
    'request' => [
        'id' => 1, 'client_id' => 900, 'category_id' => 1, 'compensation' => '500.00', 'requires_exclusive' => false, 'estimated_hours' => null,
        'now' => new DateTimeImmutable('2030-01-07 09:00:00', $utc), 'deadline' => new DateTimeImmutable('2030-01-11 17:00:00', $utc),
        'skills' => [
            ['skill_id' => 10, 'is_mandatory' => true], ['skill_id' => 11, 'is_mandatory' => false],
            ['skill_id' => 12, 'is_mandatory' => false], ['skill_id' => 13, 'is_mandatory' => false],
        ],
    ],
    'weights' => ['category' => 30, 'skills' => 40, 'availability' => 20, 'budget' => 10],
    'providers' => [
        $provider(1),                                                                   // all skills, within budget
        $provider(2, ['skill_ids' => [10, 11, 12], 'min_rate' => '450.00']),            // 3 of 4 skills
        $provider(3, ['skill_ids' => [10], 'min_rate' => '300.00']),                    // 1 of 4 skills
        $provider(4, ['min_rate' => '800.00']),                                         // all skills, OVER_BUDGET
        $provider(5, ['skill_ids' => [11, 12, 13]]),                                    // lacks mandatory skill 10
        $provider(6, ['category_ids' => [2]]),                                          // wrong category
        $provider(7, ['status' => 'suspended']),                                        // suspended
        $provider(8, ['availability' => []]),                                           // no declared availability
        $provider(9, ['blocked_client_ids' => [900]]),                                  // blocked by the client
    ],
    'expected_ranking' => [
        ['provider_id' => 1, 'rank' => 1, 'final_score' => 100.0, 'skills_score' => 100.0, 'budget_status' => 'WITHIN_BUDGET'],
        ['provider_id' => 4, 'rank' => 2, 'final_score' => 90.0, 'skills_score' => 100.0, 'budget_status' => 'OVER_BUDGET'],
        ['provider_id' => 2, 'rank' => 3, 'final_score' => 90.0, 'skills_score' => 75.0, 'budget_status' => 'WITHIN_BUDGET'],
        ['provider_id' => 3, 'rank' => 4, 'final_score' => 70.0, 'skills_score' => 25.0, 'budget_status' => 'WITHIN_BUDGET'],
    ],
    'expected_excluded' => [
        5 => ['MISSING_MANDATORY_SKILL'], 6 => ['CATEGORY_MISMATCH'], 7 => ['ACCOUNT_NOT_ACTIVE'],
        8 => ['NO_AVAILABILITY_DECLARED'], 9 => ['BLOCKED_BY_CLIENT'],
    ],
];
