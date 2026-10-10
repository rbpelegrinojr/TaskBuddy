<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Sign in · TaskBuddy Admin</title><link rel="stylesheet" href="<?= $e($url('/assets/admin.css')) ?>"></head>
<body class="login">
<main class="card narrow">
  <h1>TaskBuddy Admin</h1>
  <p class="muted">Authorised administrators only.</p>
  <?php if (!empty($error)): ?><div class="flash error" role="alert"><?= $e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= $e($url('/login')) ?>" autocomplete="off">
    <?= $csrf() ?>
    <label for="email">Email</label>
    <input id="email" name="email" type="email" required autocomplete="username" value="<?= $e($email ?? '') ?>">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" required autocomplete="current-password">
    <button class="btn" type="submit">Sign in</button>
  </form>
</main>
</body></html>
