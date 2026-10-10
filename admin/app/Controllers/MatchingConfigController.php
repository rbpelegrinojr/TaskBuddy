<?php
declare(strict_types=1);

namespace TaskBuddy\Admin\Controllers;

use TaskBuddy\Admin\Http\Response;
use TaskBuddy\Services\MatchingService;
use TaskBuddy\Support\Database;

final class MatchingConfigController extends Controller
{
    public function index(): Response
    {
        $history = Database::all('SELECT c.id, c.version, c.algorithm_version, c.weight_category, c.weight_skills, c.weight_availability, c.weight_budget, c.is_active, c.note, c.created_at, u.full_name FROM matching_configurations c LEFT JOIN users u ON u.id = c.created_by ORDER BY c.version DESC');
        return $this->view('matching_config', 'Matching configuration', ['history' => $history, 'active' => (new MatchingService())->activeConfiguration()]);
    }

    public function update(): Response
    {
        $r = $this->ctx->request;
        $w = [];
        foreach (['category', 'skills', 'availability', 'budget'] as $k) {
            $v = $r->postStr('w_' . $k);
            $w[$k] = ctype_digit($v) ? (int)$v : -1;
        }
        $cfg = (new MatchingService())->updateWeights($this->adminId(), $w, mb_substr($r->postStr('note'), 0, 255) ?: null);
        return $this->redirect('/matching-config', 'Created configuration version ' . $cfg['version'] . '. It applies to future matching runs only; existing results are unchanged.');
    }
}
