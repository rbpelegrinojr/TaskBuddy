<?php
declare(strict_types=1);

namespace TaskBuddy\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Clock;
use TaskBuddy\Support\Database;
use TaskBuddy\Support\Env;

/** Loads data for the pure MatchingEngine, persists results immutably and serves them back. */
final class MatchingService
{
    private const ACTIVE_STATUSES = "'IN_PROGRESS','SUBMITTED','UNDER_REVIEW','REVISION_REQUESTED','DISPUTED'";

    public function __construct(private readonly MatchingEngine $engine = new MatchingEngine())
    {
    }

    /** @return array{id:int,version:int,algorithm_version:string,weights:array<string,int>,note:?string,created_at:string} */
    public function activeConfiguration(): array
    {
        $c = Database::one('SELECT * FROM matching_configurations WHERE is_active = 1 ORDER BY version DESC LIMIT 1');
        if ($c === null) {
            throw new ApiException(500, 'MATCHING_NOT_CONFIGURED', 'No active matching configuration exists.');
        }
        return [
            'id' => (int)$c['id'], 'version' => (int)$c['version'], 'algorithm_version' => $c['algorithm_version'],
            'weights' => ['category' => (int)$c['weight_category'], 'skills' => (int)$c['weight_skills'],
                'availability' => (int)$c['weight_availability'], 'budget' => (int)$c['weight_budget']],
            'note' => $c['note'], 'created_at' => Clock::iso($c['created_at']),
        ];
    }

    /**
     * Administrator-only: weights are immutable per version. A change creates a new version and
     * never rewrites persisted matching results.
     * @param array<string,mixed> $weights @return array<string,mixed>
     */
    public function updateWeights(int $adminId, array $weights, ?string $note): array
    {
        try {
            $w = MatchingEngine::validateWeights($weights);
        } catch (InvalidArgumentException $e) {
            throw ApiException::validation(['weights' => $e->getMessage()]);
        }
        return Database::transaction(function () use ($adminId, $w, $note): array {
            $old = $this->activeConfiguration();
            $version = (int)Database::value('SELECT COALESCE(MAX(version),0) + 1 FROM matching_configurations FOR UPDATE');
            Database::run('UPDATE matching_configurations SET is_active = 0 WHERE is_active = 1');
            Database::insert(
                'INSERT INTO matching_configurations (version, algorithm_version, weight_category, weight_skills, weight_availability, weight_budget, is_active, note, created_by) VALUES (?,?,?,?,?,?,1,?,?)',
                [$version, MatchingEngine::ALGORITHM_VERSION, $w['category'], $w['skills'], $w['availability'], $w['budget'], $note, $adminId]
            );
            AuditLogger::log($adminId, 'admin', 'MATCHING_WEIGHTS_CHANGED', 'matching_configuration', $version, ['old' => $old['weights'], 'new' => $w]);
            return $this->activeConfiguration();
        });
    }

    /** @return array<string,mixed> */
    public function loadRequest(int $requestId): array
    {
        $r = Database::one('SELECT * FROM service_requests WHERE id = ?', [$requestId]);
        if ($r === null) {
            throw ApiException::notFound('Request');
        }
        $skills = Database::all('SELECT skill_id, is_mandatory FROM service_request_skills WHERE request_id = ? ORDER BY skill_id', [$requestId]);
        return [
            'id' => (int)$r['id'], 'client_id' => (int)$r['client_id'], 'category_id' => (int)$r['category_id'],
            'compensation' => $r['compensation'], 'requires_exclusive' => (bool)$r['requires_exclusive'],
            'estimated_hours' => $r['estimated_hours'] === null ? null : (float)$r['estimated_hours'],
            'deadline' => new DateTimeImmutable($r['deadline'], new DateTimeZone('UTC')),
            'now' => Clock::now(),
            'skills' => array_map(static fn($s) => ['skill_id' => (int)$s['skill_id'], 'is_mandatory' => (bool)$s['is_mandatory']], $skills),
        ];
    }

