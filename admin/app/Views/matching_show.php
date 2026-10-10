<p><a href="<?= $e($url('/matching')) ?>">← All matching records</a> · <a href="<?= $e($url('/requests/' . $m['request_id'])) ?>">Request #<?= $e($m['request_id']) ?>: <?= $e($m['title']) ?></a></p>
<section class="card"><dl class="kv"><dt>Algorithm</dt><dd><?= $e($m['algorithm_version']) ?> (configuration v<?= $e($m['config_version']) ?>)</dd>
<dt>Weights used</dt><dd>category <?= $e($weights['category'] ?? '?') ?> · skills <?= $e($weights['skills'] ?? '?') ?> · availability <?= $e($weights['availability'] ?? '?') ?> · budget <?= $e($weights['budget'] ?? '?') ?></dd>
<dt>Providers evaluated</dt><dd><?= $e($m['candidates_evaluated']) ?></dd><dt>Eligible</dt><dd><?= $e($m['eligible_count']) ?></dd>
<dt>Excluded (reason: count)</dt><dd><?php if (!$exclusions): ?>none<?php endif; ?><?php foreach ($exclusions as $reason => $count): ?><span class="badge muted"><?= $e($reason) ?>: <?= $e($count) ?></span> <?php endforeach; ?></dd>
<dt>Run at</dt><dd><?= $e($dt($m['created_at'])) ?></dd></dl>
<p class="muted">Stored results are immutable: later weight changes never alter this record.</p></section>
<div class="card"><table><thead><tr><th>Rank</th><th>Provider</th><th>Final</th><th>Category</th><th>Skills</th><th>Availability</th><th>Budget</th><th>Budget status</th><th>Explanation</th></tr></thead><tbody>
<?php if (!$details): ?><tr><td colspan="9" class="muted">No eligible provider was found for this run.</td></tr><?php endif; ?>
<?php foreach ($details as $d): ?>
<tr><td><?= $e($d['rank_position']) ?></td><td><?= $e($d['full_name']) ?></td><td><strong><?= $e($d['final_score']) ?></strong></td><td><?= $e($d['category_score']) ?></td><td><?= $e($d['skills_score']) ?></td><td><?= $e($d['availability_score']) ?></td><td><?= $e($d['budget_score']) ?></td><td><?= $badge($d['budget_status']) ?></td><td><?= $e($d['summary']) ?></td></tr>
<?php endforeach; ?></tbody></table></div>
