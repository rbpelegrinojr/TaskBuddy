<?php
declare(strict_types=1);

namespace TaskBuddy\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Pure, deterministic rule-based matching engine (no database, no randomness, no I/O).
 *
 * Scores are computed as integer basis points (0..10000 == 0.00..100.00) so that results are
 * exactly reproducible; rounding is half-up and happens once per component and once for the total.
 *
 * MATCH_SCORE = (w_category*CATEGORY + w_skills*SKILLS + w_availability*AVAILABILITY + w_budget*BUDGET) / 100
 *
 * @phpstan-type Weights array{category:int,skills:int,availability:int,budget:int}
 */
final class MatchingEngine
{
    public const ALGORITHM_VERSION = '1.0.0';
    public const WEIGHT_KEYS = ['category', 'skills', 'availability', 'budget'];

    public const R_NOT_ACTIVE = 'ACCOUNT_NOT_ACTIVE';
    public const R_PROFILE_INCOMPLETE = 'PROFILE_INCOMPLETE';
    public const R_CATEGORY = 'CATEGORY_MISMATCH';
    public const R_MANDATORY_SKILL = 'MISSING_MANDATORY_SKILL';
    public const R_NO_AVAILABILITY = 'NO_AVAILABILITY_DECLARED';
    public const R_NOT_AVAILABLE = 'NOT_AVAILABLE_BEFORE_DEADLINE';
    public const R_RESTRICTED = 'PROVIDER_RESTRICTED';
    public const R_BLOCKED = 'BLOCKED_BY_CLIENT';
    public const R_CONFLICT = 'CONFLICTING_ASSIGNMENT';

