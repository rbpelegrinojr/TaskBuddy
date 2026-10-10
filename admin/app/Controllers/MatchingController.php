<?php
declare(strict_types=1);

namespace TaskBuddy\Admin\Controllers;

use TaskBuddy\Admin\Http\Response;
use TaskBuddy\Admin\Support\Pager;
use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Database;

final class MatchingController extends Controller
{
    public function index(): Response
    {
        $r = $this->ctx->request;
        $q = $r->queryStr('q');
        $p = [];
        $w = '1=1';
        if ($q !== '') {
            $w = '(sr.title LIKE ? OR sr.id = ?)';
            $p = [$this->like($q), ctype_digit($q) ? (int)$q : 0];
        }
        $from = "FROM matching_results m JOIN service_requests sr ON sr.id = m.request_id JOIN matching_configurations cfg ON cfg.id = m.configuration_id WHERE $w";
        $pager = new Pager((int)Database::value("SELECT COUNT(*) $from", $p), $r->queryInt('page'));
        $rows = Database::all("SELECT m.id, m.request_id, sr.title, m.algorithm_version, cfg.version AS config_version, m.weights_json, m.candidates_evaluated, m.eligible_count, m.created_at $from ORDER BY m.id DESC LIMIT {$pager->perPage} OFFSET {$pager->offset}", $p);
        return $this->view('matching', 'Provider matching records', ['rows' => $rows, 'q' => $q, 'pagerHtml' => $this->pagerHtml($pager, '/matching', array_filter(['q' => $q]))]);
    }

    /** @param array<string,string> $params */
    public function show(array $params): Response
    {
        $id = (int)$params['id'];
        $m = Database::one('SELECT m.*, sr.title, cfg.version AS config_version FROM matching_results m JOIN service_requests sr ON sr.id = m.request_id JOIN matching_configurations cfg ON cfg.id = m.configuration_id WHERE m.id = ?', [$id]);
        if ($m === null) {
            throw ApiException::notFound('Matching result');
        }
        $details = Database::all('SELECT d.*, u.full_name FROM matching_result_details d JOIN users u ON u.id = d.provider_id WHERE d.result_id = ? ORDER BY d.rank_position', [$id]);
        foreach ($details as &$d) {
            $x = json_decode((string)$d['explanation_json'], true);
            $d['summary'] = is_array($x) ? (string)($x['summary'] ?? '') : '';
        }
        unset($d);
        return $this->view('matching_show', 'Matching result #' . $id, [
            'm' => $m, 'details' => $details,
            'weights' => json_decode((string)$m['weights_json'], true) ?: [],
            'exclusions' => json_decode((string)$m['exclusion_summary_json'], true) ?: [],
        ]);
    }
}