    /** @param array<string,mixed> $request @return array<int,array<string,mixed>> */
    public function loadProviders(array $request): array
    {
        $rows = Database::all(
            "SELECT u.id, u.status, p.headline, p.experience_summary, p.min_rate
             FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id WHERE u.role = 'provider' ORDER BY u.id"
        );
        $group = static function (string $sql, string $key, string $val): array {
            $out = [];
            foreach (Database::all($sql) as $r) {
                $out[(int)$r[$key]][] = $val === 'slot' ? $r : (int)$r[$val];
            }
            return $out;
        };
        $cats = $group('SELECT provider_id, category_id FROM provider_categories', 'provider_id', 'category_id');
        $skills = $group('SELECT provider_id, skill_id FROM provider_skills', 'provider_id', 'skill_id');
        $slots = $group('SELECT provider_id, day_of_week, TIME_FORMAT(start_time,"%H:%i") AS start_time, TIME_FORMAT(end_time,"%H:%i") AS end_time FROM provider_availability', 'provider_id', 'slot');
        $blocks = [];
        foreach (Database::all('SELECT blocker_id, blocked_id FROM user_blocks WHERE blocker_id = ?', [$request['client_id']]) as $b) {
            $blocks[(int)$b['blocked_id']] = true;
        }
        $commit = [];
        foreach (Database::all(
            'SELECT a.provider_id, a.assigned_at, r.deadline, r.requires_exclusive FROM service_assignments a JOIN service_requests r ON r.id = a.request_id
             WHERE r.status IN (' . self::ACTIVE_STATUSES . ') AND r.id <> ?',
            [$request['id']]
        ) as $c) {
            $commit[(int)$c['provider_id']][] = [
                'start' => new DateTimeImmutable($c['assigned_at'], new DateTimeZone('UTC')),
                'end' => new DateTimeImmutable($c['deadline'], new DateTimeZone('UTC')),
                'exclusive' => (bool)$c['requires_exclusive'],
            ];
        }
        $out = [];
        foreach ($rows as $r) {
            $id = (int)$r['id'];
            $categoryIds = $cats[$id] ?? [];
            $out[] = [
                'id' => $id, 'status' => $r['status'],
                'profile_complete' => ProfileService::missingProfileFields($r, count($categoryIds)) === [],
                'min_rate' => $r['min_rate'], 'category_ids' => $categoryIds, 'skill_ids' => $skills[$id] ?? [],
                'availability' => $slots[$id] ?? [], 'blocked_client_ids' => isset($blocks[$id]) ? [(int)$request['client_id']] : [],
                'commitments' => $commit[$id] ?? [], 'restricted' => false,
            ];
        }
        return $out;
    }

    /** Run matching for an OPEN request and persist the outcome. Returns the new result id. */
    public function run(int $requestId, ?int $triggeredBy): int
    {
        return Database::transaction(function () use ($requestId, $triggeredBy): int {
            $status = Database::value('SELECT status FROM service_requests WHERE id = ? FOR UPDATE', [$requestId]);
            if ($status === null) {
                throw ApiException::notFound('Request');
            }
            if ($status !== 'OPEN') {
                throw ApiException::conflict('INVALID_STATE', 'Matching can only be run while the request is OPEN.');
            }
            $config = $this->activeConfiguration();
            $request = $this->loadRequest($requestId);
            $result = $this->engine->rank($request, $this->loadProviders($request), $config['weights'], Env::get('APP_TIMEZONE', 'UTC') ?? 'UTC');

            $resultId = Database::insert(
                'INSERT INTO matching_results (request_id, configuration_id, algorithm_version, weights_json, candidates_evaluated, eligible_count, exclusion_summary_json, triggered_by) VALUES (?,?,?,?,?,?,?,?)',
                [$requestId, $config['id'], MatchingEngine::ALGORITHM_VERSION, json_encode($config['weights']), $result['evaluated'], count($result['eligible']),
                    json_encode((object)$result['exclusion_summary']), $triggeredBy]
            );
            foreach ($result['eligible'] as $e) {
                Database::insert(
                    'INSERT INTO matching_result_details (result_id, provider_id, rank_position, final_score, category_score, skills_score, availability_score, budget_score, budget_status, explanation_json) VALUES (?,?,?,?,?,?,?,?,?,?)',
                    [$resultId, $e['provider_id'], $e['rank'], $e['final_score'], $e['category_score'], $e['skills_score'], $e['availability_score'], $e['budget_score'], $e['budget_status'], json_encode($e['explanation'])]
                );
            }
            AuditLogger::log($triggeredBy, null, 'MATCHING_EXECUTED', 'service_request', $requestId, [
                'result_id' => $resultId, 'eligible' => count($result['eligible']), 'config_version' => $config['version'],
            ]);
            return $resultId;
        });
    }

