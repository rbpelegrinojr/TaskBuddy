<?php
$nav = [
    ['/', 'Overview'], ['/users', 'Users'], ['/providers', 'Providers'], ['/catalog', 'Categories & skills'],
    ['/requests', 'Service requests'], ['/matching', 'Matching records'], ['/transactions', 'Active transactions'],
    ['/payments', 'Simulated payments'], ['/disputes', 'Disputes'], ['/moderation', 'User reports'], ['/announcements', 'Announcements'],
    ['/logs', 'Activity logs'], ['/reports', 'Reports'], ['/matching-config', 'Matching configuration'], ['/account', 'Account'],
];
$current = $ctx->request->path;
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= $e($title) ?> · TaskBuddy Admin</title>
<link rel="stylesheet" href="<?= $e($url('/assets/admin.css')) ?>">
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<input type="checkbox" id="navtoggle" class="navtoggle" aria-label="Toggle navigation">
<header class="topbar">
  <label for="navtoggle" class="burger" aria-hidden="true">☰</label>
  <strong>TaskBuddy Admin</strong>
  <span class="spacer"></span>
  <span class="who"><?= $e($admin['full_name'] ?? '') ?></span>
  <form method="post" action="<?= $e($url('/logout')) ?>"><?= $csrf() ?><button class="btn small secondary" type="submit">Sign out</button></form>
</header>
<div class="shell">
  <nav class="sidebar" aria-label="Main">
    <ul>
    <?php foreach ($nav as [$p, $label]): $active = $p === '/' ? $current === '/' : str_starts_with($current, $p) && !($p === '/matching' && str_starts_with($current, '/matching-config')); ?>
      <li><a href="<?= $e($url($p)) ?>"<?= $active ? ' aria-current="page" class="active"' : '' ?>><?= $e($label) ?></a></li>
    <?php endforeach; ?>
    </ul>
  </nav>
  <main id="main">
    <h1><?= $e($title) ?></h1>
    <?php foreach ($flash as $f): ?><div class="flash <?= $e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= $e($f['message']) ?></div><?php endforeach; ?>
    <?= $content ?>
  </main>
</div>
</body>
</html>
