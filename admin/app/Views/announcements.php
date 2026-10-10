<section class="card"><h2>New announcement</h2><p class="muted">Delivered as an in-app notification (REST polling) to active users. No push provider is configured.</p>
<form method="post" action="<?= $e($url('/announcements')) ?>"><?= $csrf() ?>
<label for="title">Title</label><input id="title" name="title" required minlength="3" maxlength="150">
<label for="body">Message (max 500 characters)</label><textarea id="body" name="body" required minlength="3" maxlength="500" rows="3"></textarea>
<label for="audience">Audience</label><select id="audience" name="audience"><option value="all">All active users</option><option value="clients">Clients only</option><option value="providers">Providers only</option></select>
<button class="btn" type="submit">Send announcement</button></form></section>
<div class="card"><h2>Sent announcements</h2><table><thead><tr><th>Sent</th><th>Title</th><th>Message</th><th>Audience</th><th>Recipients</th><th>By</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="6" class="muted">None sent yet.</td></tr><?php endif; ?>
<?php foreach ($rows as $a): ?><tr><td><?= $e($dt($a['created_at'])) ?></td><td><?= $e($a['title']) ?></td><td><?= $e($a['body']) ?></td><td><?= $e($a['audience']) ?></td><td><?= $e($a['recipients']) ?></td><td><?= $e($a['full_name']) ?></td></tr><?php endforeach; ?>
</tbody></table><?= $pagerHtml ?></div>
