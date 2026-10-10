<p><a href="<?= $e($url('/disputes')) ?>">← All disputes</a></p>
<section class="card"><h2>Dispute #<?= $e($d['id']) ?> <?= $badge($d['status']) ?></h2>
<dl class="kv"><dt>Request</dt><dd><a href="<?= $e($url('/requests/' . $d['request_id'])) ?>">#<?= $e($d['request_id']) ?> <?= $e($d['title']) ?></a> <?= $badge($d['request_status']) ?></dd>
<dt>Client</dt><dd><?= $e($d['client']) ?></dd><dt>Provider</dt><dd><?= $e($d['provider']) ?></dd><dt>Opened by</dt><dd><?= $e($d['opener']) ?> · <?= $e($dt($d['created_at'])) ?></dd>
<dt>Reason</dt><dd><pre><?= $e($d['reason']) ?></pre></dd>
<dt>Simulated payment</dt><dd><?= $payment ? $e($payment['reference']) . ' · ' . $e($money($payment['amount'])) . ' · ' . $badge($payment['state']) . ' <span class="badge warn">SIMULATED</span>' : '—' ?></dd>
<?php if ($d['status'] === 'RESOLVED'): ?><dt>Resolution</dt><dd><?= $e($d['resolution']) ?> by <?= $e($d['resolver']) ?> · <?= $e($dt($d['resolved_at'])) ?><pre><?= $e($d['resolution_note']) ?></pre></dd><?php endif; ?></dl></section>
<?php if ($messages): ?><section class="card"><h2>Recent conversation</h2><ul class="plain"><?php foreach ($messages as $m): ?><li><strong><?= $e($m['full_name']) ?></strong> <span class="muted"><?= $e($dt($m['created_at'])) ?></span><br><?= $e($m['body']) ?></li><?php endforeach; ?></ul></section><?php endif; ?>
<?php if ($d['status'] === 'OPEN'): ?>
<section class="card"><h2>Resolve dispute</h2>
<form method="post" action="<?= $e($url('/disputes/' . $d['id'] . '/resolve')) ?>"><?= $csrf() ?>
<label for="res">Outcome</label><select id="res" name="resolution" required>
<option value="COMPLETE_TASK">Complete the task — simulated payment becomes COMPLETED</option>
<option value="CANCEL_TASK">Cancel the task — simulated payment becomes CANCELLED</option>
<option value="RESUME_TASK">Resume the task — back to IN PROGRESS, simulated payment PENDING</option></select>
<label for="note">Resolution note (visible in the audit log)</label><textarea id="note" name="note" required minlength="5" maxlength="1000" rows="4"></textarea>
<button class="btn" type="submit">Resolve dispute</button></form></section>
<?php endif; ?>
