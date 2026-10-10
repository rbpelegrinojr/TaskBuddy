<section class="card"><h2>Active weights <span class="badge info">v<?= $e($active['version']) ?> · algorithm <?= $e($active['algorithm_version']) ?></span></h2>
<p class="muted">The four weights must be whole numbers that add up to exactly 100. Saving creates a <strong>new version</strong>; previous versions and every stored matching result keep the weights they were computed with.</p>
<form method="post" action="<?= $e($url('/matching-config')) ?>"><?= $csrf() ?>
<div class="filters">
<?php foreach (['category' => 'Category match', 'skills' => 'Skills match', 'availability' => 'Availability', 'budget' => 'Budget fit'] as $k => $label): ?>
<div><label for="w_<?= $e($k) ?>"><?= $e($label) ?></label><input id="w_<?= $e($k) ?>" name="w_<?= $e($k) ?>" type="number" min="0" max="100" step="1" required value="<?= $e($active['weights'][$k]) ?>"></div>
<?php endforeach; ?></div>
<label for="note">Reason for the change (recorded)</label><input id="note" name="note" maxlength="255">
<button class="btn" type="submit">Save as new version</button></form></section>
<section class="card"><h2>Version history</h2><table><thead><tr><th>Version</th><th>Algorithm</th><th>Category</th><th>Skills</th><th>Availability</th><th>Budget</th><th>Status</th><th>Changed by</th><th>Note</th><th>Created</th></tr></thead><tbody>
<?php foreach ($history as $h): ?><tr><td>v<?= $e($h['version']) ?></td><td><?= $e($h['algorithm_version']) ?></td><td><?= $e($h['weight_category']) ?></td><td><?= $e($h['weight_skills']) ?></td><td><?= $e($h['weight_availability']) ?></td><td><?= $e($h['weight_budget']) ?></td><td><?= (int)$h['is_active'] === 1 ? '<span class="badge ok">active</span>' : '<span class="badge muted">superseded</span>' ?></td><td><?= $e($h['full_name'] ?? 'system') ?></td><td><?= $e($h['note']) ?></td><td><?= $e($dt($h['created_at'])) ?></td></tr><?php endforeach; ?>
</tbody></table></section>
