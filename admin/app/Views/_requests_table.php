<form class="card filters" method="get" action="<?= $e($url($path)) ?>">
  <div><label for="q">Search title, client or ID</label><input id="q" name="q" value="<?= $e($filters['q']) ?>"></div>
  <div><label for="status">Status</label><select id="status" name="status"><option value="">All</option><?php foreach ($statuses as $s): ?><option value="<?= $e($s) ?>"<?= $filters['status'] === $s ? ' selected' : '' ?>><?= $e(str_replace('_', ' ', $s)) ?></option><?php endforeach; ?></select></div>
  <div><label for="category">Category</label><select id="category" name="category"><option value="">All</option><?php foreach ($categories as $c): ?><option value="<?= $e($c['id']) ?>"<?= (string)$filters['category'] === (string)$c['id'] ? ' selected' : '' ?>><?= $e($c['name']) ?></option><?php endforeach; ?></select></div>
  <button class="btn" type="submit">Filter</button>
</form>
<div class="card">
<table><thead><tr><th>ID</th><th>Title</th><th>Category</th><th>Client</th><th>Provider</th><th>Compensation</th><th>Deadline</th><th>Status</th><th>Payment (simulated)</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="9" class="muted">No requests match the filters.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?>
<tr><td><a href="<?= $e($url('/requests/' . $r['id'])) ?>">#<?= $e($r['id']) ?></a></td><td><?= $e($r['title']) ?></td><td><?= $e($r['category']) ?></td><td><?= $e($r['client']) ?></td><td><?= $e($r['provider'] ?? '—') ?></td>
<td><?= $e($money($r['compensation'])) ?></td><td><?= $e($dt($r['deadline'])) ?></td><td><?= $badge($r['status']) ?></td><td><?= $r['payment_state'] ? $badge($r['payment_state']) : '—' ?></td></tr>
<?php endforeach; ?></tbody></table>
<?= $pagerHtml ?>
</div>
