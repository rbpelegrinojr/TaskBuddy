<form class="card filters" method="get" action="<?= $e($url('/providers')) ?>">
  <div><label for="q">Search name or email</label><input id="q" name="q" value="<?= $e($filters['q']) ?>"></div>
  <div><label for="ready">Matching readiness</label><select id="ready" name="ready"><option value="">All</option><option value="yes"<?= $filters['ready'] === 'yes' ? ' selected' : '' ?>>Eligible for matching</option><option value="no"<?= $filters['ready'] === 'no' ? ' selected' : '' ?>>Not yet eligible</option></select></div>
  <button class="btn" type="submit">Filter</button>
</form>
<p class="muted">A provider can be recommended only when the account is active, the profile is complete (headline, experience summary, minimum rate, at least one category) and weekly availability is declared.</p>
<div class="card">
<table><thead><tr><th>ID</th><th>Provider</th><th>Headline</th><th>Min rate</th><th>Cat.</th><th>Skills</th><th>Slots</th><th>Completed</th><th>Profile</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="11" class="muted">No providers match the filters.</td></tr><?php endif; ?>
<?php foreach ($rows as $p): ?>
<tr><td><?= $e($p['id']) ?></td><td><?= $e($p['full_name']) ?><br><span class="muted"><?= $e($p['email']) ?></span></td><td><?= $e($p['headline'] ?? '—') ?></td><td><?= $p['min_rate'] === null ? '—' : $e($money($p['min_rate'])) ?></td>
<td><?= $e($p['categories']) ?></td><td><?= $e($p['skills']) ?></td><td><?= $e($p['slots']) ?></td><td><?= $e($p['completed']) ?></td>
<td><?= (int)$p['profile_complete'] === 1 ? '<span class="badge ok">complete</span>' : '<span class="badge warn">incomplete</span>' ?></td><td><?= $badge($p['status']) ?></td>
<td><form class="inline" method="post" action="<?= $e($url('/users/' . $p['id'] . '/status')) ?>"><?= $csrf() ?><input type="hidden" name="_return" value="<?= $e($returnTo) ?>">
<?php if ($p['status'] === 'active'): ?><input type="hidden" name="status" value="suspended"><button class="btn small danger" type="submit">Suspend</button><?php else: ?><input type="hidden" name="status" value="active"><button class="btn small" type="submit">Reactivate</button><?php endif; ?></form></td></tr>
<?php endforeach; ?></tbody></table>
<?= $pagerHtml ?>
</div>