    /**
     * Fresh (non-cached) eligibility check of one provider against one request. Empty list == eligible.
     * @return string[]
     */
    public function eligibilityFor(int $requestId, int $providerId): array
    {
        $request = $this->loadRequest($requestId);
        foreach ($this->loadProviders($request) as $p) {
            if ((int)$p['id'] === $providerId) {
                return $this->engine->eligibilityReasons($request, $p, Env::get('APP_TIMEZONE', 'UTC') ?? 'UTC');
            }
        }
        return [MatchingEngine::R_NOT_ACTIVE];
    }

    public function latestResultId(int $requestId): ?int
    {
        $v = Database::value('SELECT MAX(id) FROM matching_results WHERE request_id = ?', [$requestId]);
        return $v === null ? null : (int)$v;
    }

    /** True if the provider is an eligible candidate in the latest persisted result (server-side authority). */
    public function isRecommended(int $requestId, int $providerId): ?int
    {
        $resultId = $this->latestResultId($requestId);
        if ($resultId === null) {
            return null;
        }
        $ok = Database::value('SELECT id FROM matching_result_details WHERE result_id = ? AND provider_id = ?', [$resultId, $providerId]);
        return $ok === null ? null : $resultId;
    }

    /** @return array{items:array<int,array<string,mixed>>,meta:array<string,mixed>} */
    public function results(int $requestId, int $page, int $perPage, ?int $resultId = null): array
    {
        $resultId ??= $this->latestResultId($requestId);
        if ($resultId === null) {
            return ['items' => [], 'meta' => ['result_id' => null, 'no_match' => true, 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'has_more' => false,
                'message' => 'Matching has not been run for this request yet.']];
        }
        $res = Database::one('SELECT * FROM matching_results WHERE id = ? AND request_id = ?', [$resultId, $requestId]);
        if ($res === null) {
            throw ApiException::notFound('Matching result');
        }
        $rows = Database::all(
            'SELECT d.*, u.full_name, p.headline, p.min_rate, p.years_experience FROM matching_result_details d
             JOIN users u ON u.id = d.provider_id LEFT JOIN user_profiles p ON p.user_id = d.provider_id
             WHERE d.result_id = ? ORDER BY d.rank_position LIMIT ' . (int)$perPage . ' OFFSET ' . (($page - 1) * $perPage),
            [$resultId]
        );
        $names = [];
        foreach (Database::all('SELECT id, name FROM skills') as $s) {
            $names[(int)$s['id']] = $s['name'];
        }
        $label = static fn(array $ids) => array_map(static fn($i) => ['id' => $i, 'name' => $names[$i] ?? 'Unknown'], $ids);

        $items = array_map(function (array $d) use ($label): array {
            $x = json_decode($d['explanation_json'], true);
            $x['skills']['matched_skills'] = $label($x['skills']['matched_skill_ids'] ?? []);
            $x['skills']['missing_nonmandatory_skills'] = $label($x['skills']['missing_nonmandatory_skill_ids'] ?? []);
            return [
                'rank' => (int)$d['rank_position'],
                'provider' => ['id' => (int)$d['provider_id'], 'full_name' => $d['full_name'], 'headline' => $d['headline'],
                    'min_rate' => $d['min_rate'], 'years_experience' => $d['years_experience'] === null ? null : (int)$d['years_experience']],
                'final_score' => (float)$d['final_score'],
                'scores' => ['category' => (float)$d['category_score'], 'skills' => (float)$d['skills_score'],
                    'availability' => (float)$d['availability_score'], 'budget' => (float)$d['budget_score']],
                'budget_status' => $d['budget_status'],
                'explanation' => $x,
            ];
        }, $rows);

        $total = (int)$res['eligible_count'];
        return ['items' => $items, 'meta' => [
            'result_id' => (int)$res['id'], 'algorithm_version' => $res['algorithm_version'], 'weights' => json_decode($res['weights_json'], true),
            'candidates_evaluated' => (int)$res['candidates_evaluated'], 'eligible_count' => $total,
            'exclusion_summary' => (object)json_decode($res['exclusion_summary_json'], true),
            'generated_at' => Clock::iso($res['created_at']), 'page' => $page, 'per_page' => $perPage, 'total' => $total,
            'has_more' => $page * $perPage < $total, 'no_match' => $total === 0,
            'message' => $total === 0 ? 'No providers currently qualify for this request.' : null,
        ]];
    }
}
