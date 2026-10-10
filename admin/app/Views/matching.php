<form class="card filters" method="get" action="<?= $e($url('/matching')) ?>"><div><label for="q">Search request title or ID</label><input id="q" name="q" value="<?= $e($q) ?>"></div><button class="btn" type="submit">Search</button></form>
<div class="card"><table><thead><tr><th>Result</th><th>Request</th><th>Algorithm</th><th>Config</th><th>Weights (cat/skills/avail/budget)</th><th>Evaluated</th><th>Eligible</th><th>Run at</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="8" class="muted">No matching records.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): $w = json_decode((string)$r['weights_json'], true) ?: []; ?>
<tr><td><a href="<?= $e($url('/matching/' . $r['id'])) ?>">#<?= $e($r['id']) ?></a></td><td><a href="<?= $e($url('/requests/' . $r['request_id'])) ?>">#<?= $e($r['request_id']) ?></a> <?= $e($r['title']) ?></td><td><?= $e($r['algorithm_version']) ?></td><td>v<?= $e($r['config_version']) ?></td>
<td><?= $e(($w['category'] ?? '?') . ' / ' . ($w['skills'] ?? '?') . ' / ' . ($w['availability'] ?? '?') . ' / ' . ($w['budget'] ?? '?')) ?></td><td><?= $e($r['candidates_evaluated']) ?></td><td><?= $e($r['eligible_count']) ?></td><td><?= $e($dt($r['created_at'])) ?></td></tr>
<?php endforeach; ?></tbody></table><?= $pagerHtml ?></div>
