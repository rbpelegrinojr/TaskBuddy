<div class="grid two">
<section class="card"><h2>Add category</h2>
<form method="post" action="<?= $e($url('/catalog/categories')) ?>"><?= $csrf() ?>
<label for="cn">Name</label><input id="cn" name="name" required minlength="2" maxlength="100">
<label for="cd">Description</label><input id="cd" name="description" maxlength="255">
<button class="btn" type="submit">Create category</button></form></section>
<section class="card"><h2>Add skill</h2>
<form method="post" action="<?= $e($url('/catalog/skills')) ?>"><?= $csrf() ?>
<label for="sn">Name</label><input id="sn" name="name" required minlength="2" maxlength="100">
<label for="sc">Attach to category (optional)</label><select id="sc" name="category_id"><option value="">None</option><?php foreach ($cats as $c): ?><option value="<?= $e($c['id']) ?>"><?= $e($c['name']) ?></option><?php endforeach; ?></select>
<button class="btn" type="submit">Create skill</button></form></section>
</div>
<section class="card"><h2>Categories</h2>
<table><thead><tr><th>Name &amp; description</th><th>Status</th><th>Skills in category</th><th>Usage</th><th>Save</th></tr></thead><tbody>
<?php foreach ($cats as $c): $fid = 'cf' . $c['id']; ?>
<tr><td>
<input form="<?= $e($fid) ?>" name="name" value="<?= $e($c['name']) ?>" required minlength="2" maxlength="100" aria-label="Category name">
<input form="<?= $e($fid) ?>" name="description" value="<?= $e($c['description']) ?>" maxlength="255" aria-label="Category description">
</td><td><label class="row"><input form="<?= $e($fid) ?>" type="checkbox" name="is_active" value="1"<?= (int)$c['is_active'] === 1 ? ' checked' : '' ?>> active</label></td>
<td><?php foreach (($links[(int)$c['id']] ?? []) as $s): ?><span class="badge muted"><?= $e($s['name']) ?></span> <?php endforeach; ?></td>
<td class="muted"><?= $e($c['requests']) ?> request(s), <?= $e($c['providers']) ?> provider(s)</td>
<td><form id="<?= $e($fid) ?>" method="post" action="<?= $e($url('/catalog/categories/' . $c['id'])) ?>"><?= $csrf() ?><button class="btn small" type="submit">Save</button></form></td></tr>
<?php endforeach; ?></tbody></table></section>
<section class="card"><h2>Skills</h2>
<table><thead><tr><th>Skill</th><th>Status</th><th>Providers</th><th>Save</th></tr></thead><tbody>
<?php foreach ($skills as $s): $fid = 'sf' . $s['id']; ?>
<tr><td><input form="<?= $e($fid) ?>" name="name" value="<?= $e($s['name']) ?>" required minlength="2" maxlength="100" aria-label="Skill name"></td>
<td><label class="row"><input form="<?= $e($fid) ?>" type="checkbox" name="is_active" value="1"<?= (int)$s['is_active'] === 1 ? ' checked' : '' ?>> active</label></td><td><?= $e($s['providers']) ?></td>
<td><form id="<?= $e($fid) ?>" method="post" action="<?= $e($url('/catalog/skills/' . $s['id'])) ?>"><?= $csrf() ?><button class="btn small" type="submit">Save</button></form></td></tr>
<?php endforeach; ?></tbody></table></section>
<section class="card"><h2>Link or unlink a skill</h2>
<form method="post" action="<?= $e($url('/catalog/links')) ?>" class="filters"><?= $csrf() ?>
<div><label for="lc">Category</label><select id="lc" name="category_id"><?php foreach ($cats as $c): ?><option value="<?= $e($c['id']) ?>"><?= $e($c['name']) ?></option><?php endforeach; ?></select></div>
<div><label for="ls">Skill</label><select id="ls" name="skill_id"><?php foreach ($skills as $s): ?><option value="<?= $e($s['id']) ?>"><?= $e($s['name']) ?></option><?php endforeach; ?></select></div>
<div><label for="lo">Action</label><select id="lo" name="op"><option value="add">Link</option><option value="remove">Unlink</option></select></div>
<button class="btn" type="submit">Apply</button></form></section>
