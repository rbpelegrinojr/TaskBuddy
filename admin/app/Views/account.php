<div class="grid two">
<section class="card"><h2>Profile</h2><p class="muted"><?= $e($me['email']) ?> · last sign-in <?= $e($dt($me['last_login_at'])) ?></p>
<form method="post" action="<?= $e($url('/account/profile')) ?>"><?= $csrf() ?><label for="fn">Full name</label><input id="fn" name="full_name" value="<?= $e($me['full_name']) ?>" required minlength="2" maxlength="150"><button class="btn" type="submit">Save</button></form></section>
<section class="card"><h2>Change password</h2>
<form method="post" action="<?= $e($url('/account/password')) ?>" autocomplete="off"><?= $csrf() ?>
<label for="cp">Current password</label><input id="cp" name="current_password" type="password" required autocomplete="current-password">
<label for="np">New password (12–72 characters, letters and digits)</label><input id="np" name="new_password" type="password" required minlength="12" maxlength="72" autocomplete="new-password">
<label for="np2">Confirm new password</label><input id="np2" name="confirm_password" type="password" required minlength="12" maxlength="72" autocomplete="new-password">
<button class="btn" type="submit">Change password</button></form></section>
</div>
