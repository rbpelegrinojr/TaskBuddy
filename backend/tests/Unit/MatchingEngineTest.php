<?php
declare(strict_types=1);

namespace TaskBuddy\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TaskBuddy\Services\MatchingEngine;

final class MatchingEngineTest extends TestCase
{
    private const W = ['category' => 30, 'skills' => 40, 'availability' => 20, 'budget' => 10];

    private MatchingEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new MatchingEngine();
    }

    private function dt(string $s): DateTimeImmutable
    {
        return new DateTimeImmutable($s, new DateTimeZone('UTC'));
    }

    /** Monday 2030-01-07 09:00 -> Friday 2030-01-11 17:00 */
    private function request(array $over = []): array
    {
        return $over + [
            'id' => 1, 'client_id' => 900, 'category_id' => 1, 'compensation' => '500.00', 'requires_exclusive' => false, 'estimated_hours' => null,
            'now' => $this->dt('2030-01-07 09:00:00'), 'deadline' => $this->dt('2030-01-11 17:00:00'),
            'skills' => [['skill_id' => 10, 'is_mandatory' => true], ['skill_id' => 11, 'is_mandatory' => false]],
        ];
    }

    private function provider(int $id = 1, array $over = []): array
    {
        $weekdays = array_map(static fn($d) => ['day_of_week' => $d, 'start_time' => '09:00', 'end_time' => '17:00'], [1, 2, 3, 4, 5]);
        return $over + [
            'id' => $id, 'status' => 'active', 'profile_complete' => true, 'min_rate' => '400.00', 'category_ids' => [1], 'skill_ids' => [10, 11],
            'availability' => $weekdays, 'blocked_client_ids' => [], 'commitments' => [], 'restricted' => false,
        ];
    }

    private function rank(array $providers, ?array $request = null, array $weights = self::W): array
    {
        return $this->engine->rank($request ?? $this->request(), $providers, $weights);
    }

    public function testExactCategoryMatchIsEligibleWithConstantCategoryScore(): void
    {
        $r = $this->rank([$this->provider()]);
        $this->assertCount(1, $r['eligible']);
        $this->assertSame(100.0, $r['eligible'][0]['category_score']);
        $this->assertTrue($r['eligible'][0]['explanation']['category']['matched']);
    }

    public function testWrongCategoryIsExcluded(): void
    {
        $r = $this->rank([$this->provider(1, ['category_ids' => [2]])]);
        $this->assertSame([], $r['eligible']);
        $this->assertSame([MatchingEngine::R_CATEGORY], $r['excluded'][0]['reasons']);
        $this->assertSame([MatchingEngine::R_CATEGORY => 1], $r['exclusion_summary']);
    }

    public function testCompleteSkillMatchScores100(): void
    {
        $e = $this->rank([$this->provider()])['eligible'][0];
        $this->assertSame(100.0, $e['skills_score']);
        $this->assertSame([10, 11], $e['explanation']['skills']['matched_skill_ids']);
        $this->assertSame([], $e['explanation']['skills']['missing_nonmandatory_skill_ids']);
    }

    public function testPartialNonMandatorySkillMatch(): void
    {
        $req = $this->request(['skills' => [['skill_id' => 10, 'is_mandatory' => true], ['skill_id' => 11, 'is_mandatory' => false],
            ['skill_id' => 12, 'is_mandatory' => false], ['skill_id' => 13, 'is_mandatory' => false]]]);
        $e = $this->rank([$this->provider(1, ['skill_ids' => [10, 11, 12]])], $req)['eligible'][0];
        $this->assertSame(75.0, $e['skills_score']);
        $this->assertSame([13], $e['explanation']['skills']['missing_nonmandatory_skill_ids']);
        $this->assertStringContainsString('3 of 4 requested skills', $e['explanation']['summary']);
        // 0.30*100 + 0.40*75 + 0.20*100 + 0.10*100 = 90
        $this->assertSame(90.0, $e['final_score']);
    }

    public function testMissingMandatorySkillIsExcluded(): void
    {
        $r = $this->rank([$this->provider(1, ['skill_ids' => [11]])]);
        $this->assertSame([], $r['eligible']);
        $this->assertSame([MatchingEngine::R_MANDATORY_SKILL], $r['excluded'][0]['reasons']);
    }

    public function testAvailableProviderGetsBinaryAvailabilityScore(): void
    {
        $e = $this->rank([$this->provider()])['eligible'][0];
        $this->assertSame(100.0, $e['availability_score']);
        $this->assertTrue($e['explanation']['availability']['available']);
        $this->assertGreaterThan(0, $e['explanation']['availability']['available_hours_before_deadline']);
        $this->assertSame(40.0, $e['explanation']['availability']['available_hours_before_deadline']); // Mon-Fri, 8 h per day
    }

    public function testUnavailableProviderIsExcluded(): void
    {
        $weekend = [['day_of_week' => 0, 'start_time' => '09:00', 'end_time' => '17:00'], ['day_of_week' => 6, 'start_time' => '09:00', 'end_time' => '17:00']];
        $r = $this->rank([$this->provider(1, ['availability' => $weekend])]);
        $this->assertSame([MatchingEngine::R_NOT_AVAILABLE], $r['excluded'][0]['reasons']);
    }

    public function testMissingAvailabilityInformationIsNeverTreatedAsAvailable(): void
    {
        $r = $this->rank([$this->provider(1, ['availability' => []])]);
        $this->assertSame([], $r['eligible']);
        $this->assertSame([MatchingEngine::R_NO_AVAILABILITY], $r['excluded'][0]['reasons']);
    }

    public function testEstimatedHoursMustFitDeclaredAvailability(): void
    {
        $short = [['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '11:00']];
        $r = $this->rank([$this->provider(1, ['availability' => $short])], $this->request(['estimated_hours' => 10.0]));
        $this->assertSame([MatchingEngine::R_NOT_AVAILABLE], $r['excluded'][0]['reasons']);
        $ok = $this->rank([$this->provider(1, ['availability' => $short])], $this->request(['estimated_hours' => 2.0]));
        $this->assertCount(1, $ok['eligible']);
    }

    public function testConflictingCommitmentExcludesProviderOnlyForExclusiveTasks(): void
    {
        $commit = [['start' => $this->dt('2030-01-05 00:00:00'), 'end' => $this->dt('2030-01-09 00:00:00'), 'exclusive' => false]];
        $p = $this->provider(1, ['commitments' => $commit]);
        $exclusive = $this->rank([$p], $this->request(['requires_exclusive' => true]));
        $this->assertSame([MatchingEngine::R_CONFLICT], $exclusive['excluded'][0]['reasons']);
        $this->assertCount(1, $this->rank([$p])['eligible'], 'non-exclusive task tolerates a non-exclusive commitment');
        // an existing exclusive commitment blocks any new task
        $commit[0]['exclusive'] = true;
        $this->assertSame([], $this->rank([$this->provider(1, ['commitments' => $commit])])['eligible']);
        // a commitment that ended before now never conflicts
        $past = [['start' => $this->dt('2030-01-01 00:00:00'), 'end' => $this->dt('2030-01-06 00:00:00'), 'exclusive' => true]];
        $this->assertCount(1, $this->rank([$this->provider(1, ['commitments' => $past])], $this->request(['requires_exclusive' => true]))['eligible']);
    }

    public function testCompatibleBudget(): void
    {
        $e = $this->rank([$this->provider(1, ['min_rate' => '500.00'])])['eligible'][0];
        $this->assertSame('WITHIN_BUDGET', $e['budget_status']);
        $this->assertSame(100.0, $e['budget_score']);
    }

    public function testOverBudgetProviderIsKeptButScoredZeroAndLabelled(): void
    {
        $r = $this->rank([$this->provider(1, ['min_rate' => '500.01']), $this->provider(2, ['min_rate' => '100.00'])]);
        $this->assertCount(2, $r['eligible']);
        $over = array_values(array_filter($r['eligible'], static fn($e) => $e['provider_id'] === 1))[0];
        $this->assertSame('OVER_BUDGET', $over['budget_status']);
        $this->assertSame(0.0, $over['budget_score']);
        $this->assertSame(90.0, $over['final_score']);
        $this->assertSame(2, $over['rank']);
        $this->assertStringContainsString('OVER_BUDGET', $over['explanation']['summary']);
        $this->assertSame(2, $r['eligible'][0]['provider_id'], 'within-budget provider ranks first at equal skills');
    }

    public function testEmptyProviderDirectory(): void
    {
        $r = $this->rank([]);
        $this->assertSame([], $r['eligible']);
        $this->assertSame([], $r['excluded']);
        $this->assertSame(0, $r['evaluated']);
    }

    public function testSuspendedAndIncompleteProvidersAreExcluded(): void
    {
        $r = $this->rank([$this->provider(1, ['status' => 'suspended']), $this->provider(2, ['profile_complete' => false]), $this->provider(3, ['restricted' => true]),
            $this->provider(4, ['blocked_client_ids' => [900]])]);
        $this->assertSame([], $r['eligible']);
        $reasons = array_column($r['excluded'], 'reasons', 'provider_id');
        $this->assertSame([MatchingEngine::R_NOT_ACTIVE], $reasons[1]);
        $this->assertSame([MatchingEngine::R_PROFILE_INCOMPLETE], $reasons[2]);
        $this->assertSame([MatchingEngine::R_RESTRICTED], $reasons[3]);
        $this->assertSame([MatchingEngine::R_BLOCKED], $reasons[4]);
    }

    public function testEqualScoresAreOrderedByProviderIdAscending(): void
    {
        $r = $this->rank([$this->provider(7), $this->provider(3), $this->provider(5)]);
        $this->assertSame([3, 5, 7], array_column($r['eligible'], 'provider_id'));
        $this->assertSame([1, 2, 3], array_column($r['eligible'], 'rank'));
    }

    public function testRankingOrdersByScoreThenSkillsThenId(): void
    {
        $req = $this->request(['skills' => [['skill_id' => 10, 'is_mandatory' => true], ['skill_id' => 11, 'is_mandatory' => false], ['skill_id' => 12, 'is_mandatory' => false]]]);
        $r = $this->rank([
            $this->provider(1, ['skill_ids' => [10], 'min_rate' => '100.00']),          // 33.33 skills, within budget
            $this->provider(2, ['skill_ids' => [10, 11, 12], 'min_rate' => '900.00']),  // 100 skills, over budget -> 90
            $this->provider(3, ['skill_ids' => [10, 11], 'min_rate' => '100.00']),      // 66.67 skills, within budget -> 86.67
            $this->provider(4, ['skill_ids' => [10, 11, 12], 'min_rate' => '100.00']),  // 100
        ], $req);
        $this->assertSame([4, 2, 3, 1], array_column($r['eligible'], 'provider_id'));
        $this->assertSame([100.0, 90.0, 86.67, 73.33], array_column($r['eligible'], 'final_score'));
    }

    public function testNoRequiredSkillsUsesDocumentedNeutralScore(): void
    {
        $e = $this->rank([$this->provider(1, ['skill_ids' => []])], $this->request(['skills' => []]))['eligible'][0];
        $this->assertSame(100.0, $e['skills_score']);
        $this->assertSame(100.0, $e['final_score']);
        $this->assertStringContainsString('no specific skills', $e['explanation']['summary']);
    }

    public function testRoundingIsHalfUpAndDeterministic(): void
    {
        $req = $this->request(['skills' => [['skill_id' => 10, 'is_mandatory' => false], ['skill_id' => 11, 'is_mandatory' => false], ['skill_id' => 12, 'is_mandatory' => false]]]);
        $e = $this->rank([$this->provider(1, ['skill_ids' => [10], 'min_rate' => '900.00'])], $req)['eligible'][0];
        $this->assertSame(33.33, $e['skills_score']);
        $this->assertSame(63.33, $e['final_score']);  // (30*100 + 40*33.33 + 20*100 + 10*0)/100 = 63.332 -> 63.33
        $two = $this->rank([$this->provider(1, ['skill_ids' => [10, 11], 'min_rate' => '900.00'])], $req)['eligible'][0];
        $this->assertSame(66.67, $two['skills_score']);
        $this->assertSame(76.67, $two['final_score']);
    }

    public function testRepeatedMatchingWithIdenticalInputsIsIdentical(): void
    {
        $providers = [$this->provider(2), $this->provider(1, ['min_rate' => '900.00']), $this->provider(3, ['skill_ids' => [10]])];
        $a = json_encode($this->rank($providers));
        $b = json_encode($this->rank(array_reverse($providers)));
        $this->assertSame($a, json_encode($this->rank($providers)));
        $this->assertSame(json_decode($a, true)['eligible'], json_decode($b, true)['eligible'], 'input order must not influence the ranking');
    }

    public function testWeightChangesAffectScoresWithoutChangingEligibility(): void
    {
        $p = [$this->provider(1, ['min_rate' => '900.00'])];
        $default = $this->rank($p)['eligible'][0]['final_score'];
        $budgetHeavy = $this->rank($p, null, ['category' => 10, 'skills' => 20, 'availability' => 10, 'budget' => 60])['eligible'][0]['final_score'];
        $this->assertSame(90.0, $default);
        $this->assertSame(40.0, $budgetHeavy);
        $this->assertSame(100.0, $this->rank($p, null, ['category' => 0, 'skills' => 0, 'availability' => 100, 'budget' => 0])['eligible'][0]['final_score']);
    }

    /** @dataProvider invalidWeights */
    public function testInvalidWeightsAreRejected(array $weights): void
    {
        $this->expectException(InvalidArgumentException::class);
        MatchingEngine::validateWeights($weights);
    }

    public function invalidWeights(): array
    {
        return [
            'negative' => [['category' => -10, 'skills' => 60, 'availability' => 30, 'budget' => 20]],
            'above 100' => [['category' => 101, 'skills' => 0, 'availability' => 0, 'budget' => -1]],
            'sum below 100' => [['category' => 30, 'skills' => 40, 'availability' => 20, 'budget' => 5]],
            'sum above 100' => [['category' => 30, 'skills' => 40, 'availability' => 20, 'budget' => 11]],
            'missing key' => [['category' => 50, 'skills' => 50]],
            'non-integer' => [['category' => 30.5, 'skills' => 39.5, 'availability' => 20, 'budget' => 10]],
            'string text' => [['category' => 'abc', 'skills' => 40, 'availability' => 20, 'budget' => 10]],
        ];
    }

    public function testValidWeightsAreAccepted(): void
    {
        $this->assertSame(self::W, MatchingEngine::validateWeights(['category' => '30', 'skills' => 40, 'availability' => 20, 'budget' => 10]));
        $this->assertSame(['category' => 100, 'skills' => 0, 'availability' => 0, 'budget' => 0],
            MatchingEngine::validateWeights(['category' => 100, 'skills' => 0, 'availability' => 0, 'budget' => 0]));
    }

    public function testRankPropagatesInvalidWeights(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->rank([$this->provider()], null, ['category' => 50, 'skills' => 50, 'availability' => 50, 'budget' => 0]);
    }

    public function testDocumentedReferenceDataset(): void
    {
        $d = require __DIR__ . '/../fixtures/matching_dataset.php';
        $r = $this->engine->rank($d['request'], $d['providers'], $d['weights']);
        $actual = array_map(static fn($e) => ['provider_id' => $e['provider_id'], 'rank' => $e['rank'], 'final_score' => $e['final_score'],
            'skills_score' => $e['skills_score'], 'budget_status' => $e['budget_status']], $r['eligible']);
        $this->assertSame($d['expected_ranking'], $actual);
        $excluded = array_column($r['excluded'], 'reasons', 'provider_id');
        $this->assertSame($d['expected_excluded'], $excluded);
        $this->assertSame(9, $r['evaluated']);
    }

    public function testAvailabilityIsInterpretedInConfiguredTimezone(): void
    {
        // Provider is available Monday 09:00-17:00 local (Asia/Manila, UTC+8). Window: Mon 2030-01-07 00:00 UTC .. 08:00 UTC == Mon 08:00-16:00 local.
        $slots = [['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00']];
        $req = $this->request(['now' => $this->dt('2030-01-07 00:00:00'), 'deadline' => $this->dt('2030-01-07 08:00:00')]);
        $this->assertSame(7.0, $this->engine->availableHours($req, $slots, 'Asia/Manila'));
        $this->assertSame(0.0, $this->engine->availableHours($req, $slots, 'UTC'));
    }
}
