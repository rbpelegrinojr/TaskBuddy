<form class="card filters" method="get" action="<?= $e($url('/users')) ?>">
  <div><label for="q">Search name or email</label><input id="q" name="q" value="<?= $e($filters['q']) ?>"></div>
  <div><label for="role">Role</label><select id="role" name="role"><option value="">All</option><?php foreach (['client','provider'] as $o): ?><option<?= $filters['role'] === $o ? ' selected' : '' ?>><?= $e($o) ?></option><?php endforeach; ?></select></div>
  <div><label for="status">Status</label><select id="status" name="status"><option value="">All</option><?php foreach (['active','suspended'] as $o): ?><option<?= $filters['status'] === $o ? ' selected' : '' ?>><?= $e($o) ?></option><?php endforeach; ?></select></div>
  <button class="btn" type="submit">Filter</button>
</form>
<div class="card">
<table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Registered</th><th>Last login</th><th>Action</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="8" class="muted">No users match the filters.</td></tr><?php endif; ?>
<?php foreach ($rows as $u): ?>
<tr><td><?= $e($u['id']) ?></td><td><?= $e($u['full_name']) ?></td><td><?= $e($u['email']) ?></td><td><?= $e($u['role']) ?></td><td><?= $badge($u['status']) ?></td><td><?= $e($dt($u['created_at'])) ?></td><td><?= $e($dt($u['last_login_at'])) ?></td>
<td><form class="inline" method="post" action="<?= $e($url('/users/' . $u['id'] . '/status')) ?>"><?= $csrf() ?><input type="hidden" name="_return" value="<?= $e($returnTo) ?>">
<?php if ($u['status'] === 'active'): ?><input type="hidden" name="status" value="suspended"><button class="btn small danger" type="submit">Suspend</button><?php else: ?><input type="hidden" name="status" value="active"><button class="btn small" type="submit">Reactivate</button><?php endif; ?></form></td></tr>
<?php endforeach; ?></tbody></table>
<?= $pagerHtml ?>
</div>
