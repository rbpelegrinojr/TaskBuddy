<p><a href="<?= $e($url('/requests')) ?>">← All requests</a></p>
<section class="card"><h2><?= $e($req['title']) ?> <?= $badge($req['status']) ?></h2>
<dl class="kv">
<dt>Client</dt><dd><?= $e($req['client_name']) ?> <span class="muted">(<?= $e($req['client_email']) ?>)</span></dd>
<dt>Provider</dt><dd><?= $asg ? $e($asg['provider_name']) . ' <span class="muted">(' . $e($asg['provider_email']) . ')</span>' : '—' ?></dd>
<dt>Category</dt><dd><?= $e($req['category']) ?></dd>
<dt>Compensation</dt><dd><?= $e($money($req['compensation'])) ?> <span class="badge warn">simulated</span></dd>
<dt>Deadline</dt><dd><?= $e($dt($req['deadline'])) ?></dd>
<dt>Estimated hours</dt><dd><?= $e($req['estimated_hours'] ?? '—') ?></dd>
<dt>Exclusive task</dt><dd><?= (int)$req['requires_exclusive'] === 1 ? 'Yes' : 'No' ?></dd>
<dt>Skills</dt><dd><?php foreach ($skills as $s): ?><span class="badge <?= (int)$s['is_mandatory'] === 1 ? 'info' : 'muted' ?>"><?= $e($s['name']) ?><?= (int)$s['is_mandatory'] === 1 ? ' (required)' : '' ?></span> <?php endforeach; ?></dd>
<dt>Description</dt><dd><pre><?= $e($req['description']) ?></pre></dd>
<dt>Created</dt><dd><?= $e($dt($req['created_at'])) ?></dd>
</dl></section>
<div class="grid two">
<section class="card"><h2>Invitations</h2><?php if (!$invitations): ?><p class="muted">None.</p><?php else: ?>
<table><thead><tr><th>Provider</th><th>Status</th><th>Sent</th><th>Expires</th></tr></thead><tbody><?php foreach ($invitations as $i): ?><tr><td><?= $e($i['provider']) ?></td><td><?= $badge($i['status']) ?></td><td><?= $e($dt($i['created_at'])) ?></td><td><?= $e($dt($i['expires_at'])) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
<section class="card"><h2>Matching runs</h2><?php if (!$results): ?><p class="muted">None.</p><?php else: ?>
<ul class="plain"><?php foreach ($results as $m): ?><li><a href="<?= $e($url('/matching/' . $m['id'])) ?>">Result #<?= $e($m['id']) ?></a> · <?= $e($m['eligible_count']) ?> eligible of <?= $e($m['candidates_evaluated']) ?> · algorithm <?= $e($m['algorithm_version']) ?> · <?= $e($dt($m['created_at'])) ?></li><?php endforeach; ?></ul><?php endif; ?></section>
<section class="card"><h2>Simulated payment</h2><p class="sim">SIMULATED / TEST TRANSACTION</p>
<?php if (!$payment): ?><p class="muted">Not created yet.</p><?php else: ?><p><?= $e($payment['reference']) ?> · <?= $e($money($payment['amount'])) ?> · <?= $badge($payment['state']) ?></p><?php endif; ?></section>
<section class="card"><h2>Dispute</h2><?php if (!$dispute): ?><p class="muted">None.</p><?php else: ?><p><a href="<?= $e($url('/disputes/' . $dispute['id'])) ?>">Dispute #<?= $e($dispute['id']) ?></a> <?= $badge($dispute['status']) ?></p><?php endif; ?></section>
</div>
<section class="card"><h2>Files <span class="muted">(metadata only; contents are private to the participants)</span></h2>
<h3>Request attachments</h3><?php if (!$attachments): ?><p class="muted">None.</p><?php else: ?><ul class="plain"><?php foreach ($attachments as $a): ?><li><?= $e($a['original_name']) ?> · <?= $e($a['mime_type']) ?> · <?= $e(number_format((int)$a['size_bytes'] / 1024, 1)) ?> KB</li><?php endforeach; ?></ul><?php endif; ?>
<h3>Submissions</h3><?php if (!$subs): ?><p class="muted">None.</p><?php endif; ?>
<?php foreach ($subs as $s): ?><p><strong>Submission #<?= $e($s['id']) ?></strong> · <?= $e($dt($s['created_at'])) ?><br><?= $e($s['note'] ?? '') ?></p><ul class="plain"><?php foreach (($files[(int)$s['id']] ?? []) as $f): ?><li><?= $e($f['original_name']) ?> · <?= $e($f['mime_type']) ?> · <?= $e(number_format((int)$f['size_bytes'] / 1024, 1)) ?> KB</li><?php endforeach; ?></ul><?php endforeach; ?>
<?php if ($revisions): ?><h3>Revision requests</h3><ul class="plain"><?php foreach ($revisions as $v): ?><li><?= $e($dt($v['created_at'])) ?> — <?= $e($v['reason']) ?></li><?php endforeach; ?></ul><?php endif; ?></section>
<section class="card"><h2>History</h2><?php if (!$history): ?><p class="muted">No logged events.</p><?php else: ?>
<ul class="plain"><?php foreach ($history as $h): ?><li><strong><?= $e($h['action']) ?></strong> <span class="muted"><?= $e($dt($h['created_at'])) ?></span> <code><?= $e($h['details_json']) ?></code></li><?php endforeach; ?></ul><?php endif; ?></section>
