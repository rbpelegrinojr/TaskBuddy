<p class="sim">SIMULATED / TEST TRANSACTIONS — no real money is collected, held, transferred or paid out. These records only demonstrate the payment workflow.</p>
<section class="grid stats"><?php foreach ($totals as $s => $t): ?><div class="card stat"><div class="num"><?= $e($money($t)) ?></div><div class="muted"><?= $badge($s) ?></div></div><?php endforeach; ?></section>
<form class="card filters" method="get" action="<?= $e($url('/payments')) ?>">
<div><label for="q">Search reference or request title</label><input id="q" name="q" value="<?= $e($filters['q']) ?>"></div>
<div><label for="state">State</label><select id="state" name="state"><option value="">All</option><?php foreach (['PENDING','COMPLETED','ON_HOLD','CANCELLED'] as $s): ?><option value="<?= $e($s) ?>"<?= $filters['state'] === $s ? ' selected' : '' ?>><?= $e($s) ?></option><?php endforeach; ?></select></div>
<button class="btn" type="submit">Filter</button></form>
<div class="card"><table><thead><tr><th>Reference</th><th>Request</th><th>Client</th><th>Provider</th><th>Amount</th><th>State</th><th>Created</th><th>Completed</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="8" class="muted">No simulated payments.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?><tr><td><?= $e($r['reference']) ?></td><td><a href="<?= $e($url('/requests/' . $r['request_id'])) ?>">#<?= $e($r['request_id']) ?></a> <?= $e($r['title']) ?></td><td><?= $e($r['client']) ?></td><td><?= $e($r['provider']) ?></td><td><?= $e($money($r['amount'])) ?></td><td><?= $badge($r['state']) ?></td><td><?= $e($dt($r['created_at'])) ?></td><td><?= $e($dt($r['completed_at'])) ?></td></tr><?php endforeach; ?>
</tbody></table><?= $pagerHtml ?></div>
