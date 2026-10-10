<?php if ($_bare ?? false): ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Error</title><link rel="stylesheet" href="<?= $e($url('/assets/admin.css')) ?>"></head><body class="login"><main class="card narrow"><?php endif; ?>
<h2>Error <?= $e($status) ?></h2>
<p><?= $e($message) ?></p>
<p><a href="<?= $e($url('/')) ?>">Back to the dashboard</a></p>
<?php if ($_bare ?? false): ?></main></body></html><?php endif; ?>