    /** @param array<string,mixed> $weights @return array{category:int,skills:int,availability:int,budget:int} */
    public static function validateWeights(array $weights): array
    {
        $out = [];
        $errors = [];
        foreach (self::WEIGHT_KEYS as $k) {
            $w = $weights[$k] ?? null;
            if (is_string($w) && preg_match('/^\d+$/', $w)) {
                $w = (int)$w;
            }
            if (!is_int($w)) {
                $errors[] = "Weight '$k' must be an integer.";
            } elseif ($w < 0 || $w > 100) {
                $errors[] = "Weight '$k' must be between 0 and 100.";
            } else {
                $out[$k] = $w;
            }
        }
        if (!$errors && array_sum($out) !== 100) {
            $errors[] = 'Weights must total exactly 100 (got ' . array_sum($out) . ').';
        }
        if ($errors) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $request see MatchingService::loadRequest()
     * @param array<int,array<string,mixed>> $providers see MatchingService::loadProviders()
     * @param array<string,int> $weights
     * @return array{eligible:array<int,array<string,mixed>>,excluded:array<int,array<string,mixed>>,evaluated:int,exclusion_summary:array<string,int>}
     */
    public function rank(array $request, array $providers, array $weights, string $timezone = 'UTC'): array
    {
        $weights = self::validateWeights($weights);
        $eligible = [];
        $excluded = [];
        $summary = [];

        foreach ($providers as $p) {
            $reasons = $this->eligibilityReasons($request, $p, $timezone);
            if ($reasons) {
                foreach ($reasons as $r) {
                    $summary[$r] = ($summary[$r] ?? 0) + 1;
                }
                $excluded[] = ['provider_id' => (int)$p['id'], 'reasons' => $reasons];
                continue;
            }
            $eligible[] = $this->score($request, $p, $weights, $timezone);
        }

        usort($eligible, static fn(array $a, array $b) =>
            [$b['final_score_bp'], $b['skills_score_bp'], $a['provider_id']] <=> [$a['final_score_bp'], $a['skills_score_bp'], $b['provider_id']]);
        foreach ($eligible as $i => &$row) {
            $row['rank'] = $i + 1;
        }
        unset($row);
        ksort($summary);

        return ['eligible' => $eligible, 'excluded' => $excluded, 'evaluated' => count($providers), 'exclusion_summary' => $summary];
    }

    /**
     * Mandatory eligibility rules 1-7. Returns every failed rule code (empty list == eligible).
     * @param array<string,mixed> $req @param array<string,mixed> $p @return string[]
     */
    public function eligibilityReasons(array $req, array $p, string $timezone = 'UTC'): array
    {
        $r = [];
        if (($p['status'] ?? '') !== 'active') {
            $r[] = self::R_NOT_ACTIVE;                                                   // Rule 1
        }
        if (empty($p['profile_complete']) || !isset($p['min_rate'])) {
            $r[] = self::R_PROFILE_INCOMPLETE;                                           // Rule 2
        }
        if (!in_array((int)$req['category_id'], array_map('intval', $p['category_ids'] ?? []), true)) {
            $r[] = self::R_CATEGORY;                                                     // Rule 3
        }
        $have = array_map('intval', $p['skill_ids'] ?? []);
        foreach ($req['skills'] as $s) {                                                 // Rule 4
            if (!empty($s['is_mandatory']) && !in_array((int)$s['skill_id'], $have, true)) {
                $r[] = self::R_MANDATORY_SKILL;
                break;
            }
        }
        if (empty($p['availability'])) {                                                 // Rule 5
            $r[] = self::R_NO_AVAILABILITY;                                              // never assume "available"
        } elseif ($this->availableHours($req, $p['availability'], $timezone) <= 0.0
            || (isset($req['estimated_hours']) && $req['estimated_hours'] !== null
                && $this->availableHours($req, $p['availability'], $timezone) < (float)$req['estimated_hours'])) {
            $r[] = self::R_NOT_AVAILABLE;
        }
        if (!empty($p['restricted'])) {                                                  // Rule 6
            $r[] = self::R_RESTRICTED;
        }
        if (in_array((int)$req['client_id'], array_map('intval', $p['blocked_client_ids'] ?? []), true)) {
            $r[] = self::R_BLOCKED;
        }
        if ($this->hasConflict($req, $p['commitments'] ?? [])) {                         // Rule 7
            $r[] = self::R_CONFLICT;
        }
        return array_values(array_unique($r));
    }

    /**
     * Total hours of declared weekly availability inside [now, deadline].
     * @param array<string,mixed> $req @param array<int,array<string,mixed>> $slots
     */
    public function availableHours(array $req, array $slots, string $timezone = 'UTC'): float
    {
        $tz = new DateTimeZone($timezone);
        /** @var DateTimeImmutable $now */
        $now = $req['now'];
        /** @var DateTimeImmutable $deadline */
        $deadline = $req['deadline'];
        if ($deadline <= $now) {
            return 0.0;
        }
        $from = $now->setTimezone($tz);
        $to = $deadline->setTimezone($tz);
        $seconds = 0;
        $day = $from->setTime(0, 0);
        for ($i = 0; $day <= $to && $i < 400; $i++, $day = $day->modify('+1 day')) {
            $dow = (int)$day->format('w');
            foreach ($slots as $s) {
                if ((int)$s['day_of_week'] !== $dow) {
                    continue;
                }
                [$sh, $sm] = array_map('intval', explode(':', $s['start_time']));
                [$eh, $em] = array_map('intval', explode(':', $s['end_time']));
                $start = max($day->setTime($sh, $sm), $from);
                $end = min($day->setTime($eh, $em), $to);
                if ($end > $start) {
                    $seconds += $end->getTimestamp() - $start->getTimestamp();
                }
            }
        }
        return round($seconds / 3600, 2);
    }

    /** @param array<string,mixed> $req @param array<int,array<string,mixed>> $commitments */
    private function hasConflict(array $req, array $commitments): bool
    {
        foreach ($commitments as $c) {
            $exclusive = !empty($req['requires_exclusive']) || !empty($c['exclusive']);
            if ($exclusive && $c['start'] < $req['deadline'] && $c['end'] > $req['now']) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $req @param array<string,mixed> $p @param array<string,int> $w
     * @return array<string,mixed>
     */
    private function score(array $req, array $p, array $w, string $timezone): array
    {
        $category = 10000; // constant for all eligible providers: wrong categories are already excluded (Rule 3)

        $total = count($req['skills']);
        $have = array_map('intval', $p['skill_ids'] ?? []);
        $matched = [];
        $missingOptional = [];
        foreach ($req['skills'] as $s) {
            if (in_array((int)$s['skill_id'], $have, true)) {
                $matched[] = (int)$s['skill_id'];
            } elseif (empty($s['is_mandatory'])) {
                $missingOptional[] = (int)$s['skill_id'];
            }
        }
        sort($matched);
        sort($missingOptional);
        $skills = $total === 0 ? 10000 : intdiv(count($matched) * 20000 + $total, 2 * $total); // half-up

        $availability = 10000; // binary in v1.0.0: unavailable providers were excluded by Rule 5
        $hours = $this->availableHours($req, $p['availability'], $timezone);

        $rateCents = self::cents((string)$p['min_rate']);
        $compCents = self::cents((string)$req['compensation']);
        $withinBudget = $rateCents <= $compCents;
        $budget = $withinBudget ? 10000 : 0;

        $final = intdiv($w['category'] * $category + $w['skills'] * $skills + $w['availability'] * $availability + $w['budget'] * $budget + 50, 100);

        $hoursText = rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');
        $summary = sprintf(
            'Matched service category%s. Provider is available before the deadline (%s hour(s) of declared availability). %s',
            $total === 0 ? ' (no specific skills were requested)' : sprintf(' and %d of %d requested skill%s', count($matched), $total, $total === 1 ? '' : 's'),
            $hoursText,
            $withinBudget ? "The provider's minimum rate is within the proposed compensation." : "The provider's minimum rate exceeds the proposed compensation (OVER_BUDGET)."
        );

        return [
            'provider_id' => (int)$p['id'],
            'final_score_bp' => $final,
            'category_score_bp' => $category,
            'skills_score_bp' => $skills,
            'availability_score_bp' => $availability,
            'budget_score_bp' => $budget,
            'final_score' => $final / 100.0,
            'category_score' => $category / 100.0,
            'skills_score' => $skills / 100.0,
            'availability_score' => $availability / 100.0,
            'budget_score' => $budget / 100.0,
            'budget_status' => $withinBudget ? 'WITHIN_BUDGET' : 'OVER_BUDGET',
            'explanation' => [
                'algorithm_version' => self::ALGORITHM_VERSION,
                'final_score' => $final / 100.0,
                'weights' => $w,
                'category' => ['matched' => true, 'score' => 100.0],
                'skills' => [
                    'score' => $skills / 100.0,
                    'required_total' => $total,
                    'matched_count' => count($matched),
                    'matched_skill_ids' => $matched,
                    'missing_nonmandatory_skill_ids' => $missingOptional,
                ],
                'availability' => ['available' => true, 'score' => 100.0, 'available_hours_before_deadline' => $hours],
                'budget' => [
                    'status' => $withinBudget ? 'WITHIN_BUDGET' : 'OVER_BUDGET',
                    'score' => $budget / 100.0,
                    'provider_min_rate' => number_format($rateCents / 100, 2, '.', ''),
                    'proposed_compensation' => number_format($compCents / 100, 2, '.', ''),
                ],
                'summary' => $summary,
            ],
        ];
    }

    private static function cents(string $amount): int
    {
        return (int)round(((float)$amount) * 100);
    }
}
