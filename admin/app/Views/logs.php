<form class="card filters" method="get" action="<?= $e($url('/logs')) ?>">
<div><label for="action">Action</label><select id="action" name="action"><option value="">All</option><?php foreach ($actions as $a): ?><option<?= $filters['action'] === $a ? ' selected' : '' ?>><?= $e($a) ?></option><?php endforeach; ?></select></div>
<div><label for="q">Search actor, entity or IP</label><input id="q" name="q" value="<?= $e($filters['q']) ?>"></div><button class="btn" type="submit">Filter</button></form>
<div class="card"><table><thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="6" class="muted">No log entries.</td></tr><?php endif; ?>
<?php foreach ($rows as $l): ?><tr><td><?= $e($dt($l['created_at'])) ?></td><td><?= $e($l['full_name'] ?? 'system') ?> <span class="muted"><?= $e($l['actor_role']) ?></span></td><td><?= $e($l['action']) ?></td><td><?= $e($l['entity_type']) ?> <?= $l['entity_id'] !== null ? '#' . $e($l['entity_id']) : '' ?></td><td><code><?= $e($l['details_json']) ?></code></td><td><?= $e($l['ip_address']) ?></td></tr><?php endforeach; ?>
</tbody></table><?= $pagerHtml ?></div>
<p class="muted">Logs are append-only. Passwords, tokens and uploaded file contents are never recorded.</p>
