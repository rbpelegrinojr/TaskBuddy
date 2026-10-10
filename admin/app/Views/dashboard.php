<?php if ($openDisputes > 0 || $openReports > 0): ?>
<div class="flash info" role="status">
  <?php if ($openDisputes > 0): ?><a href="<?= $e($url('/disputes', ['status' => 'OPEN'])) ?>"><?= $e($openDisputes) ?> open dispute(s)</a> need attention. <?php endif; ?>
  <?php if ($openReports > 0): ?><a href="<?= $e($url('/moderation')) ?>"><?= $e($openReports) ?> open user report(s)</a>.<?php endif; ?>
</div>
<?php endif; ?>
<section class="grid stats" aria-label="Key figures">
<?php foreach ($stats as $label => $value): ?>
  <div class="card stat"><div class="num"><?= $e($value) ?></div><div class="muted"><?= $e($label) ?></div></div>
<?php endforeach; ?>
</section>
<section class="card">
  <h2>Simulated payment totals <span class="badge warn">SIMULATED / TEST</span></h2>
  <p class="muted">No real money is processed. Amounts are test records only.</p>
  <div class="grid stats">
  <?php foreach ($payments as $state => $total): ?>
    <div class="stat"><div class="num"><?= $e($money($total)) ?></div><div class="muted"><?= $badge($state) ?></div></div>
  <?php endforeach; ?>
  </div>
</section>
<section class="grid two">
  <div class="card"><h2>Requests by status</h2><?= $chartStatus ?></div>
  <div class="card"><h2>New requests (14 days)</h2><?= $chartDays ?></div>
  <div class="card"><h2>Simulated payments by state</h2><?= $chartPayments ?></div>
  <div class="card"><h2>Recent activity</h2>
    <?php if (!$recent): ?><p class="muted">No activity recorded yet.</p><?php else: ?>
    <ul class="plain">
    <?php foreach ($recent as $r): ?>
      <li><strong><?= $e($r['action']) ?></strong> <span class="muted"><?= $e($r['entity_type']) ?> #<?= $e($r['entity_id']) ?> · <?= $e($r['full_name'] ?? 'system') ?> · <?= $e($dt($r['created_at'])) ?></span></li>
    <?php endforeach; ?></ul>
    <?php endif; ?>
  </div>
</section>
